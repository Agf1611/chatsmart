<?php

namespace App\Http\Controllers;

use App\Models\MessageHistory;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessagesHistoryController extends Controller
{
    public function index(Request $request)
    {
        $devices = $request->user()->devices()->orderBy('body')->get();
        $messageQuery = $this->applyFilters(
            $request->user()->messageHistories()->with('device')->latest(),
            $request
        );

        $messages = (clone $messageQuery)->paginate(20)->withQueryString();
        $stats = [
            'total' => (clone $messageQuery)->count(),
            'success' => (clone $messageQuery)->where('status', 'success')->count(),
            'failed' => (clone $messageQuery)->where('status', 'failed')->count(),
        ];

        return view('pages.histories.message', compact('messages', 'devices', 'stats'));
    }

    public function resend(Request $request, WhatsappService $wa)
    {
        try {
            $history = $request->user()->messageHistories()->with('device')->find($request->id);
            if (!$history) {
                return response()->json(['error' => true, 'msg' => 'Message history not found']);
            }

            if ($history->status === 'success') {
                return response()->json(['error' => true, 'msg' => 'Message already sent, refresh page to update status']);
            }

            $result = $this->resendHistory($history, $wa);
            return response()->json([
                'error' => !$result['success'],
                'msg' => $result['message'],
            ]);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json(['error' => true, 'msg' => 'Something went wrong while resending message']);
        }
    }

    public function resendFailed(Request $request, WhatsappService $wa)
    {
        $histories = $this->applyFilters(
            $request->user()->messageHistories()->with('device')->latest(),
            $request
        )
            ->where('status', 'failed')
            ->limit(100)
            ->get();

        if ($histories->isEmpty()) {
            return backWithFlash('info', 'There are no failed messages to resend.');
        }

        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($histories as $history) {
            $result = $this->resendHistory($history, $wa);
            if ($result['success']) {
                $success++;
                continue;
            }

            if ($history->device?->status !== 'Connected' || str_contains(strtolower($result['message']), 'already sent')) {
                $skipped++;
                continue;
            }

            $failed++;
        }

        return backWithFlash(
            $success > 0 ? 'success' : 'warning',
            "Resend finished. Success: {$success}, failed: {$failed}, skipped: {$skipped}."
        );
    }

    public function destroy(Request $request, MessageHistory $messageHistory)
    {
        abort_if($messageHistory->user_id !== $request->user()->id, 403);

        $messageHistory->delete();

        return backWithFlash('success', 'Message history deleted.');
    }

    public function clear(Request $request)
    {
        $scope = $request->input('scope', 'filtered');
        $query = $this->applyFilters($request->user()->messageHistories(), $request);

        if ($scope === 'failed') {
            $query->where('status', 'failed');
        } elseif ($scope === 'success') {
            $query->where('status', 'success');
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            return backWithFlash('info', 'No message history matched the cleanup filter.');
        }

        $query->delete();

        return backWithFlash('success', "{$count} message histories deleted.");
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->q);
            $query->where(function ($innerQuery) use ($keyword) {
                $innerQuery
                    ->where('number', 'like', '%' . $keyword . '%')
                    ->orWhere('message', 'like', '%' . $keyword . '%')
                    ->orWhere('note', 'like', '%' . $keyword . '%');
            });
        }

        return $query;
    }

    private function resendHistory(MessageHistory $history, WhatsappService $wa): array
    {
        try {
            $device = $history->device;
            if (!$device) {
                $history->update(['note' => 'Device was deleted and can no longer resend this message.']);
                return ['success' => false, 'message' => 'Device was deleted and can no longer resend this message.'];
            }

            if ($device->status !== 'Connected') {
                $history->update(['note' => 'Device is not connected.']);
                return ['success' => false, 'message' => 'Device is not connected.'];
            }

            if (!$this->isGroupTarget($history->number)) {
                $checkNumber = $wa->checkNumber($device->body, $history->number);
                $isActiveNumber = is_object($checkNumber) && (bool) ($checkNumber->active ?? false);

                if (!$isActiveNumber) {
                    $history->update([
                        'status' => 'failed',
                        'note' => 'Recipient number is not active on WhatsApp.',
                    ]);

                    return ['success' => false, 'message' => 'Recipient number is not active on WhatsApp.'];
                }
            }

            $params = json_decode($history->payload);
            if (!$params) {
                $history->update(['note' => 'Stored payload is invalid and cannot be resent.']);
                return ['success' => false, 'message' => 'Stored payload is invalid and cannot be resent.'];
            }

            $params->sender = $params->sender ?? $device->body;
            $method = match ($history->type) {
                'text' => 'sendText',
                'media' => 'sendMedia',
                'button' => 'sendButton',
                'template' => 'sendTemplate',
                'list' => 'sendList',
                'poll' => 'sendPoll',
                default => null,
            };

            if (!$method || !method_exists($wa, $method)) {
                $history->update(['note' => 'This message type can not be resent automatically.']);
                return ['success' => false, 'message' => 'This message type can not be resent automatically.'];
            }

            $res = $wa->$method($params, $history->number);
            $isSent = (bool) ($res->status ?? false);

            if ($isSent) {
                $history->update([
                    'status' => 'success',
                    'whatsapp_message_id' => data_get($res, 'data.key.id'),
                    'resolved_jid' => data_get($res, 'data.key.remoteJid'),
                    'delivery_status' => 'pending',
                    'sent_at' => now(),
                    'delivered_at' => null,
                    'read_at' => null,
                    'note' => 'Resent successfully on ' . now()->format('d M Y H:i'),
                ]);
                MessageHistory::syncRecentDeliveryReceipts();

                return ['success' => true, 'message' => 'Resend message success'];
            }

            $history->update([
                'status' => 'failed',
                'delivery_status' => 'failed',
                'note' => $res->message ?? 'Failed to resend this message.',
            ]);

            return [
                'success' => false,
                'message' => $res->message ?? 'Failed to resend this message.',
            ];
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            $history->update([
                'status' => 'failed',
                'note' => 'Something went wrong while resending this message.',
            ]);

            return ['success' => false, 'message' => 'Something went wrong while resending this message.'];
        }
    }

    private function isGroupTarget(string $number): bool
    {
        return str_contains($number, '@g.us');
    }
}

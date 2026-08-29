<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiConversationController extends Controller
{
    public function index(Request $request)
    {
        $bots = $request->user()->aiBots()->orderBy('name')->get();
        $devices = $request->user()->devices()->orderBy('body')->get();

        $stats = $request->user()->aiConversations()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused")
            ->first();

        $messageCount = AiConversationMessage::query()
            ->whereHas('conversation', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->count();

        $query = $request->user()->aiConversations()
            ->with(['bot', 'device'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('ai_bot_id')) {
            $query->where('ai_bot_id', $request->ai_bot_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('chat_jid', 'like', '%' . $search . '%')
                    ->orWhere('contact_name', 'like', '%' . $search . '%')
                    ->orWhere('last_user_message', 'like', '%' . $search . '%')
                    ->orWhere('last_bot_message', 'like', '%' . $search . '%');
            });
        }

        return view('pages.ai-bots.conversations', [
            'conversations' => $query->paginate(20)->withQueryString(),
            'bots' => $bots,
            'devices' => $devices,
            'conversationStats' => [
                'total' => (int) ($stats->total ?? 0),
                'active' => (int) ($stats->active ?? 0),
                'paused' => (int) ($stats->paused ?? 0),
                'messages' => $messageCount,
            ],
        ]);
    }

    public function pause(Request $request, AiConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->update([
            'status' => 'paused',
            'paused_until' => null,
            'paused_reason' => 'Paused manually by operator',
        ]);

        return backWithFlash('success', 'Bot berhasil dipause untuk kontak ini.');
    }

    public function resume(Request $request, AiConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->update([
            'status' => 'active',
            'paused_until' => null,
            'paused_reason' => null,
        ]);

        return backWithFlash('success', 'Bot kembali aktif untuk kontak ini.');
    }

    public function destroy(Request $request, AiConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $messageCount = $conversation->messages()->count();
        $conversation->delete();

        return backWithFlash(
            'success',
            'Histori percakapan dihapus bersama ' . number_format($messageCount) . ' pesan.'
        );
    }

    public function cleanup(Request $request)
    {
        $validated = $request->validate([
            'older_than_days' => ['required', 'integer', 'in:30,60,90,180'],
        ]);

        $cutoff = now()->subDays((int) $validated['older_than_days']);

        [$conversationCount, $messageCount] = DB::transaction(function () use ($request, $cutoff) {
            $query = $request->user()->aiConversations()
                ->where(function ($builder) use ($cutoff) {
                    $builder->where('last_message_at', '<', $cutoff)
                        ->orWhere(function ($emptyConversation) use ($cutoff) {
                            $emptyConversation->whereNull('last_message_at')
                                ->where('created_at', '<', $cutoff);
                        });
                });

            $conversationCount = (clone $query)->count();
            $messageCount = AiConversationMessage::query()
                ->whereIn('ai_conversation_id', (clone $query)->select('id'))
                ->count();

            $query->delete();

            return [$conversationCount, $messageCount];
        });

        if ($conversationCount === 0) {
            return backWithFlash('info', 'Tidak ada histori lama yang memenuhi kriteria pembersihan.');
        }

        return backWithFlash(
            'success',
            number_format($conversationCount) . ' percakapan dan ' . number_format($messageCount) . ' pesan lama berhasil dihapus.'
        );
    }

    protected function authorizeConversation(Request $request, AiConversation $conversation): void
    {
        abort_unless((int) $request->user()->id === (int) $conversation->user_id, 404);
    }
}

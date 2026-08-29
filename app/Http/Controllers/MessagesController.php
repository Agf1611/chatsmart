<?php


namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Models\MessageHistory;
use App\Repositories\DeviceRepository;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessagesController extends Controller
{
    protected WhatsappService $whatsappService;
    protected DeviceRepository $deviceRepository;
    protected $processor = [
        'text' => 'sendText',
        'media' => 'sendMedia',
        'button' => 'sendButton',
        'template' => 'sendTemplate',
        'list' => 'sendList',
        'poll' => 'sendPoll',
    ];

    public function __construct(WhatsappService $whatsappService, DeviceRepository $deviceRepository)
    {
        $this->whatsappService = $whatsappService;
        $this->deviceRepository = $deviceRepository;
    }


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $devices = $request->user()->devices()->latest()->paginate(10);
        return view('pages.messagetest', compact('devices'));
    }

    /**
     * Sending and storing message successfully
     */
    public function store(SendMessageRequest $request)
    {
        $receivers = explode('|', $request->number);
        $unique = array_unique($receivers);
        $type = $request->type;
        $success = 0;
        $dataForBatchInput = [];
        $firstFailureMessage = null;
        $device = $request->user()->devices()->where('body', $request->sender)->first();

        if (!$device) {
            return backWithFlash('danger', 'Selected sender device was not found. Please select the device again.');
        }

        foreach ($unique as $number) {
            try {
                $method = $this->processor[$type];
                $messageSent = $this->whatsappService->$method($request, $number);
                $isSent = (bool) ($messageSent->status ?? false);
                $note = $messageSent->message ?? null;

                if (!$isSent && !str_contains($number, '@g.us')) {
                    $checkNumberResult = $this->whatsappService->checkNumber($request->sender, $number);
                    $isActiveNumber = is_object($checkNumberResult) && (bool) ($checkNumberResult->active ?? false);

                    if ($isActiveNumber) {
                        $retryResult = $this->whatsappService->$method($request, $number);
                        $retrySent = (bool) ($retryResult->status ?? false);

                        if ($retrySent) {
                            $messageSent = $retryResult;
                            $isSent = true;
                            $note = 'Sent after automatic retry.';
                        } else {
                            $note = $retryResult->message ?? 'Automatic retry failed.';
                        }
                    } else {
                        $note = 'Recipient number is not active on WhatsApp.';
                    }
                }

                $firstFailureMessage = !$isSent && !$firstFailureMessage
                    ? ($note ?? 'Failed to send message to this number.')
                    : $firstFailureMessage;

                $dataForBatchInput[] = [
                    'user_id' => $request->user()->id,
                    'device_id' => $device->id,
                    'number' => $number,
                    'message' => $request->message ? $request->message : ($request->caption ? $request->caption : ''),
                    'payload' => json_encode($request->all()),
                    'status' => $isSent ? 'success' : 'failed',
                    'whatsapp_message_id' => data_get($messageSent, 'data.key.id'),
                    'resolved_jid' => data_get($messageSent, 'data.key.remoteJid'),
                    'delivery_status' => $isSent ? 'pending' : 'failed',
                    'sent_at' => $isSent ? now() : null,
                    'type' => $request->type,
                    'send_by' => 'web',
                    'note' => $note,
                ];

                $success = $isSent ? $success + 1 : $success;
            } catch (\Exception $e) {
                Log::error('Error sending message to ' . $number .  ': ' . $e->getMessage());
                return backWithFlash('danger', 'Failed to send message. ' . $e->getMessage());
            }
        }

        MessageHistory::insert($dataForBatchInput);
        MessageHistory::syncRecentDeliveryReceipts();
        $this->deviceRepository->incrementMessageSent($device->id, $success);
        return backWithFlash(
            $success > 0 ? 'success' : 'danger',
            $success > 0
                ? "Message sent to $success number"
                : ($firstFailureMessage ?: 'Failed to send message to all number, check your WhatsApp connection and try again.')
        );
    }
}

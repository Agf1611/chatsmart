<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use Illuminate\Http\Request;

class AiConversationController extends Controller
{
    public function index(Request $request)
    {
        $bots = $request->user()->aiBots()->orderBy('name')->get();
        $devices = $request->user()->devices()->orderBy('body')->get();

        $query = $request->user()->aiConversations()->with(['bot', 'device'])->latest('last_message_at');

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

    protected function authorizeConversation(Request $request, AiConversation $conversation): void
    {
        abort_unless((int) $request->user()->id === (int) $conversation->user_id, 404);
    }
}

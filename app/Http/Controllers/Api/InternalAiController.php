<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiReplyService;
use Illuminate\Http\Request;

class InternalAiController extends Controller
{
    protected $aiReplyService;

    public function __construct(AiReplyService $aiReplyService)
    {
        $this->aiReplyService = $aiReplyService;
    }

    public function respond(Request $request)
    {
        abort_unless($this->isAuthorized($request), 403);

        $payload = $request->validate([
            'device_body' => ['required', 'string'],
            'chat_jid' => ['required', 'string'],
            'participant' => ['nullable', 'string'],
            'push_name' => ['nullable', 'string'],
            'incoming_text' => ['nullable', 'string'],
            'matched_rule_id' => ['nullable', 'integer'],
            'bot_id' => ['nullable', 'integer'],
            'context_type' => ['required', 'in:personal,group'],
            'whatsapp_message_id' => ['nullable', 'string'],
            'ai_route' => ['nullable', 'in:rule,default'],
        ]);

        $result = $this->aiReplyService->respondToIncoming($payload);

        return response()->json($result);
    }

    protected function isAuthorized(Request $request): bool
    {
        $expectedToken = env('AI_INTERNAL_TOKEN');
        if (!$expectedToken) {
            return false;
        }

        return hash_equals($expectedToken, (string) $request->header('X-Internal-AI-Token'));
    }
}

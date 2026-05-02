<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\Autoreply;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

class AutoreplyRuleService
{
    protected $messageService;
    protected $aiReplyService;

    public function __construct(MessageService $messageService, AiReplyService $aiReplyService)
    {
        $this->messageService = $messageService;
        $this->aiReplyService = $aiReplyService;
    }

    public function buildReplyConfig(array $validated)
    {
        $type = $validated['type'];

        switch ($type) {
            case 'text':
                return [
                    'message' => $validated['message'],
                ];
            case 'media':
                return [
                    'url' => $validated['url'],
                    'media_type' => $validated['media_type'],
                    'caption' => isset($validated['caption']) ? $validated['caption'] : '',
                ];
            case 'list':
                return [
                    'message' => $validated['message'],
                    'buttontext' => $validated['buttontext'],
                    'name' => $validated['name_list'],
                    'title' => $validated['title'],
                    'items' => $this->cleanList(isset($validated['items']) ? $validated['items'] : []),
                ];
            case 'button':
                return [
                    'message' => $validated['message'],
                    'footer' => isset($validated['footer']) ? $validated['footer'] : '',
                    'image' => isset($validated['image']) ? $validated['image'] : '',
                    'buttons' => $this->cleanList(isset($validated['buttons']) ? $validated['buttons'] : []),
                ];
            case 'template':
                return [
                    'message' => $validated['message'],
                    'footer' => isset($validated['footer']) ? $validated['footer'] : '',
                    'image' => isset($validated['image']) ? $validated['image'] : '',
                    'templates' => $this->cleanList(isset($validated['templates']) ? $validated['templates'] : []),
                ];
            case 'ai':
                $bot = !empty($validated['ai_bot_id']) ? AiBot::find($validated['ai_bot_id']) : null;
                return [
                    'ai_bot_id' => $validated['ai_bot_id'],
                    'ai_summary' => $this->aiReplyService->replySummary($bot),
                ];
            default:
                return [];
        }
    }

    public function buildRuntimeReply($type, array $replyConfig)
    {
        if (!in_array($type, ['text', 'media', 'list', 'button', 'template', 'ai'], true)) {
            return [];
        }

        $payload = $replyConfig;

        if ($type === 'ai') {
            return [
                'mode' => 'ai',
                'ai_bot_id' => $replyConfig['ai_bot_id'] ?? null,
                'summary' => $replyConfig['ai_summary'] ?? [],
            ];
        }

        if ($type === 'list') {
            $payload['name'] = isset($replyConfig['name']) ? $replyConfig['name'] : '';
            $payload['list'] = isset($replyConfig['items']) ? $replyConfig['items'] : [];
            unset($payload['items']);
        }

        if ($type === 'button') {
            $payload['button'] = isset($replyConfig['buttons']) ? $replyConfig['buttons'] : [];
            unset($payload['buttons']);
        }

        if ($type === 'template') {
            $payload['template'] = isset($replyConfig['templates']) ? $replyConfig['templates'] : [];
            unset($payload['templates']);
        }

        return $this->messageService->format($type, (object) $payload);
    }

    public function inferReplyConfig(Autoreply $autoreply)
    {
        if (is_array($autoreply->reply_config) && !empty($autoreply->reply_config)) {
            return $autoreply->reply_config;
        }

        $payload = is_array($autoreply->reply) ? $autoreply->reply : json_decode($autoreply->reply, true);

        if (!is_array($payload)) {
            return [];
        }

        switch ($autoreply->type) {
            case 'text':
                return ['message' => isset($payload['text']) ? $payload['text'] : ''];
            case 'media':
                return [
                    'url' => isset($payload['url']) ? $payload['url'] : '',
                    'media_type' => isset($payload['type']) ? $payload['type'] : 'document',
                    'caption' => isset($payload['caption']) ? $payload['caption'] : '',
                ];
            case 'list':
                $items = [];
                if (!empty($payload['sections'][0]['rows'])) {
                    foreach ($payload['sections'][0]['rows'] as $row) {
                        $items[] = isset($row['title']) ? $row['title'] : '';
                    }
                }

                return [
                    'message' => isset($payload['text']) ? $payload['text'] : '',
                    'buttontext' => isset($payload['buttonText']) ? $payload['buttonText'] : '',
                    'name' => isset($payload['title']) ? $payload['title'] : '',
                    'title' => isset($payload['sections'][0]['title']) ? $payload['sections'][0]['title'] : '',
                    'items' => $items,
                ];
            case 'button':
                $buttons = [];
                if (!empty($payload['buttons'])) {
                    foreach ($payload['buttons'] as $button) {
                        $buttons[] = isset($button['buttonText']['displayText']) ? $button['buttonText']['displayText'] : '';
                    }
                }

                return [
                    'message' => isset($payload['text']) ? $payload['text'] : (isset($payload['caption']) ? $payload['caption'] : ''),
                    'footer' => isset($payload['footer']) ? $payload['footer'] : '',
                    'image' => isset($payload['image']['url']) ? $payload['image']['url'] : '',
                    'buttons' => $buttons,
                ];
            case 'template':
                $templates = [];
                if (!empty($payload['templateButtons'])) {
                    foreach ($payload['templateButtons'] as $template) {
                        if (isset($template['urlButton'])) {
                            $templates[] = 'url|' . $template['urlButton']['displayText'] . '|' . $template['urlButton']['url'];
                        } elseif (isset($template['callButton'])) {
                            $templates[] = 'call|' . $template['callButton']['displayText'] . '|' . $template['callButton']['phoneNumber'];
                        }
                    }
                }

                return [
                    'message' => isset($payload['text']) ? $payload['text'] : (isset($payload['caption']) ? $payload['caption'] : ''),
                    'footer' => isset($payload['footer']) ? $payload['footer'] : '',
                    'image' => isset($payload['image']['url']) ? $payload['image']['url'] : '',
                    'templates' => $templates,
                ];
            case 'ai':
                $bot = $autoreply->ai_bot_id ? AiBot::find($autoreply->ai_bot_id) : null;
                return [
                    'ai_bot_id' => $autoreply->ai_bot_id,
                    'ai_summary' => $this->aiReplyService->replySummary($bot),
                ];
            default:
                return [];
        }
    }

    public function formatForPersistence(array $validated)
    {
        $replyConfig = $this->buildReplyConfig($validated);
        $runtimeReply = $this->buildRuntimeReply($validated['type'], $replyConfig);

        return [
            'name' => $validated['name'],
            'device_id' => $validated['device_id'],
            'contact_tag_id' => $validated['contact_tag_id'] ?? null,
            'ai_bot_id' => isset($validated['ai_bot_id']) ? $validated['ai_bot_id'] : null,
            'trigger_event' => $validated['trigger_event'],
            'keyword' => $validated['keyword'],
            'type_keyword' => $validated['type_keyword'],
            'reply_when' => $validated['reply_when'],
            'type' => $validated['type'],
            'reply' => $runtimeReply,
            'reply_config' => $replyConfig,
            'status' => strtolower($validated['status']),
            'is_quoted' => (bool) $validated['is_quoted'],
            'priority' => (int) $validated['priority'],
            'schedule_mode' => $validated['schedule_mode'],
            'schedule_days' => $validated['schedule_mode'] === 'scheduled' ? array_values($validated['schedule_days']) : null,
            'schedule_start' => $validated['schedule_mode'] === 'scheduled' ? $validated['schedule_start'] : null,
            'schedule_end' => $validated['schedule_mode'] === 'scheduled' ? $validated['schedule_end'] : null,
        ];
    }

    public function getFormData(Autoreply $autoreply = null)
    {
        if (!$autoreply) {
            return [
                'name' => '',
                'device_id' => session()->has('selectedDevice') ? session()->get('selectedDevice')['device_id'] : '',
                'trigger_event' => 'keyword',
                'keyword' => '',
                'type_keyword' => 'Equal',
                'reply_when' => 'All',
                'contact_tag_id' => null,
                'type' => '',
                'ai_bot_id' => null,
                'status' => 'active',
                'is_quoted' => false,
                'priority' => 100,
                'schedule_mode' => 'always',
                'schedule_days' => [],
                'schedule_start' => '',
                'schedule_end' => '',
                'reply_config' => [],
            ];
        }

        return [
            'name' => $autoreply->name,
            'device_id' => $autoreply->device_id,
            'trigger_event' => $autoreply->trigger_event ?: 'keyword',
            'keyword' => str_starts_with((string) $autoreply->keyword, '__first_chat__:') ? '' : $autoreply->keyword,
            'type_keyword' => $autoreply->type_keyword,
            'reply_when' => $autoreply->reply_when,
            'contact_tag_id' => $autoreply->contact_tag_id,
            'type' => $autoreply->type,
            'ai_bot_id' => $autoreply->ai_bot_id,
            'status' => $autoreply->status,
            'is_quoted' => (bool) $autoreply->is_quoted,
            'priority' => $autoreply->priority ?: 100,
            'schedule_mode' => $autoreply->schedule_mode ?: 'always',
            'schedule_days' => is_array($autoreply->schedule_days) ? $autoreply->schedule_days : [],
            'schedule_start' => $autoreply->schedule_start,
            'schedule_end' => $autoreply->schedule_end,
            'reply_config' => $this->inferReplyConfig($autoreply),
        ];
    }

    public function renderPreview($keyword, $type, $replyPayload)
    {
        if (!is_array($replyPayload)) {
            $replyPayload = json_decode($replyPayload, true);
        }

        switch ($type) {
            case 'text':
                return View::make('ajax.messages.textshow', [
                    'keyword' => $keyword,
                    'text' => isset($replyPayload['text']) ? $replyPayload['text'] : '',
                ])->render();
            case 'media':
                return View::make('ajax.messages.mediashow', [
                    'keyword' => $keyword,
                    'message' => (object) $replyPayload,
                ])->render();
            case 'button':
                return View::make('ajax.messages.buttonshow', [
                    'keyword' => $keyword,
                    'message' => isset($replyPayload['text']) ? $replyPayload['text'] : (isset($replyPayload['caption']) ? $replyPayload['caption'] : ''),
                    'footer' => isset($replyPayload['footer']) ? $replyPayload['footer'] : '',
                    'buttons' => json_decode(json_encode(isset($replyPayload['buttons']) ? $replyPayload['buttons'] : [])),
                    'image' => isset($replyPayload['image']['url']) ? $replyPayload['image']['url'] : null,
                ])->render();
            case 'template':
                return View::make('ajax.messages.templateshow', [
                    'keyword' => $keyword,
                    'message' => isset($replyPayload['text']) ? $replyPayload['text'] : (isset($replyPayload['caption']) ? $replyPayload['caption'] : ''),
                    'footer' => isset($replyPayload['footer']) ? $replyPayload['footer'] : '',
                    'templates' => json_decode(json_encode(isset($replyPayload['templateButtons']) ? $replyPayload['templateButtons'] : [])),
                    'image' => isset($replyPayload['image']['url']) ? $replyPayload['image']['url'] : null,
                ])->render();
            case 'list':
                return View::make('ajax.messages.listshow', [
                    'keyword' => $keyword,
                    'message' => isset($replyPayload['text']) ? $replyPayload['text'] : '',
                    'buttonText' => isset($replyPayload['buttonText']) ? $replyPayload['buttonText'] : '',
                    'title' => isset($replyPayload['title']) ? $replyPayload['title'] : '',
                    'sectionTitle' => isset($replyPayload['sections'][0]['title']) ? $replyPayload['sections'][0]['title'] : '',
                    'rows' => isset($replyPayload['sections'][0]['rows']) ? $replyPayload['sections'][0]['rows'] : [],
                ])->render();
            case 'ai':
                return View::make('ajax.messages.aishow', [
                    'keyword' => $keyword,
                    'summary' => isset($replyPayload['summary']) && is_array($replyPayload['summary']) ? $replyPayload['summary'] : [],
                ])->render();
            default:
                return View::make('ajax.messages.emptyshow')->render();
        }
    }

    public function matchRules(Collection $rules, $incomingMessage, $context, Carbon $now = null, array $options = [])
    {
        $now = $now ?: now();
        $normalizedContext = strtolower($context);
        $message = trim((string) $incomingMessage);
        $isFirstChat = !empty($options['is_first_chat']);
        $registeredTagIds = array_map('intval', $options['registered_tag_ids'] ?? []);

        $firstChat = $isFirstChat
            ? $rules->filter(function ($rule) {
                return ($rule['trigger_event'] ?? 'keyword') === 'first_chat';
            })->sort(function ($left, $right) {
                $leftPriority = isset($left['priority']) ? (int) $left['priority'] : 100;
                $rightPriority = isset($right['priority']) ? (int) $right['priority'] : 100;
                if ($leftPriority !== $rightPriority) {
                    return $leftPriority <=> $rightPriority;
                }

                $leftTime = isset($left['updated_at']) ? strtotime((string) $left['updated_at']) : 0;
                $rightTime = isset($right['updated_at']) ? strtotime((string) $right['updated_at']) : 0;
                return $rightTime <=> $leftTime;
            })->values()
            : collect();

        $exact = $rules->filter(function ($rule) use ($message) {
            return ($rule['trigger_event'] ?? 'keyword') === 'keyword'
                && isset($rule['type_keyword'])
                && $rule['type_keyword'] === 'Equal'
                && trim((string) $rule['keyword']) === $message;
        })->sortByDesc('updated_at')->values();

        $contain = $rules->filter(function ($rule) use ($message) {
            return ($rule['trigger_event'] ?? 'keyword') === 'keyword'
                && isset($rule['type_keyword'])
                && $rule['type_keyword'] === 'Contain'
                && stripos($message, trim((string) $rule['keyword'])) !== false;
        })->sort(function ($left, $right) {
            $leftPriority = isset($left['priority']) ? (int) $left['priority'] : 100;
            $rightPriority = isset($right['priority']) ? (int) $right['priority'] : 100;
            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            $leftTime = isset($left['updated_at']) ? strtotime((string) $left['updated_at']) : 0;
            $rightTime = isset($right['updated_at']) ? strtotime((string) $right['updated_at']) : 0;
            return $rightTime <=> $leftTime;
        })->values();

        $ordered = $firstChat->concat($exact)->concat($contain);

        foreach ($ordered as $rule) {
            $normalized = $this->normalizeRuleForMatching($rule);
            if (!$this->canRuleReplyToContext($normalized['reply_when'], $normalizedContext)) {
                continue;
            }

            if (!$this->canRuleReplyToRegisteredContact($normalized['contact_tag_id'] ?? null, $registeredTagIds)) {
                continue;
            }

            if (!$this->isRuleScheduledActive($normalized, $now)) {
                continue;
            }

            return [
                'matched' => true,
                'rule' => $normalized,
                'source' => isset($normalized['source']) ? $normalized['source'] : 'existing',
            ];
        }

        return [
            'matched' => false,
            'rule' => null,
            'source' => null,
        ];
    }

    public function explainDraftMismatch(array $draftRule, $incomingMessage, $context, Carbon $now = null, array $options = [])
    {
        $now = $now ?: now();
        $reasons = [];
        $isFirstChat = !empty($draftRule['is_first_chat']);
        $registeredTagIds = array_map('intval', $options['registered_tag_ids'] ?? []);

        if ($draftRule['status'] !== 'active') {
            $reasons[] = 'Rule sedang nonaktif.';
        }

        if (($draftRule['trigger_event'] ?? 'keyword') === 'first_chat' && !$isFirstChat) {
            $reasons[] = 'Simulasi belum ditandai sebagai chat pertama.';
        }

        if (($draftRule['trigger_event'] ?? 'keyword') === 'keyword' && !$this->keywordMatches($draftRule, $incomingMessage)) {
            $reasons[] = 'Pesan contoh tidak cocok dengan keyword rule ini.';
        }

        if (!$this->canRuleReplyToContext($draftRule['reply_when'], strtolower($context))) {
            $reasons[] = 'Rule ini tidak aktif untuk konteks pengirim yang dipilih.';
        }

        if (
            !empty($draftRule['contact_tag_id']) &&
            !$this->canRuleReplyToRegisteredContact($draftRule['contact_tag_id'], $registeredTagIds)
        ) {
            $reasons[] = 'Nomor contoh belum dianggap terdaftar di Phone Book pelanggan yang dipilih.';
        }

        if (!$this->isRuleScheduledActive($draftRule, $now)) {
            $reasons[] = 'Rule sedang di luar jadwal aktif.';
        }

        return $reasons;
    }

    public function buildContainWarnings(Collection $rules, $keyword, $typeKeyword, $ignoreId = null, $triggerEvent = 'keyword')
    {
        if ($triggerEvent !== 'keyword' || $typeKeyword !== 'Contain' || trim((string) $keyword) === '') {
            return [];
        }

        $keyword = strtolower(trim($keyword));

        return $rules->filter(function ($rule) use ($ignoreId, $keyword) {
            if ($ignoreId && (int) $rule->id === (int) $ignoreId) {
                return false;
            }

            $current = strtolower(trim((string) $rule->keyword));
            return $current !== '' && ($current === $keyword || strpos($current, $keyword) !== false || strpos($keyword, $current) !== false);
        })->map(function ($rule) {
            return $rule->keyword;
        })->values()->all();
    }

    public function generateDuplicateAttributes(Autoreply $autoreply, Collection $peerRules)
    {
        $baseName = $autoreply->name ?: $autoreply->keyword;
        $baseKeyword = $autoreply->keyword;
        $suffix = 1;
        $candidateKeyword = $baseKeyword . '-copy';

        while ($peerRules->contains(function ($rule) use ($candidateKeyword) {
            return $rule->keyword === $candidateKeyword;
        })) {
            $suffix++;
            $candidateKeyword = $baseKeyword . '-copy-' . $suffix;
        }

        return [
            'name' => $baseName . ' Copy',
            'keyword' => $candidateKeyword,
        ];
    }

    protected function cleanList(array $items)
    {
        return array_values(array_filter(array_map(function ($item) {
            return trim((string) $item);
        }, $items), function ($item) {
            return $item !== '';
        }));
    }

    protected function normalizeRuleForMatching($rule)
    {
        if ($rule instanceof Autoreply) {
            return [
                'id' => $rule->id,
                'name' => $rule->name,
                'trigger_event' => $rule->trigger_event ?: 'keyword',
                'keyword' => $rule->keyword,
                'type_keyword' => $rule->type_keyword,
                'reply_when' => $rule->reply_when,
                'contact_tag_id' => $rule->contact_tag_id,
                'type' => $rule->type,
                'reply' => is_array($rule->reply) ? $rule->reply : json_decode($rule->reply, true),
                'status' => strtolower($rule->status),
                'priority' => (int) $rule->priority,
                'schedule_mode' => $rule->schedule_mode ?: 'always',
                'schedule_days' => is_array($rule->schedule_days) ? $rule->schedule_days : [],
                'schedule_start' => $rule->schedule_start,
                'schedule_end' => $rule->schedule_end,
                'is_quoted' => (bool) $rule->is_quoted,
                'updated_at' => $rule->updated_at,
                'source' => 'existing',
            ];
        }

        return [
            'id' => isset($rule['id']) ? $rule['id'] : null,
            'name' => isset($rule['name']) ? $rule['name'] : '',
            'trigger_event' => isset($rule['trigger_event']) ? $rule['trigger_event'] : 'keyword',
            'keyword' => isset($rule['keyword']) ? $rule['keyword'] : '',
            'type_keyword' => isset($rule['type_keyword']) ? $rule['type_keyword'] : 'Equal',
            'reply_when' => isset($rule['reply_when']) ? $rule['reply_when'] : 'All',
            'contact_tag_id' => isset($rule['contact_tag_id']) ? $rule['contact_tag_id'] : null,
            'type' => isset($rule['type']) ? $rule['type'] : 'text',
            'reply' => isset($rule['reply']) ? $rule['reply'] : [],
            'status' => isset($rule['status']) ? strtolower($rule['status']) : 'active',
            'priority' => isset($rule['priority']) ? (int) $rule['priority'] : 100,
            'schedule_mode' => isset($rule['schedule_mode']) ? $rule['schedule_mode'] : 'always',
            'schedule_days' => isset($rule['schedule_days']) && is_array($rule['schedule_days']) ? $rule['schedule_days'] : [],
            'schedule_start' => isset($rule['schedule_start']) ? $rule['schedule_start'] : null,
            'schedule_end' => isset($rule['schedule_end']) ? $rule['schedule_end'] : null,
            'is_quoted' => !empty($rule['is_quoted']),
            'updated_at' => isset($rule['updated_at']) ? $rule['updated_at'] : now()->toDateTimeString(),
            'source' => isset($rule['source']) ? $rule['source'] : 'draft',
        ];
    }

    protected function canRuleReplyToContext($replyWhen, $context)
    {
        if ($replyWhen === 'All') {
            return true;
        }

        if ($replyWhen === 'Group') {
            return $context === 'group';
        }

        if ($replyWhen === 'Personal') {
            return $context === 'personal';
        }

        return false;
    }

    protected function keywordMatches(array $draftRule, $incomingMessage)
    {
        $incomingMessage = trim((string) $incomingMessage);
        $keyword = trim((string) $draftRule['keyword']);

        if ($draftRule['type_keyword'] === 'Equal') {
            return $keyword === $incomingMessage;
        }

        return stripos($incomingMessage, $keyword) !== false;
    }

    protected function canRuleReplyToRegisteredContact($contactTagId, array $registeredTagIds): bool
    {
        if (!$contactTagId) {
            return true;
        }

        return in_array((int) $contactTagId, $registeredTagIds, true);
    }

    public function isRuleScheduledActive(array $rule, Carbon $now = null)
    {
        $now = $now ?: now();

        if (!isset($rule['status']) || strtolower($rule['status']) !== 'active') {
            return false;
        }

        if (!isset($rule['schedule_mode']) || $rule['schedule_mode'] !== 'scheduled') {
            return true;
        }

        $days = isset($rule['schedule_days']) && is_array($rule['schedule_days']) ? $rule['schedule_days'] : [];
        $currentDay = strtolower($now->format('l'));
        if (!in_array($currentDay, $days, true)) {
            return false;
        }

        $start = isset($rule['schedule_start']) ? $rule['schedule_start'] : null;
        $end = isset($rule['schedule_end']) ? $rule['schedule_end'] : null;
        if (!$start || !$end) {
            return true;
        }

        $currentTime = $now->format('H:i');
        if ($start <= $end) {
            return $currentTime >= substr($start, 0, 5) && $currentTime <= substr($end, 0, 5);
        }

        return $currentTime >= substr($start, 0, 5) || $currentTime <= substr($end, 0, 5);
    }
}

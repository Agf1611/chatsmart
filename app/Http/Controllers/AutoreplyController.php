<?php

namespace App\Http\Controllers;

use App\Models\AiBot;
use App\Http\Requests\SaveAutoreplyRequest;
use App\Models\Autoreply;
use App\Models\Device;
use App\Models\Tag;
use App\Services\AutoreplyRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AutoreplyController extends Controller
{
    protected $ruleService;

    public function __construct(AutoreplyRuleService $ruleService)
    {
        $this->ruleService = $ruleService;
    }

    public function index(Request $request)
    {
        $devices = $request->user()->devices()->orderBy('body')->get();
        $defaultDeviceId = $request->input('device_id', session()->has('selectedDevice') ? session()->get('selectedDevice')['device_id'] : null);

        $query = $request->user()->autoreplies()->with(['device', 'phonebook'])->latest();

        if ($defaultDeviceId) {
            $query->where('device_id', $defaultDeviceId);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('keyword', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('reply_when')) {
            $query->where('reply_when', $request->input('reply_when'));
        }

        if ($request->filled('contact_tag_id')) {
            $query->where('contact_tag_id', $request->input('contact_tag_id'));
        }

        $autoreplies = $query->paginate(15)->withQueryString();

        return view('pages.autoreply', [
            'autoreplies' => $autoreplies,
            'devices' => $devices,
            'phonebooks' => $request->user()->phonebooks()->orderBy('name')->get(),
            'aiBots' => $request->user()->aiBots()->with('device')->orderBy('name')->get(),
            'selectedDeviceId' => $defaultDeviceId,
            'replyTypes' => $this->replyTypes(),
            'weekdayOptions' => $this->weekdayOptions(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->formView($request, null);
    }

    public function store(SaveAutoreplyRequest $request)
    {
        $device = $this->resolveOwnedDevice($request, $request->input('device_id'));
        $payload = $this->ruleService->formatForPersistence($request->validated());
        $payload['device_id'] = $device->id;

        $autoreply = $request->user()->autoreplies()->create($payload);
        $warnings = $this->ruleService->buildContainWarnings(
            $request->user()->autoreplies()->where('device_id', $device->id)->get(),
            $autoreply->keyword,
            $autoreply->type_keyword,
            $autoreply->id,
            $autoreply->trigger_event
        );

        return redirect()
            ->route('autoreply.edit', $autoreply->id)
            ->with('alert', ['type' => 'success', 'msg' => 'Auto reply berhasil ditambahkan.'])
            ->with('autoreplyWarnings', $warnings);
    }

    public function edit(Request $request, Autoreply $autoreply)
    {
        $this->authorizeAutoreply($request, $autoreply);
        return $this->formView($request, $autoreply);
    }

    public function update(SaveAutoreplyRequest $request, Autoreply $autoreply)
    {
        $this->authorizeAutoreply($request, $autoreply);
        $device = $this->resolveOwnedDevice($request, $request->input('device_id'));
        $payload = $this->ruleService->formatForPersistence($request->validated());
        $payload['device_id'] = $device->id;

        $autoreply->update($payload);
        $warnings = $this->ruleService->buildContainWarnings(
            $request->user()->autoreplies()->where('device_id', $device->id)->get(),
            $autoreply->keyword,
            $autoreply->type_keyword,
            $autoreply->id,
            $autoreply->trigger_event
        );

        return redirect()
            ->route('autoreply.edit', $autoreply->id)
            ->with('alert', ['type' => 'success', 'msg' => 'Auto reply berhasil diperbarui.'])
            ->with('autoreplyWarnings', $warnings);
    }

    public function destroy(Request $request, Autoreply $autoreply)
    {
        $this->authorizeAutoreply($request, $autoreply);
        $autoreply->delete();

        return redirect()->route('autoreply')->with('alert', ['type' => 'success', 'msg' => 'Auto reply berhasil dihapus.']);
    }

    public function duplicate(Request $request, Autoreply $autoreply)
    {
        $this->authorizeAutoreply($request, $autoreply);
        $peerRules = $request->user()->autoreplies()->where('device_id', $autoreply->device_id)->get();
        $duplicateAttributes = $this->ruleService->generateDuplicateAttributes($autoreply, $peerRules);

        $copy = $autoreply->replicate();
        $copy->name = $duplicateAttributes['name'];
        $copy->keyword = $duplicateAttributes['keyword'];
        $copy->status = 'inactive';
        $copy->save();

        return redirect()->route('autoreply.edit', $copy->id)->with('alert', [
            'type' => 'success',
            'msg' => 'Rule berhasil diduplikasi. Salinan dibuat dalam status nonaktif agar aman dicek dulu.',
        ]);
    }

    public function toggleStatus(Request $request, Autoreply $autoreply)
    {
        $this->authorizeAutoreply($request, $autoreply);
        $status = $request->boolean('active') ? 'active' : 'inactive';
        $autoreply->update(['status' => $status]);

        return response()->json([
            'error' => false,
            'msg' => 'Status rule diperbarui.',
            'status' => $status,
        ]);
    }

    public function simulate(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable'],
            'device_id' => ['required', 'exists:devices,id'],
            'contact_tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'name' => ['required', 'string'],
            'trigger_event' => ['required', 'in:keyword,first_chat'],
            'keyword' => ['nullable', 'string'],
            'type_keyword' => ['nullable', 'in:Equal,Contain'],
            'reply_when' => ['required', 'in:Group,Personal,All'],
            'type' => ['required', 'in:text,media,list,button,template,ai'],
            'ai_bot_id' => ['nullable', 'integer', 'exists:ai_bots,id'],
            'status' => ['required', 'in:active,inactive'],
            'is_quoted' => ['nullable', 'boolean'],
            'priority' => ['required', 'integer', 'min:1'],
            'schedule_mode' => ['required', 'in:always,scheduled'],
            'schedule_days' => ['nullable', 'array'],
            'schedule_start' => ['nullable'],
            'schedule_end' => ['nullable'],
            'message' => ['nullable', 'string'],
            'url' => ['nullable', 'string'],
            'media_type' => ['nullable', 'string'],
            'caption' => ['nullable', 'string'],
            'buttontext' => ['nullable', 'string'],
            'name_list' => ['nullable', 'string'],
            'title' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'buttons' => ['nullable', 'array'],
            'templates' => ['nullable', 'array'],
            'image' => ['nullable', 'string'],
            'footer' => ['nullable', 'string'],
            'sample_message' => ['required', 'string'],
            'sample_context' => ['required', 'in:personal,group'],
            'sample_is_first_chat' => ['nullable', 'boolean'],
            'sample_registered_contact' => ['nullable', 'boolean'],
        ]);

        $device = $this->resolveOwnedDevice($request, $data['device_id']);
        $phonebook = null;
        if (!empty($data['contact_tag_id'])) {
            $phonebook = $request->user()->phonebooks()->whereKey($data['contact_tag_id'])->first();
            abort_unless($phonebook, 422);
        }
        if (!empty($data['ai_bot_id'])) {
            $bot = $request->user()->aiBots()->whereKey($data['ai_bot_id'])->first();
            abort_unless($bot && (int) $bot->device_id === (int) $device->id, 422);
        }

        $replyConfig = $this->ruleService->buildReplyConfig($data);
        $draftRule = [
            'id' => isset($data['id']) ? $data['id'] : null,
            'name' => $data['name'],
            'trigger_event' => $data['trigger_event'],
            'keyword' => $data['trigger_event'] === 'keyword' ? ($data['keyword'] ?? '') : '__first_chat__:' . $device->id,
            'type_keyword' => $data['trigger_event'] === 'keyword' ? ($data['type_keyword'] ?? 'Equal') : 'Equal',
            'reply_when' => $data['reply_when'],
            'contact_tag_id' => $phonebook?->id,
            'type' => $data['type'],
            'reply' => $this->ruleService->buildRuntimeReply($data['type'], $replyConfig),
            'status' => strtolower($data['status']),
            'priority' => (int) $data['priority'],
            'schedule_mode' => $data['schedule_mode'],
            'schedule_days' => $data['schedule_mode'] === 'scheduled' ? array_values(isset($data['schedule_days']) ? $data['schedule_days'] : []) : [],
            'schedule_start' => $data['schedule_mode'] === 'scheduled' ? $data['schedule_start'] : null,
            'schedule_end' => $data['schedule_mode'] === 'scheduled' ? $data['schedule_end'] : null,
            'is_quoted' => !empty($data['is_quoted']),
            'is_first_chat' => !empty($data['sample_is_first_chat']),
            'updated_at' => now()->toDateTimeString(),
            'source' => 'draft',
        ];

        $rules = $request->user()->autoreplies()
            ->where('device_id', $device->id)
            ->get()
            ->reject(function ($rule) use ($draftRule) {
                return $draftRule['id'] && (int) $rule->id === (int) $draftRule['id'];
            })
            ->values();

        $match = $this->ruleService->matchRules(
            $rules->concat(Collection::make([$draftRule])),
            $data['sample_message'],
            $data['sample_context'],
            null,
            [
                'is_first_chat' => !empty($data['sample_is_first_chat']),
                'registered_tag_ids' => !empty($data['sample_registered_contact']) && $phonebook ? [$phonebook->id] : [],
            ]
        );
        $draftReasons = $this->ruleService->explainDraftMismatch(
            $draftRule,
            $data['sample_message'],
            $data['sample_context'],
            null,
            ['registered_tag_ids' => !empty($data['sample_registered_contact']) && $phonebook ? [$phonebook->id] : []]
        );

        if (!$match['matched']) {
            return response()->json([
                'matched' => false,
                'source' => null,
                'rule' => null,
                'reasons' => !empty($draftReasons) ? $draftReasons : ['Tidak ada rule yang cocok untuk pesan dan konteks ini.'],
                'preview_html' => view('ajax.messages.emptyshow')->render(),
            ]);
        }

        $matchedRule = $match['rule'];
        $previewHtml = $this->ruleService->renderPreview(
            $matchedRule['trigger_event'] === 'first_chat' ? 'First Chat' : $matchedRule['keyword'],
            $matchedRule['type'],
            $matchedRule['reply']
        );
        $reasons = $matchedRule['source'] === 'draft'
            ? ['Draft rule ini yang akan dipilih oleh sistem.']
            : array_merge(
                ['Draft rule ini tidak terpilih karena ada rule lain yang lebih dulu lolos pipeline.'],
                $draftReasons
            );

        return response()->json([
            'matched' => true,
            'source' => $matchedRule['source'],
            'rule' => [
                'id' => $matchedRule['id'],
                'name' => $matchedRule['name'],
                'keyword' => $matchedRule['keyword'],
                'trigger_event' => $matchedRule['trigger_event'],
                'type' => $matchedRule['type'],
            ],
            'reasons' => $reasons,
            'preview_html' => $previewHtml,
        ]);
    }

    protected function formView(Request $request, Autoreply $autoreply = null)
    {
        $devices = $request->user()->devices()->orderBy('body')->get();
        $phonebooks = $request->user()->phonebooks()->withCount('contacts')->orderBy('name')->get();
        $formData = $this->ruleService->getFormData($autoreply);

        if (old()) {
            $formData = array_merge($formData, [
                'name' => old('name', $formData['name']),
                'device_id' => old('device_id', $formData['device_id']),
                'trigger_event' => old('trigger_event', $formData['trigger_event']),
                'keyword' => old('keyword', $formData['keyword']),
                'type_keyword' => old('type_keyword', $formData['type_keyword']),
                'reply_when' => old('reply_when', $formData['reply_when']),
                'contact_tag_id' => old('contact_tag_id', $formData['contact_tag_id']),
                'type' => old('type', $formData['type']),
                'ai_bot_id' => old('ai_bot_id', $formData['ai_bot_id']),
                'status' => old('status', $formData['status']),
                'is_quoted' => old('is_quoted', $formData['is_quoted']),
                'priority' => old('priority', $formData['priority']),
                'schedule_mode' => old('schedule_mode', $formData['schedule_mode']),
                'schedule_days' => old('schedule_days', $formData['schedule_days']),
                'schedule_start' => old('schedule_start', $formData['schedule_start']),
                'schedule_end' => old('schedule_end', $formData['schedule_end']),
                'reply_config' => array_merge($formData['reply_config'], [
                    'message' => old('message', isset($formData['reply_config']['message']) ? $formData['reply_config']['message'] : ''),
                    'url' => old('url', isset($formData['reply_config']['url']) ? $formData['reply_config']['url'] : ''),
                    'media_type' => old('media_type', isset($formData['reply_config']['media_type']) ? $formData['reply_config']['media_type'] : 'document'),
                    'caption' => old('caption', isset($formData['reply_config']['caption']) ? $formData['reply_config']['caption'] : ''),
                    'buttontext' => old('buttontext', isset($formData['reply_config']['buttontext']) ? $formData['reply_config']['buttontext'] : ''),
                    'name' => old('name_list', isset($formData['reply_config']['name']) ? $formData['reply_config']['name'] : ''),
                    'title' => old('title', isset($formData['reply_config']['title']) ? $formData['reply_config']['title'] : ''),
                    'items' => old('items', isset($formData['reply_config']['items']) ? $formData['reply_config']['items'] : []),
                    'buttons' => old('buttons', isset($formData['reply_config']['buttons']) ? $formData['reply_config']['buttons'] : []),
                    'templates' => old('templates', isset($formData['reply_config']['templates']) ? $formData['reply_config']['templates'] : []),
                    'image' => old('image', isset($formData['reply_config']['image']) ? $formData['reply_config']['image'] : ''),
                    'footer' => old('footer', isset($formData['reply_config']['footer']) ? $formData['reply_config']['footer'] : ''),
                ]),
            ]);
        }

        $warnings = session('autoreplyWarnings', []);
        if ($autoreply) {
            $warnings = $this->ruleService->buildContainWarnings(
                $request->user()->autoreplies()->where('device_id', $autoreply->device_id)->get(),
                $formData['keyword'],
                $formData['type_keyword'],
                $autoreply->id,
                $formData['trigger_event']
            );
        }

        $aiBots = $request->user()->aiBots()->with('device')->orderBy('name')->get();
        $aiBotsPayload = $aiBots->map(function ($bot) {
            return [
                'id' => $bot->id,
                'device_id' => $bot->device_id,
                'name' => $bot->name,
                'engine_type' => strtoupper($bot->engine_type),
                'model' => $bot->model,
                'thinking_mode' => ucfirst($bot->thinking_mode),
                'persona' => $bot->persona,
                'response_style' => $bot->response_style,
            ];
        })->values();

        return view('pages.autoreply-form', [
            'autoreply' => $autoreply,
            'devices' => $devices,
            'phonebooks' => $phonebooks,
            'aiBots' => $aiBots,
            'aiBotsPayload' => $aiBotsPayload,
            'formData' => $formData,
            'replyTypes' => $this->replyTypes(),
            'weekdayOptions' => $this->weekdayOptions(),
            'warnings' => $warnings,
            'initialPreviewHtml' => $this->buildInitialPreview($formData, $autoreply),
        ]);
    }

    protected function resolveOwnedDevice(Request $request, $deviceId)
    {
        $device = $request->user()->devices()->find($deviceId);
        abort_unless($device, 404);

        return $device;
    }

    protected function authorizeAutoreply(Request $request, Autoreply $autoreply)
    {
        abort_unless((int) $autoreply->user_id === (int) $request->user()->id, 404);
    }

    protected function replyTypes()
    {
        return [
            'text' => 'Text Message',
            'media' => 'Media Message',
            'list' => 'List Message',
            'ai' => 'AI Reply',
            'button' => 'Button Message (Legacy)',
            'template' => 'Template Message (Legacy)',
        ];
    }

    protected function weekdayOptions()
    {
        return [
            'monday' => 'Senin',
            'tuesday' => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday' => 'Kamis',
            'friday' => 'Jumat',
            'saturday' => 'Sabtu',
            'sunday' => 'Minggu',
        ];
    }

    protected function buildInitialPreview(array $formData, Autoreply $autoreply = null)
    {
        if ($autoreply) {
            return $this->ruleService->renderPreview(
                ($autoreply->trigger_event ?? 'keyword') === 'first_chat' ? 'First Chat' : $autoreply->keyword,
                $autoreply->type,
                $autoreply->reply
            );
        }

        if (!empty($formData['type']) && !empty($formData['reply_config'])) {
            try {
                return $this->ruleService->renderPreview(
                    ($formData['trigger_event'] ?? 'keyword') === 'first_chat' ? 'First Chat' : ($formData['keyword'] ?: 'Preview'),
                    $formData['type'],
                    $this->ruleService->buildRuntimeReply($formData['type'], $formData['reply_config'])
                );
            } catch (\Throwable $th) {
                return view('ajax.messages.emptyshow')->render();
            }
        }

        return view('ajax.messages.emptyshow')->render();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AiBot;
use App\Services\AiReplyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AiBotController extends Controller
{
    protected $aiReplyService;

    public function __construct(AiReplyService $aiReplyService)
    {
        $this->aiReplyService = $aiReplyService;
    }

    public function index(Request $request)
    {
        $devices = $request->user()->devices()->orderBy('body')->get();
        $query = $request->user()->aiBots()->with('device')->latest();

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('engine_type')) {
            $query->where('engine_type', $request->engine_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('persona', 'like', '%' . $search . '%')
                    ->orWhere('model', 'like', '%' . $search . '%');
            });
        }

        return view('pages.ai-bots.index', [
            'bots' => $query->paginate(15)->withQueryString(),
            'devices' => $devices,
            'engineOptions' => [
                'openai' => 'OpenAI / ChatGPT',
                'gemini' => 'Gemini',
                'ollama' => 'Ollama (Local)',
                'webhook' => 'Webhook',
            ],
        ]);
    }

    public function create(Request $request)
    {
        return $this->formView($request);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $request->user()->aiBots()->create($data);

        return redirect()->route('ai-bots.index')->with('alert', [
            'type' => 'success',
            'msg' => 'AI bot profile berhasil dibuat.',
        ]);
    }

    public function edit(Request $request, AiBot $aiBot)
    {
        $this->authorizeBot($request, $aiBot);
        return $this->formView($request, $aiBot);
    }

    public function update(Request $request, AiBot $aiBot)
    {
        $this->authorizeBot($request, $aiBot);
        $data = $this->validatePayload($request);
        $aiBot->update($data);

        return redirect()->route('ai-bots.index')->with('alert', [
            'type' => 'success',
            'msg' => 'AI bot profile berhasil diperbarui.',
        ]);
    }

    public function destroy(Request $request, AiBot $aiBot)
    {
        $this->authorizeBot($request, $aiBot);
        $aiBot->delete();

        return redirect()->route('ai-bots.index')->with('alert', [
            'type' => 'success',
            'msg' => 'AI bot profile berhasil dihapus.',
        ]);
    }

    public function duplicate(Request $request, AiBot $aiBot)
    {
        $this->authorizeBot($request, $aiBot);
        $copy = $aiBot->replicate();
        $copy->name = $aiBot->name . ' Copy';
        $copy->status = 'inactive';
        $copy->save();

        return redirect()->route('ai-bots.edit', $copy->id)->with('alert', [
            'type' => 'success',
            'msg' => 'AI bot berhasil diduplikasi dalam status nonaktif.',
        ]);
    }

    protected function formView(Request $request, AiBot $aiBot = null)
    {
        $devices = $request->user()->devices()->orderBy('body')->get();
        $engineOptions = [
            'openai' => 'OpenAI / ChatGPT',
            'gemini' => 'Gemini',
            'ollama' => 'Ollama (Local)',
            'webhook' => 'Webhook',
        ];

        $defaults = [
            'name' => old('name', $aiBot->name ?? ''),
            'device_id' => old('device_id', $aiBot->device_id ?? (session('selectedDevice.device_id') ?: '')),
            'engine_type' => old('engine_type', $aiBot->engine_type ?? 'openai'),
            'model' => old('model', $aiBot->model ?? 'gpt-5-mini'),
            'thinking_mode' => old('thinking_mode', $aiBot->thinking_mode ?? 'balanced'),
            'system_prompt' => old('system_prompt', $aiBot->system_prompt ?? ''),
            'persona' => old('persona', $aiBot->persona ?? ''),
            'response_style' => old('response_style', $aiBot->response_style ?? ''),
            'memory_window' => old('memory_window', $aiBot->memory_window ?? 20),
            'handoff_mode' => old('handoff_mode', $aiBot->handoff_mode ?? 'manual_pause'),
            'fallback_mode' => old('fallback_mode', $aiBot->fallback_mode ?? 'silent'),
            'fallback_message' => old('fallback_message', $aiBot->fallback_message ?? ''),
            'timeout_seconds' => old('timeout_seconds', $aiBot->timeout_seconds ?? (int) env('AI_DEFAULT_TIMEOUT', 20)),
            'max_output_tokens' => old('max_output_tokens', $aiBot->max_output_tokens ?? (int) env('AI_DEFAULT_MAX_OUTPUT', 400)),
            'daily_limit' => old('daily_limit', $aiBot->daily_limit ?? ''),
            'webhook_url' => old('webhook_url', $aiBot->webhook_url ?? ''),
            'webhook_auth_header' => old('webhook_auth_header', $aiBot->webhook_auth_header ?? 'Authorization'),
            'webhook_auth_token' => old('webhook_auth_token', $aiBot->webhook_auth_token ?? ''),
            'status' => old('status', $aiBot->status ?? 'active'),
        ];

        return view('pages.ai-bots.form', [
            'aiBot' => $aiBot,
            'devices' => $devices,
            'engineOptions' => $engineOptions,
            'thinkingModes' => $this->aiReplyService->thinkingModeOptions(),
            'modelOptions' => $this->aiReplyService->botModelOptions(),
            'formData' => $defaults,
        ]);
    }

    protected function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'device_id' => ['required', 'exists:devices,id'],
            'engine_type' => ['required', 'in:openai,gemini,ollama,webhook'],
            'model' => ['nullable', 'string', 'max:191'],
            'thinking_mode' => ['required', 'in:precise,balanced,creative'],
            'system_prompt' => ['nullable', 'string'],
            'persona' => ['nullable', 'string', 'max:191'],
            'response_style' => ['nullable', 'string', 'max:191'],
            'memory_window' => ['required', 'integer', 'min:2', 'max:50'],
            'handoff_mode' => ['required', 'in:manual_pause,none'],
            'fallback_mode' => ['required', 'in:silent,text'],
            'fallback_message' => ['nullable', 'string'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:60'],
            'max_output_tokens' => ['required', 'integer', 'min:50', 'max:2000'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'webhook_url' => ['nullable', 'url', 'max:2048'],
            'webhook_auth_header' => ['nullable', 'string', 'max:191'],
            'webhook_auth_token' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        abort_unless($request->user()->devices()->whereKey($data['device_id'])->exists(), 404);

        if ($data['engine_type'] === 'webhook' && empty($data['webhook_url'])) {
            throw ValidationException::withMessages([
                'webhook_url' => 'Webhook URL wajib diisi untuk bot webhook.',
            ]);
        }

        if ($data['status'] === 'active' && $data['engine_type'] === 'openai' && !env('OPENAI_API_KEY')) {
            throw ValidationException::withMessages([
                'engine_type' => 'OpenAI API key belum diisi di Admin Settings.',
            ]);
        }

        if ($data['status'] === 'active' && $data['engine_type'] === 'gemini' && !env('GEMINI_API_KEY')) {
            throw ValidationException::withMessages([
                'engine_type' => 'Gemini API key belum diisi di Admin Settings.',
            ]);
        }

        if ($data['status'] === 'active' && $data['engine_type'] === 'ollama' && !trim((string) env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'))) {
            throw ValidationException::withMessages([
                'engine_type' => 'OLLAMA_BASE_URL belum diisi.',
            ]);
        }

        return $data;
    }

    protected function authorizeBot(Request $request, AiBot $aiBot): void
    {
        abort_unless((int) $request->user()->id === (int) $aiBot->user_id, 404);
    }
}

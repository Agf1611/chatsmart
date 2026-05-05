<x-layout-dashboard title="{{ $aiBot ? 'Edit AI Bot' : 'Tambah AI Bot' }}">
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Whatsapp</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('ai-bots.index') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ai-bots.index') }}">AI Bot</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $aiBot ? 'Edit Bot' : 'Tambah Bot' }}</li>
                </ol>
            </nav>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert border-0 bg-light-danger alert-dismissible fade show py-2">
            <div class="d-flex align-items-center">
                <div class="fs-3 text-danger"><i class="bi bi-exclamation-circle-fill"></i></div>
                <div class="ms-3">
                    <p class="mb-1">Masih ada data yang perlu diperbaiki.</p>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="section-hero-card mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-xl-8">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="hero-icon">
                                <i class="bx bx-brain"></i>
                            </div>
                            <div>
                                <h3 class="mb-2">{{ $aiBot ? 'Edit AI Bot' : 'Buat AI Bot Baru' }}</h3>
                                <p class="hero-meta mb-0">
                            Siapkan profil bot AI dengan persona, model, memory, fallback, engine lokal Ollama,
                            dan webhook agar Auto Reply bisa memakai engine yang paling sesuai untuk kebutuhan operasional Anda.
                                </p>
                            </div>
                        </div>
            </div>
            <div class="col-xl-4">
                <div class="hero-metrics">
                    <div class="hero-metric">
                        <span>Mode form</span>
                        <strong>{{ $aiBot ? 'Edit' : 'Create' }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Engine tersedia</span>
                        <strong>{{ count($engineOptions) }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Device tersedia</span>
                        <strong>{{ $devices->count() }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ $aiBot ? route('ai-bots.update', $aiBot->id) : route('ai-bots.store') }}" method="POST" id="ai-bot-form">
        @csrf
        @if ($aiBot)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card smart-form-card">
                    <div class="smart-card-header">
                        <h5>1. Info Bot</h5>
                        <p>Isi identitas bot, device tujuan, engine, model, dan status operasional.</p>
                    </div>
                    <div class="card-body row g-3 pt-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Bot</label>
                            <input type="text" name="name" class="form-control" value="{{ $formData['name'] }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Whatsapp Account</label>
                            <select name="device_id" class="form-select" required>
                                <option value="">Pilih device</option>
                                @foreach ($devices as $device)
                                    <option value="{{ $device->id }}" {{ (string) $formData['device_id'] === (string) $device->id ? 'selected' : '' }}>
                                        {{ $device->body }} ({{ $device->status }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Engine</label>
                            <select name="engine_type" id="engine_type" class="form-select" required>
                                @foreach ($engineOptions as $value => $label)
                                    <option value="{{ $value }}" {{ $formData['engine_type'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Model</label>
                            <select name="model" id="bot_model" class="form-select">
                                @foreach ($modelOptions as $engine => $models)
                                    @foreach ($models as $model)
                                        <option value="{{ $model }}" data-engine="{{ $engine }}" {{ $formData['model'] === $model ? 'selected' : '' }}>
                                            {{ $model }}
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" {{ $formData['status'] === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $formData['status'] === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card smart-form-card">
                    <div class="smart-card-header">
                        <h5>2. Persona dan Prompt</h5>
                        <p>Atur karakter balasan, gaya bahasa, memori, dan instruksi inti untuk bot.</p>
                    </div>
                    <div class="card-body row g-3 pt-3">
                        <div class="col-md-6">
                            <label class="form-label">Persona</label>
                            <input type="text" name="persona" class="form-control" value="{{ $formData['persona'] }}" placeholder="Contoh: CS ramah dan cepat">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Response Style</label>
                            <input type="text" name="response_style" class="form-control" value="{{ $formData['response_style'] }}" placeholder="Contoh: singkat, formal, persuasif">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Thinking Mode</label>
                            <select name="thinking_mode" class="form-select">
                                @foreach ($thinkingModes as $value => $label)
                                    <option value="{{ $value }}" {{ $formData['thinking_mode'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Memory Window</label>
                            <input type="number" name="memory_window" class="form-control" min="2" max="50" value="{{ $formData['memory_window'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Handoff</label>
                            <select name="handoff_mode" class="form-select">
                                <option value="manual_pause" {{ $formData['handoff_mode'] === 'manual_pause' ? 'selected' : '' }}>Manual Pause</option>
                                <option value="none" {{ $formData['handoff_mode'] === 'none' ? 'selected' : '' }}>No Handoff</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">System Prompt</label>
                            <textarea name="system_prompt" class="form-control" rows="8" placeholder="Instruksi utama bot AI">{{ $formData['system_prompt'] }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card smart-form-card">
                    <div class="smart-card-header">
                        <h5>3. Output dan Fallback</h5>
                        <p>Tentukan batas waktu respon, output token, limit harian, dan fallback saat engine gagal.</p>
                    </div>
                    <div class="card-body row g-3 pt-3">
                        <div class="col-md-4">
                            <label class="form-label">Timeout (detik)</label>
                            <input type="number" name="timeout_seconds" class="form-control" min="5" max="60" value="{{ $formData['timeout_seconds'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max Output Tokens</label>
                            <input type="number" name="max_output_tokens" class="form-control" min="50" max="2000" value="{{ $formData['max_output_tokens'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Daily Limit</label>
                            <input type="number" name="daily_limit" class="form-control" min="1" value="{{ $formData['daily_limit'] }}" placeholder="Opsional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fallback Mode</label>
                            <select name="fallback_mode" id="fallback_mode" class="form-select">
                                <option value="silent" {{ $formData['fallback_mode'] === 'silent' ? 'selected' : '' }}>Silent</option>
                                <option value="text" {{ $formData['fallback_mode'] === 'text' ? 'selected' : '' }}>Send Text</option>
                            </select>
                        </div>
                        <div class="col-md-8 fallback-message-wrapper">
                            <label class="form-label">Fallback Message</label>
                            <input type="text" name="fallback_message" class="form-control" value="{{ $formData['fallback_message'] }}" placeholder="Pesan jika AI error / timeout">
                        </div>
                    </div>
                </div>

                <div class="card smart-form-card webhook-fields">
                    <div class="smart-card-header">
                        <h5>4. Webhook Engine</h5>
                        <p>Bagian ini hanya dipakai jika engine bot diarahkan ke integrasi webhook eksternal.</p>
                    </div>
                    <div class="card-body row g-3 pt-3">
                        <div class="col-md-12">
                            <label class="form-label">Webhook URL</label>
                            <input type="url" name="webhook_url" class="form-control" value="{{ $formData['webhook_url'] }}" placeholder="https://...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Auth Header</label>
                            <input type="text" name="webhook_auth_header" class="form-control" value="{{ $formData['webhook_auth_header'] }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Auth Token</label>
                            <input type="text" name="webhook_auth_token" class="form-control" value="{{ $formData['webhook_auth_token'] }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card smart-panel-card sticky-panel">
                    <div class="smart-card-header">
                        <h5>Ringkasan</h5>
                        <p>Semua AI reply tetap dipanggil dari rule Auto Reply bertipe <strong>AI Reply</strong>.</p>
                    </div>
                    <div class="card-body pt-3">
                        <div class="smart-panel-note mb-3">
                            Untuk V1, input yang diproses hanya pesan teks. Media dari user tetap diabaikan dan dicatat di log. AI reply dapat dipakai sebagai rule khusus atau bot default per device. Jika memilih engine Ollama, pastikan server lokalnya aktif di <code>OLLAMA_BASE_URL</code>.
                        </div>
                        <div class="smart-kpi-grid mb-3">
                            <div class="smart-kpi">
                                <span>Engine</span>
                                <strong>{{ strtoupper($formData['engine_type'] ?: 'openai') }}</strong>
                            </div>
                            <div class="smart-kpi">
                                <span>Thinking</span>
                                <strong>{{ ucfirst($formData['thinking_mode'] ?: 'balanced') }}</strong>
                            </div>
                            <div class="smart-kpi">
                                <span>Fallback</span>
                                <strong>{{ ucfirst($formData['fallback_mode'] ?: 'silent') }}</strong>
                            </div>
                            <div class="smart-kpi">
                                <span>Memory</span>
                                <strong>{{ $formData['memory_window'] }} pesan</strong>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">{{ $aiBot ? 'Simpan Perubahan' : 'Simpan AI Bot' }}</button>
                            <a href="{{ route('ai-bots.index') }}" class="btn btn-outline-secondary">Kembali ke daftar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        (function() {
            const engineInput = document.getElementById('engine_type');
            const modelInput = document.getElementById('bot_model');
            const fallbackInput = document.getElementById('fallback_mode');
            const webhookFields = document.querySelector('.webhook-fields');
            const fallbackWrapper = document.querySelector('.fallback-message-wrapper');

            function syncModels() {
                const engine = engineInput.value;
                Array.from(modelInput.options).forEach(function(option) {
                    option.hidden = option.dataset.engine !== engine;
                });

                const selected = modelInput.options[modelInput.selectedIndex];
                if (!selected || selected.hidden) {
                    const nextOption = Array.from(modelInput.options).find(function(option) {
                        return !option.hidden;
                    });
                    if (nextOption) {
                        modelInput.value = nextOption.value;
                    }
                }

                webhookFields.classList.toggle('d-none', engine !== 'webhook');
            }

            function syncFallback() {
                fallbackWrapper.classList.toggle('d-none', fallbackInput.value !== 'text');
            }

            syncModels();
            syncFallback();
            engineInput.addEventListener('change', syncModels);
            fallbackInput.addEventListener('change', syncFallback);
        })();
    </script>
</x-layout-dashboard>

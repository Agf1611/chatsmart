<x-layout-dashboard title="Settings Server ">
    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Admin</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Setting Server</li>
                </ol>
            </nav>
        </div>
    </div>
    <!--end breadcrumb-->

    @if (session()->has('alert'))
        <x-alert>
            @slot('type', session('alert')['type'])
            @slot('msg', session('alert')['msg'])
        </x-alert>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="row">

        <div class="col">
            <div class="page-description page-description-tabbed">


                <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#server"
                            type="button" role="tab" aria-controls="hoaccountme"
                            aria-selected="true">Server</button>
                    </li>


                </ul>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="server" role="tabpanel" aria-labelledby="account-tab">
                    <div class="card">
                        <div class="card-body">
                            <div class="row g-4 align-items-start">
                                <div class="col-lg-8">
                                    <form action="{{ route('setServer') }}" method="POST" id="server-config-form">
                                        @csrf
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Deployment Profile</label>
                                                <select name="deployment_profile" id="deployment_profile" class="form-control" required>
                                                    @foreach ($serverProfiles as $key => $profile)
                                                        <option value="{{ $key }}" {{ ($serverPreview['deployment_profile'] ?? 'auto') === $key ? 'selected' : '' }}>
                                                            {{ $profile['label'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted d-block mt-2">Pilih pola deploy yang paling sesuai agar app mengisi env otomatis tanpa terminal.</small>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">App URL</label>
                                                <input type="url" name="app_url" class="form-control" id="app_url" value="{{ $serverPreview['APP_URL'] ?? config('app.url') }}" required>
                                            </div>

                                            <div class="col-md-6">
                                                <label for="Port" class="form-label">Port Node JS</label>
                                                <input type="number" name="portnode" class="form-control" id="portnode"
                                                    value="{{ $serverPreview['PORT_NODE'] ?? env('PORT_NODE') }}" min="1" max="65535" required>
                                            </div>

                                            <div class="col-md-6 node-public-group">
                                                <label class="form-label">URL Node Publik</label>
                                                <input type="url" class="form-control" value="{{ $serverPreview['WA_URL_SERVER'] ?? env('WA_URL_SERVER') }}" name="urlnode" id="urlnode">
                                                <small class="text-muted d-block mt-2">
                                                    Gunakan subdomain Node publik jika Laravel dan Node dipisah.
                                                </small>
                                            </div>

                                            <div class="col-md-6 node-internal-group">
                                                <label class="form-label">URL Node Internal</label>
                                                <input type="url" class="form-control" value="{{ $serverPreview['WA_URL_SERVER_INTERNAL'] ?? env('WA_URL_SERVER_INTERNAL') }}" name="urlnode_internal" id="urlnode_internal">
                                                <small class="text-muted d-block mt-2">
                                                    Untuk server sendiri atau Cloudflare Tunnel, ini dipakai dari sisi Laravel/PHP.
                                                </small>
                                            </div>

                                            <div class="col-12">
                                                <div class="alert alert-light border mb-0">
                                                    <div class="fw-semibold mb-2">Preview konfigurasi aktif</div>
                                                    <div class="row g-2 small">
                                                        <div class="col-md-6"><strong>TYPE_SERVER:</strong> <span id="preview_type">{{ $serverPreview['TYPE_SERVER'] ?? 'auto' }}</span></div>
                                                        <div class="col-md-6"><strong>WA_URL_SERVER:</strong> <span id="preview_node">{{ $serverPreview['WA_URL_SERVER'] ?? '-' }}</span></div>
                                                        <div class="col-md-6"><strong>WA_URL_SERVER_INTERNAL:</strong> <span id="preview_internal">{{ $serverPreview['WA_URL_SERVER_INTERNAL'] ?? '-' }}</span></div>
                                                        <div class="col-md-6"><strong>SESSION_SECURE_COOKIE:</strong> <span id="preview_secure">{{ $serverPreview['SESSION_SECURE_COOKIE'] ?? 'false' }}</span></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 d-flex gap-2 flex-wrap mt-2">
                                                <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="test-server-btn">Test Node</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="col-lg-4">
                                    <div class="alert alert-info border mb-3">
                                        <div class="fw-semibold mb-1">Panduan Cepat</div>
                                        <ul class="mb-0 ps-3">
                                            <li><code>localhost</code> untuk XAMPP/dev.</li>
                                            <li><code>hosting_same_domain</code> jika web dan Node diproxy lewat domain yang sama.</li>
                                            <li><code>hosting_remote_node</code> jika Node hidup di subdomain/server lain.</li>
                                            <li><code>self_hosted_tunnel</code> jika Node dipublish via Cloudflare Tunnel.</li>
                                        </ul>
                                    </div>
                                    <div class="alert alert-secondary border mb-0" id="server-test-result">
                                        Status test Node akan tampil di sini.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="mb-1">Auto Cleanup Message History</h5>
                                    <p class="text-secondary mb-4">
                                        Hapus histori pesan lama otomatis setiap hari agar database tidak menumpuk.
                                    </p>
                                </div>
                            </div>

                            <form action="{{ route('settings.history-cleanup') }}" method="POST">
                                @csrf
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label for="history_cleanup_enabled" class="form-label">Status Auto Cleanup</label>
                                        <select name="enabled" id="history_cleanup_enabled" class="form-control">
                                            <option value="0" {{ !$historyCleanup['enabled'] ? 'selected' : '' }}>Nonaktif</option>
                                            <option value="1" {{ $historyCleanup['enabled'] ? 'selected' : '' }}>Aktif</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="history_cleanup_days" class="form-label">Simpan Histori Maksimal</label>
                                        <select name="days" id="history_cleanup_days" class="form-control" required>
                                            @foreach ([7, 30, 60, 90] as $dayOption)
                                                <option value="{{ $dayOption }}" {{ $historyCleanup['days'] === $dayOption ? 'selected' : '' }}>
                                                    {{ $dayOption }} hari
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="alert alert-light border mb-0">
                                            Jadwal cleanup otomatis: setiap hari pukul 01:30 server.
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">Simpan Pengaturan</button>
                                    </div>
                                </div>
                            </form>

                            <form action="{{ route('settings.history-cleanup.run') }}" method="POST" class="mt-3">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Jalankan cleanup histori sekarang?')">
                                    Jalankan Cleanup Sekarang
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="mb-1">AI Bot Settings</h5>
                                    <p class="text-secondary mb-4">
                                        Pengaturan global untuk provider AI dan orkestrasi bot otomatis.
                                    </p>
                                </div>
                            </div>

                            <form action="{{ route('settings.ai-bot') }}" method="POST">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Status AI Bot</label>
                                        <select name="enabled" class="form-control">
                                            <option value="0" {{ !$aiBotSettings['enabled'] ? 'selected' : '' }}>Nonaktif</option>
                                            <option value="1" {{ $aiBotSettings['enabled'] ? 'selected' : '' }}>Aktif</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Default Timeout (detik)</label>
                                        <input type="number" name="default_timeout" class="form-control" min="5" max="60" value="{{ $aiBotSettings['default_timeout'] }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Default Max Output</label>
                                        <input type="number" name="default_max_output" class="form-control" min="50" max="2000" value="{{ $aiBotSettings['default_max_output'] }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">OpenAI API Key</label>
                                        <input type="text" name="openai_api_key" id="openai_api_key" class="form-control" placeholder="{{ $aiBotSettings['openai_key'] ?: 'Masukkan API key OpenAI' }}">
                                        <div class="form-text">Kosongkan jika tidak ingin mengubah key yang sudah tersimpan.</div>
                                        <div class="mt-2 d-flex gap-2 flex-wrap">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="test-openai-btn">Test OpenAI</button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gemini API Key</label>
                                        <input type="text" name="gemini_api_key" id="gemini_api_key" class="form-control" placeholder="{{ $aiBotSettings['gemini_key'] ?: 'Masukkan API key Gemini' }}">
                                        <div class="form-text">Kosongkan jika tidak ingin mengubah key yang sudah tersimpan.</div>
                                        <div class="mt-2 d-flex gap-2 flex-wrap">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="test-gemini-btn">Test Gemini</button>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">Simpan AI Settings</button>
                                    </div>
                                </div>
                            </form>
                            <div class="alert alert-secondary border mt-3 mb-0" id="ai-test-result">
                                Status test API akan tampil di sini.
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>


    <script>
        const syncServerProfile = () => {
            const profile = $('#deployment_profile').val();
            const appUrl = $('#app_url').val();
            const port = $('#portnode').val() || '3100';
            const showNodeFields = profile === 'hosting_remote_node' || profile === 'self_hosted_tunnel';

            $('.node-public-group, .node-internal-group').toggleClass('d-none', !showNodeFields);

            if (profile === 'hosting_same_domain') {
                $('#urlnode').val(appUrl);
                $('#urlnode_internal').val(appUrl);
            }

            if (profile === 'localhost') {
                $('#urlnode').val('http://127.0.0.1:' + port);
                $('#urlnode_internal').val('http://127.0.0.1:' + port);
            }

            $('#preview_type').text(profile);
            $('#preview_node').text($('#urlnode').val() || appUrl || '-');
            $('#preview_internal').text($('#urlnode_internal').val() || appUrl || '-');
            $('#preview_secure').text(appUrl.indexOf('https://') === 0 ? 'true' : 'false');
        };

        $('#deployment_profile, #app_url, #portnode').on('change keyup', syncServerProfile);
        syncServerProfile();

        $('#test-server-btn').on('click', function() {
            const $btn = $(this);
            $btn.prop('disabled', true).text('Testing...');

            $.ajax({
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '{{ route('settings.server.test') }}',
                data: {
                    deployment_profile: $('#deployment_profile').val(),
                    app_url: $('#app_url').val(),
                    portnode: $('#portnode').val(),
                    urlnode: $('#urlnode').val(),
                    urlnode_internal: $('#urlnode_internal').val(),
                },
                success: function(response) {
                    const lines = [];
                    lines.push(`Profile: ${response.profile}`);
                    (response.tests || []).forEach((item) => {
                        const status = item.healthy ? 'OK' : 'FAIL';
                        lines.push(`${status} ${item.target}${item.status ? ' (HTTP ' + item.status + ')' : ''}${item.error ? ' - ' + item.error : ''}`);
                    });
                    $('#server-test-result').removeClass('alert-secondary alert-success alert-danger').addClass('alert-success').html(lines.join('<br>'));
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Test Node gagal dijalankan.';
                    $('#server-test-result').removeClass('alert-secondary alert-success alert-danger').addClass('alert-danger').text(message);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Test Node');
                }
            });
        });

        const runAiTest = (provider, $btn, apiKeySelector) => {
            $btn.prop('disabled', true).text('Testing...');
            $.ajax({
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '{{ route('settings.ai-bot.test') }}',
                data: {
                    provider: provider,
                    api_key: $(apiKeySelector).val(),
                },
                success: function(response) {
                    $('#ai-test-result')
                        .removeClass('alert-secondary alert-success alert-danger')
                        .addClass('alert-success')
                        .text(response.message || 'API key valid.');
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Test API gagal dijalankan.';
                    $('#ai-test-result')
                        .removeClass('alert-secondary alert-success alert-danger')
                        .addClass('alert-danger')
                        .text(message);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(provider === 'openai' ? 'Test OpenAI' : 'Test Gemini');
                }
            });
        };

        $('#test-openai-btn').on('click', function() {
            runAiTest('openai', $(this), '#openai_api_key');
        });

        $('#test-gemini-btn').on('click', function() {
            runAiTest('gemini', $(this), '#gemini_api_key');
        });
    </script>
</x-layout-dashboard>

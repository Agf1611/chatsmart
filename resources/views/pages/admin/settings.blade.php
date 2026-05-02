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
                            <div class="row">



                                <div class="row m-t-lg">
                                    <form action="{{ route('setServer') }}" method="POST">
                                        @csrf
                                        <div class="col-md-6">
                                            <label for="typeServer" class="form-label">Server Type</label>
                                            <select name="typeServer" class="form-control" id="server" required>

                                                @if (env('TYPE_SERVER') === 'localhost')
                                                    <option value="localhost" selected>Localhost</option>
                                                    <option value="hosting">Hosting Shared</option>
                                                    <option value="other">Other</option>
                                                @elseif(env('TYPE_SERVER') === 'hosting')
                                                    <option value="localhost">Localhost</option>
                                                    <option value="hosting" selected>Hosting Shared</option>
                                                    <option value="other">Other</option>
                                                @else
                                                    <option value="other" required>Other</option>
                                                    <option value="localhost">Localhost</option>
                                                    <option value="hosting">Hosting Shared</option>
                                                @endif
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="Port" class="form-label">Port Node JS</label>
                                            <input type="number" name="portnode" class="form-control" id="Port"
                                                value="{{ env('PORT_NODE') }}" required>
                                        </div>
                                </div>
                                <div
                                    class="row m-t-lg {{ env('TYPE_SERVER') === 'other' ? 'd-block' : 'd-none' }} formUrlNode">
                                    <div class="col-md-6">
                                        <label for="settingsInputUserName " class="form-label">URL Node</label>
                                        <div class="input-group">
                                            <span class="input-group-text" id="settingsInputUserName-add">URL</span>
                                            <input type="text" class="form-control"
                                                value="{{ env('WA_URL_SERVER') }}" name="urlnode"
                                                id="settingsInputUserName" aria-describedby="settingsInputUserName-add">
                                        </div>
                                    </div>

                                </div>

                                <div class="row m-t-lg ">
                                    <div class="col mt-4">

                                        <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                    </div>
                                </div>
                                </form>
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
                                        <input type="text" name="openai_api_key" class="form-control" placeholder="{{ $aiBotSettings['openai_key'] ?: 'Masukkan API key OpenAI' }}">
                                        <div class="form-text">Kosongkan jika tidak ingin mengubah key yang sudah tersimpan.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gemini API Key</label>
                                        <input type="text" name="gemini_api_key" class="form-control" placeholder="{{ $aiBotSettings['gemini_key'] ?: 'Masukkan API key Gemini' }}">
                                        <div class="form-text">Kosongkan jika tidak ingin mengubah key yang sudah tersimpan.</div>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">Simpan AI Settings</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>


    <script>
        $('#server').on('change', function() {
            let type = $('#server :selected').val();
            console.log(type);
            if (type === 'other') {
                $('.formUrlNode').removeClass('d-none')
            } else {
                $('.formUrlNode').addClass('d-none')

            }
        })
    </script>
</x-layout-dashboard>

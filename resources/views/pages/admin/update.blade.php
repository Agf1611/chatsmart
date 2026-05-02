<x-layout-dashboard title="Update Version">
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Admin</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Update</li>
                </ol>
            </nav>
        </div>
    </div>

    @if (session()->has('alert'))
        <x-alert>
            @slot('type', session('alert')['type'])
            @slot('msg', session('alert')['msg'])
        </x-alert>
    @endif

    <section class="section-hero-card mb-4">
        <p class="section-kicker mb-2">Maintenance Center</p>
        <h3 class="section-title mb-2">GitHub updater & maintenance tools</h3>
        <p class="hero-meta mb-0">Pantau versi aplikasi, cek status repository update, dan jalankan maintenance utama
            dari dashboard admin yang sudah selaras dengan tema ChatSmart.</p>
    </section>

    <div class="row row-cols-1 row-cols-lg-2 g-4">
        <div class="col">
            <div class="smart-panel-card h-100 p-4">
                <h5 class="mb-3">Version Information</h5>
                <div class="smart-kpi-grid">
                    <div class="smart-kpi">
                        <span>Laravel App Version</span>
                        <strong>{{ $release['app_version'] }}</strong>
                    </div>
                    <div class="smart-kpi">
                        <span>Node Package Version</span>
                        <strong>{{ $release['node_version'] }}</strong>
                    </div>
                    <div class="smart-kpi">
                        <span>Server Type</span>
                        <strong>{{ ucfirst($release['server_type']) }}</strong>
                    </div>
                    <div class="smart-kpi">
                        <span>Node Port</span>
                        <strong>{{ $release['node_port'] }}</strong>
                    </div>
                </div>
                <div class="smart-kpi mt-3">
                    <span>Node URL</span>
                    <strong>{{ $release['node_url'] }}</strong>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="smart-panel-card h-100 p-4">
                <h5 class="mb-3">GitHub Updater</h5>
                <div class="smart-kpi-grid">
                    <div class="smart-kpi">
                        <span>Branch</span>
                        <strong>{{ $updaterStatus['remote']['branch'] ?? $updaterStatus['branch'] }}</strong>
                    </div>
                    <div class="smart-kpi">
                        <span>Last Sync</span>
                        <strong>{{ !empty($updaterStatus['last_sync']) ? ($updaterStatus['last_sync']['latest_commit_short'] ?? '-') : 'Belum sync' }}</strong>
                    </div>
                </div>

                <div class="smart-kpi mt-3">
                    <span>Repository</span>
                    <strong>{{ $updaterStatus['repo_url'] ?: '-' }}</strong>
                </div>

                <div class="smart-kpi mt-3">
                    <span>Latest Commit</span>
                    <strong>
                        @if (!empty($updaterStatus['remote']['latest_commit_short']))
                            {{ $updaterStatus['remote']['latest_commit_short'] }}
                        @else
                            -
                        @endif
                    </strong>
                    @if (!empty($updaterStatus['remote']['latest_message']))
                        <div class="small text-muted mt-2">{{ $updaterStatus['remote']['latest_message'] }}</div>
                    @endif
                </div>

                <div class="mt-3">
                    @foreach ($updaterStatus['exclude_paths'] as $path)
                        <span class="badge bg-light text-dark border me-1 mb-1">{{ $path }}</span>
                    @endforeach
                </div>

                @if (!empty($updaterStatus['remote_error']))
                    <div class="alert border-0 bg-light-danger mt-3 mb-0">
                        <div class="text-danger">{{ $updaterStatus['remote_error'] }}</div>
                    </div>
                @endif

                <p class="text-muted mt-3 mb-3">
                    Update hanya menimpa file yang berubah atau file baru. Database tidak disentuh, migration tidak
                    dijalankan otomatis, dan file lokal penting tetap dilewati.
                </p>

                <form action="{{ route('admin.update.sync') }}" method="POST"
                    onsubmit="return confirm('Lanjut sync update dari GitHub? Database tidak akan direset, tetapi file aplikasi yang berbeda akan diperbarui.');">
                    @csrf
                    <button type="submit" class="btn btn-primary" {{ empty($updaterStatus['repo_url']) ? 'disabled' : '' }}>
                        Sync Update dari GitHub
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if (!empty($updaterStatus['last_sync']['sample_changed_files']))
        <div class="smart-panel-card mt-4 p-4">
            <h5 class="mb-3">Sample Updated Files</h5>
            <div class="row">
                @foreach ($updaterStatus['last_sync']['sample_changed_files'] as $file)
                    <div class="col-12 col-lg-6 mb-2">
                        <code>{{ $file }}</code>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="smart-panel-card mt-4 p-4">
        <h5 class="mb-3">Maintenance Tools</h5>
        <p class="text-muted">Shortcut admin untuk operasi ringan yang dipakai saat deployment atau sesudah update.</p>

        <div class="toolbar-actions">
            <a class="btn btn-primary" href="{{ route('generate') }}">Generate Storage Link</a>
            <a class="btn btn-outline-secondary" href="{{ route('cache.clear') }}">Clear Optimize Cache</a>
            <a class="btn btn-outline-info" href="{{ route('admin.settings') }}">Open Server Settings</a>
        </div>
    </div>
</x-layout-dashboard>

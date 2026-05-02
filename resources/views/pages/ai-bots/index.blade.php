<x-layout-dashboard title="AI Bot Profiles">
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Whatsapp</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">AI Bot</li>
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

    <div class="section-hero-card mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-xl-7">
                <div class="d-flex gap-3 align-items-start">
                    <div class="hero-icon">
                        <i class="bx bx-bot"></i>
                    </div>
                    <div>
                        <h3 class="mb-2">AI Bot Profiles</h3>
                        <p class="hero-meta mb-0">
                            Pusat pengaturan bot AI untuk setiap device, lengkap dengan engine, prompt, memory,
                            fallback, dan alur percakapan otomatis yang tetap rapi di dalam ekosistem ChatSmart.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="hero-metrics">
                    <div class="hero-metric">
                        <span>Total profile</span>
                        <strong>{{ $bots->total() }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Device tersedia</span>
                        <strong>{{ $devices->count() }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Engine aktif</span>
                        <strong>{{ count($engineOptions) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-4">
            <div class="card smart-panel-card h-100">
                <div class="card-body">
                    <h5 class="mb-2">Aksi Cepat</h5>
                    <p class="text-muted mb-3">Buat bot baru atau buka daftar percakapan AI yang sedang berjalan.</p>
                    <div class="toolbar-actions">
                        <a href="{{ route('ai-bots.create') }}" class="btn btn-primary btn-sm">
                            <i class="bx bx-plus"></i> Tambah AI Bot
                        </a>
                        <a href="{{ route('ai-conversations.index') }}" class="btn btn-outline-secondary btn-sm">
                            Lihat Conversations
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card smart-filter-card h-100">
                <div class="card-body">
                    <form method="GET" action="{{ route('ai-bots.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Cari bot</label>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Nama, persona, model">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Device</label>
                            <select name="device_id" class="form-select">
                                <option value="">Semua</option>
                                @foreach ($devices as $device)
                                    <option value="{{ $device->id }}" {{ (string) request('device_id') === (string) $device->id ? 'selected' : '' }}>
                                        {{ $device->body }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Engine</label>
                            <select name="engine_type" class="form-select">
                                <option value="">Semua</option>
                                @foreach ($engineOptions as $value => $label)
                                    <option value="{{ $value }}" {{ request('engine_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Semua</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-12 toolbar-actions">
                            <button type="submit" class="btn btn-outline-primary btn-sm">Filter</button>
                            <a href="{{ route('ai-bots.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card smart-list-card table-shell">
        <div class="card-header bg-transparent py-3">
            <div class="smart-table-toolbar">
                <div>
                    <h5 class="smart-table-title">Daftar Bot</h5>
                    <p class="text-muted mb-0 small">Semua profil bot tampil dengan ringkasan engine, memory, dan status operasional.</p>
                </div>
                <span class="small text-muted">{{ $bots->total() }} bot</span>
            </div>
        </div>
        <div class="card-body">
            @if ($bots->count() === 0)
                <div class="smart-empty">
                    Belum ada AI bot profile. Buat profil pertama untuk mulai memakai AI reply di ChatSmart.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th>Device</th>
                                <th>Engine</th>
                                <th>Thinking</th>
                                <th>Memory</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bots as $bot)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $bot->name }}</div>
                                        <div class="small text-muted">{{ $bot->persona ?: ($bot->response_style ?: 'Tanpa persona khusus') }}</div>
                                    </td>
                                    <td>{{ optional($bot->device)->body }}</td>
                                    <td>
                                        <span class="smart-code">{{ strtoupper($bot->engine_type) }}</span>
                                        <div class="small text-muted mt-2">{{ $bot->model ?: 'default' }}</div>
                                    </td>
                                    <td>{{ ucfirst($bot->thinking_mode) }}</td>
                                    <td>{{ $bot->memory_window }} pesan</td>
                                    <td><span class="badge bg-{{ $bot->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($bot->status) }}</span></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                            <a href="{{ route('ai-bots.edit', $bot->id) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                            <form action="{{ route('ai-bots.duplicate', $bot->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-info btn-sm">Duplikat</button>
                                            </form>
                                            <form action="{{ route('ai-bots.delete', $bot->id) }}" method="POST" onsubmit="return confirm('Hapus AI bot ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($bots->hasPages())
                <div class="mt-3">
                    {{ $bots->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layout-dashboard>

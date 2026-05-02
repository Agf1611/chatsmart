<x-layout-dashboard title="AI Conversations">
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Whatsapp</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">AI Conversations</li>
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
                        <i class="bx bx-conversation"></i>
                    </div>
                    <div>
                        <h3 class="mb-2">AI Conversations</h3>
                        <p class="hero-meta mb-0">
                            Pantau semua percakapan AI per kontak, device, dan bot agar alur otomatis tetap aman,
                            jelas, dan mudah diintervensi saat dibutuhkan.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="hero-metrics">
                    <div class="hero-metric">
                        <span>Total conversation</span>
                        <strong>{{ $conversations->total() }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Bot tersedia</span>
                        <strong>{{ $bots->count() }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Device tersedia</span>
                        <strong>{{ $devices->count() }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card smart-filter-card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('ai-conversations.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Cari kontak / isi chat</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Nama, nomor, atau pesan">
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
                    <label class="form-label">AI Bot</label>
                    <select name="ai_bot_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($bots as $bot)
                            <option value="{{ $bot->id }}" {{ (string) request('ai_bot_id') === (string) $bot->id ? 'selected' : '' }}>
                                {{ $bot->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="paused" {{ request('status') === 'paused' ? 'selected' : '' }}>Paused</option>
                    </select>
                </div>
                <div class="col-12 toolbar-actions">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Filter</button>
                    <a href="{{ route('ai-conversations.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card smart-list-card table-shell">
        <div class="card-header bg-transparent py-3">
            <div class="smart-table-toolbar">
                <div>
                    <h5 class="smart-table-title">Daftar Percakapan AI</h5>
                    <p class="text-muted mb-0 small">Lihat status aktif, pesan terakhir user, dan output terakhir dari bot pada setiap chat.</p>
                </div>
                <span class="small text-muted">{{ $conversations->total() }} conversation</span>
            </div>
        </div>
        <div class="card-body">
            @if ($conversations->count() === 0)
                <div class="smart-empty">Belum ada conversation AI yang tercatat.</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Kontak</th>
                                <th>Bot</th>
                                <th>Device</th>
                                <th>Status</th>
                                <th>Pesan Terakhir</th>
                                <th>Balasan Bot</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($conversations as $conversation)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $conversation->contact_name ?: $conversation->chat_jid }}</div>
                                        <div class="small text-muted">{{ $conversation->chat_jid }}</div>
                                    </td>
                                    <td>{{ optional($conversation->bot)->name ?: '-' }}</td>
                                    <td>{{ optional($conversation->device)->body ?: '-' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $conversation->status === 'active' ? 'success' : 'warning text-dark' }}">
                                            {{ ucfirst($conversation->status) }}
                                        </span>
                                        @if ($conversation->paused_reason)
                                            <div class="small text-muted mt-2">
                                                <strong>Sumber pause:</strong> {{ $conversation->paused_reason }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ \Illuminate\Support\Str::limit($conversation->last_user_message, 80) ?: '-' }}</td>
                                    <td class="small text-muted">{{ \Illuminate\Support\Str::limit($conversation->last_bot_message, 80) ?: '-' }}</td>
                                    <td class="text-end">
                                        @if ($conversation->status === 'active')
                                            <form method="POST" action="{{ route('ai-conversations.pause', $conversation->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-warning btn-sm">Pause Bot</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('ai-conversations.resume', $conversation->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success btn-sm">Resume Bot</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($conversations->hasPages())
                <div class="mt-3">
                    {{ $conversations->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layout-dashboard>

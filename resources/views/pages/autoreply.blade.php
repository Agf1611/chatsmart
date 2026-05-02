<x-layout-dashboard title="Auto Reply Manager">
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Auto Reply</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Rule Manager</li>
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
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 align-items-lg-center">
            <div>
                <p class="section-kicker mb-2">Automation Center</p>
                <h3 class="section-title mb-2">Kelola Auto Reply per device</h3>
                <p class="hero-meta mb-0">Tambah, edit, duplikat, preview, dan filter rule auto reply dengan tampilan
                    yang lebih rapi dan sinkron dengan dashboard ChatSmart.</p>
            </div>
            <div class="toolbar-actions">
                <a href="{{ route('autoreply.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Rule
                </a>
                @if ($selectedDeviceId)
                    <span class="badge bg-light-primary text-primary align-self-center">Device aktif:
                        {{ optional($devices->firstWhere('id', $selectedDeviceId))->body }}</span>
                @else
                    <span class="badge bg-light-warning text-warning align-self-center">Belum ada device terpilih</span>
                @endif
            </div>
        </div>
    </section>

    <div class="row g-4 mb-4">
        <div class="col-xl-12">
            <div class="smart-filter-card p-4">
                <form method="GET" action="{{ route('autoreply') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Cari nama / keyword</label>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                            placeholder="Cari rule">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Device</label>
                        <select name="device_id" class="form-select">
                            <option value="">Semua</option>
                            @foreach ($devices as $device)
                                <option value="{{ $device->id }}"
                                    {{ (string) request('device_id', $selectedDeviceId) === (string) $device->id ? 'selected' : '' }}>
                                    {{ $device->body }}
                                </option>
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
                    <div class="col-md-2">
                        <label class="form-label">Tipe</label>
                        <select name="type" class="form-select">
                            <option value="">Semua</option>
                            @foreach ($replyTypes as $value => $label)
                                <option value="{{ $value }}" {{ request('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Konteks</label>
                        <select name="reply_when" class="form-select">
                            <option value="">Semua</option>
                            <option value="All" {{ request('reply_when') === 'All' ? 'selected' : '' }}>All</option>
                            <option value="Personal" {{ request('reply_when') === 'Personal' ? 'selected' : '' }}>Personal</option>
                            <option value="Group" {{ request('reply_when') === 'Group' ? 'selected' : '' }}>Group</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone Book pelanggan</label>
                        <select name="contact_tag_id" class="form-select">
                            <option value="">Semua</option>
                            @foreach ($phonebooks as $phonebook)
                                <option value="{{ $phonebook->id }}" {{ (string) request('contact_tag_id') === (string) $phonebook->id ? 'selected' : '' }}>
                                    {{ $phonebook->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Filter</button>
                        <a href="{{ route('autoreply') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="smart-list-card">
        <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Daftar Rule</h5>
            <span class="text-muted small">{{ $autoreplies->total() }} rule</span>
        </div>
        <div class="card-body p-4">
            @if ($autoreplies->count() === 0)
                <div class="smart-empty">Belum ada rule auto reply untuk filter saat ini.</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Rule</th>
                                <th>Trigger</th>
                                <th>Match</th>
                                <th>Reply When</th>
                                <th>Akses</th>
                                <th>Reply Type</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($autoreplies as $autoreply)
                                @php
                                    $scheduleLabel = $autoreply->schedule_mode === 'scheduled'
                                        ? collect($autoreply->schedule_days ?: [])
                                            ->map(function ($day) use ($weekdayOptions) {
                                                return $weekdayOptions[$day] ?? ucfirst($day);
                                            })
                                            ->implode(', ') . ' | ' . substr((string) $autoreply->schedule_start, 0, 5) . ' - ' . substr((string) $autoreply->schedule_end, 0, 5)
                                        : 'Selalu aktif';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $autoreply->name }}</div>
                                        <div class="text-muted small">{{ optional($autoreply->device)->body }}</div>
                                    </td>
                                    <td>
                                        @if (($autoreply->trigger_event ?? 'keyword') === 'first_chat')
                                            <span class="fw-semibold">First Chat</span>
                                            <div class="small text-muted">Welcome message</div>
                                        @else
                                            <span class="fw-semibold">{{ $autoreply->keyword }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light-success text-success">
                                            {{ ($autoreply->trigger_event ?? 'keyword') === 'first_chat' ? 'Welcome' : $autoreply->type_keyword }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light-warning text-warning">{{ $autoreply->reply_when }}</span>
                                    </td>
                                    <td>
                                        @if ($autoreply->phonebook)
                                            <span class="badge bg-light-primary text-primary">{{ $autoreply->phonebook->name }}</span>
                                        @else
                                            <span class="text-muted small">Semua nomor</span>
                                        @endif
                                    </td>
                                    <td>{{ $replyTypes[$autoreply->type] ?? ucfirst($autoreply->type) }}</td>
                                    <td class="small text-muted">{{ $scheduleLabel }}</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input autoreply-status-toggle" type="checkbox"
                                                data-url="{{ route('autoreply.toggle-status', $autoreply->id) }}"
                                                {{ $autoreply->status === 'active' ? 'checked' : '' }}>
                                            <label class="form-check-label text-capitalize">{{ $autoreply->status }}</label>
                                        </div>
                                    </td>
                                    <td class="small text-muted">{{ optional($autoreply->updated_at)->format('d M Y H:i') }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                            <button type="button" class="btn btn-outline-secondary btn-sm autoreply-preview-btn"
                                                data-id="{{ $autoreply->id }}" data-preview-url="{{ route('previewMessage') }}">
                                                Preview
                                            </button>
                                            <a href="{{ route('autoreply.edit', $autoreply->id) }}"
                                                class="btn btn-outline-primary btn-sm">Edit</a>
                                            <form action="{{ route('autoreply.duplicate', $autoreply->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-info btn-sm">Duplikat</button>
                                            </form>
                                            <form action="{{ route('autoreply.delete', $autoreply->id) }}" method="POST"
                                                onsubmit="return confirm('Hapus rule ini?')">
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

            @if ($autoreplies->hasPages())
                <div class="mt-3">
                    {{ $autoreplies->links() }}
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="autoreplyPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview Auto Reply</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body autoreply-preview-body">
                    <div class="text-center py-5 text-muted">Memuat preview...</div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/autoreply.js') }}"></script>
</x-layout-dashboard>

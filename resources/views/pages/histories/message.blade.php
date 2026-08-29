<x-layout-dashboard title="Messages History">
    @if (session()->has('alert'))
        <x-alert>
            @slot('type', session('alert')['type'])
            @slot('msg', session('alert')['msg'])
        </x-alert>
    @endif

    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Messages</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Messages History</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="section-hero-card mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-xl-8">
                <div class="d-flex gap-3 align-items-start">
                    <div class="hero-icon">
                        <i class="bx bx-history"></i>
                    </div>
                    <div>
                        <h3 class="mb-2">Messages History</h3>
                        <p class="hero-meta mb-0">
                            Riwayat pengiriman pesan kini tampil lebih bersih untuk memudahkan filter, retry, dan
                            pembersihan data tanpa terasa keluar dari tema utama ChatSmart.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="hero-metrics">
                    <div class="hero-metric">
                        <span>Total histori</span>
                        <strong>{{ $stats['total'] }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Berhasil</span>
                        <strong>{{ $stats['success'] }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Gagal / retry</span>
                        <strong>{{ $stats['failed'] }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="smart-stat-card">
                <div class="smart-stat-card__icon"><i class="bx bx-layer"></i></div>
                <div class="smart-stat-card__label">Total Histori</div>
                <div class="smart-stat-card__value">{{ $stats['total'] }}</div>
                <div class="smart-stat-card__hint">Semua data histori yang tersimpan saat ini.</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="smart-stat-card">
                <div class="smart-stat-card__icon"><i class="bx bx-check-circle"></i></div>
                <div class="smart-stat-card__label">Berhasil</div>
                <div class="smart-stat-card__value text-success">{{ $stats['success'] }}</div>
                <div class="smart-stat-card__hint">Pengiriman yang telah selesai dan tercatat sukses.</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="smart-stat-card">
                <div class="smart-stat-card__icon"><i class="bx bx-error-circle"></i></div>
                <div class="smart-stat-card__label">Gagal / Perlu Retry</div>
                <div class="smart-stat-card__value text-danger">{{ $stats['failed'] }}</div>
                <div class="smart-stat-card__hint">Pesan yang perlu dikirim ulang atau ditinjau kembali.</div>
            </div>
        </div>
    </div>

    <div class="card smart-filter-card mb-3">
        <div class="card-header py-3 bg-transparent">
            <div>
                <h5 class="mb-1">Filter dan Operasional</h5>
                <p class="text-muted mb-0 small">Saring histori per device, status, tipe, lalu lakukan aksi massal bila diperlukan.</p>
            </div>
        </div>
        <div class="card-body pt-0">
            <form method="GET" action="{{ route('messages.history') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label">Cari nomor / pesan / catatan</label>
                    <input type="text" class="form-control" name="q" value="{{ request('q') }}" placeholder="Cari histori">
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label class="form-label">Device</label>
                    <select class="form-select" name="device_id">
                        <option value="">Semua</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected((string) request('device_id') === (string) $device->id)>
                                {{ $device->body }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Semua</option>
                        <option value="success" @selected(request('status') === 'success')>Berhasil</option>
                        <option value="failed" @selected(request('status') === 'failed')>Gagal</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label class="form-label">Tipe</label>
                    <select class="form-select" name="type">
                        <option value="">Semua</option>
                        @foreach (['text', 'media', 'button', 'template', 'list', 'poll'] as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <a href="{{ route('messages.history') }}" class="btn btn-light w-100">Reset</a>
                </div>
            </form>

            <div class="toolbar-actions mt-4">
                <form method="POST" action="{{ route('messages.history.resend-failed') }}">
                    @csrf
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    <input type="hidden" name="device_id" value="{{ request('device_id') }}">
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <input type="hidden" name="type" value="{{ request('type') }}">
                    <button type="submit" class="btn btn-outline-warning" onclick="return confirm('Coba kirim ulang semua histori gagal yang sedang tampil?')">
                        <i class="bx bx-refresh"></i> Retry Semua Gagal
                    </button>
                </form>

                <form method="POST" action="{{ route('messages.history.clear') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="scope" value="failed">
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    <input type="hidden" name="device_id" value="{{ request('device_id') }}">
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <input type="hidden" name="type" value="{{ request('type') }}">
                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Hapus semua histori gagal yang cocok dengan filter ini?')">
                        Hapus Histori Gagal
                    </button>
                </form>

                <form method="POST" action="{{ route('messages.history.clear') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="scope" value="success">
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    <input type="hidden" name="device_id" value="{{ request('device_id') }}">
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <input type="hidden" name="type" value="{{ request('type') }}">
                    <button type="submit" class="btn btn-outline-secondary" onclick="return confirm('Hapus semua histori berhasil yang cocok dengan filter ini?')">
                        Hapus Histori Berhasil
                    </button>
                </form>

                <form method="POST" action="{{ route('messages.history.clear') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="scope" value="filtered">
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    <input type="hidden" name="device_id" value="{{ request('device_id') }}">
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <input type="hidden" name="type" value="{{ request('type') }}">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Hapus semua histori yang sedang tampil?')">
                        Bersihkan Histori Tampil
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card smart-list-card table-shell">
        <div class="card-header py-3 bg-transparent">
            <div class="smart-table-toolbar">
                <div>
                    <h5 class="smart-table-title">Daftar Histori Pesan</h5>
                    <p class="text-muted mb-0 small">Setiap baris menyimpan device pengirim, isi pesan, status, jalur kirim, dan catatan operasional.</p>
                </div>
                <small class="text-secondary">{{ $messages->total() }} data</small>
            </div>
        </div>
        <div class="card-body">
            @if ($messages->total() == 0)
                <div class="smart-empty">Belum ada data histori pesan yang cocok dengan filter ini.</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Sender</th>
                                <th>Number</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Via</th>
                                <th>Catatan</th>
                                <th>Last Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($messages as $msg)
                                <tr>
                                    <td>{{ $msg->id }}</td>
                                    <td>{{ $msg->device->body ?? '-' }}</td>
                                    <td>{{ $msg->number }}</td>
                                    <td>
                                        <span class="smart-code">{{ $msg->type }}</span>
                                        <div class="small text-muted mt-2">{{ \Illuminate\Support\Str::limit($msg->message, 70) }}</div>
                                    </td>
                                    <td>
                                        @if ($msg->status !== 'success' || $msg->delivery_status === 'failed')
                                            <span class="badge rounded-pill bg-danger">Failed</span>
                                        @elseif (in_array($msg->delivery_status, ['read', 'played'], true))
                                            <span class="badge rounded-pill bg-success">Read</span>
                                        @elseif ($msg->delivery_status === 'delivered')
                                            <span class="badge rounded-pill bg-success">Delivered</span>
                                        @elseif ($msg->delivery_status === 'server_ack')
                                            <span class="badge rounded-pill bg-info text-dark">Sent (1 tick)</span>
                                        @elseif ($msg->delivery_status === 'pending')
                                            <span class="badge rounded-pill bg-warning text-dark">Waiting</span>
                                        @else
                                            <span class="badge rounded-pill bg-secondary">Accepted</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($msg->send_by == 'web')
                                            <span class="badge rounded-pill bg-primary">Web</span>
                                        @elseif ($msg->send_by == 'autoreply')
                                            <span class="badge rounded-pill bg-info text-dark">Auto Reply</span>
                                        @else
                                            <span class="badge rounded-pill bg-warning text-dark">API</span>
                                        @endif
                                    </td>
                                    <td class="text-wrap" style="min-width: 220px;">
                                        {{ $msg->note ?: '-' }}
                                    </td>
                                    <td>{{ $msg->updated_at?->format('d M Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-sm btn-primary" onclick="resendHistory({{ $msg->id }}, '{{ $msg->status }}')">
                                                <i class="bx bx-refresh"></i> Resend
                                            </button>

                                            <form method="POST" action="{{ route('messages.history.delete', $msg) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus histori pesan ini?')">
                                                    <i class="bx bx-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($messages->lastPage() > 1)
                <nav aria-label="Message history pagination">
                    <ul class="pagination">
                        <li class="page-item {{ $messages->currentPage() == 1 ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $messages->previousPageUrl() }}">Previous</a>
                        </li>

                        @for ($i = 1; $i <= $messages->lastPage(); $i++)
                            <li class="page-item {{ $messages->currentPage() == $i ? 'active' : '' }}">
                                <a class="page-link" href="{{ $messages->url($i) }}">{{ $i }}</a>
                            </li>
                        @endfor

                        <li class="page-item {{ $messages->currentPage() == $messages->lastPage() ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $messages->nextPageUrl() }}">Next</a>
                        </li>
                    </ul>
                </nav>
            @endif
        </div>
    </div>
</x-layout-dashboard>

<script>
    function resendHistory(id, status) {
        if (status === 'success') {
            toastr.info('Message already sent');
            return;
        }

        $.ajax({
            url: '{{ route('resend.message') }}',
            type: 'POST',
            data: {
                id: id,
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                if (res.error) {
                    toastr.error(res.msg);
                    return;
                }

                toastr.success(res.msg);
                window.setTimeout(function() {
                    window.location.reload();
                }, 900);
            },
            error: function() {
                toastr.error('Something went wrong');
            }
        });
    }
</script>

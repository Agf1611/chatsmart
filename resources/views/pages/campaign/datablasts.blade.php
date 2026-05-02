<x-layout-dashboard title="Data Blast Campaign">
    @php
        $currentItems = collect($blasts->items());
        $successCount = $currentItems->where('status', 'success')->count();
        $pendingCount = $currentItems->where('status', 'pending')->count();
        $failedCount = $currentItems->reject(function ($item) {
            return in_array($item->status, ['success', 'pending'], true);
        })->count();
    @endphp

    @if (session()->has('alert'))
        <x-alert>
            @slot('type', session('alert')['type'])
            @slot('msg', session('alert')['msg'])
        </x-alert>
    @endif

    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Data Campaign</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $campaign_name }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="section-hero-card mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-xl-7">
                <div class="d-flex gap-3 align-items-start">
                    <div class="hero-icon">
                        <i class="bx bx-send"></i>
                    </div>
                    <div>
                        <h3 class="mb-2">{{ $campaign_name }}</h3>
                        <p class="hero-meta mb-0">
                            Ringkasan status pengiriman campaign per receiver, lengkap dengan progres update terakhir
                            dalam tampilan yang lebih rapi dan seragam dengan dashboard ChatSmart.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="hero-metrics">
                    <div class="hero-metric">
                        <span>Total receiver</span>
                        <strong>{{ $blasts->total() }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Halaman aktif</span>
                        <strong>{{ $blasts->currentPage() }}/{{ $blasts->lastPage() }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Data per halaman</span>
                        <strong>{{ $currentItems->count() }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="smart-stat-card">
                <div class="smart-stat-card__icon"><i class="bx bx-check-circle"></i></div>
                <div class="smart-stat-card__label">Sent di halaman ini</div>
                <div class="smart-stat-card__value">{{ $successCount }}</div>
                <div class="smart-stat-card__hint">Pengiriman yang sudah selesai dengan status sukses.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="smart-stat-card">
                <div class="smart-stat-card__icon"><i class="bx bx-time-five"></i></div>
                <div class="smart-stat-card__label">Pending di halaman ini</div>
                <div class="smart-stat-card__value">{{ $pendingCount }}</div>
                <div class="smart-stat-card__hint">Data yang masih menunggu antrian atau proses lanjutan.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="smart-stat-card">
                <div class="smart-stat-card__icon"><i class="bx bx-x-circle"></i></div>
                <div class="smart-stat-card__label">Gagal di halaman ini</div>
                <div class="smart-stat-card__value">{{ $failedCount }}</div>
                <div class="smart-stat-card__hint">Status selain sukses dan pending terhitung sebagai gagal.</div>
            </div>
        </div>
    </div>

    <div class="card smart-list-card table-shell">
        <div class="card-header bg-transparent py-3">
            <div class="smart-table-toolbar">
                <div>
                    <h5 class="smart-table-title">Daftar Receiver Campaign</h5>
                    <p class="text-muted mb-0 small">Status pengiriman ditampilkan per nomor receiver beserta waktu pembaruan terakhir.</p>
                </div>
                <span class="small text-muted">{{ $blasts->total() }} receiver</span>
            </div>
        </div>
        <div class="card-body">
            @if ($currentItems->isEmpty())
                <div class="smart-empty">Belum ada data blast untuk campaign ini.</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Receiver</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($blasts as $blast)
                                <tr>
                                    <td class="fw-semibold">{{ $blast->receiver }}</td>
                                    <td>
                                        @if ($blast->status == 'success')
                                            <span class="badge rounded-pill bg-success">Sent</span>
                                        @elseif ($blast->status == 'pending')
                                            <span class="badge rounded-pill bg-warning text-dark">Pending</span>
                                        @else
                                            <span class="badge rounded-pill bg-danger">{{ $blast->status }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $blast->updated_at }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($blasts->lastPage() > 1)
                <nav aria-label="Page navigation example">
                    <ul class="pagination">
                        <li class="page-item {{ $blasts->currentPage() == 1 ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $blasts->previousPageUrl() }}">Previous</a>
                        </li>

                        @for ($i = 1; $i <= $blasts->lastPage(); $i++)
                            <li class="page-item {{ $blasts->currentPage() == $i ? 'active' : '' }}">
                                <a class="page-link" href="{{ $blasts->url($i) }}">{{ $i }}</a>
                            </li>
                        @endfor

                        <li class="page-item {{ $blasts->currentPage() == $blasts->lastPage() ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $blasts->nextPageUrl() }}">Next</a>
                        </li>
                    </ul>
                </nav>
            @endif
        </div>
    </div>
</x-layout-dashboard>

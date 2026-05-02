<x-layout-dashboard title="System Update">
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
        <p class="section-kicker mb-2">Update Center</p>
        <h3 class="section-title mb-2">System updater</h3>
        <p class="hero-meta mb-0">
            Halaman ini fokus untuk sinkronisasi update aplikasi dari repository utama tanpa menyentuh database.
            Yang ditampilkan hanya status sinkronisasi dan file apa saja yang akan berubah.
        </p>
    </section>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="smart-panel-card h-100 p-4">
                <h5 class="mb-3">Status Update</h5>

                <div class="smart-kpi-grid">
                    <div class="smart-kpi">
                        <span>Branch</span>
                        <strong>{{ $updaterStatus['remote']['branch'] ?? $updaterStatus['branch'] }}</strong>
                    </div>
                    <div class="smart-kpi">
                        <span>Status</span>
                        <strong>
                            @if (!empty($updaterStatus['remote_error']))
                                Gagal cek
                            @elseif ($updaterStatus['is_up_to_date'])
                                Sudah terbaru
                            @else
                                Update tersedia
                            @endif
                        </strong>
                    </div>
                    <div class="smart-kpi">
                        <span>Commit terbaru</span>
                        <strong>{{ $updaterStatus['remote']['latest_commit_short'] ?? '-' }}</strong>
                    </div>
                    <div class="smart-kpi">
                        <span>Sync terakhir</span>
                        <strong>{{ $updaterStatus['last_sync']['latest_commit_short'] ?? 'Belum pernah' }}</strong>
                    </div>
                </div>

                <div class="smart-kpi mt-3">
                    <span>Mirror repo lokal</span>
                    <strong>{{ $updaterStatus['mirror_enabled'] ? 'Aktif' : 'Tidak aktif' }}</strong>
                    @if (!empty($updaterStatus['mirror_path']))
                        <div class="small text-muted mt-2">{{ $updaterStatus['mirror_path'] }}</div>
                    @endif
                </div>

                @if (!empty($updaterStatus['remote']['latest_message']))
                    <div class="smart-panel-note mt-3">
                        <div class="fw-semibold mb-1">Catatan commit terbaru</div>
                        <div>{{ $updaterStatus['remote']['latest_message'] }}</div>
                    </div>
                @endif

                @if (!empty($updaterStatus['last_sync']['synced_at']))
                    <div class="small text-muted mt-3">
                        Sync terakhir: {{ $updaterStatus['last_sync']['synced_at'] }}
                    </div>
                @endif

                @if (!empty($updaterStatus['remote_error']))
                    <div class="alert border-0 bg-light-danger mt-3 mb-0">
                        <div class="text-danger">{{ $updaterStatus['remote_error'] }}</div>
                    </div>
                @endif

                <p class="text-muted mt-3 mb-3">
                    Update hanya mengganti file yang berubah atau file baru. File penting lokal tetap dilewati dan database tidak direset.
                </p>

                <form action="{{ route('admin.update.sync') }}" method="POST"
                    onsubmit="return confirm('Lanjut jalankan update sekarang? File aplikasi yang berbeda akan diperbarui, database tidak direset.');">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100" {{ empty($updaterStatus['configured']) ? 'disabled' : '' }}>
                        Jalankan Update
                    </button>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="smart-panel-card h-100 p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="mb-1">Perubahan Update</h5>
                        <p class="text-muted mb-0">Ringkasan file yang akan dibawa oleh update berikutnya.</p>
                    </div>
                    <div class="smart-kpi-grid" style="min-width: min(100%, 360px);">
                        <div class="smart-kpi">
                            <span>Total</span>
                            <strong>{{ $updaterStatus['pending_summary']['total'] }}</strong>
                        </div>
                        <div class="smart-kpi">
                            <span>Baru</span>
                            <strong>{{ $updaterStatus['pending_summary']['added'] }}</strong>
                        </div>
                        <div class="smart-kpi">
                            <span>Diubah</span>
                            <strong>{{ $updaterStatus['pending_summary']['modified'] }}</strong>
                        </div>
                        <div class="smart-kpi">
                            <span>Dihapus/Rename</span>
                            <strong>{{ $updaterStatus['pending_summary']['removed'] + $updaterStatus['pending_summary']['renamed'] }}</strong>
                        </div>
                    </div>
                </div>

                @if ($updaterStatus['is_up_to_date'])
                    <div class="smart-empty">
                        Tidak ada perubahan baru. Sistem Anda sudah sinkron dengan update terbaru.
                    </div>
                @elseif (!empty($updaterStatus['pending_changes']))
                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Status</th>
                                    <th>File</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($updaterStatus['pending_changes'] as $change)
                                    <tr>
                                        <td class="text-nowrap">
                                            @if ($change['status'] === 'added')
                                                <span class="badge bg-success">Baru</span>
                                            @elseif ($change['status'] === 'removed')
                                                <span class="badge bg-danger">Dihapus</span>
                                            @elseif ($change['status'] === 'renamed')
                                                <span class="badge bg-info text-dark">Rename</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Diubah</span>
                                            @endif
                                        </td>
                                        <td><code>{{ $change['path'] }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="smart-empty">
                        Belum ada data perubahan yang bisa ditampilkan.
                    </div>
                @endif

                @if (!empty($updaterStatus['last_sync']['sample_changed_files']))
                    <div class="mt-4">
                        <h6 class="mb-3">File yang berubah pada sync terakhir</h6>
                        <div class="row">
                            @foreach ($updaterStatus['last_sync']['sample_changed_files'] as $file)
                                <div class="col-12 col-lg-6 mb-2">
                                    <code>{{ $file }}</code>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layout-dashboard>

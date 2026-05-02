<x-layout-dashboard title="Database Tools">
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Admin</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Database Tools</li>
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

    <section class="section-hero-card mb-4">
        <p class="section-kicker mb-2">Database Center</p>
        <h3 class="section-title mb-2">Backup dan restore database dengan aman</h3>
        <p class="hero-meta mb-0">Pantau koneksi database aktif, buat backup `.sql`, dan jalankan restore dengan safety
            backup dari panel admin yang lebih rapi.</p>
    </section>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="smart-panel-card h-100 p-4">
                <h5 class="mb-3">Database Info</h5>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <tbody>
                            <tr><th>Connection</th><td>{{ $status['connection'] }}</td></tr>
                            <tr><th>Database</th><td>{{ $status['database'] }}</td></tr>
                            <tr><th>Host</th><td>{{ $status['host'] }}:{{ $status['port'] }}</td></tr>
                            <tr><th>User</th><td>{{ $status['username'] }}</td></tr>
                            <tr><th>Backup Dir</th><td class="small">{{ $status['backup_dir'] }}</td></tr>
                            <tr><th>mysqldump</th><td class="small">{{ $status['mysqldump'] }}</td></tr>
                            <tr><th>mysql</th><td class="small">{{ $status['mysql'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="smart-panel-card h-100 p-4">
                <h5 class="mb-3">Buat Backup Baru</h5>
                <p class="text-muted">Backup akan menghasilkan file `.sql` penuh dari database aktif dan menyimpannya ke server.</p>
                <form action="{{ route('admin.database-tools.backup') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Label Backup</label>
                        <input type="text" name="label" class="form-control" placeholder="Contoh: sebelum-update">
                        <div class="form-text">Opsional. Label akan ditambahkan ke nama file backup.</div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Buat Backup Sekarang</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="smart-panel-card h-100 p-4 border-warning">
                <h5 class="mb-3 text-warning">Restore Database</h5>
                <div class="alert border-0 bg-light-warning">
                    Restore akan menimpa isi database aktif. Disarankan gunakan safety backup sebelum restore.
                </div>
                <form action="{{ route('admin.database-tools.restore') }}" method="POST" enctype="multipart/form-data"
                    class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Pilih backup yang sudah ada</label>
                        <select name="backup_file" class="form-select">
                            <option value="">Tidak dipilih</option>
                            @foreach ($backups as $backup)
                                <option value="{{ $backup['name'] }}">{{ $backup['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Atau upload file .sql</label>
                        <input type="file" name="upload_file" class="form-control" accept=".sql,.txt">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="create_safety_backup"
                                id="create_safety_backup" value="1" checked>
                            <label class="form-check-label" for="create_safety_backup">Buat safety backup otomatis sebelum restore</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Ketik <code>RESTORE</code> untuk konfirmasi</label>
                        <input type="text" name="confirmation_text" class="form-control" placeholder="RESTORE">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-warning text-dark">Jalankan Restore</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="smart-list-card">
        <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between px-4">
            <h5 class="mb-0">Daftar Backup</h5>
            <span class="text-muted small">{{ $backups->count() }} file</span>
        </div>
        <div class="card-body p-4">
            @if ($backups->isEmpty())
                <div class="smart-empty">Belum ada file backup database.</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nama File</th>
                                <th>Ukuran</th>
                                <th>Modified</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($backups as $backup)
                                <tr>
                                    <td class="fw-semibold">{{ $backup['name'] }}</td>
                                    <td>{{ $backup['size_human'] }}</td>
                                    <td>{{ $backup['modified_at'] }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                            <a href="{{ route('admin.database-tools.download', ['file' => $backup['name']]) }}"
                                                class="btn btn-outline-primary btn-sm">Download</a>
                                            <form action="{{ route('admin.database-tools.restore') }}" method="POST"
                                                onsubmit="return confirm('Restore database dari file ini? Database aktif akan ditimpa.');">
                                                @csrf
                                                <input type="hidden" name="backup_file" value="{{ $backup['name'] }}">
                                                <input type="hidden" name="confirmation_text" value="RESTORE">
                                                <input type="hidden" name="create_safety_backup" value="1">
                                                <button type="submit" class="btn btn-outline-warning btn-sm">Restore</button>
                                            </form>
                                            <form action="{{ route('admin.database-tools.delete') }}" method="POST"
                                                onsubmit="return confirm('Hapus file backup ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="backup_file" value="{{ $backup['name'] }}">
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
        </div>
    </div>
</x-layout-dashboard>

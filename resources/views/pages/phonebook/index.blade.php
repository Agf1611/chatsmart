<x-layout-dashboard title="Phone Book">

    @if (session()->has('alert'))
        <x-alert>
            @slot('type', session('alert')['type'])
            @slot('msg', session('alert')['msg'])
        </x-alert>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Phone Book</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Contact Manager</li>
                </ol>
            </nav>
        </div>
    </div>

    <section class="section-hero-card mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 align-items-lg-center">
            <div>
                <p class="section-kicker mb-2">Contact Center</p>
                <h3 class="section-title mb-2">Kelola Phone Book dengan cepat</h3>
                <p class="hero-meta mb-0">Ambil grup dari device aktif, tambah kontak baru, import file, dan kelola
                    daftar penerima campaign dari satu tempat.</p>
            </div>
            <div class="toolbar-actions">
                <form action="{{ route('fetch.groups') }}" method="post">
                    @csrf
                    <select name="device" class="form-select" required>
                        <option value="">Pilih device connected</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected((string) old('device', $selectedDeviceId) === (string) $device->id)>
                                {{ $device->body }} ({{ $device->status }})
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary">
                        Fetch Device <i class="bi bi-whatsapp ms-2"></i>
                    </button>
                </form>
                <button type="button" class="btn btn-outline-secondary" onclick="clearPhonebook()">
                    Clear Phonebook <i class="bi bi-trash ms-2"></i>
                </button>
            </div>
        </div>
    </section>

    <div class="email-wrapper">
        <div class="email-sidebar">
            <div class="email-sidebar-header d-grid">
                <button data-bs-toggle="modal" data-bs-target="#addTag" class="btn btn-primary compose-mail-btn">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Phonebook
                </button>
                <input type="text" class="form-control mt-3 search-phonebook" placeholder="Cari phonebook">
            </div>
            <div class="email-sidebar-content">
                <div class="email-navigation">
                    <div class="list-group list-group-flush phone-book-list"
                        style="overflow-y: auto !important; max-height: 560px;">
                        <div class="d-flex justify-content-center align-items-center load-phonebook text-danger"></div>
                    </div>
                </div>
            </div>
            <div class="email-meeting mt-3">
                <div class="list-group list-group-flush">
                    <button class="btn btn-outline-primary load-more" data-page="1">Load More</button>
                </div>
            </div>
        </div>

        <div>
            <div class="email-header d-xl-flex align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <div class="email-toggle-btn"><i class='bx bx-menu'></i></div>
                    <button onclick="deleteAllContact()" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash"></i> Hapus Semua
                    </button>
                </div>
                <div class="flex-grow-1 mx-xl-3 my-3 my-xl-0">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control search-contact" placeholder="Cari kontak">
                    </div>
                </div>
                <div class="toolbar-actions">
                    <button class="btn btn-primary btn-sm add-contact" onclick="addContact()">Add Contact</button>
                    <button class="btn btn-success btn-sm import-contact" onclick="importContact()">
                        <i class="bi bi-upload"></i> Import
                    </button>
                    <button class="btn btn-warning btn-sm export-contact" onclick="exportContact()">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
            </div>

            <div class="email-content">
                <div class="contacts-list email-list"></div>
                <div class="d-flex justify-content-center align-items-center mt-4 process-get-contact smart-empty">
                    Silakan pilih phonebook untuk menampilkan kontak
                </div>
            </div>
        </div>
    </div>

    <div class="overlay email-toggle-btn-mobile">Click to close tab</div>

    <div class="modal fade" id="addTag" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Add Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('tag.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <label for="name" class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" id="name" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit" class="btn btn-primary">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addContact" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Add Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form class="add-contact-form" method="POST" enctype="multipart/form-data">
                        @csrf
                        <label for="name" class="form-label">Name</label>
                        <input type="text" name="name" class="form-control contact-name" id="name" required>
                        <label for="number" class="form-label mt-3">Number</label>
                        <input type="number" name="number" class="form-control contact-number" id="number" required>
                        <input type="hidden" class="input_phonebookid" name="tag_id" value=" ">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit" class="btn btn-primary add-contact">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importContacts" tabindex="-1" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Import Contacts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="import-contact-form" method="POST" enctype="multipart/form-data">
                        @csrf
                        <label for="fileContacts" class="form-label">File (xlsx)</label>
                        <input accept=".xlsx" type="file" name="fileContacts" class="form-control file-import"
                            id="fileContacts" required>
                        <input type="hidden" name="tag_id" value="" class="import_phonebookid">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit" class="btn btn-primary">Import</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.phonebookRoutes = {
            getPhonebook: @json(route('getPhonebook')),
            clearPhonebook: @json(route('clearPhonebook')),
            getContactBase: @json(url('get-contact')),
            contactStore: @json(route('contact.store')),
            contactDeleteBase: @json(url('contact/delete')),
            contactDeleteAllBase: @json(url('contact/delete-all')),
            contactImport: @json(route('import')),
            contactExportBase: @json(url('contact/export')),
        };
    </script>
    <script src="{{ asset('js/phonebook.js') }}?v={{ filemtime(public_path('js/phonebook.js')) }}"></script>
</x-layout-dashboard>

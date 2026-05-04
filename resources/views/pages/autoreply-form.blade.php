<x-layout-dashboard title="{{ $autoreply ? 'Edit Auto Reply' : 'Tambah Auto Reply' }}">
    @php
        $replyConfig = $formData['reply_config'];
        $listItems = old('items', $replyConfig['items'] ?? ['']);
        $buttons = old('buttons', $replyConfig['buttons'] ?? ['']);
        $templates = old('templates', $replyConfig['templates'] ?? ['']);
    @endphp

    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Whatsapp</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('autoreply') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('autoreply') }}">Auto Reply</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $autoreply ? 'Edit Rule' : 'Tambah Rule' }}</li>
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

    @if (!empty($warnings))
        <div class="alert border-0 bg-light-warning mb-3">
            <div class="d-flex align-items-start">
                <div class="fs-4 text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="ms-3">
                    <div class="fw-semibold">Perhatian untuk keyword contain</div>
                    <div class="small text-muted">Rule ini berpotensi bertabrakan dengan keyword berikut: {{ implode(', ', $warnings) }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="section-hero-card mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-xl-8">
                <div class="d-flex gap-3 align-items-start">
                    <div class="hero-icon">
                        <i class="bx bx-message-detail"></i>
                    </div>
                    <div>
                        <h3 class="mb-2">{{ $autoreply ? 'Edit Rule Auto Reply' : 'Buat Rule Auto Reply' }}</h3>
                        <p class="hero-meta mb-0">
                            Susun rule balasan otomatis dengan trigger, tipe reply, jadwal, dan simulasi real-time
                            agar pengalaman operasional terasa konsisten dengan dashboard ChatSmart.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="hero-metrics">
                    <div class="hero-metric">
                        <span>Mode</span>
                        <strong>{{ $autoreply ? 'Edit' : 'Create' }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Device tersedia</span>
                        <strong>{{ $devices->count() }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Tipe balasan</span>
                        <strong>{{ count($replyTypes) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ $autoreply ? route('autoreply.update', $autoreply->id) : route('autoreply.store') }}" method="POST" id="autoreply-form">
        @csrf
        @if ($autoreply)
            @method('PUT')
        @endif
        <input type="hidden" name="id" value="{{ $autoreply->id ?? '' }}">

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card smart-form-card">
                    <div class="smart-card-header">
                        <h5>1. Info Rule</h5>
                        <p>Atur nama rule, device tujuan, status aktif, prioritas, dan opsi quote reply.</p>
                    </div>
                    <div class="card-body row g-3 pt-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Rule</label>
                            <input type="text" name="name" class="form-control" value="{{ $formData['name'] }}" placeholder="Contoh: Jam Operasional Umum" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Whatsapp Account</label>
                            <select name="device_id" class="form-select" required>
                                <option value="">Pilih device</option>
                                @foreach ($devices as $device)
                                    <option value="{{ $device->id }}" {{ (string) $formData['device_id'] === (string) $device->id ? 'selected' : '' }}>
                                        {{ $device->body }} ({{ $device->status }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" {{ $formData['status'] === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $formData['status'] === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <input type="number" name="priority" class="form-control" min="1" value="{{ $formData['priority'] }}">
                            <div class="form-text">Angka lebih kecil diprioritaskan untuk rule contain.</div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" id="is_quoted" name="is_quoted" value="1" {{ $formData['is_quoted'] ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_quoted">Balas sambil quote pesan masuk</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card smart-form-card">
                    <div class="smart-card-header">
                        <h5>2. Pemicu</h5>
                        <p>Pilih rule berdasarkan keyword biasa atau sambutan otomatis untuk chat pertama kali.</p>
                    </div>
                    <div class="card-body row g-3 pt-3">
                        <div class="col-12">
                            <label class="form-label d-block">Jenis Trigger</label>
                            <div class="d-flex gap-4 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input trigger-event-radio" type="radio" name="trigger_event" id="trigger_keyword" value="keyword" {{ $formData['trigger_event'] === 'keyword' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="trigger_keyword">Keyword biasa</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input trigger-event-radio" type="radio" name="trigger_event" id="trigger_first_chat" value="first_chat" {{ $formData['trigger_event'] === 'first_chat' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="trigger_first_chat">Chat pertama kali</label>
                                </div>
                            </div>
                            <div class="form-text">Pilih <strong>Chat pertama kali</strong> jika ingin mengirim welcome message otomatis saat nomor itu baru pertama menghubungi device ini.</div>
                        </div>
                        <div class="col-md-6 keyword-trigger-fields">
                            <label class="form-label">Keyword</label>
                            <input type="text" name="keyword" class="form-control" value="{{ $formData['keyword'] }}" placeholder="Contoh: jam buka">
                        </div>
                        <div class="col-md-6 keyword-trigger-fields">
                            <label class="form-label">Tipe Pencocokan</label>
                            <select name="type_keyword" class="form-select">
                                <option value="Equal" {{ $formData['type_keyword'] === 'Equal' ? 'selected' : '' }}>Equal</option>
                                <option value="Contain" {{ $formData['type_keyword'] === 'Contain' ? 'selected' : '' }}>Contain</option>
                            </select>
                        </div>
                        <div class="col-12 first-chat-trigger-note">
                            <div class="alert border-0 bg-light-info mb-0">
                                Rule ini akan dipicu sekali saat nomor tersebut pertama kali menghubungi device ini. Setelah itu, chat berikutnya tidak akan memakai trigger ini lagi.
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Only reply when sender is</label>
                            <div class="d-flex gap-4 flex-wrap">
                                @foreach (['All' => 'All', 'Personal' => 'Personal', 'Group' => 'Group'] as $value => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="reply_when" id="reply_when_{{ strtolower($value) }}" value="{{ $value }}" {{ $formData['reply_when'] === $value ? 'checked' : '' }}>
                                        <label class="form-check-label" for="reply_when_{{ strtolower($value) }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Phone Book pelanggan yang diizinkan</label>
                            <select name="contact_tag_id" class="form-select">
                                <option value="">Semua nomor boleh</option>
                                @foreach ($phonebooks as $phonebook)
                                    <option value="{{ $phonebook->id }}" {{ (string) ($formData['contact_tag_id'] ?? '') === (string) $phonebook->id ? 'selected' : '' }}>
                                        {{ $phonebook->name }} ({{ $phonebook->contacts_count }} kontak)
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Jika dipilih, rule ini hanya aktif untuk nomor yang terdaftar di Phone Book tersebut. Cocok untuk pelanggan Sickas WiFi.</div>
                        </div>
                    </div>
                </div>

                <div class="card smart-form-card">
                    <div class="smart-card-header">
                        <h5>3. Balasan</h5>
                        <p>Pilih jenis respon yang akan dikirim, dari teks sederhana sampai AI reply dan template interaktif.</p>
                    </div>
                    <div class="card-body pt-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tipe Balasan</label>
                                <select name="type" id="autoreply-type" class="form-select" required>
                                    <option value="">Pilih tipe balasan</option>
                                    @foreach ($replyTypes as $value => $label)
                                        <option value="{{ $value }}" {{ $formData['type'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Transport Policy</label>
                                <select name="transport_policy" id="autoreply-transport-policy" class="form-select">
                                    @foreach ($transportPolicies as $value => $label)
                                        <option value="{{ $value }}" {{ ($formData['transport_policy'] ?? 'text_fallback') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="alert border-0 bg-light-warning mb-0">
                                    <strong>Catatan kompatibilitas:</strong> gunakan <strong>Interactive preferred</strong> untuk
                                    welcome/menu yang memang ingin tampil sebagai list. Gunakan <strong>Text fallback</strong>
                                    bila isi rule harus tetap aman muncul di WhatsApp HP walau payload interaktif tidak tersinkron.
                                </div>
                            </div>
                        </div>

                        <div class="autoreply-type-panel mt-4" data-type-panel="text">
                            <label class="form-label">Text Message</label>
                            <textarea name="message" class="form-control" rows="6">{{ $replyConfig['message'] ?? '' }}</textarea>
                        </div>

                        <div class="autoreply-type-panel mt-4" data-type-panel="media">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Media URL</label>
                                    <div class="input-group">
                                        <button type="button" class="btn btn-primary lfm-picker" data-input="autoreply-media-url">Choose</button>
                                        <input id="autoreply-media-url" type="text" name="url" class="form-control" value="{{ $replyConfig['url'] ?? '' }}" placeholder="https://... atau pilih dari file manager">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Caption</label>
                                    <textarea name="caption" class="form-control" rows="4">{{ $replyConfig['caption'] ?? '' }}</textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Media Type</label>
                                    <select name="media_type" class="form-select">
                                        @foreach (['document' => 'Document', 'image' => 'Image', 'video' => 'Video', 'audio' => 'Voice Note'] as $value => $label)
                                            <option value="{{ $value }}" {{ ($replyConfig['media_type'] ?? 'document') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="autoreply-type-panel mt-4" data-type-panel="list">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="4">{{ $replyConfig['message'] ?? '' }}</textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Button Text</label>
                                    <input type="text" name="buttontext" class="form-control" value="{{ $replyConfig['buttontext'] ?? '' }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Name List</label>
                                    <input type="text" name="name_list" class="form-control" value="{{ $replyConfig['name'] ?? '' }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Title List</label>
                                    <input type="text" name="title" class="form-control" value="{{ $replyConfig['title'] ?? '' }}">
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label mb-0">Items</label>
                                        <button type="button" class="btn btn-outline-primary btn-sm add-repeatable" data-target="#list-items" data-name="items">Tambah Item</button>
                                    </div>
                                    <div id="list-items" class="repeatable-group smart-repeatable-group">
                                        @foreach ($listItems as $item)
                                            <div class="input-group repeatable-row smart-repeatable-row">
                                                <input type="text" name="items[]" class="form-control" value="{{ $item }}">
                                                <button type="button" class="btn btn-outline-danger remove-repeatable">Hapus</button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="autoreply-type-panel mt-4" data-type-panel="button">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="4">{{ $replyConfig['message'] ?? '' }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Footer</label>
                                    <input type="text" name="footer" class="form-control" value="{{ $replyConfig['footer'] ?? '' }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Image URL (optional)</label>
                                    <div class="input-group">
                                        <button type="button" class="btn btn-primary lfm-picker" data-input="autoreply-button-image">Choose</button>
                                        <input id="autoreply-button-image" type="text" name="image" class="form-control" value="{{ $replyConfig['image'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label mb-0">Buttons</label>
                                        <button type="button" class="btn btn-outline-primary btn-sm add-repeatable" data-target="#button-items" data-name="buttons">Tambah Button</button>
                                    </div>
                                    <div id="button-items" class="repeatable-group smart-repeatable-group">
                                        @foreach ($buttons as $button)
                                            <div class="input-group repeatable-row smart-repeatable-row">
                                                <input type="text" name="buttons[]" class="form-control" value="{{ $button }}">
                                                <button type="button" class="btn btn-outline-danger remove-repeatable">Hapus</button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="autoreply-type-panel mt-4" data-type-panel="template">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="4">{{ $replyConfig['message'] ?? '' }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Footer</label>
                                    <input type="text" name="footer" class="form-control" value="{{ $replyConfig['footer'] ?? '' }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Image URL (optional)</label>
                                    <div class="input-group">
                                        <button type="button" class="btn btn-primary lfm-picker" data-input="autoreply-template-image">Choose</button>
                                        <input id="autoreply-template-image" type="text" name="image" class="form-control" value="{{ $replyConfig['image'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="alert border-0 bg-light-info mb-2">
                                        Format template: <code>url|Nama Tombol|https://contoh.com</code> atau <code>call|Hubungi Kami|62812...</code>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label mb-0">Template Buttons</label>
                                        <button type="button" class="btn btn-outline-primary btn-sm add-repeatable" data-target="#template-items" data-name="templates">Tambah Template</button>
                                    </div>
                                    <div id="template-items" class="repeatable-group smart-repeatable-group">
                                        @foreach ($templates as $template)
                                            <div class="input-group repeatable-row smart-repeatable-row">
                                                <input type="text" name="templates[]" class="form-control" value="{{ $template }}">
                                                <button type="button" class="btn btn-outline-danger remove-repeatable">Hapus</button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="autoreply-type-panel mt-4" data-type-panel="ai">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">AI Bot Profile</label>
                                    <select name="ai_bot_id" class="form-select" id="autoreply-ai-bot-id">
                                        <option value="">Pilih AI bot</option>
                                        @foreach ($aiBots as $bot)
                                            <option
                                                value="{{ $bot->id }}"
                                                data-device-id="{{ $bot->device_id }}"
                                                {{ (string) ($formData['ai_bot_id'] ?? '') === (string) $bot->id ? 'selected' : '' }}>
                                                {{ $bot->name }} | {{ strtoupper($bot->engine_type) }} | {{ optional($bot->device)->body }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Bot profile menentukan provider, prompt, memory, dan fallback reply.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Aksi Cepat</label>
                                    <div class="d-grid">
                                        <a href="{{ route('ai-bots.create') }}" class="btn btn-outline-primary">Buat AI Bot Baru</a>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="alert border-0 bg-light-info mb-0">
                                        AI reply hanya memproses pesan teks di V1. Media dari user akan diabaikan dengan log. Jika tidak ada keyword yang cocok, bot AI aktif terbaru per device bisa dipakai sebagai default.
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="smart-preview-panel" id="autoreply-ai-summary">
                                        <div class="text-muted">Pilih AI bot profile untuk melihat ringkasan cara kerja bot.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card smart-form-card">
                    <div class="smart-card-header">
                        <h5>4. Jadwal</h5>
                        <p>Atur apakah rule selalu aktif atau hanya berjalan pada hari dan jam tertentu.</p>
                    </div>
                    <div class="card-body pt-3">
                        <div class="d-flex gap-4 flex-wrap mb-3">
                            <div class="form-check">
                                <input class="form-check-input schedule-mode-radio" type="radio" name="schedule_mode" id="schedule_always" value="always" {{ $formData['schedule_mode'] === 'always' ? 'checked' : '' }}>
                                <label class="form-check-label" for="schedule_always">Selalu aktif</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input schedule-mode-radio" type="radio" name="schedule_mode" id="schedule_custom" value="scheduled" {{ $formData['schedule_mode'] === 'scheduled' ? 'checked' : '' }}>
                                <label class="form-check-label" for="schedule_custom">Jadwal khusus</label>
                            </div>
                        </div>

                        <div id="schedule-fields" class="{{ $formData['schedule_mode'] === 'scheduled' ? '' : 'd-none' }}">
                            <div class="mb-3">
                                <label class="form-label d-block">Hari aktif</label>
                                <div class="d-flex gap-3 flex-wrap">
                                    @foreach ($weekdayOptions as $value => $label)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="schedule_days[]" value="{{ $value }}" id="schedule_day_{{ $value }}" {{ in_array($value, $formData['schedule_days'] ?: [], true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="schedule_day_{{ $value }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Jam mulai</label>
                                    <input type="time" name="schedule_start" class="form-control" value="{{ $formData['schedule_start'] ? substr((string) $formData['schedule_start'], 0, 5) : '' }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Jam selesai</label>
                                    <input type="time" name="schedule_end" class="form-control" value="{{ $formData['schedule_end'] ? substr((string) $formData['schedule_end'], 0, 5) : '' }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card smart-panel-card sticky-panel">
                    <div class="smart-card-header">
                        <h5>5. Preview dan Simulasi</h5>
                        <p>Coba rule secara langsung sebelum disimpan supaya hasil balasannya lebih terkontrol.</p>
                    </div>
                    <div class="card-body pt-3">
                        <div class="mb-3">
                            <label class="form-label">Simulasi pesan masuk</label>
                            <textarea id="simulate-message" class="form-control" rows="3" placeholder="Contoh: jam buka"></textarea>
                            <div class="form-text">Untuk trigger chat pertama, isi ini boleh bebas karena keyword tidak dipakai.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Konteks pengirim</label>
                            <select id="simulate-context" class="form-select">
                                <option value="personal">Personal</option>
                                <option value="group">Group</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="simulate-first-chat">
                            <label class="form-check-label" for="simulate-first-chat">Anggap ini chat pertama kali</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="simulate-registered-contact">
                            <label class="form-check-label" for="simulate-registered-contact">Anggap nomor contoh sudah terdaftar di Phone Book pelanggan</label>
                        </div>
                        <div class="d-grid gap-2 mb-3">
                            <button type="button" class="btn btn-outline-primary" id="simulate-autoreply-btn">Jalankan Simulasi</button>
                            <button type="submit" class="btn btn-primary">{{ $autoreply ? 'Simpan Perubahan' : 'Simpan Rule' }}</button>
                            <a href="{{ route('autoreply') }}" class="btn btn-outline-secondary">Kembali ke daftar</a>
                        </div>
                        <div id="simulate-result" class="alert border-0 bg-light-info d-none"></div>
                        <div class="smart-preview-panel" id="autoreply-preview-panel">
                            {!! $initialPreviewHtml !!}
                        </div>
                        <div class="alert border-0 bg-light-warning mt-3 mb-0 small">
                            Preview menunjukkan format ideal. Saat runtime, sistem bisa menurunkan pesan interaktif menjadi teks
                            sesuai <strong>Transport Policy</strong> dan jenis tujuan chat untuk menjaga sinkronisasi ke WhatsApp HP.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script src="{{ asset('vendor/laravel-filemanager/js/stand-alone-button.js') }}"></script>
    <script>
        window.autoreplyFormConfig = {
            type: @json($formData['type']),
            triggerEvent: @json($formData['trigger_event']),
            simulateUrl: @json(route('autoreply.simulate')),
            fileManagerPrefix: @json(url('/filemanager')),
            aiBots: @json($aiBotsPayload),
        };
    </script>
    <script src="{{ asset('js/autoreply-form.js') }}"></script>
</x-layout-dashboard>

@php
    $deviceLimit = max((int) $user->limit_device, 1);
    $deviceUsage = $user->limit_device > 0
        ? min(100, (int) round(($user->devices_count / $deviceLimit) * 100))
        : 0;
    $connectedDevices = (int) $user->connected_devices_count;
    $hasSelectedDevice = $selectedDevice !== null;
@endphp

<x-layout-dashboard :title="__('system.dashboard')">
    <div class="dashboard-shell cs-dashboard">
        @if (session()->has('alert'))
            <x-alert>
                @slot('type', session('alert')['type'])
                @slot('msg', session('alert')['msg'])
            </x-alert>
        @endif

        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger cs-inline-alert mb-0">{{ $errors->first() }}</div>
        @endif

        @if ($dashboard['load_warning'])
            <div class="alert alert-warning cs-inline-alert mb-0">
                <i class="bi bi-exclamation-circle"></i> {{ $dashboard['load_warning'] }}
            </div>
        @endif

        <section class="cs-dashboard-header">
            <div class="cs-dashboard-heading">
                <span class="cs-eyebrow">Workspace hari ini</span>
                <h1>Halo, {{ $user->username }}</h1>
                <p>Pantau WhatsApp dan jalankan pekerjaan utama dari satu tampilan yang ringkas.</p>
            </div>

            <div class="cs-dashboard-actions">
                @if ($hasSelectedDevice)
                    <a href="{{ route('messagetest') }}" class="btn btn-primary cs-btn">
                        <i class="bi bi-send"></i> Kirim pesan
                    </a>
                @endif
                <button type="button" class="btn btn-outline-secondary cs-btn" data-bs-toggle="modal"
                    data-bs-target="#addDevice">
                    <i class="bi bi-plus-lg"></i> Tambah perangkat
                </button>
            </div>
        </section>

        <section class="cs-metric-grid" aria-label="Ringkasan aktivitas">
            <article class="cs-metric-card">
                <span class="cs-metric-icon is-green"><i class="bi bi-whatsapp"></i></span>
                <div>
                    <span class="cs-metric-label">Perangkat aktif</span>
                    <strong>{{ $connectedDevices }}<small>/{{ $user->devices_count }}</small></strong>
                    <span class="cs-metric-note">{{ $connectedDevices > 0 ? 'Siap menerima pesan' : 'Perlu dihubungkan' }}</span>
                </div>
            </article>

            <article class="cs-metric-card">
                <span class="cs-metric-icon is-blue"><i class="bi bi-chat-square-text"></i></span>
                <div>
                    <span class="cs-metric-label">Pesan hari ini</span>
                    <strong>{{ number_format($user->messages_today_count) }}</strong>
                    <span class="cs-metric-note">{{ number_format($user->messages_success_today_count) }} berhasil terkirim</span>
                </div>
            </article>

            <article class="cs-metric-card">
                <span class="cs-metric-icon is-amber"><i class="bi bi-graph-up-arrow"></i></span>
                <div>
                    <span class="cs-metric-label">Keberhasilan kirim</span>
                    <strong>{{ $dashboard['delivery_rate'] }}<small>%</small></strong>
                    <span class="cs-metric-note">{{ number_format($user->messages_failed_today_count) }} pesan perlu dicek</span>
                </div>
            </article>

            <article class="cs-metric-card">
                <span class="cs-metric-icon is-slate"><i class="bi bi-stars"></i></span>
                <div>
                    <span class="cs-metric-label">Percakapan AI</span>
                    <strong>{{ number_format($dashboard['active_ai_conversations']) }}</strong>
                    <span class="cs-metric-note">{{ number_format($user->paused_ai_conversations_count) }} sedang dipause</span>
                </div>
            </article>
        </section>

        <div class="cs-dashboard-grid">
            <div class="cs-dashboard-main">
                <section class="cs-panel cs-quick-panel">
                    <div class="cs-panel-heading">
                        <div>
                            <span class="cs-eyebrow">Akses cepat</span>
                            <h2>Pekerjaan utama</h2>
                        </div>
                        @if ($hasSelectedDevice)
                            <span class="cs-device-chip is-connected"><span></span>{{ $selectedDevice->body }}</span>
                        @else
                            <span class="cs-device-chip"><span></span>Pilih perangkat</span>
                        @endif
                    </div>

                    <div class="cs-quick-grid">
                        <a href="{{ $hasSelectedDevice ? route('autoreply') : '#device_idd' }}" class="cs-quick-action">
                            <span><i class="bi bi-reply"></i></span>
                            <div><strong>Auto Reply</strong><small>Atur balasan otomatis</small></div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        <a href="{{ $hasSelectedDevice ? route('campaign.create') : '#device_idd' }}" class="cs-quick-action">
                            <span><i class="bi bi-megaphone"></i></span>
                            <div><strong>Buat Campaign</strong><small>Kirim pesan terjadwal</small></div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        <a href="{{ route('ai-conversations.index') }}" class="cs-quick-action">
                            <span><i class="bi bi-stars"></i></span>
                            <div><strong>Histori AI</strong><small>Pantau dan bersihkan chat</small></div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        <a href="{{ route('messages.history') }}" class="cs-quick-action">
                            <span><i class="bi bi-clock-history"></i></span>
                            <div><strong>Riwayat Pesan</strong><small>Cek status pengiriman</small></div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </section>

                <section class="cs-panel cs-device-panel">
                    <div class="cs-panel-heading">
                        <div>
                            <span class="cs-eyebrow">WhatsApp</span>
                            <h2>Perangkat Anda</h2>
                            <p>Hubungkan, pantau, dan buka pengaturan lanjutan bila diperlukan.</p>
                        </div>
                        <span class="cs-count-badge">{{ $numbers->total() }} perangkat</span>
                    </div>

                    <div class="cs-device-list">
                        @forelse ($numbers as $number)
                            @php
                                $webhookRead = (bool) ($number->webhook_read ?? false);
                                $webhookRejectCall = (bool) ($number->webhook_reject_call ?? false);
                                $setAvailable = (bool) ($number->set_available ?? false);
                                $webhookTyping = (bool) ($number->webhook_typing ?? false);
                                $isConnected = $number->status === 'Connected';
                            @endphp

                            <article class="cs-device-item">
                                <div class="cs-device-summary">
                                    <span class="cs-device-avatar {{ $isConnected ? 'is-connected' : '' }}">
                                        <i class="bi bi-phone"></i>
                                    </span>
                                    <div class="cs-device-copy">
                                        <div class="cs-device-title-row">
                                            <strong>{{ $number->body }}</strong>
                                            <span class="cs-status-pill {{ $isConnected ? 'is-connected' : 'is-offline' }}">
                                                <span></span>{{ $isConnected ? 'Terhubung' : 'Terputus' }}
                                            </span>
                                        </div>
                                        <span>{{ number_format((int) $number->message_sent) }} pesan terkirim</span>
                                    </div>

                                    <div class="cs-device-actions">
                                        <a href="{{ route('scan', $number->body) }}" class="btn btn-sm btn-primary cs-icon-btn"
                                            title="Hubungkan dengan QR" aria-label="Hubungkan {{ $number->body }} dengan QR">
                                            <i class="bi bi-qr-code"></i>
                                        </a>
                                        <a href="{{ route('connect-via-code', $number->body) }}"
                                            class="btn btn-sm btn-outline-secondary cs-icon-btn" title="Hubungkan dengan kode"
                                            aria-label="Hubungkan {{ $number->body }} dengan kode">
                                            <i class="bi bi-phone"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary cs-icon-btn"
                                            data-bs-toggle="collapse" data-bs-target="#deviceSettings{{ $number->id }}"
                                            aria-expanded="false" aria-controls="deviceSettings{{ $number->id }}"
                                            title="Pengaturan lanjutan" aria-label="Pengaturan {{ $number->body }}">
                                            <i class="bi bi-sliders"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="collapse" id="deviceSettings{{ $number->id }}">
                                    <div class="cs-device-settings">
                                        <div class="cs-setting-field">
                                            <label for="webhook-{{ $number->id }}">Webhook URL</label>
                                            <input id="webhook-{{ $number->id }}" type="url"
                                                class="form-control webhook-url-form" data-id="{{ $number->body }}"
                                                value="{{ $number->webhook }}" placeholder="https://domain.com/webhook">
                                        </div>

                                        <div class="cs-toggle-grid">
                                            <label class="cs-toggle-option">
                                                <span><strong>Status dibaca</strong><small>Kirim event pesan dibaca</small></span>
                                                <input data-url="{{ route('setHookRead') }}" class="form-check-input toggle-read"
                                                    type="checkbox" data-id="{{ $number->body }}" {{ $webhookRead ? 'checked' : '' }}>
                                            </label>
                                            <label class="cs-toggle-option">
                                                <span><strong>Tolak panggilan</strong><small>Tolak panggilan WhatsApp</small></span>
                                                <input data-url="{{ route('setHookReject') }}" class="form-check-input toggle-reject"
                                                    type="checkbox" data-id="{{ $number->body }}" {{ $webhookRejectCall ? 'checked' : '' }}>
                                            </label>
                                            <label class="cs-toggle-option">
                                                <span><strong>Status online</strong><small>Tampilkan akun selalu aktif</small></span>
                                                <input data-url="{{ route('setAvailable') }}" class="form-check-input toggle-available"
                                                    type="checkbox" data-id="{{ $number->body }}" {{ $setAvailable ? 'checked' : '' }}>
                                            </label>
                                            <label class="cs-toggle-option">
                                                <span><strong>Event mengetik</strong><small>Kirim event typing ke webhook</small></span>
                                                <input data-url="{{ route('setHookTyping') }}" class="form-check-input toggle-typing"
                                                    type="checkbox" data-id="{{ $number->body }}" {{ $webhookTyping ? 'checked' : '' }}>
                                            </label>
                                        </div>

                                        <form action="{{ route('deleteDevice') }}" method="POST" class="cs-danger-action"
                                            onsubmit="return confirm('Hapus perangkat {{ $number->body }} beserta kredensial koneksinya?')">
                                            @method('delete')
                                            @csrf
                                            <input name="deviceId" type="hidden" value="{{ $number->id }}">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash"></i> Hapus perangkat
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="cs-empty-state">
                                <span><i class="bi bi-phone"></i></span>
                                <strong>Belum ada perangkat</strong>
                                <p>Tambahkan nomor WhatsApp pertama untuk mulai menggunakan ChatSmart.</p>
                                <button type="button" class="btn btn-primary cs-btn" data-bs-toggle="modal"
                                    data-bs-target="#addDevice">Tambah perangkat</button>
                            </div>
                        @endforelse
                    </div>

                    @if ($numbers->hasPages())
                        <div class="cs-pagination">{{ $numbers->links() }}</div>
                    @endif
                </section>
            </div>

            <div class="cs-dashboard-side">
                <section class="cs-panel cs-focus-card">
                    <span class="cs-eyebrow">Status hari ini</span>
                    <h2>{{ $connectedDevices > 0 ? 'Siap melayani pelanggan' : 'Hubungkan WhatsApp' }}</h2>
                    <p>{{ $connectedDevices > 0
                        ? 'Perangkat aktif dan fitur pesan dapat digunakan dari dashboard.'
                        : 'Belum ada perangkat yang terhubung. Scan QR untuk mulai menerima pesan.' }}</p>
                    @if ($connectedDevices > 0)
                        <div class="cs-status-line is-good"><i class="bi bi-check2-circle"></i> Koneksi tersedia</div>
                    @else
                        <div class="cs-status-line is-warning"><i class="bi bi-exclamation-circle"></i> Perlu tindakan</div>
                    @endif
                </section>

                <section class="cs-panel cs-account-card">
                    <div class="cs-panel-heading compact">
                        <div>
                            <span class="cs-eyebrow">Akun</span>
                            <h2>Paket & kapasitas</h2>
                        </div>
                        <span class="cs-plan-pill {{ $user->subscription_status === 'Expired' ? 'is-expired' : '' }}">
                            {{ $user->subscription_status }}
                        </span>
                    </div>
                    <div class="cs-capacity-row">
                        <span>Perangkat</span>
                        <strong>{{ $user->devices_count }} dari {{ $user->limit_device }}</strong>
                    </div>
                    <div class="cs-progress"><span style="width: {{ $deviceUsage }}%"></span></div>
                    <div class="cs-account-meta">
                        <span>Aktif sampai</span>
                        <strong>{{ $user->expired_subscription_status }}</strong>
                    </div>
                </section>

                @if ($user->blasts_pending > 0 || $user->messages_failed_today_count > 0 || $user->paused_ai_conversations_count > 0)
                    <section class="cs-panel cs-attention-card">
                        <div class="cs-panel-heading compact">
                            <div>
                                <span class="cs-eyebrow">Perlu perhatian</span>
                                <h2>Tugas tertunda</h2>
                            </div>
                        </div>
                        <a href="{{ route('campaigns') }}"><span>Campaign menunggu</span><strong>{{ $user->blasts_pending }}</strong></a>
                        <a href="{{ route('messages.history') }}"><span>Pesan gagal hari ini</span><strong>{{ $user->messages_failed_today_count }}</strong></a>
                        <a href="{{ route('ai-conversations.index', ['status' => 'paused']) }}"><span>AI dipause</span><strong>{{ $user->paused_ai_conversations_count }}</strong></a>
                    </section>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="addDevice" tabindex="-1" aria-labelledby="addDeviceLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content cs-modal">
                <form action="{{ route('addDevice') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <div>
                            <span class="cs-eyebrow">WhatsApp baru</span>
                            <h5 class="modal-title" id="addDeviceLabel">Tambah perangkat</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nomor" class="form-label">Nomor WhatsApp</label>
                            <input type="text" inputmode="numeric" name="sender" class="form-control" id="nomor"
                                placeholder="628123456789" pattern="[0-9]{8,15}" required>
                            <div class="form-text">Gunakan kode negara tanpa tanda plus, contoh 62812...</div>
                        </div>
                        <div>
                            <label for="urlwebhook" class="form-label">Webhook URL <span class="text-muted">(opsional)</span></label>
                            <input type="url" name="urlwebhook" class="form-control" id="urlwebhook"
                                placeholder="https://domain.com/webhook">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan perangkat</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layout-dashboard>

<script>
    (function() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const webhookTimers = new WeakMap();

        document.querySelectorAll('.webhook-url-form').forEach(function(input) {
            input.addEventListener('input', function() {
                window.clearTimeout(webhookTimers.get(input));
                webhookTimers.set(input, window.setTimeout(function() {
                    $.ajax({
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        url: @json(route('setHook')),
                        data: { number: input.dataset.id, webhook: input.value },
                        success: function() { toastr.success('Webhook berhasil diperbarui.'); },
                        error: function(response) {
                            const message = response.responseJSON && response.responseJSON.message;
                            toastr.error(message || 'Webhook gagal diperbarui.');
                        }
                    });
                }, 700));
            });
        });

        function bindToggle(selector, fieldName) {
            document.querySelectorAll(selector).forEach(function(toggle) {
                toggle.addEventListener('change', function() {
                    const checked = toggle.checked;
                    $.ajax({
                        url: toggle.dataset.url,
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        data: { id: toggle.dataset.id, [fieldName]: checked ? '1' : '0' },
                        success: function(result) {
                            if (result.error) {
                                toggle.checked = !checked;
                                toastr.error(result.msg || 'Pengaturan gagal diperbarui.');
                                return;
                            }
                            toastr.success('Pengaturan berhasil diperbarui.');
                        },
                        error: function() {
                            toggle.checked = !checked;
                            toastr.error('Pengaturan gagal diperbarui.');
                        }
                    });
                });
            });
        }

        bindToggle('.toggle-read', 'webhook_read');
        bindToggle('.toggle-reject', 'webhook_reject_call');
        bindToggle('.toggle-available', 'set_available');
        bindToggle('.toggle-typing', 'webhook_typing');
    })();
</script>

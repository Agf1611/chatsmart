<x-layout-dashboard title="Operational Audit">
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Admin</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Operational Audit</li>
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

    @if (!empty($alerts))
        <div class="mb-4 d-grid gap-3">
            @foreach ($alerts as $alert)
                <div class="alert alert-{{ $alert['level'] }} mb-0">
                    <strong>{{ $alert['title'] }}</strong>
                    <div class="small mt-1">{{ $alert['message'] }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <section class="section-hero-card mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-xl-8">
                <p class="section-kicker mb-2">Operations Center</p>
                <h3 class="section-title mb-2">Audit kesiapan operasional</h3>
                <p class="hero-meta mb-0">
                    Panel ini merangkum kesehatan Laravel, Node runtime, database, AI provider, dan trafik harian
                    agar masalah operasional bisa terlihat sebelum berdampak ke customer service.
                </p>
            </div>
            <div class="col-xl-4">
                <div class="hero-metrics">
                    <div class="hero-metric">
                        <span>Connected device</span>
                        <strong>{{ $metrics['connected_devices'] }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>Paused chat</span>
                        <strong>{{ $metrics['paused_conversations'] }}</strong>
                    </div>
                    <div class="hero-metric">
                        <span>AI fallback</span>
                        <strong>{{ $metrics['ai_fallback_today'] }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4 mb-4">
        @foreach ($health as $healthCard)
            <div class="col-md-6 col-xl-3">
                <div class="surface-card h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="section-kicker mb-2">{{ $healthCard['label'] }}</div>
                            <h4 class="mb-2 text-capitalize">{{ $healthCard['status'] }}</h4>
                            <p class="text-muted mb-0 small">{{ $healthCard['message'] }}</p>
                        </div>
                        <span class="badge {{ $healthCard['badge_class'] }}">{{ ucfirst($healthCard['status']) }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="surface-card h-100">
                <div class="account-shell__header">
                    <div>
                        <p class="section-kicker">Traffic Summary</p>
                        <h3 class="section-title">Ringkasan hari ini</h3>
                        <div class="section-line"></div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted">Chat aktif hari ini</div>
                            <div class="fs-4 fw-semibold">{{ $metrics['incoming_active_chats_today'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted">Pesan incoming tercatat</div>
                            <div class="fs-4 fw-semibold">{{ $metrics['incoming_messages_tracked_today'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted">Manual / test sent</div>
                            <div class="fs-4 fw-semibold">{{ $metrics['manual_or_test_sent_today'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted">Auto reply sukses</div>
                            <div class="fs-4 fw-semibold text-success">{{ $metrics['auto_reply_success_today'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted">Auto reply gagal</div>
                            <div class="fs-4 fw-semibold text-danger">{{ $metrics['auto_reply_failed_today'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted">Interactive rule pakai fallback</div>
                            <div class="fs-4 fw-semibold">{{ $metrics['interactive_rules_with_fallback'] }}/{{ $metrics['interactive_rules'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="surface-card h-100">
                <div class="account-shell__header">
                    <div>
                        <p class="section-kicker">Paused Queue</p>
                        <h3 class="section-title">Kontak menunggu operator</h3>
                        <div class="section-line"></div>
                    </div>
                </div>
                @if ($recentPaused->isEmpty())
                    <div class="smart-empty">Belum ada chat yang sedang dipause.</div>
                @else
                    <div class="d-grid gap-3">
                        @foreach ($recentPaused as $conversation)
                            <div class="border rounded-3 p-3">
                                <div class="fw-semibold">{{ $conversation->contact_name ?: $conversation->chat_jid }}</div>
                                <div class="small text-muted">{{ optional($conversation->device)->body }} | {{ optional($conversation->bot)->name ?: '-' }}</div>
                                <div class="small mt-2">{{ $conversation->paused_reason ?: 'Paused tanpa keterangan' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="surface-card">
        <div class="account-shell__header">
            <div>
                <p class="section-kicker">Device Overview</p>
                <h3 class="section-title">Status device dan rule aktif</h3>
                <div class="section-line"></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Device</th>
                        <th>Status</th>
                        <th>Message Sent</th>
                        <th>Active Auto Reply</th>
                        <th>Paused AI Chat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($devices as $device)
                        <tr>
                            <td>{{ $device->body }}</td>
                            <td>
                                <span class="badge {{ $device->status === 'Connected' ? 'bg-success' : 'bg-danger' }}">
                                    {{ $device->status }}
                                </span>
                            </td>
                            <td>{{ $device->message_sent }}</td>
                            <td>{{ $device->active_autoreplies_count }}</td>
                            <td>{{ $device->paused_conversations_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Belum ada device yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layout-dashboard>

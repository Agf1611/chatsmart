<x-layout-dashboard title="Histori AI">
    <div class="cs-page-stack cs-conversations-page">
        @if (session()->has('alert'))
            <x-alert>
                @slot('type', session('alert')['type'])
                @slot('msg', session('alert')['msg'])
            </x-alert>
        @endif

        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger cs-inline-alert mb-0">{{ $errors->first() }}</div>
        @endif

        <section class="cs-page-header">
            <div>
                <span class="cs-eyebrow">AI Workspace</span>
                <h1>Histori percakapan</h1>
                <p>Pantau balasan bot, ambil alih chat, dan bersihkan histori lama agar penyimpanan tetap terjaga.</p>
            </div>

            <form method="POST" action="{{ route('ai-conversations.cleanup') }}" class="cs-cleanup-form"
                onsubmit="return confirm('Hapus permanen percakapan dan seluruh pesan yang lebih lama dari periode ini?')">
                @csrf
                @method('DELETE')
                <label for="older_than_days">Bersihkan histori</label>
                <div>
                    <select name="older_than_days" id="older_than_days" class="form-select" required>
                        <option value="30">Lebih dari 30 hari</option>
                        <option value="60" selected>Lebih dari 60 hari</option>
                        <option value="90">Lebih dari 90 hari</option>
                        <option value="180">Lebih dari 180 hari</option>
                    </select>
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash3"></i> Bersihkan
                    </button>
                </div>
            </form>
        </section>

        <section class="cs-ai-stats" aria-label="Ringkasan histori AI">
            <article><span>Total chat</span><strong>{{ number_format($conversationStats['total']) }}</strong></article>
            <article><span>Bot aktif</span><strong>{{ number_format($conversationStats['active']) }}</strong></article>
            <article><span>Dipause</span><strong>{{ number_format($conversationStats['paused']) }}</strong></article>
            <article><span>Pesan tersimpan</span><strong>{{ number_format($conversationStats['messages']) }}</strong></article>
        </section>

        @php
            $hasFilters = request()->filled('search') || request()->filled('device_id')
                || request()->filled('ai_bot_id') || request()->filled('status');
        @endphp
        <details class="cs-filter-panel" {{ $hasFilters ? 'open' : '' }}>
            <summary>
                <span><i class="bi bi-funnel"></i> Cari dan filter</span>
                @if ($hasFilters)<span class="cs-filter-active">Filter aktif</span>@endif
                <i class="bi bi-chevron-down"></i>
            </summary>
            <form method="GET" action="{{ route('ai-conversations.index') }}" class="cs-filter-grid">
                <div class="cs-filter-search">
                    <label for="search">Kontak atau isi chat</label>
                    <div class="cs-input-icon">
                        <i class="bi bi-search"></i>
                        <input type="search" id="search" name="search" class="form-control"
                            value="{{ request('search') }}" placeholder="Nama, nomor, atau pesan">
                    </div>
                </div>
                <div>
                    <label for="device_id">Perangkat</label>
                    <select name="device_id" id="device_id" class="form-select">
                        <option value="">Semua perangkat</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected((string) request('device_id') === (string) $device->id)>
                                {{ $device->body }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="ai_bot_id">AI Bot</label>
                    <select name="ai_bot_id" id="ai_bot_id" class="form-select">
                        <option value="">Semua bot</option>
                        @foreach ($bots as $bot)
                            <option value="{{ $bot->id }}" @selected((string) request('ai_bot_id') === (string) $bot->id)>
                                {{ $bot->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Semua status</option>
                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                        <option value="paused" @selected(request('status') === 'paused')>Dipause</option>
                    </select>
                </div>
                <div class="cs-filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Terapkan</button>
                    @if ($hasFilters)
                        <a href="{{ route('ai-conversations.index') }}" class="btn btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </details>

        <section class="cs-conversation-section">
            <div class="cs-list-heading">
                <div>
                    <h2>Daftar percakapan</h2>
                    <p>{{ number_format($conversations->total()) }} chat sesuai filter</p>
                </div>
            </div>

            @if ($conversations->isEmpty())
                <div class="cs-empty-state cs-panel">
                    <span><i class="bi bi-chat-square-dots"></i></span>
                    <strong>Belum ada percakapan</strong>
                    <p>Histori akan muncul setelah AI Bot menerima dan membalas pesan.</p>
                </div>
            @else
                <div class="cs-conversation-list">
                    @foreach ($conversations as $conversation)
                        @php
                            $displayName = $conversation->contact_name ?: preg_replace('/@.*$/', '', $conversation->chat_jid);
                            $isActive = $conversation->status === 'active';
                            $lastActivity = $conversation->last_message_at ?: $conversation->updated_at;
                        @endphp
                        <article class="cs-conversation-card">
                            <div class="cs-conversation-top">
                                <span class="cs-contact-avatar">{{ strtoupper(\Illuminate\Support\Str::substr($displayName, 0, 1)) }}</span>
                                <div class="cs-contact-copy">
                                    <div>
                                        <h3>{{ $displayName }}</h3>
                                        <span class="cs-status-pill {{ $isActive ? 'is-connected' : 'is-paused' }}">
                                            <span></span>{{ $isActive ? 'Bot aktif' : 'Dipause' }}
                                        </span>
                                    </div>
                                    <p>{{ $conversation->chat_jid }}</p>
                                </div>
                                <div class="dropdown cs-card-menu">
                                    <button class="btn cs-icon-btn" type="button" data-bs-toggle="dropdown"
                                        aria-expanded="false" aria-label="Aksi percakapan {{ $displayName }}">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            @if ($isActive)
                                                <form method="POST" action="{{ route('ai-conversations.pause', $conversation) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item"><i class="bi bi-pause-circle"></i> Pause bot</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('ai-conversations.resume', $conversation) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item"><i class="bi bi-play-circle"></i> Aktifkan bot</button>
                                                </form>
                                            @endif
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('ai-conversations.destroy', $conversation) }}"
                                                onsubmit="return confirm('Hapus permanen histori chat {{ addslashes($displayName) }} dan seluruh pesannya?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash3"></i> Hapus histori</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="cs-conversation-meta">
                                <span><i class="bi bi-stars"></i>{{ optional($conversation->bot)->name ?: 'Bot tidak tersedia' }}</span>
                                <span><i class="bi bi-phone"></i>{{ optional($conversation->device)->body ?: '-' }}</span>
                                <span><i class="bi bi-chat-dots"></i>{{ number_format($conversation->messages_count) }} pesan</span>
                            </div>

                            <div class="cs-message-preview">
                                <div>
                                    <span>Pelanggan</span>
                                    <p>{{ \Illuminate\Support\Str::limit($conversation->last_user_message, 120) ?: 'Belum ada pesan pelanggan.' }}</p>
                                </div>
                                <div class="is-bot">
                                    <span>Balasan bot</span>
                                    <p>{{ \Illuminate\Support\Str::limit($conversation->last_bot_message, 120) ?: 'Belum ada balasan bot.' }}</p>
                                </div>
                            </div>

                            @if ($conversation->paused_reason)
                                <div class="cs-pause-note"><i class="bi bi-info-circle"></i>{{ $conversation->paused_reason }}</div>
                            @endif

                            <footer class="cs-conversation-footer">
                                <span><i class="bi bi-clock"></i>{{ $lastActivity ? $lastActivity->diffForHumans() : 'Belum aktif' }}</span>
                                @if ($isActive)
                                    <form method="POST" action="{{ route('ai-conversations.pause', $conversation) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-warning btn-sm"><i class="bi bi-pause"></i> Pause</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('ai-conversations.resume', $conversation) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-play"></i> Aktifkan</button>
                                    </form>
                                @endif
                            </footer>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($conversations->hasPages())
                <div class="cs-pagination">{{ $conversations->links() }}</div>
            @endif
        </section>
    </div>
</x-layout-dashboard>

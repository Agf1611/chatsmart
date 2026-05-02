@props([
    'keyword' => 'Pesan masuk',
    'contactName' => 'Pelanggan',
    'businessName' => 'Sickas WiFi',
    'status' => 'online',
    'time' => '19.24',
])

<div class="wa-preview-shell">
    <div class="wa-preview-phone">
        <div class="wa-preview-phone__notch"></div>
        <div class="wa-preview-screen">
            <div class="wa-preview-statusbar">
                <span>{{ $time }}</span>
                <div class="wa-preview-statusbar__icons">
                    <span>LTE</span>
                    <span class="wa-preview-icon">))))</span>
                    <span class="wa-preview-icon">67%</span>
                </div>
            </div>

            <div class="wa-preview-header">
                <div class="wa-preview-header__left">
                    <span class="wa-preview-header__back">&#x2039;</span>
                    <div class="wa-preview-avatar">{{ strtoupper(substr($businessName, 0, 1)) }}</div>
                    <div class="wa-preview-header__meta">
                        <strong>{{ $businessName }}</strong>
                        <span>{{ $status }}</span>
                    </div>
                </div>
                <div class="wa-preview-header__actions">
                    <span>&#128249;</span>
                    <span>&#128222;</span>
                    <span>&#8942;</span>
                </div>
            </div>

            <div class="wa-preview-body">
                <div class="wa-preview-date">Hari ini</div>

                <div class="wa-bubble wa-bubble--received">
                    <div class="wa-bubble__sender">{{ $contactName }}</div>
                    <div class="wa-bubble__text">{!! nl2br(e($keyword ?: 'Pesan masuk')) !!}</div>
                    <div class="wa-bubble__meta">{{ $time }}</div>
                </div>

                <div class="wa-bubble wa-bubble--sent">
                    <div class="wa-bubble__content">
                        {{ $slot }}
                    </div>
                    <div class="wa-bubble__meta wa-bubble__meta--sent">
                        {{ $time }}
                        <span class="wa-bubble__checks">&#10003;&#10003;</span>
                    </div>
                </div>
            </div>

            <div class="wa-preview-composer">
                <span class="wa-preview-composer__emoji">&#9786;</span>
                <div class="wa-preview-composer__input">Ketik pesan</div>
                <span class="wa-preview-composer__clip">&#128206;</span>
                <span class="wa-preview-composer__camera">&#128247;</span>
                <span class="wa-preview-composer__send">&#10148;</span>
            </div>
        </div>
    </div>
</div>

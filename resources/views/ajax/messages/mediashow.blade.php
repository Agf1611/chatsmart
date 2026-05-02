@php
    $mediaType = $message->type ?? 'document';
    $mediaUrl = $message->url ?? '';
    $caption = $message->caption ?? '';
    $fileName = $mediaUrl ? basename(parse_url($mediaUrl, PHP_URL_PATH) ?: $mediaUrl) : 'media-file';
@endphp

<x-whatsapp-preview :keyword="$keyword">
    @if ($mediaType === 'image')
        <div class="wa-media-frame">
            <img src="{{ $mediaUrl }}" alt="Preview media" class="wa-media-frame__image">
        </div>
    @elseif ($mediaType === 'video')
        <div class="wa-media-frame">
            <video class="wa-media-frame__image" controls preload="metadata">
                <source src="{{ $mediaUrl }}" type="video/mp4">
            </video>
            <span class="wa-media-frame__badge">Video</span>
        </div>
    @else
        <div class="wa-document-card">
            <div class="wa-document-card__icon">&#128196;</div>
            <div class="wa-document-card__meta">
                <strong>{{ $fileName }}</strong>
                <span>Dokumen akan dikirim sebagai lampiran</span>
            </div>
        </div>
    @endif

    @if ($caption !== '')
        <div class="wa-message-copy mt-2">{!! nl2br(e($caption)) !!}</div>
    @endif
</x-whatsapp-preview>

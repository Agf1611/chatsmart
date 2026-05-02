<x-whatsapp-preview :keyword="$keyword">
    @if ($image)
        <div class="wa-media-frame wa-media-frame--compact">
            <img src="{{ $image }}" alt="Preview gambar tombol" class="wa-media-frame__image">
        </div>
    @endif

    <div class="wa-message-copy">{!! nl2br(e($message)) !!}</div>

    @if ($footer)
        <div class="wa-message-footer">{{ $footer }}</div>
    @endif

    <div class="wa-actions">
        @foreach ($buttons as $btn)
            <div class="wa-action-chip">
                <span class="wa-action-chip__icon">&#10095;</span>
                <span>{{ $btn->buttonText->displayText ?? '' }}</span>
            </div>
        @endforeach
    </div>
</x-whatsapp-preview>

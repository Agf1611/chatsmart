<x-whatsapp-preview :keyword="$keyword">
    @if ($image)
        <div class="wa-media-frame wa-media-frame--compact">
            <img src="{{ $image }}" alt="Preview gambar template" class="wa-media-frame__image">
        </div>
    @endif

    <div class="wa-message-copy">{!! nl2br(e($message)) !!}</div>

    @if ($footer)
        <div class="wa-message-footer">{{ $footer }}</div>
    @endif

    <div class="wa-actions">
        @foreach ($templates as $template)
            @php
                $isUrl = property_exists($template, 'urlButton');
                $buttonText = $isUrl ? ($template->urlButton->displayText ?? '') : ($template->callButton->displayText ?? '');
            @endphp
            <div class="wa-action-chip">
                <span class="wa-action-chip__icon">{{ $isUrl ? '&#8599;' : '&#9742;' }}</span>
                <span>{{ $buttonText }}</span>
            </div>
        @endforeach
    </div>
</x-whatsapp-preview>

<x-whatsapp-preview :keyword="$keyword">
    <div class="wa-message-copy">{!! nl2br(e($message)) !!}</div>

    <div class="wa-list-card">
        @if ($title)
            <div class="wa-list-card__title">{{ $title }}</div>
        @endif
        @if ($sectionTitle)
            <div class="wa-list-card__section">{{ $sectionTitle }}</div>
        @endif

        <div class="wa-list-card__items">
            @foreach ($rows as $row)
                @php
                    $rowTitle = is_array($row) ? ($row['title'] ?? '') : ($row->title ?? '');
                    $rowDescription = is_array($row) ? ($row['description'] ?? '') : ($row->description ?? '');
                @endphp
                <div class="wa-list-card__item">
                    <strong>{{ $rowTitle }}</strong>
                    @if ($rowDescription)
                        <span>{{ $rowDescription }}</span>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($buttonText)
            <div class="wa-list-card__cta">{{ $buttonText }}</div>
        @endif
    </div>
</x-whatsapp-preview>

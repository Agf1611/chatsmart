@php
    $title = $summary['title'] ?? 'AI Bot';
    $meta = $summary['meta'] ?? 'Bot AI akan memproses balasan sesuai konteks chat.';
    $description = $summary['description'] ?? 'Balasan akan dibuat dinamis berdasarkan pertanyaan pelanggan.';
@endphp

<x-whatsapp-preview :keyword="$keyword" :business-name="$title">
    <div class="wa-ai-card">
        <div class="wa-ai-card__eyebrow">AI Reply</div>
        <div class="wa-ai-card__title">{{ $title }}</div>
        <div class="wa-ai-card__meta">{{ $meta }}</div>
        <div class="wa-ai-card__reply">{{ $description }}</div>
        <div class="wa-ai-card__hint">Preview ini menampilkan gaya balasan. Isi akhir akan menyesuaikan konteks chat pelanggan.</div>
    </div>
</x-whatsapp-preview>

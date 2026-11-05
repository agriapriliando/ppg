@props(['active'])
<ol class="d-flex flex-wrap gap-2 list-unstyled small mb-4" aria-label="Alur pendaftaran">
    @foreach([1 => 'Biodata', 2 => 'Berkas & Kirim', 3 => 'Status Pendaftaran'] as $number => $label)
        <li @class(['rounded-pill px-3 py-2 border', 'bg-warning-subtle border-warning-subtle fw-semibold' => $active === $number, 'bg-light text-muted' => $active !== $number]) @if($active === $number) aria-current="step" @endif>{{ $number }}. {{ $label }}</li>
    @endforeach
</ol>

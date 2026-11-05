@props(['active', 'period'])
<ol class="d-flex flex-wrap gap-2 list-unstyled small mb-4" aria-label="Alur pendaftaran">
    @foreach([1 => 'Biodata', 2 => 'Berkas & Kirim', 3 => 'Status Pendaftaran'] as $number => $label)
        <li>
            <a href="{{ route([1 => 'applicant.form.edit', 2 => 'applicant.documents.index', 3 => 'applicant.application.status'][$number], $period) }}"
               @class(['d-block rounded-pill px-3 py-2 border text-decoration-none', 'bg-warning-subtle border-warning-subtle fw-semibold text-dark' => $active === $number, 'bg-light text-muted' => $active !== $number])
               @if($active === $number) aria-current="step" @endif>{{ $number }}. {{ $label }}</a>
        </li>
    @endforeach
</ol>

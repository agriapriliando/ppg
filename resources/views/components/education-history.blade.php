@props(['education'])
@php
    $history = $education?->education_history ?? [];
    if ($history === [] && ($education?->pt_name_snapshot || $education?->prodi_name_snapshot)) {
        $history[$education?->degree_level ?? 'S1'] = ['pt_name' => $education?->pt_name_snapshot, 'prodi_name' => $education?->prodi_name_snapshot, 'graduation_year' => $education?->graduation_year];
    }
@endphp
<p class="mb-3">Pendidikan terakhir: <strong>{{ $education?->degree_level ?? 'Belum diisi' }}</strong></p>
@forelse($history as $level => $item)
    <div class="border rounded-3 p-3 mb-3">
        <h4 class="h6 fw-bold text-purple">{{ $level }}</h4>
        <dl class="detail-list mb-0">
            <dt>Perguruan tinggi</dt><dd>{{ $item['pt_name'] ?? 'Belum diisi' }}</dd>
            <dt>Program studi</dt><dd>{{ $item['prodi_name'] ?? 'Belum diisi' }}</dd>
            <dt>Tahun lulus</dt><dd>{{ $item['graduation_year'] ?? 'Belum diisi' }}</dd>
        </dl>
    </div>
@empty
    <p class="text-muted">Riwayat pendidikan belum diisi.</p>
@endforelse

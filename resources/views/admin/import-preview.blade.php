<x-layouts::app title="Pratinjau Impor SK">
    @php
        $counts = $import->counts ?? [];
        $problemCount = ($counts['error'] ?? 0) + ($counts['conflict'] ?? 0);
        $validCount = ($counts['new'] ?? 0) + ($counts['change'] ?? 0) + ($counts['identical'] ?? 0);
        $committed = $import->state === 'committed';
        $canCommit = $import->matching_version === \App\Models\SkImport::MATCHING_VERSION && in_array($import->state, ['ready', 'invalid'], true) && $validCount > 0 && !$import->staging_purged_at;
    @endphp
    <section class="workspace-card">
        <div class="panel-heading"><h2>{{ $import->original_filename }}</h2><p>{{ $import->period->name }} · Kode periode {{ $import->period->code }}</p></div>
        <div class="panel-body">
            <p><strong>Jenis impor:</strong> {{ $import->modeLabel() }}. <strong>Pencocokan:</strong> {{ $import->matching_version === \App\Models\SkImport::MATCHING_VERSION ? 'Periode + ID peserta SK (Peg ID)' : 'Algoritme lama' }}.</p>
            @if(!$committed && $import->matching_version !== \App\Models\SkImport::MATCHING_VERSION && in_array($import->state, ['ready', 'invalid'], true))
                <div class="alert alert-warning">Pratinjau ini menggunakan aturan pencocokan lama. Unggah ulang berkas untuk pemeriksaan berdasarkan ID peserta SK.</div>
            @endif
            @if(in_array($import->state, ['uploaded', 'validating']))
                <div class="notice-box info"><i class="bi bi-hourglass-split" aria-hidden="true"></i><div><strong>Berkas sedang menunggu hasil validasi.</strong><p class="mb-0">Muat ulang halaman untuk melihat hasil terbaru.</p></div></div>
                <a class="btn btn-outline-purple" href="{{ route('admin.imports.show', $import) }}">Perbarui Hasil</a>
            @else
                <div class="notice-box {{ $committed && $problemCount === 0 ? 'success' : ($import->state === 'ready' ? 'info' : 'warning') }}">
                    <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                    <div><strong>{{ $committed && $problemCount > 0 ? 'Impor selesai sebagian — ada baris ditunda' : (['ready' => 'Pratinjau siap dikonfirmasi', 'invalid' => 'Ada kesalahan atau konflik', 'failed' => 'Berkas tidak dapat divalidasi', 'committed' => 'Impor telah selesai', 'expired' => 'Pratinjau kedaluwarsa'][$import->state] ?? $import->state) }}</strong><p class="mb-0">{{ $import->safe_summary }}</p></div>
                </div>
            @endif
            @if($import->counts)
                <div class="row g-3 my-3">
                    @foreach(['new' => 'Baru', 'identical' => 'Identik', 'change' => 'Perubahan', 'conflict' => 'Konflik', 'error' => 'Kesalahan'] as $key => $label)
                        <div class="col"><div class="mini-metric"><strong>{{ $counts[$key] ?? 0 }}</strong><span>{{ $label }}</span></div></div>
                    @endforeach
                </div>
            @endif
            @if($problemCount > 0 && !$import->staging_purged_at)
                <div class="alert alert-warning mt-3"><strong>{{ $problemCount }} baris bermasalah {{ $committed ? 'ditunda' : 'akan ditunda' }}.</strong> Baris error atau konflik tidak mengubah data peserta. Periksa nomor baris Excel dan catatannya, perbaiki berkas sumber, lalu unggah ulang melalui halaman Impor SK. Baris yang sudah identik tidak ditambahkan kembali.</div>
            @endif
            @if($import->staging_purged_at)
                <div class="alert alert-secondary mt-3">Berkas sumber dan rincian baris telah dibersihkan sesuai masa retensi. Metadata riwayat dan peserta hasil impor tetap tersimpan. Unggah ulang berkas sumber jika diperlukan pemeriksaan baru.</div>
            @else
                <div class="d-flex flex-wrap align-items-center gap-2 my-3">
                    <a class="btn btn-sm {{ $rowFilter === 'all' ? 'btn-purple' : 'btn-outline-purple' }}" href="{{ route('admin.imports.show', $import) }}">Semua Baris</a>
                    <a class="btn btn-sm {{ $rowFilter === 'problems' ? 'btn-purple' : 'btn-outline-purple' }}" href="{{ route('admin.imports.show', ['import' => $import, 'rows' => 'problems']) }}">Baris Bermasalah ({{ $problemCount }})</a>
                    @if($problemCount > 0 || in_array($import->state, ['invalid', 'failed']))
                        <a class="btn btn-outline-purple btn-sm" href="{{ route('admin.imports.errors', $import) }}"><i class="bi bi-download" aria-hidden="true"></i> Unduh Laporan Kesalahan Excel</a>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table portal-table">
                        <thead><tr><th>Baris Excel</th><th>Nama / nomor SK</th><th>Nomor HP</th><th>Kota/Kabupaten Asal</th><th>Provinsi Asal</th><th>Hasil</th><th>Catatan</th></tr></thead>
                        <tbody>
                            @forelse($rows as $row)
                                @php($hasProblem = in_array($row->result, ['error', 'conflict']))
                                <tr @class(['table-danger' => $row->result === 'error', 'table-warning' => $row->result === 'conflict'])>
                                    <td class="fw-semibold">{{ $row->row_number }}</td>
                                    <td>{{ $row->normalized_payload['name_on_sk'] }}<small class="d-block text-muted">ID peserta SK: {{ $row->normalized_payload['official_participant_id'] ?? 'Belum diisi' }}</small><small class="d-block text-muted">{{ $row->normalized_payload['sk_number'] }} · urut {{ $row->normalized_payload['sk_order'] }}</small></td>
                                    <td>{{ $row->normalized_payload['phone_number'] ?? 'Belum diisi' }}</td>
                                    <td>{{ $row->normalized_payload['origin_city_name'] ?? $row->normalized_payload['origin_city_code'] ?? 'Belum diisi' }}</td>
                                    <td>{{ $row->normalized_payload['origin_province_name'] ?? 'Belum diisi' }}</td>
                                    <td>
                                        @if($hasProblem)
                                            <span class="badge {{ $row->result === 'error' ? 'text-bg-danger' : 'text-bg-warning' }}">{{ $committed ? 'Ditunda' : 'Perlu perbaikan' }} · {{ $row->result === 'error' ? 'Error' : 'Konflik' }}</span>
                                        @else
                                            <span class="badge {{ $committed ? 'text-bg-success' : 'text-bg-light border' }}">{{ $committed && $row->result !== 'identical' ? 'Tersimpan' : (['new' => 'Baru', 'identical' => 'Identik', 'change' => 'Perubahan'][$row->result] ?? $row->result) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @foreach($row->errorMessages() as $error)<div class="small text-danger">{{ $error }}</div>@endforeach
                                        @foreach($row->changes ?? [] as $field => $change)
                                            <div class="small text-break"><strong>{{ ['sk_number'=>'Nomor SK', 'sk_order'=>'Nomor urut', 'sk_date'=>'Tanggal SK', 'name_on_sk'=>'Nama sesuai SK', 'phone_number'=>'Nomor HP', 'assigned_operator_id'=>'ID operator', 'internal_note'=>'Catatan internal', 'origin_city_code'=>'Kode kota/kabupaten', 'origin_city_name'=>'Kota/kabupaten', 'origin_province_code'=>'Kode provinsi', 'origin_province_name'=>'Provinsi', 'origin_reference_dataset_id'=>'Versi referensi asal'][$field] ?? $field }}:</strong> {{ $change['old'] ?? 'Kosong' }} → {{ $change['new'] ?? 'Kosong' }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">{{ $rowFilter === 'problems' ? 'Tidak ada baris bermasalah.' : 'Belum ada rincian baris.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $rows->links() }}
            @endif
            @if($canCommit)
                <form method="post" action="{{ route('admin.imports.commit', $import) }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="preview_hash" value="{{ $import->preview_hash }}">
                    <p>{{ $counts['new'] ?? 0 }} baris baru dan {{ $counts['change'] ?? 0 }} baris perubahan akan disimpan. {{ $counts['identical'] ?? 0 }} baris identik dipertahankan. {{ $problemCount }} baris bermasalah ditunda.</p>
                    <div class="form-check mb-3"><input class="form-check-input" id="confirm-import" type="checkbox" required><label class="form-check-label" for="confirm-import">Saya telah memeriksa sumber resmi dan menyetujui penyimpanan baris valid serta penundaan baris bermasalah.</label></div>
                    <button class="btn btn-gold">{{ $problemCount > 0 ? 'Simpan Baris Valid' : 'Konfirmasi Impor' }}</button>
                </form>
            @elseif($import->state === 'invalid' && $validCount === 0)
                <p class="text-danger mt-3">Tidak ada baris valid untuk disimpan. Perbaiki baris bermasalah lalu unggah ulang.</p>
            @endif
            <a href="{{ route('admin.imports') }}" class="btn btn-light border mt-3">Kembali ke Riwayat Impor</a>
        </div>
    </section>
</x-layouts::app>

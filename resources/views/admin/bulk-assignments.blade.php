<x-layouts::app title="Penugasan Massal">
    <div class="notice-box warning"><i class="bi bi-arrow-left-right" aria-hidden="true"></i><p class="mb-0">Perubahan ini memindahkan akses operator terhadap peserta aktif. Kepemilikan akun pendaftar dan isi formulir tetap. Peserta yang ditarik dikecualikan.</p></div>
    <section class="workspace-card mb-4">
        <div class="panel-heading">
            <h2>Tinjau transfer atau penyeimbangan beban</h2>
            <p>Semua akun Operator aktif dapat langsung digunakan pada seluruh periode tanpa pendaftaran terpisah.</p>
        </div>
        <div class="panel-body">
            @if($periods->isEmpty())
                <div class="alert alert-warning p-4 rounded-3 text-center mb-0" role="alert">
                    <i class="bi bi-calendar-x fs-1 text-warning d-block mb-3"></i>
                    <h3 class="h5 fw-bold text-dark">Belum Ada Periode Pendaftaran</h3>
                    <p class="text-muted small mb-4">
                        Penugasan massal operator memerlukan periode pendaftaran aktif. Silakan buat periode pendaftaran terlebih dahulu.
                    </p>
                    <a href="{{ route('admin.periods') }}" class="btn btn-purple btn-sm">
                        <i class="bi bi-calendar-plus me-1"></i>Kelola Periode & Gelombang
                    </a>
                </div>
            @else
                <form method="get" action="{{ route('admin.assignments.bulk') }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="bulk-period" class="form-label">Periode *</label>
                            <select id="bulk-period" name="period_id" class="form-select" required>
                                <option value="">Pilih periode</option>
                                @foreach($periods as $option)
                                    <option value="{{ $option->id }}" @selected($period?->id===$option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="bulk-mode" class="form-label">Metode *</label>
                            <select id="bulk-mode" name="mode" class="form-select">
                                <option value="rebalance" @selected($mode==='rebalance')>Seimbangkan seluruh peserta aktif</option>
                                <option value="transfer" @selected($mode==='transfer')>Transfer seluruh tugas aktif satu operator</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="bulk-source" class="form-label">Operator sumber (khusus transfer)</label>
                            <select id="bulk-source" name="source_id" class="form-select">
                                <option value="">Pilih sumber transfer</option>
                                @foreach($operators as $operator)
                                    <option value="{{ $operator->id }}" @selected($sourceId===$operator->id)>{{ $operator->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <fieldset class="mt-4">
                        <legend class="h6">Operator tujuan *</legend>
                        <p class="small text-muted">Pilih satu untuk transfer; satu atau lebih untuk penyeimbangan. Semua akun Operator aktif dapat dipilih pada seluruh periode.</p>
                        <div class="row g-2">
                            @foreach($activeOperators as $operator)
                                <div class="col-md-6">
                                    <label class="form-check border rounded p-3 ps-5">
                                        <input class="form-check-input" type="checkbox" name="operators[]" value="{{ $operator->id }}" @checked(in_array($operator->id, $selected, true))>
                                        <span class="form-check-label">{{ $operator->name }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                    <button class="btn btn-outline-purple mt-4">Buat Pratinjau Perubahan</button>
                </form>
            @endif
        </div>
    </section>
    @if($plan !== null)
        <section class="workspace-card mb-4"><div class="panel-heading"><h2>{{ count($plan['changes']) }} penugasan akan berubah</h2><p>{{ $period->name }} · Penyeimbangan mempertahankan tugas lama sebanyak kuota tujuan memungkinkan. Tidak ada perubahan sebelum konfirmasi.</p></div><div class="panel-body">
            <div class="table-responsive"><table class="table portal-table"><thead><tr><th>Operator</th><th>Beban aktif awal</th><th>Beban aktif akhir</th></tr></thead><tbody>@foreach($plan['loads'] as $id=>$load)<tr><td>{{ $operators->get($id)?->name ?? 'Operator #'.$id }}</td><td>{{ $load['before'] }}</td><td>{{ $load['after'] }}</td></tr>@endforeach</tbody></table></div>
            <h3 class="h6 mt-4">Rincian peserta yang berpindah</h3>
            <div class="table-responsive"><table class="table portal-table"><thead><tr><th>Peserta / SK</th><th>Operator lama</th><th>Operator baru</th></tr></thead><tbody>@forelse($changes as $change)<tr><td>{{ $change['name'] }}<small class="d-block text-muted">{{ $change['sk'] }}</small></td><td>{{ $operators->get($change['old'])?->name ?? 'Belum ditugaskan' }}</td><td>{{ $operators->get($change['new'])?->name }}</td></tr>@empty<tr><td colspan="3">Distribusi sudah sesuai; tidak ada tugas yang perlu diubah.</td></tr>@endforelse</tbody></table></div>
            {{ $changes->links() }}
            @if($plan['changes'] !== [])<form method="post" action="{{ route('admin.assignments.bulk.store') }}" class="mt-4">@csrf<input type="hidden" name="preview_token" value="{{ $token }}"><label for="bulk-reason" class="form-label">Alasan perubahan *</label><textarea id="bulk-reason" name="reason" class="form-control mb-3" minlength="10" maxlength="1000" required></textarea><label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirmed" value="1" required><span class="form-check-label">Saya telah meninjau seluruh halaman rincian dan menyetujui perubahan akses operator.</span></label><button class="btn btn-gold">Konfirmasi Perubahan Penugasan</button></form>@endif
        </div></section>
    @endif
    <a href="{{ route('admin.assignments', ['period_id'=>$period?->id]) }}" class="btn btn-light border">Kembali ke Pembagian Operator</a>
</x-layouts::app>

<x-layouts::app title="Ekspor Peserta">
    <section class="workspace-card mb-4">
        <div class="panel-heading">
            <h2>Siapkan ekspor peserta</h2>
            <p>Pilih laporan CSV atau Excel untuk memperbarui data melalui Impor SK.</p>
        </div>
        <div class="panel-body">
            @if($periods->isEmpty())
                <div class="alert alert-info d-flex align-items-center gap-2 p-3 mb-3 rounded-3" role="alert">
                    <i class="bi bi-info-circle-fill text-primary"></i>
                    <span class="small">Belum ada periode pendaftaran di sistem. Ekspor akan mencakup seluruh data peserta yang tersedia tanpa filter periode.</span>
                </div>
            @endif

            <form method="post" action="{{ route('admin.exports.store') }}" class="row g-3">
                @csrf
                <div class="col-12">
                    <label for="export-format" class="form-label">Jenis ekspor</label>
                    <select class="form-select" id="export-format" name="format">
                        <option value="csv" @selected(old('format', 'csv') === 'csv')>Laporan administrasi (CSV)</option>
                        <option value="sk_reimport" @selected(old('format') === 'sk_reimport')>Excel untuk Impor Ulang SK (.xlsx)</option>
                    </select>
                    <div class="form-text">Excel impor ulang wajib satu periode yang belum diarsipkan, berisi 1–5.000 peserta dengan PEG ID terisi. Setelah diedit, unggah melalui menu Impor SK pada periode yang sama dengan mode Perbarui saja. Laporan CSV tidak digunakan untuk impor.</div>
                    <a href="{{ route('admin.imports') }}" class="small">Buka menu Impor SK</a>
                    <x-field-error name="format" />
                </div>
                <div class="col-md-4">
                    <label for="export-period" class="form-label">Periode</label>
                    <select class="form-select" id="export-period" name="period_id">
                        <option value="">Semua periode</option>
                        @foreach($periods as $period)
                            <option value="{{ $period->id }}" @selected(old('period_id') == $period->id)>{{ $period->name }}</option>
                        @endforeach
                    </select>
                    <x-field-error name="period_id" />
                </div>
                <div class="col-md-4">
                    <label for="export-operator" class="form-label">Operator</label>
                    <select class="form-select" id="export-operator" name="operator_id">
                        <option value="">Semua operator</option>
                        @foreach($operators as $operator)
                            <option value="{{ $operator->id }}" @selected(old('operator_id') == $operator->id)>{{ $operator->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="export-status" class="form-label">Status pendaftaran</label>
                    <select class="form-select" id="export-status" name="status">
                        <option value="">Semua status</option>
                        @foreach(App\Enums\ApplicationStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-purple">Siapkan Ekspor</button>
                </div>
            </form>
        </div>
    </section>

    <section class="workspace-card">
        <div class="panel-heading">
            <h2>Hasil ekspor</h2>
            <p>Berkas siap diunduh selama 24 jam. Pemeriksaan akses dilakukan kembali saat unduh.</p>
        </div>
        <div class="table-responsive">
            <table class="table portal-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Status</th>
                        <th>Jenis</th>
                        <th>Jumlah</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr>
                            <td>{{ $run->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</td>
                            <td>{{ ['queued'=>'Disiapkan','processing'=>'Disiapkan','ready'=>'Siap','failed'=>'Gagal','expired'=>'Kedaluwarsa'][$run->state]??$run->state }}</td>
                            <td>{{ ($run->scope_snapshot['format'] ?? 'csv') === 'sk_reimport' ? 'Excel impor ulang SK' : 'Laporan CSV' }}</td>
                            <td>{{ $run->row_count }}</td>
                            <td>
                                @if($run->state==='ready' && $run->expires_at?->isFuture())
                                    <a class="btn btn-sm btn-outline-purple" href="{{ route('admin.exports.download',$run) }}">{{ ($run->scope_snapshot['format'] ?? 'csv') === 'sk_reimport' ? 'Unduh Excel' : 'Unduh CSV' }}</a>
                                @else
                                    <span class="text-muted small">Belum tersedia</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted py-4">Belum ada ekspor disiapkan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-body">{{ $runs->links() }}</div>
    </section>
</x-layouts::app>

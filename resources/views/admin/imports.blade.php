<x-layouts::app title="Impor SK">
    <div class="row g-4">
        <div class="col-xl-5">
            <section class="workspace-card">
                <div class="panel-heading">
                    <h2>Unggah daftar peserta resmi</h2>
                    <p>Berkas divalidasi sebelum peserta disimpan.</p>
                </div>
                <div class="panel-body">
                    @if($periods->isEmpty())
                        <div class="alert alert-warning p-4 rounded-3 text-center mb-0" role="alert">
                            <i class="bi bi-calendar-x fs-1 text-warning d-block mb-3"></i>
                            <h3 class="h5 fw-bold text-dark">Belum Ada Periode Pendaftaran</h3>
                            <p class="text-muted small mb-4">
                                Pengunggahan berkas SK memerlukan periode pendaftaran aktif sebagai target peserta. Silakan buat periode pendaftaran terlebih dahulu.
                            </p>
                            <a href="{{ route('admin.periods') }}" class="btn btn-purple btn-sm">
                                <i class="bi bi-calendar-plus me-1"></i>Kelola Periode & Gelombang
                            </a>
                        </div>
                    @else
                        <form method="post" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="import-period" class="form-label">Periode tujuan *</label>
                                <select class="form-select" id="import-period" name="period_id" required>
                                    <option value="">Pilih periode</option>
                                    @foreach($periods as $period)
                                        <option value="{{ $period->id }}" @selected(\App\Support\FormInput::old('period_id')==$period->id)>{{ $period->name }} · {{ $period->code }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Semua peserta dalam Excel akan masuk ke periode yang dipilih. Template terbaru tidak memerlukan kolom periode.</div>
                            </div>
                            <div class="mb-3">
                                <label for="import-mode" class="form-label">Jenis impor *</label>
                                <select id="import-mode" name="import_mode" class="form-select" required>
                                    <option value="update_only" @selected(\App\Support\FormInput::old('import_mode', 'update_only') === 'update_only')>Perbarui saja</option>
                                    <option value="upsert" @selected(\App\Support\FormInput::old('import_mode', 'update_only') === 'upsert')>Tambah dan perbarui</option>
                                </select>
                                <div class="form-text">Untuk unggah ulang, pilih Perbarui saja. Peserta dicocokkan berdasarkan periode dan id_peserta_sk (Peg ID), bukan nomor SK atau nomor urut. Pilih Tambah dan perbarui untuk memasukkan peserta baru.</div>
                                <x-field-error name="import_mode" />
                            </div>
                            <div class="alert alert-info small">Kolom <code>id_peserta_sk</code> wajib diisi sebagai teks agar nol awal tetap utuh. Isian opsional kosong mempertahankan data lama. Perubahan peserta terikat klaim diperiksa melalui jalur administratif.</div>
                            <label class="upload-zone" for="sk-file">
                                <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                                <strong>Pilih berkas Excel SK</strong>
                                <span>.xlsx · maksimal 5 MiB · 5.000 baris</span>
                                <input id="sk-file" name="file" type="file" class="form-control mt-3" accept=".xlsx" required>
                            </label>
                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <button class="btn btn-purple">Unggah & Validasi</button>
                                <a href="{{ route('admin.imports.template') }}" class="btn btn-outline-purple">Unduh Template</a>
                            </div>
                        </form>
                        <div class="notice-box info mt-4">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            <p class="mb-0">Baris valid tetap dapat dikonfirmasi meskipun ada kesalahan atau konflik. Baris bermasalah ditunda dan ditandai pada pratinjau agar dapat diperbaiki lalu diunggah ulang. Unggah ulang identik mempertahankan data dan penugasan yang sudah ada.</p>
                        </div>
                        <div class="alert alert-light border mt-3 mb-0">
                            <strong class="d-block mb-2">Nomor HP</strong>
                            <p class="mb-3">Kolom <code>nomor_hp</code> opsional. Awalan <code>08</code> dan <code>+628</code> otomatis dikonversi ke <code>628</code>. Isian yang belum rapi tetap dapat disimpan lalu diperbaiki di daftar Peserta SK. Simpan sebagai teks agar angka tetap utuh. Kolom kosong atau template lama mempertahankan nomor yang sudah tersimpan.</p>
                            <strong class="d-block mb-2">ID Operator</strong>
                            <p class="mb-3">Isi kolom <code>id_operator</code> dengan <strong>ID login operator</strong>, misalnya <code>operator.satu</code>. Semua akun Operator aktif dapat digunakan pada seluruh periode tanpa pendaftaran operator per periode. Jika dikosongkan, peserta baru belum ditugaskan dan penugasan peserta lama tetap dipertahankan.</p>
                            <strong class="d-block mb-2">Kota/Kabupaten Asal dan Provinsi</strong>
                            <p class="mb-1">Gunakan template terbaru. Isi kolom <code>kode_kota_kabupaten_asal</code> dengan kode teks dari sheet <strong>Referensi_Wilayah</strong>. Provinsi ditentukan otomatis, tanpa isian kecamatan.</p>
                            <small>Kolom asal opsional. Jika belum tersedia, kosongkan; informasi asal yang sudah tersimpan tidak akan dihapus saat impor ulang.</small>
                        </div>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-xl-7">
            <section class="workspace-card">
                <div class="panel-heading">
                    <h2>Riwayat impor</h2>
                </div>
                <div class="table-responsive">
                    <table class="table portal-table">
                        <thead>
                            <tr>
                                <th>Berkas / periode</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($imports as $import)
                                <tr>
                                    <td>
                                        <strong>{{ $import->original_filename }}</strong>
                                        <small class="d-block text-muted">{{ $import->period->name }} · {{ $import->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</small>
                                    </td>
                                    <td>{{ $import->state === 'committed' && (($import->counts['error'] ?? 0) + ($import->counts['conflict'] ?? 0)) > 0 ? 'Selesai sebagian' : (['uploaded'=>'Antre validasi','validating'=>'Divalidasi','ready'=>'Siap ditinjau','invalid'=>'Perlu koreksi','failed'=>'Validasi gagal','committed'=>'Selesai','expired'=>'Kedaluwarsa'][$import->state] ?? $import->state) }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a class="btn btn-sm btn-outline-purple" href="{{ route('admin.imports.show', $import) }}">Lihat Hasil</a>
                                            @can('delete', $import)
                                                @if(in_array($import->state, ['uploaded', 'validating'], true))
                                                    <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Tunggu validasi selesai"><i class="bi bi-trash me-1" aria-hidden="true"></i>Hapus</button>
                                                @else
                                                    <form method="post" action="{{ route('admin.imports.destroy', $import) }}" onsubmit="return confirm('Hapus riwayat impor ini beserta berkas sumber dan hasil validasinya? Data peserta yang sudah diimpor tetap tersimpan.');">
                                                        @csrf
                                                        @method('delete')
                                                        <input type="hidden" name="confirmed" value="1">
                                                        <input type="hidden" name="expected_state" value="{{ $import->state }}">
                                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Hapus</button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-muted">Belum ada berkas diunggah.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="panel-body">{{ $imports->links() }}</div>
            </section>
        </div>
    </div>
    <livewire:city-code-search />
</x-layouts::app>

<x-layouts::app title="Pengaturan Berkas">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold text-purple"><i class="bi bi-folder-check me-2" aria-hidden="true"></i>Pengaturan Berkas</h1>
            <p class="text-muted small mb-0">Kelola batas ukuran berkas, kuota, dan format dokumen yang diizinkan untuk periode pendaftaran.</p>
        </div>
        @if ($selectedPeriod && $periods->isNotEmpty())
            <div>
                <form method="get" action="{{ route('admin.document-policies.index') }}" class="d-flex align-items-center gap-2">
                    <label for="period-selector" class="small fw-semibold text-muted text-nowrap">Pilih Periode:</label>
                    <select id="period-selector" name="period_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach ($periods as $p)
                            <option value="{{ $p->id }}" @selected($p->id === $selectedPeriod->id)>
                                {{ $p->name }} ({{ $p->academic_year }})
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        @endif
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <h6 class="alert-heading fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Terjadi Kesalahan Validasi:</h6>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $selectedPeriod)
        <div class="workspace-card p-5 text-center">
            <div class="mb-3">
                <span class="dayak-shield-badge" style="width: 56px; height: 56px; font-size: 1.5rem; background: #f3e8ff; color: var(--ppg-purple-primary); margin: 0 auto;">
                    <i class="bi bi-folder-x" aria-hidden="true"></i>
                </span>
            </div>
            <h2 class="h5 fw-bold text-dark mb-2">Belum Ada Periode Pendaftaran</h2>
            <p class="text-muted small mx-auto mb-4" style="max-width: 520px;">
                Pengaturan batas ukuran berkas, kuota, dan format dokumen diatur berdasarkan periode pendaftaran resmi. Silakan buat periode pendaftaran terlebih dahulu untuk mulai mengatur aturan berkas.
            </p>
            <a href="{{ route('admin.periods') }}" class="btn btn-purple d-inline-flex align-items-center gap-2">
                <i class="bi bi-calendar-plus" aria-hidden="true"></i>
                <span>Kelola Periode & Gelombang</span>
            </a>
        </div>
    @else
        <div class="row g-4">
        <!-- Form Pengaturan & Publikasi Versi -->
        <div class="col-xl-8">
            <section class="workspace-card mb-4">
                <div class="panel-heading">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">
                        <div>
                            <h2 class="h5 mb-1">Konfigurasi Aturan Berkas</h2>
                            <p class="text-muted small mb-0">Perubahan aturan akan disimpan sebagai versi baru (immutable) untuk periode <strong>{{ $selectedPeriod->name }}</strong>.</p>
                        </div>
                        <span class="badge bg-purple px-3 py-2">Versi Aktif Saat Ini: v{{ $currentPolicy->version }}</span>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="post" action="{{ route('admin.document-policies.store') }}">
                        @csrf
                        <input type="hidden" name="admission_period_id" value="{{ $selectedPeriod->id }}">

                        <h3 class="h6 fw-bold mb-3 text-purple border-bottom pb-2">1. Format File yang Diperbolehkan</h3>
                        <div class="row g-2 mb-4">
                            @foreach ($supportedTypes as $ext => $config)
                                <div class="col-6 col-sm-4 col-md-3">
                                    <div class="form-check p-2 border rounded bg-light">
                                        <input class="form-check-input ms-1" type="checkbox" name="allowed_extensions[]"
                                            value="{{ $ext }}" id="ext-{{ $ext }}"
                                            @checked(in_array($ext, old('allowed_extensions', $currentPolicy->allowedExtensions()), true))>
                                        <label class="form-check-label fw-semibold ms-1 small" for="ext-{{ $ext }}">
                                            {{ $config['label'] }} <small class="text-muted">(.{{ $ext }})</small>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <h3 class="h6 fw-bold mb-3 text-purple border-bottom pb-2">2. Batas Ukuran & Kuota</h3>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="max_file_size_mb" class="form-label small fw-semibold">Batas Ukuran Maksimal per File (MB) *</label>
                                <div class="input-group">
                                    <input type="number" step="0.5" min="0.5" max="100" class="form-control"
                                        id="max_file_size_mb" name="max_file_size_mb" required
                                        value="{{ old('max_file_size_mb', round($currentPolicy->max_file_size_bytes / (1024 * 1024), 1)) }}">
                                    <span class="input-group-text">MB</span>
                                </div>
                                <div class="form-text small">Berlaku untuk semua format, termasuk video, bagi pendaftar dan pengelola. Durasi video tidak dibatasi.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="workspace_quota_mb" class="form-label small fw-semibold">Kapasitas Total Workspace per Periode (Opsional)</label>
                                <div class="input-group">
                                    <input type="number" step="1" min="1" max="10000" class="form-control"
                                        id="workspace_quota_mb" name="workspace_quota_mb"
                                        value="{{ old('workspace_quota_mb', $currentPolicy->workspace_quota_bytes ? round($currentPolicy->workspace_quota_bytes / (1024 * 1024)) : '') }}"
                                        placeholder="Kosongkan jika tanpa batas kuota">
                                    <span class="input-group-text">MB</span>
                                </div>
                                <div class="form-text small">Wajib diisi jika video diizinkan. Jika video tidak aktif dan dikosongkan, kuota tidak dibatasi oleh aplikasi.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="max-video-files" class="form-label small fw-semibold">Maksimal video per workspace peserta (jika video diizinkan)</label>
                                <input id="max-video-files" type="number" min="1" max="30" name="max_video_files" class="form-control" value="{{ old('max_video_files', $currentPolicy->max_video_files ?? 1) }}">
                                <div class="form-text">1–30 video MP4, MOV, atau WebM secara keseluruhan. Berlaku untuk berkas peserta; berkas informasi periode tidak memakai batas jumlah ini.</div>
                            </div>
                        </div>

                        <h3 class="h6 fw-bold mb-3 text-purple border-bottom pb-2">3. Petunjuk Umum</h3>
                        <div class="mb-4">
                            <label for="general_instructions" class="form-label small fw-semibold">Petunjuk yang Ditampilkan pada Layar Pendaftar & Pengelola</label>
                            <textarea id="general_instructions" name="general_instructions" rows="12" class="form-control" maxlength="10000" aria-describedby="general-instructions-help"
                                placeholder="Contoh:&#10;1. Pastikan hasil pindai terbaca jelas.&#10;2. Unggah setiap dokumen secara terpisah.&#10;3. Periksa kembali berkas sebelum mengirim.">{{ old('general_instructions', $currentPolicy->general_instructions) }}</textarea>
                            <div id="general-instructions-help" class="form-text">Tekan Enter untuk membuat baris baru. Baris baru dan paragraf akan ditampilkan sesuai penulisan pada panduan unggah berkas. Maksimal 10.000 karakter.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-purple px-4 fw-semibold">
                                <i class="bi bi-check-circle me-1"></i>Publikasikan Versi Kebijakan Baru
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <!-- Riwayat Versi Kebijakan -->
        <div class="col-xl-4">
            <section class="workspace-card">
                <div class="panel-heading">
                    <h2 class="h5 mb-1">Riwayat Versi Aturan</h2>
                    <p class="text-muted small mb-0">Daftar versi yang pernah dipublikasikan pada periode ini.</p>
                </div>
                <div class="panel-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach ($history as $v)
                            <div class="list-group-item p-3 {{ $v->id === $currentPolicy->id ? 'bg-light border-start border-4 border-purple' : '' }}">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-purple">Versi {{ $v->version }}</strong>
                                    @if ($v->id === $currentPolicy->id)
                                        <span class="badge bg-purple">Aktif</span>
                                    @endif
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-clock me-1"></i>{{ $v->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
                                    @if ($v->creator)
                                        · oleh {{ $v->creator->name }}
                                    @endif
                                </div>
                                <div class="small">
                                    <div><strong>Format:</strong> {{ $v->allowedExtensionLabels() }}</div>
                                    <div><strong>Batas File:</strong> {{ $v->formattedMaxFileSize() }}</div>
                                    @if ($v->workspace_quota_bytes)
                                        <div><strong>Kuota:</strong> {{ $v->formattedWorkspaceQuota() }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>
    @endif
</x-layouts::app>

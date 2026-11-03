<x-layouts::app title="Daftar Unggahan">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold text-purple"><i class="bi bi-files me-2" aria-hidden="true"></i>Daftar Unggahan</h1>
            <p class="text-muted small mb-0">Daftar seluruh berkas yang telah diunggah oleh pendaftar lintas periode.</p>
        </div>
    </div>

    <section class="workspace-card mb-4">
        <div class="panel-body">
            <form method="get" action="{{ route('admin.document-policies.uploads') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="filter-period" class="form-label small fw-semibold">Periode</label>
                    <select id="filter-period" name="period_id" class="form-select form-select-sm">
                        <option value="">Semua Periode</option>
                        @foreach ($periods as $p)
                            <option value="{{ $p->id }}" @selected(($filters['period_id'] ?? '') == $p->id)>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="filter-search" class="form-label small fw-semibold">Pencarian</label>
                    <input type="text" id="filter-search" name="search" class="form-control form-control-sm"
                        value="{{ $filters['search'] ?? '' }}" placeholder="Nama pendaftar / nama berkas / no pendaftaran">
                </div>
                <div class="col-md-2">
                    <label for="filter-ext" class="form-label small fw-semibold">Ekstensi</label>
                    <select id="filter-ext" name="extension" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach (['pdf', 'jpg', 'jpeg', 'png', 'docx', 'doc', 'xlsx', 'xls'] as $ext)
                            <option value="{{ $ext }}" @selected(($filters['extension'] ?? '') === $ext)>{{ strtoupper($ext) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-purple btn-sm flex-grow-1">
                        <i class="bi bi-filter me-1"></i>Filter
                    </button>
                    <a href="{{ route('admin.document-policies.uploads') }}" class="btn btn-light border btn-sm">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </section>

    <section class="workspace-card">
        <div class="panel-heading">
            <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
                <div>
                    <h2 class="h5 mb-1">Seluruh Dokumen Terunggah</h2>
                    <p class="text-muted small mb-0">Total ditemukan: <strong>{{ $documents->total() }}</strong> berkas</p>
                </div>
            </div>
        </div>
        <div class="panel-body p-0">
            @if ($documents->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-4 text-muted mb-2 d-block"></i>
                    <h3 class="h6 text-muted">Tidak ada berkas yang cocok dengan filter</h3>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Pemilik Workspace</th>
                                <th>Nama Berkas</th>
                                <th>Periode</th>
                                <th>Ukuran</th>
                                <th>Waktu</th>
                                <th>Pengunggah</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documents as $doc)
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-semibold text-dark">
                                            {{ $doc->workspace?->biodata?->full_name ?? ($doc->owner?->name ?? 'Pendaftar') }}
                                        </div>
                                        <small class="text-muted">
                                            @if ($doc->workspace?->application)
                                                {{ $doc->workspace->application->application_number }}
                                            @else
                                                <span class="badge text-bg-warning">Pra-Klaim</span>
                                            @endif
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi {{ $doc->iconClass() }} fs-5"></i>
                                            <div>
                                                <span class="fw-semibold text-dark">{{ $doc->display_name }}</span><span class="text-muted">.{{ $doc->extension }}</span>
                                                <small class="text-muted d-block" style="font-size: 0.72rem;">Asli: {{ $doc->original_name }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="small">{{ $doc->period?->name }}</span>
                                    </td>
                                    <td>
                                        <span class="small fw-semibold">{{ $doc->formattedSize() }}</span>
                                    </td>
                                    <td>
                                        <div class="small">{{ $doc->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</div>
                                        <small class="text-muted">{{ $doc->created_at->timezone('Asia/Jakarta')->format('H:i') }} WIB</small>
                                    </td>
                                    <td>
                                        @if ($doc->uploaded_by === $doc->owner_user_id)
                                            <span class="badge text-bg-success">Pendaftar</span>
                                        @else
                                            <span class="small fw-semibold text-secondary">
                                                {{ $doc->uploader?->name ?? 'Petugas' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="doc-actions-group" role="group" aria-label="Aksi berkas {{ $doc->file_name }}">
                                            <a href="{{ route('admin.workspaces.documents.show', $doc->workspace_id) }}"
                                                class="doc-btn doc-btn-open" title="Buka Workspace Berkas">
                                                <i class="bi bi-box-arrow-up-right"></i><span>Buka</span>
                                            </a>
                                            @if ($doc->isPreviewable())
                                                <a href="{{ route('admin.workspaces.documents.preview', ['workspace' => $doc->workspace_id, 'document' => $doc->id]) }}"
                                                    target="_blank" class="doc-btn doc-btn-preview" title="Lihat di tab baru">
                                                    <i class="bi bi-eye"></i><span>Lihat</span>
                                                </a>
                                            @endif
                                            <a href="{{ route('admin.workspaces.documents.download', ['workspace' => $doc->workspace_id, 'document' => $doc->id]) }}"
                                                class="doc-btn doc-btn-download" title="Unduh berkas">
                                                <i class="bi bi-download"></i><span>Unduh</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-top">
                    {{ $documents->links() }}
                </div>
            @endif
        </div>
    </section>
</x-layouts::app>

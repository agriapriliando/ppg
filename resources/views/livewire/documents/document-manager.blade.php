<div x-data="{ fileInfo: null }">
    @if ($successMessage)
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ $successMessage }}
            <button type="button" class="btn-close" wire:click="$set('successMessage', null)" aria-label="Tutup"></button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ $errorMessage }}
            <button type="button" class="btn-close" wire:click="$set('errorMessage', null)" aria-label="Tutup"></button>
        </div>
    @endif

    @if ($isArchived)
        <div class="notice-box warning mb-4">
            <i class="bi bi-archive-fill" aria-hidden="true"></i>
            <div>
                <strong>Periode penerimaan telah diarsipkan.</strong>
                <p class="mb-0">Seluruh berkas berada dalam mode hanya-baca. Anda dapat melihat dan mengunduh berkas, namun tidak dapat mengunggah berkas baru, mengubah nama, atau menghapus.</p>
            </div>
        </div>
    @elseif ($isAdminHold && $mode === 'applicant')
        <div class="notice-box warning mb-4">
            <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
            <div>
                <strong>Kepesertaan Anda sedang dalam peninjauan administratif.</strong>
                <p class="mb-0">Anda hanya dapat melihat dan mengunduh berkas yang tersimpan. Pengelola yang berwenang dapat membantu memperbarui berkas Anda jika diperlukan.</p>
            </div>
        </div>
    @endif

    @if($uploadBatchId)
        <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <span>Unggahan masih berlangsung atau ada file gagal. Selesaikan atau batalkan sebelum mengirim pendaftaran.</span>
            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="cancelUploadBatch">Batalkan antrean unggah</button>
            @if($errorMessage && $uploadedFiles)<button type="button" class="btn btn-sm btn-outline-purple" wire:click="retryFailedUpload" wire:loading.attr="disabled">Coba ulang file gagal</button>@endif
        </div>
    @endif
    @if(!$canUpload && !$isArchived && !$isAdminHold)
        <div class="alert alert-info">Berkas pada pendaftaran terkirim dikunci selama pemeriksaan. Pengubahan dibuka saat perbaikan.</div>
    @endif
    @if (filled($policy->general_instructions))
        <section class="workspace-card mb-4 border-start border-4 border-warning" aria-label="Panduan unggah berkas" x-data="{ guideOpen: false }">
            <div class="panel-heading bg-warning-subtle d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 p-sm-4">
                <div class="flex-grow-1">
                    <h2 class="h5 mb-1"><i class="bi bi-info-circle-fill text-purple me-2" aria-hidden="true"></i>Panduan Unggah Berkas</h2>
                    <p class="text-dark mt-2 mb-0 lh-base">Baca petunjuk berikut sebelum memilih dan mengunggah file.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-purple px-3 py-2 flex-shrink-0" @click="guideOpen = true" aria-haspopup="dialog" :aria-expanded="guideOpen.toString()" aria-controls="upload-guide-{{ $workspaceId }}">
                    <i class="bi bi-book me-1" aria-hidden="true"></i>Buka Panduan
                </button>
            </div>
            <div id="upload-guide-{{ $workspaceId }}" x-cloak x-show="guideOpen" x-trap.inert.noscroll="guideOpen" class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="upload-guide-title-{{ $workspaceId }}" @keydown.escape.window="guideOpen = false" @click.self="guideOpen = false">
                <div class="ui-modal">
                    <div class="ui-modal-header">
                        <h2 id="upload-guide-title-{{ $workspaceId }}">Panduan Unggah Berkas</h2>
                        <button type="button" class="btn-close" @click="guideOpen = false" aria-label="Tutup panduan"></button>
                    </div>
                    <div class="ui-modal-body">
                        <div class="text-dark" style="white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.8;font-size:0.95rem">{{ $policy->general_instructions }}</div>
                    </div>
                    <div class="p-3 border-top text-end"><button type="button" class="btn btn-purple" @click="guideOpen = false">Tutup</button></div>
                </div>
            </div>
        </section>
    @endif

    <!-- Upload Section -->
    @if ($canUpload)
        <x-file-upload-queue :rules="$uploadRules" />
    @endif

    <!-- Daftar Berkas Tersimpan -->
    <section class="workspace-card">
        <div class="panel-heading">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">
                <div>
                    <h2 class="h5 mb-1"><i class="bi bi-files text-purple me-2"></i>Berkas yang Telah Diunggah</h2>
                    <p class="text-muted small mb-0">Seluruh dokumen yang tersimpan pada ruang kerja periode ini.</p>
                </div>
            </div>
            <div class="mt-3 mb-3" style="max-width: 420px">
                <label for="document-search-{{ $workspaceId }}" class="form-label small fw-semibold">Cari dokumen</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input id="document-search-{{ $workspaceId }}" type="search" class="form-control" wire:model.live.debounce.300ms="documentSearch" maxlength="255" placeholder="Cari nama berkas" autocomplete="off">
                    @if (trim($documentSearch) !== '')
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('documentSearch', '')">Hapus pencarian</button>
                    @endif
                </div>
                <small class="text-muted" wire:loading.delay wire:target="documentSearch">Mencari dokumen...</small>
            </div>
        </div>
        <div class="panel-body p-0">
            @if ($documents->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-folder2-open display-4 text-muted mb-2 d-block"></i>
                    @if ($hasDocuments && trim($documentSearch) !== '')
                        <h3 class="h6 text-muted mb-1">Tidak ada dokumen yang cocok</h3>
                        <p class="small text-muted mb-0">Coba kata kunci lain atau hapus pencarian.</p>
                    @else
                        <h3 class="h6 text-muted mb-1">Belum ada berkas yang diunggah</h3>
                        <p class="small text-muted mb-0">Silakan gunakan form unggah di atas untuk melengkapi berkas pendaftaran Anda.</p>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-4">Nama Berkas</th>
                                <th scope="col">Ukuran / Format</th>
                                <th scope="col">Pengunggah</th>
                                <th scope="col" class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documents as $doc)
                                <tr wire:key="doc-row-{{ $doc->id }}" data-document-row>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi {{ $doc->iconClass() }} fs-5 flex-shrink-0"></i>
                                            <div class="d-flex align-items-center gap-1">
                                                <div>
                                                    <span class="fw-semibold text-dark">{{ $doc->display_name }}</span><span class="text-muted">.{{ $doc->extension }}</span>
                                                    <small class="d-block text-muted">Diunggah {{ $doc->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</small>
                                                </div>
                                                <button type="button" class="btn btn-link text-purple p-2 flex-shrink-0"
                                                    style="min-width:44px;min-height:44px"
                                                    aria-label="Informasi berkas {{ $doc->file_name }}" title="Informasi berkas"
                                                    @click="fileInfo = @js([
                                                        'name' => $doc->file_name,
                                                        'originalName' => $doc->original_name,
                                                        'size' => $doc->formattedSize(),
                                                        'format' => strtoupper($doc->extension),
                                                        'uploadedAt' => $doc->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
                                                        'uploader' => $doc->uploader?->name ?? 'Pengguna',
                                                    ])">
                                                    <i class="bi bi-info-circle fs-5" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-dark">{{ $doc->formattedSize() }}</div>
                                        <small class="text-muted text-uppercase" style="font-size: 0.72rem;">{{ $doc->extension }}</small>
                                    </td>
                                    <td>
                                        @if ($doc->uploaded_by === $currentUser->id)
                                            <span class="badge text-bg-success">Anda</span>
                                        @else
                                            <span class="small fw-semibold text-secondary d-block">
                                                {{ $doc->uploader?->role->value === 'operator' ? 'Operator: ' : ($doc->uploader?->role->value === 'administrator' ? 'Admin: ' : 'Pendaftar: ') }}
                                                {{ $doc->uploader?->name ?? 'Pengguna' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="doc-actions-group" role="group" aria-label="Aksi berkas {{ $doc->file_name }}">
                                            @if ($doc->isPreviewable())
                                                <a href="{{ $this->previewUrl($doc) }}" target="_blank" rel="noopener noreferrer" class="doc-btn doc-btn-preview" title="{{ $doc->isVideo() ? 'Buka Video (tab baru)' : 'Lihat di tab baru' }}">
                                                    <i class="bi bi-eye"></i><span>{{ $doc->isVideo() ? 'Buka Video' : 'Lihat' }}</span>
                                                </a>
                                            @endif
                                            <a href="{{ $this->downloadUrl($doc) }}" class="doc-btn doc-btn-download" title="Unduh berkas">
                                                <i class="bi bi-download"></i><span>Unduh</span>
                                            </a>
                                            @if ($canModify)
                                                <button type="button" class="doc-btn doc-btn-rename"
                                                    wire:click="startRename('{{ $doc->id }}')" title="Ubah nama tampilan">
                                                    <i class="bi bi-pencil"></i><span>Ubah</span>
                                                </button>
                                                <button type="button" class="doc-btn doc-btn-delete"
                                                    wire:click="confirmDelete('{{ $doc->id }}')" title="Hapus permanen">
                                                    <i class="bi bi-trash3"></i><span>Hapus</span>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <div x-cloak x-show="fileInfo !== null" x-trap.inert.noscroll="fileInfo !== null"
         class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="document-info-title-{{ $workspaceId }}"
         @keydown.escape.window="fileInfo = null" @click.self="fileInfo = null">
        <div class="ui-modal">
            <div class="ui-modal-header">
                <h2 id="document-info-title-{{ $workspaceId }}"><i class="bi bi-info-circle text-purple me-2" aria-hidden="true"></i>Informasi Berkas</h2>
                <button type="button" class="btn-close" @click="fileInfo = null" aria-label="Tutup informasi berkas"></button>
            </div>
            <div class="ui-modal-body">
                <dl class="mb-0" style="overflow-wrap:anywhere">
                    <dt>Nama berkas</dt><dd class="mb-3" x-text="fileInfo?.name"></dd>
                    <dt>Nama asli file</dt><dd class="mb-3" x-text="fileInfo?.originalName"></dd>
                    <dt>Ukuran</dt><dd class="mb-3" x-text="fileInfo?.size"></dd>
                    <dt>Format</dt><dd class="mb-3" x-text="fileInfo?.format"></dd>
                    <dt>Waktu unggah</dt><dd class="mb-3" x-text="fileInfo?.uploadedAt"></dd>
                    <dt>Pengunggah</dt><dd class="mb-0" x-text="fileInfo?.uploader"></dd>
                </dl>
            </div>
            <div class="d-flex justify-content-end border-top p-3">
                <button type="button" class="btn btn-purple" @click="fileInfo = null">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Dialog: Ubah Nama -->
    @if ($renamingDocumentId !== null)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow">
                    <div class="modal-header border-bottom">
                        <h5 class="modal-title h6 fw-bold"><i class="bi bi-pencil-square text-purple me-2"></i>Ubah Nama Berkas</h5>
                        <button type="button" class="btn-close" wire:click="cancelRename" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <label for="rename-input" class="form-label small fw-semibold">Nama Baru (Tanpa Ekstensi)</label>
                        <div class="input-group">
                            <input type="text" id="rename-input" class="form-control @error('renameNewName') is-invalid @enderror"
                                wire:model="renameNewName" maxlength="180" autofocus required>
                            <span class="input-group-text bg-light text-muted">.{{ $renameExtension }}</span>
                        </div>
                        @error('renameNewName')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                        <div class="form-text small mt-2">
                            Ekstensi tidak dapat diubah untuk menjamin integritas file. Nama maksimal 180 karakter.
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-light border" wire:click="cancelRename">Batal</button>
                        <button type="button" class="btn btn-purple px-4" wire:click="saveRename">Simpan Perubahan</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Dialog: Hapus Permanen -->
    @if ($deletingDocumentId !== null)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-danger shadow">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title h6 fw-bold mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Berkas</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="cancelDelete" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Anda akan menghapus berkas:</p>
                        <div class="p-2 border rounded bg-light fw-bold text-dark mb-3 text-truncate">
                            <i class="bi bi-file-earmark-text text-danger me-2"></i>{{ $deletingDocumentName }}
                        </div>
                        <div class="alert alert-danger py-2 small mb-0 fw-semibold">
                            “Berkas akan dihapus permanen dan tidak dapat dipulihkan melalui aplikasi.”
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-light border" wire:click="cancelDelete">Batal</button>
                        <button type="button" class="btn btn-danger px-4" wire:click="executeDelete">
                            <i class="bi bi-trash3-fill me-1"></i>Hapus Permanen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

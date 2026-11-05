<div>
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
    @if(!$canModify && !$isArchived && !$isAdminHold)
        <div class="alert alert-info">Berkas pada pendaftaran terkirim dikunci selama pemeriksaan. Pengubahan dibuka saat perbaikan.</div>
    @endif
    <!-- Upload Section -->
    @if ($canModify)
        <section class="workspace-card mb-4"
            x-data="{
                isDragOver: false,
                isUploading: false,
                uploadProgress: 0,
                async startUpload(files) {
                    if (!files?.length || this.isUploading) return;
                    this.isUploading = true;
                    this.$dispatch('registration-uploading', { busy: true });
                    try {
                        await this.$wire.beginUploadBatch();
                        this.$wire.$uploadMultiple('uploadedFiles', [...files],
                            () => { this.isUploading = false; this.uploadProgress = 100; this.$dispatch('registration-uploading', { busy: false }); this.$dispatch('registration-documents-changed'); },
                            () => { this.isUploading = false; this.$dispatch('registration-uploading', { busy: false }); },
                            event => { this.uploadProgress = event.detail.progress; });
                    } catch (error) {
                        this.isUploading = false;
                        this.$dispatch('registration-uploading', { busy: false });
                    }
                },
                handleDrop(event) {
                    const files = event.dataTransfer?.files;
                    this.isDragOver = false;
                    if (!files?.length) return;
                    this.$refs.fileInput.files = files;
                    this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }"
            x-on:livewire-upload-start="isUploading = true; uploadProgress = 0"
            x-on:livewire-upload-progress="uploadProgress = $event.detail.progress"
            x-on:livewire-upload-finish="isUploading = false; uploadProgress = 100"
            x-on:livewire-upload-error="isUploading = false">
            <div class="panel-heading">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">
                    <div>
                        <h2 class="h5 mb-1"><i class="bi bi-cloud-arrow-up text-purple me-2"></i>Unggah Berkas</h2>
                        <p class="text-muted small mb-0">Seret atau pilih satu atau beberapa berkas sekaligus dari perangkat Anda.</p>
                    </div>
                    <span class="badge text-bg-light border text-secondary px-3 py-2">
                        Total Berkas: <strong class="text-purple">{{ $documents->count() }}</strong>
                    </span>
                </div>
            </div>
            <div class="panel-body">
                @if ($policy->general_instructions)
                    <div class="alert alert-light border small text-muted mb-3">
                        <i class="bi bi-info-circle me-1 text-purple"></i>{{ $policy->general_instructions }}
                    </div>
                @endif

                <div class="p-2 border rounded-3 bg-light mb-3">
                    <div class="small text-muted mb-1">
                        <strong>Ketentuan Berkas:</strong> Format didukung: <span class="fw-semibold text-dark">{{ $policy->allowedExtensionLabels() }}</span>
                    </div>
                    <div class="small text-muted">
                        Maksimal <strong class="text-dark">{{ $policy->formattedMaxFileSize() }}</strong> per berkas
                        @if ($quotaBytes !== null)
                            · Kuota Total: <strong>{{ $policy->formattedWorkspaceQuota() }}</strong>
                            (Tersisa: <strong class="text-purple">{{ round($remainingBytes / (1024 * 1024), 1) }} MB</strong>)
                        @else
                            · Kapasitas total tidak dibatasi aplikasi
                        @endif
                    </div>
                </div>

                <!-- Dropzone Area -->
                <div class="border border-2 border-dashed rounded-3 p-4 text-center cursor-pointer mb-3 position-relative transition-all"
                    style="border-color: #c4b5fd !important; background-color: #faf5ff;"
                    x-bind:class="{ 'border-primary bg-white shadow-sm': isDragOver, 'opacity-50': isUploading }"
                    x-on:click="if (!isUploading) $refs.fileInput.click()"
                    x-on:dragover.prevent="if (!isUploading) isDragOver = true"
                    x-on:dragleave.prevent="isDragOver = false"
                    x-on:drop.prevent="if (!isUploading) handleDrop($event)">
                    <i class="bi bi-cloud-arrow-up-fill text-purple display-5 d-block mb-2"></i>
                    <p class="fw-semibold mb-1 text-dark fs-6">Klik untuk memilih berkas atau seret ke sini</p>
                    <p class="text-purple small mb-1 fw-medium">
                        <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Berkas yang dipilih atau di-drop akan langsung terunggah otomatis
                    </p>
                    <p class="text-muted small mb-0" style="font-size: 0.75rem;">
                        Format yang didukung: {{ $policy->allowedExtensionLabels() }} · Maks {{ $policy->formattedMaxFileSize() }} per berkas
                    </p>
                </div>

                <input type="file" id="fileInput" x-ref="fileInput" class="d-none" multiple @change="startUpload($event.target.files)"
                    accept="{{ collect($policy->allowedExtensions())->map(fn($ext) => '.' . $ext)->implode(',') }}"
                    x-bind:disabled="isUploading">

                @error('uploadedFiles')
                    <div class="alert alert-danger py-2 small mb-3">{{ $message }}</div>
                @enderror
                @error('uploadedFiles.*')
                    <div class="alert alert-danger py-2 small mb-3">{{ $message }}</div>
                @enderror

                <!-- Progress Bar -->
                <div x-cloak x-show="isUploading" class="mb-3">
                    <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                        <span>Mengirim berkas ke server...</span>
                        <span class="fw-bold text-purple" x-text="`${uploadProgress}%`"></span>
                    </div>
                    <div class="progress" style="height: 10px; border-radius: 999px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-purple"
                            role="progressbar" x-bind:style="`width: ${uploadProgress}%`"></div>
                    </div>
                </div>

                <!-- Indikator Proses Penyimpanan Livewire -->
                <div wire:loading wire:target="uploadedFiles" class="alert alert-light border border-purple-subtle py-2 small mb-3">
                    <div class="d-flex align-items-center gap-2 text-purple">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <span class="fw-medium">Memproses dan menyimpan berkas ke workspace Anda...</span>
                    </div>
                </div>
            </div>
        </section>
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
        </div>
        <div class="panel-body p-0">
            @if ($documents->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-folder2-open display-4 text-muted mb-2 d-block"></i>
                    <h3 class="h6 text-muted mb-1">Belum ada berkas yang diunggah</h3>
                    <p class="small text-muted mb-0">Silakan gunakan form unggah di atas untuk melengkapi berkas pendaftaran Anda.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-4">Nama Berkas</th>
                                <th scope="col">Ukuran / Format</th>
                                <th scope="col">Waktu Unggah</th>
                                <th scope="col">Pengunggah</th>
                                <th scope="col" class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documents as $doc)
                                <tr wire:key="doc-row-{{ $doc->id }}">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi {{ $doc->iconClass() }} fs-5 flex-shrink-0"></i>
                                            <div>
                                                <span class="fw-semibold text-dark">{{ $doc->display_name }}</span><span class="text-muted">.{{ $doc->extension }}</span>
                                                <small class="text-muted d-block" style="font-size: 0.72rem;">Asli: {{ $doc->original_name }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-dark">{{ $doc->formattedSize() }}</div>
                                        <small class="text-muted text-uppercase" style="font-size: 0.72rem;">{{ $doc->extension }}</small>
                                    </td>
                                    <td>
                                        <div class="small">{{ $doc->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</div>
                                        <small class="text-muted">{{ $doc->created_at->timezone('Asia/Jakarta')->format('H:i') }} WIB</small>
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
                                                <a href="{{ $this->previewUrl($doc) }}" target="_blank" class="doc-btn doc-btn-preview" title="Lihat di tab baru">
                                                    <i class="bi bi-eye"></i><span>Lihat</span>
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
                                                    <i class="bi bi-trash3"></i><span>Hapus / Lepas</span>
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
                        <h5 class="modal-title h6 fw-bold mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus / Lepas Berkas</h5>
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
                            <i class="bi bi-trash3-fill me-1"></i>Hapus / Lepas Berkas
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

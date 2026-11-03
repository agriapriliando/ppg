<x-layouts::app title="Detail Peserta SK">
    <div x-data="{ editSkModal: false }">
        <div class="participant-heading mb-4">
            <div>
                <h2>{{ $participant->name_on_sk }}</h2>
                <p>Data resmi kepesertaan bersumber dari SK penerimaan mahasiswa baru.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a class="btn btn-outline-purple" href="{{ route('participants.index') }}">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Peserta
                </a>
                @if($participant->application)
                    <a class="btn btn-outline-purple" href="{{ route('reviews.documents', $participant->application) }}">
                        <i class="bi bi-folder2-open me-1"></i>Ruang Pemeriksaan Berkas
                    </a>
                @endif
            </div>
        </div>

        <!-- Informasi Resmi SK -->
        <section class="workspace-card mb-4">
            <div class="panel-heading d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h2 class="h5 mb-1"><i class="bi bi-award text-purple me-2"></i>Informasi Resmi SK</h2>
                    <p class="text-muted small mb-0">Rincian data penetapan kepesertaan berdasarkan SK resmi.</p>
                </div>
                @if(auth()->user()->isAdministrator() || auth()->user()->role === \App\Enums\UserRole::Operator)
                    <button type="button" @click="editSkModal = true" class="btn btn-sm btn-purple d-inline-flex align-items-center gap-1">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                        <span>Edit Data SK</span>
                    </button>
                @endif
            </div>
        <div class="panel-body">
            <dl class="detail-list">
                <dt>Nama Sesuai SK</dt>
                <dd class="fw-bold text-dark fs-6">{{ $participant->name_on_sk }}</dd>

                <dt>Nomor SK & Tanggal</dt>
                <dd>{{ $participant->sk_number }} · {{ $participant->sk_date->format('d/m/Y') }}</dd>

                <dt>Nomor Urut SK</dt>
                <dd>{{ $participant->sk_order }}</dd>

                <dt>Pengenal Resmi SK</dt>
                <dd>{{ $participant->official_participant_id ?? 'Tidak tersedia' }}</dd>

                <dt>Periode Penerimaan</dt>
                <dd>{{ $participant->period->name }}</dd>

                <dt>Operator Penanggung Jawab</dt>
                <dd>
                    @if($participant->assignedOperator)
                        <span class="badge text-bg-light border text-purple fs-6">{{ $participant->assignedOperator->name }}</span>
                    @else
                        <span class="text-muted">Belum ditugaskan</span>
                    @endif
                </dd>

                <dt>Status Daftar Resmi</dt>
                <dd>
                    @if($participant->roster_state === 'active')
                        <span class="status-pill diterima_lengkap">Aktif</span>
                    @else
                        <span class="status-pill declined">Ditarik dari Kepesertaan</span>
                    @endif
                </dd>

                <dt>Status Akun Portal</dt>
                <dd>
                    @if($participant->claimedUser)
                        @if($participant->claimedUser->account_state === App\Enums\AccountState::Disabled)
                            <span class="status-pill declined">Akun Dinonaktifkan</span>
                        @elseif($participant->claimedUser->account_state === App\Enums\AccountState::PendingActivation)
                            <span class="status-pill perbaikan">Menunggu Aktivasi</span>
                        @else
                            <span class="status-pill diterima_lengkap">Akun Terhubung</span>
                        @endif
                        <span class="text-muted ms-2">({{ $participant->claimedUser->name }} · {{ $participant->claimedUser->login_id }})</span>
                    @else
                        <span class="status-pill draf">Belum Mengklaim Akun</span>
                    @endif
                </dd>

                <dt>Status Antrean Pendaftaran</dt>
                <dd>
                    @if($participant->application)
                        <span class="status-pill {{ $participant->application->status->value }}">
                            {{ $participant->application->status->label() }}
                        </span>
                        <span class="text-muted ms-2">({{ $participant->application->application_number }})</span>
                    @else
                        <span class="status-pill draf">Belum Masuk Antrean Pendaftaran</span>
                    @endif
                </dd>
            </dl>
        </div>
    </section>

    <!-- Biodata Calon Mahasiswa -->
    <section class="workspace-card mb-4">
        <div class="panel-heading">
            <h2 class="h5 mb-1"><i class="bi bi-person-vcard text-purple me-2"></i>Biodata Calon Mahasiswa</h2>
            <p class="text-muted small mb-0">Rincian data identitas diri, alamat domisili/KTP, dan pendidikan asal.</p>
        </div>
        <div class="panel-body">
            @if($biodata)
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h3 class="h6 fw-bold text-purple mb-3 border-bottom pb-2">
                            <i class="bi bi-person me-1"></i>Identitas Diri
                        </h3>
                        <dl class="detail-list">
                            <dt>Nama Lengkap (dengan Gelar)</dt>
                            <dd class="fw-bold text-dark fs-6">{{ $biodata->full_name_with_title }}</dd>

                            <dt>NIK (Nomor Induk Kependudukan)</dt>
                            <dd><code>{{ $biodata->nik }}</code></dd>

                            <dt>Jenis Kelamin</dt>
                            <dd>{{ $biodata->gender?->label() ?? '-' }}</dd>

                            <dt>Tempat & Tanggal Lahir</dt>
                            <dd>{{ $biodata->birth_place ?? '-' }}, {{ $biodata->birth_date ? $biodata->birth_date->format('d/m/Y') : '-' }}</dd>

                            <dt>Nama Ibu Kandung</dt>
                            <dd>{{ $biodata->mother_maiden_name ?? '-' }}</dd>

                            <dt>Agama</dt>
                            <dd>{{ $biodata->religion?->label() ?? '-' }}</dd>

                            <dt>Nomor WhatsApp Aktif</dt>
                            <dd>
                                @if($biodata->whatsapp_number)
                                    <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $biodata->whatsapp_number)) }}" target="_blank" rel="noopener noreferrer" class="text-success fw-semibold text-decoration-none">
                                        <i class="bi bi-whatsapp me-1"></i>{{ $biodata->whatsapp_number }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </dd>
                        </dl>
                    </div>

                    <div class="col-lg-6">
                        <h3 class="h6 fw-bold text-purple mb-3 border-bottom pb-2">
                            <i class="bi bi-geo-alt me-1"></i>Alamat Sesuai KTP
                        </h3>
                        <dl class="detail-list">
                            <dt>Alamat Lengkap</dt>
                            <dd>{{ $biodata->full_address ?? '-' }}</dd>

                            <dt>RT / RW</dt>
                            <dd>{{ $biodata->rt ?? '-' }} / {{ $biodata->rw ?? '-' }}</dd>

                            <dt>Desa / Kelurahan</dt>
                            <dd>{{ $biodata->village ?? '-' }}</dd>

                            <dt>Kecamatan</dt>
                            <dd>{{ $biodata->district ?? '-' }}</dd>

                            <dt>Kabupaten / Kota</dt>
                            <dd>{{ $biodata->city ?? '-' }}</dd>

                            <dt>Provinsi</dt>
                            <dd>{{ $biodata->province ?? '-' }}</dd>

                            <dt>Kode Pos</dt>
                            <dd>{{ $biodata->postal_code ?? '-' }}</dd>
                        </dl>
                    </div>
                </div>

                <h3 class="h6 fw-bold text-purple mt-4 mb-3 border-bottom pb-2">
                    <i class="bi bi-mortarboard me-1"></i>Pendidikan Asal S1 / D-IV
                </h3>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <dl class="detail-list">
                            <dt>Jenjang Pendidikan</dt>
                            <dd>{{ $education?->degree_level ?? '-' }}</dd>

                            <dt>Perguruan Tinggi Asal</dt>
                            <dd class="fw-semibold text-dark">{{ $education?->institution_name ?? '-' }}</dd>

                            <dt>Program Studi Asal</dt>
                            <dd>{{ $education?->study_program_name ?? '-' }}</dd>

                            <dt>Nomor Induk Mahasiswa (NIM)</dt>
                            <dd><code>{{ $education?->student_id_number ?? '-' }}</code></dd>
                        </dl>
                    </div>
                    <div class="col-lg-6">
                        <dl class="detail-list">
                            <dt>Tahun Masuk / Tahun Lulus</dt>
                            <dd>{{ $education?->entry_year ?? '-' }} / {{ $education?->graduation_year ?? '-' }}</dd>

                            <dt>Indeks Prestasi Kumulatif (IPK)</dt>
                            <dd class="fw-bold text-purple">{{ $education?->gpa ?? '-' }}</dd>

                            <dt>Nomor Ijazah</dt>
                            <dd><code>{{ $education?->diploma_number ?? '-' }}</code></dd>
                        </dl>
                    </div>
                </div>
            @else
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-person-x display-6 d-block mb-2 text-muted"></i>
                    <p class="mb-0">Peserta belum mengisi atau menyimpan biodata pendaftaran.</p>
                </div>
            @endif
        </div>
    </section>

    <!-- Berkas yang Diunggah -->
    <section class="workspace-card mb-4">
        <div class="panel-heading d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h5 mb-1"><i class="bi bi-files text-purple me-2"></i>Berkas yang Diunggah</h2>
                <p class="text-muted small mb-0">Dokumen persyaratan pendaftaran yang telah diunggah oleh peserta.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($documents->isNotEmpty())
                    <span class="badge text-bg-light border text-purple px-3 py-2 fw-bold">Total: {{ $documents->count() }} Berkas</span>
                @endif
                @if($workspace)
                    <a href="{{ route('admin.workspaces.documents.show', $workspace->id) }}" class="btn btn-sm btn-outline-purple">
                        <i class="bi bi-folder2-open me-1"></i>Workspace Berkas
                    </a>
                @endif
            </div>
        </div>
        <div class="panel-body p-0">
            @if($documents->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-folder2-open display-5 text-muted mb-2 d-block"></i>
                    <h3 class="h6 text-muted mb-1">Belum ada berkas yang diunggah</h3>
                    <p class="small text-muted mb-0">Peserta belum mengunggah dokumen persyaratan ke dalam ruang kerja.</p>
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
                            @foreach($documents as $doc)
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi {{ $doc->iconClass() }} fs-5 flex-shrink-0 text-purple"></i>
                                            <div>
                                                <strong class="text-dark">{{ $doc->display_name }}</strong><span class="text-muted">.{{ $doc->extension }}</span>
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
                                        @if($participant->claimed_user_id && $doc->uploaded_by === $participant->claimed_user_id)
                                            <span class="badge text-bg-success">Pendaftar</span>
                                        @else
                                            <span class="small fw-semibold text-secondary">
                                                {{ $doc->uploader?->role->value === 'operator' ? 'Operator: ' : ($doc->uploader?->role->value === 'administrator' ? 'Admin: ' : '') }}
                                                {{ $doc->uploader?->name ?? 'Petugas' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="doc-actions-group" role="group" aria-label="Aksi berkas {{ $doc->file_name }}">
                                            @if($doc->isPreviewable())
                                                <a href="{{ route('admin.workspaces.documents.preview', ['workspace' => $doc->workspace_id, 'document' => $doc->id]) }}" target="_blank" class="doc-btn doc-btn-preview" title="Lihat di tab baru">
                                                    <i class="bi bi-eye"></i><span>Lihat</span>
                                                </a>
                                            @endif
                                            <a href="{{ route('admin.workspaces.documents.download', ['workspace' => $doc->workspace_id, 'document' => $doc->id]) }}" class="doc-btn doc-btn-download" title="Unduh berkas">
                                                <i class="bi bi-download"></i><span>Unduh</span>
                                            </a>
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

    <!-- Pindahkan Penugasan -->
    @if(auth()->user()->isAdministrator() || auth()->user()->role === \App\Enums\UserRole::Operator)
        <section class="workspace-card mb-4" x-data="{ openTransfer: false }">
            <div class="panel-heading d-flex justify-content-between align-items-center flex-wrap gap-2" :class="{ 'pb-4': !openTransfer }">
                <div>
                    <h2>Pindahkan penugasan</h2>
                    <p>Operator tujuan harus aktif dan terdaftar pada periode.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-purple d-flex align-items-center gap-1" @click="openTransfer = !openTransfer" :aria-expanded="openTransfer">
                    <span x-text="openTransfer ? 'Tutup Formulir' : 'Buka Formulir'">Buka Formulir</span>
                    <i class="bi" :class="openTransfer ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </button>
            </div>
            <div class="panel-body border-top" x-show="openTransfer" x-cloak x-transition>
                <form method="post" action="{{ route('admin.assignments.transfer', $participant) }}">
                    @csrf
                    <input type="hidden" name="version" value="{{ $participant->lock_version }}">
                    <div class="mb-3">
                        <label for="transfer-operator" class="form-label">Operator tujuan</label>
                        <select id="transfer-operator" class="form-select" name="operator_id" required>
                            <option value="">Pilih operator</option>
                            @foreach($operators as $operator)
                                <option value="{{ $operator->id }}" @disabled($operator->id === $participant->assigned_operator_id)>{{ $operator->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="transfer-reason" class="form-label">Alasan perpindahan *</label>
                        <textarea class="form-control" id="transfer-reason" name="reason" required minlength="10" maxlength="1000" rows="3"></textarea>
                    </div>
                    <button class="btn btn-outline-purple">Konfirmasi Perpindahan</button>
                </form>
            </div>
        </section>
    @endif

    <!-- Kondisi Administratif Kepesertaan -->
    @if(auth()->user()->isAdministrator())
        <section class="workspace-card mt-4">
            <div class="panel-heading">
                <h2>Kondisi administratif kepesertaan</h2>
                <p>Penarikan atau penahanan tidak menghapus riwayat aplikasi.</p>
            </div>
            <div class="panel-body">
                <form method="post" action="{{ route('admin.participants.condition', $participant) }}">
                    @csrf
                    <input type="hidden" name="version" value="{{ $participant->lock_version }}">
                    <label for="roster-state" class="form-label">Daftar resmi</label>
                    <select id="roster-state" name="roster_state" class="form-select mb-3">
                        <option value="active" @selected($participant->roster_state === 'active')>Aktif</option>
                        <option value="withdrawn" @selected($participant->roster_state === 'withdrawn')>Ditarik dari kepesertaan</option>
                    </select>
                    <label for="admin-hold" class="form-label">Pemeriksaan administratif</label>
                    <select id="admin-hold" name="admin_hold" class="form-select mb-3">
                        <option value="0" @selected(!$participant->admin_hold)>Tidak ditahan</option>
                        <option value="1" @selected($participant->admin_hold)>Tahan pengiriman dan keputusan lengkap</option>
                    </select>
                    <label for="condition-reason" class="form-label">Dasar resmi / alasan *</label>
                    <textarea id="condition-reason" name="reason" class="form-control mb-3" required minlength="20" maxlength="1000" rows="3"></textarea>
                    <button class="btn btn-outline-purple">Simpan Kondisi Kepesertaan</button>
                </form>
            </div>
        </section>
    @endif

    <!-- Modal Edit Data Resmi SK -->
    <div 
        class="ui-modal-backdrop"
        x-show="editSkModal"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-edit-sk-title"
    >
        <div 
            class="ui-modal"
            style="max-width: 650px;"
            x-show="editSkModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.outside="editSkModal = false"
        >
            <div class="ui-modal-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="dayak-shield-badge" style="width: 32px; height: 32px; font-size: 0.85rem;">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                    </span>
                    <h2 id="modal-edit-sk-title" class="h6 mb-0 fw-bold text-purple">Edit Data Resmi Daftar SK</h2>
                </div>
                <button type="button" class="btn-close" @click="editSkModal = false" aria-label="Tutup"></button>
            </div>

            <form method="post" action="{{ route('participants.update-sk', $participant) }}">
                @csrf
                @method('put')
                <input type="hidden" name="version" value="{{ $participant->lock_version }}">

                <div class="ui-modal-body">
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Perubahan data resmi SK akan dicatat pada riwayat audit sistem.
                    </div>

                    <div class="mb-3">
                        <label for="edit-name-on-sk" class="form-label fw-bold">Nama Sesuai SK *</label>
                        <input type="text" id="edit-name-on-sk" name="name_on_sk" class="form-control" value="{{ old('name_on_sk', $participant->name_on_sk) }}" required minlength="3" maxlength="255">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="edit-sk-number" class="form-label fw-bold">Nomor SK *</label>
                            <input type="text" id="edit-sk-number" name="sk_number" class="form-control" value="{{ old('sk_number', $participant->sk_number) }}" required maxlength="100">
                        </div>
                        <div class="col-md-5">
                            <label for="edit-sk-date" class="form-label fw-bold">Tanggal SK *</label>
                            <input type="date" id="edit-sk-date" name="sk_date" class="form-control" value="{{ old('sk_date', $participant->sk_date->format('Y-m-d')) }}" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="edit-sk-order" class="form-label fw-bold">Nomor Urut SK *</label>
                            <input type="number" id="edit-sk-order" name="sk_order" class="form-control" value="{{ old('sk_order', $participant->sk_order) }}" required min="1" max="999999">
                        </div>
                        <div class="col-md-7">
                            <label for="edit-official-id" class="form-label fw-bold">Pengenal Resmi SK / No. Peserta</label>
                            <input type="text" id="edit-official-id" name="official_participant_id" class="form-control" value="{{ old('official_participant_id', $participant->official_participant_id) }}" maxlength="100" placeholder="Contoh: 30200325189001">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit-operator-id" class="form-label fw-bold">Operator Penanggung Jawab</label>
                        <select id="edit-operator-id" name="assigned_operator_id" class="form-select">
                            <option value="">-- Belum Ditugaskan --</option>
                            @foreach($operators as $op)
                                <option value="{{ $op->id }}" @selected(old('assigned_operator_id', $participant->assigned_operator_id) == $op->id)>
                                    {{ $op->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="edit-reason" class="form-label fw-bold">Alasan Perubahan Data *</label>
                        <textarea id="edit-reason" name="reason" class="form-control" rows="2" required minlength="5" maxlength="1000" placeholder="Jelaskan alasan atau dasar pembaruan data resmi SK ini...">{{ old('reason') }}</textarea>
                    </div>
                </div>

                <div class="modal-actions border-top p-3 bg-light m-0 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" @click="editSkModal = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-purple">
                        <i class="bi bi-check2-circle me-1"></i>Simpan Perubahan SK
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</x-layouts::app>

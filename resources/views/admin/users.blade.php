<x-layouts::app title="Kelola Pengguna">
    <div x-data="{
        showCreateModal: false,
        showEditModal: false,
        showDeleteModal: false,
        editUser: {
            id: null,
            name: '',
            login_id: '',
            email: '',
            role: 'operator'
        },
        deleteUser: {
            id: null,
            name: '',
            login_id: '',
            role: ''
        },
        openEdit(user) {
            this.editUser = { ...user };
            this.showEditModal = true;
        },
        openDelete(user) {
            this.deleteUser = { ...user };
            this.showDeleteModal = true;
        }
    }">
        <section class="workspace-card mb-4">
            <div class="panel-heading d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h2>Pengguna Pengelola</h2>
                    <p class="mb-0">Kelola akun administrator dan operator yang memiliki akses dashboard sistem.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.users.import') }}" class="btn btn-outline-purple d-inline-flex align-items-center gap-2"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i>Impor Excel</a>
                <button type="button" class="btn btn-purple d-inline-flex align-items-center gap-2" @click="showCreateModal = true">
                    <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
                    <span>Tambah Pengelola</span>
                </button>
                </div>
            </div>

            <!-- Filter dan Pencarian -->
            <div class="p-3 border-bottom bg-light">
                <form method="get" action="{{ route('admin.users') }}" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Cari nama, ID login, atau email..." value="{{ $search }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Semua Peran</option>
                            <option value="administrator" @selected($role === 'administrator')>Administrator</option>
                            <option value="operator" @selected($role === 'operator')>Operator</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="active" @selected($status === 'active')>Aktif</option>
                            <option value="disabled" @selected($status === 'disabled')>Dinonaktifkan</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-purple w-100">Filter</button>
                        @if($search || $role || $status)
                            <a href="{{ route('admin.users') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table portal-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Pengguna</th>
                            <th>Peran</th>
                            <th>Status Akses</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="dayak-shield-badge" style="width: 36px; height: 36px; font-size: 0.9rem; background: {{ $user->role === App\Enums\UserRole::Administrator ? '#5c2d91' : '#2b579a' }}; color: #fff;">
                                            <i class="bi {{ $user->role === App\Enums\UserRole::Administrator ? 'bi-shield-lock-fill' : 'bi-person-badge-fill' }}" aria-hidden="true"></i>
                                        </span>
                                        <div>
                                            <strong class="text-dark d-block">{{ $user->name }}</strong>
                                            <small class="text-muted">
                                                <code>{{ $user->login_id }}</code>
                                                @if($user->email)
                                                    · {{ $user->email }}
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($user->role === App\Enums\UserRole::Administrator)
                                        <span class="badge bg-purple-subtle text-purple border border-purple-subtle px-2 py-1">
                                            <i class="bi bi-shield-lock me-1"></i>Administrator
                                        </span>
                                    @else
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                            <i class="bi bi-person-gear me-1"></i>Operator
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($user->account_state === App\Enums\AccountState::Active)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i>Aktif
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                            <i class="bi bi-dash-circle-fill me-1"></i>Dinonaktifkan
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($user->id === auth()->id())
                                        <span class="badge bg-light text-muted border px-2 py-1">
                                            <i class="bi bi-person-fill me-1"></i>Akun Anda
                                        </span>
                                    @else
                                        <div class="d-inline-flex align-items-center justify-content-end gap-2 flex-wrap">
                                            <!-- Tombol Edit -->
                                            <button 
                                                type="button" 
                                                class="btn btn-sm btn-outline-purple d-inline-flex align-items-center gap-1"
                                                style="padding: 0.25rem 0.65rem; font-size: 0.8125rem; border-width: 1px;"
                                                title="Edit Pengguna"
                                                @click="openEdit({
                                                    id: {{ $user->id }},
                                                    name: '{{ addslashes($user->name) }}',
                                                    login_id: '{{ addslashes($user->login_id) }}',
                                                    email: '{{ addslashes($user->email ?? '') }}',
                                                    role: '{{ $user->role->value }}'
                                                })"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                                <span>Edit</span>
                                            </button>

                                            <!-- Tombol Toggle Status (Aktif/Nonaktif) -->
                                            <form method="post" action="{{ route('admin.users.toggle', $user) }}" class="d-inline m-0">
                                                @csrf
                                                @method('patch')
                                                <input type="hidden" name="expected_state" value="{{ $user->account_state->value }}">
                                                @if($user->account_state === App\Enums\AccountState::Active)
                                                    <button 
                                                        type="submit" 
                                                        class="btn btn-sm btn-outline-warning d-inline-flex align-items-center gap-1"
                                                        style="padding: 0.25rem 0.65rem; font-size: 0.8125rem; border-color: #f59e0b; color: #b45309;"
                                                        title="Nonaktifkan Akses"
                                                        onclick="return confirm('Apakah Anda yakin ingin menonaktifkan akses {{ $user->name }}?')"
                                                    >
                                                        <i class="bi bi-person-x"></i>
                                                        <span>Nonaktifkan</span>
                                                    </button>
                                                @else
                                                    <button 
                                                        type="submit" 
                                                        class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1"
                                                        style="padding: 0.25rem 0.65rem; font-size: 0.8125rem;"
                                                        title="Aktifkan Akses"
                                                        onclick="return confirm('Aktifkan kembali akses {{ $user->name }}?')"
                                                    >
                                                        <i class="bi bi-person-check"></i>
                                                        <span>Aktifkan</span>
                                                    </button>
                                                @endif
                                            </form>

                                            <!-- Tombol Hapus Pengguna -->
                                            <button 
                                                type="button" 
                                                class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1"
                                                style="padding: 0.25rem 0.65rem; font-size: 0.8125rem;"
                                                title="Hapus Pengguna"
                                                @click="openDelete({
                                                    id: {{ $user->id }},
                                                    name: '{{ addslashes($user->name) }}',
                                                    login_id: '{{ addslashes($user->login_id) }}',
                                                    role: '{{ $user->role->label() }}'
                                                })"
                                            >
                                                <i class="bi bi-trash"></i>
                                                <span>Hapus</span>
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="bi bi-people fs-2 d-block mb-2 text-muted"></i>
                                    Tidak ada data pengguna pengelola yang sesuai dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <div class="panel-body border-top">
                    {{ $users->links() }}
                </div>
            @endif
        </section>

        <!-- ========================================== -->
        <!-- MODAL POP UP: TAMBAH PENGELOLA             -->
        <!-- ========================================== -->
        <div 
            x-cloak 
            x-show="showCreateModal" 
            class="ui-modal-backdrop" 
            @keydown.escape.window="showCreateModal = false" 
            @click.self="showCreateModal = false"
            style="display: none;"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-create-title"
        >
            <div 
                class="ui-modal"
                x-show="showCreateModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                style="max-width: 560px;"
            >
                <div class="ui-modal-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="dayak-shield-badge" style="width: 32px; height: 32px; font-size: 0.85rem; background: #5c2d91; color: #fff;">
                            <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
                        </span>
                        <h2 id="modal-create-title" class="h6 mb-0 fw-bold text-dark">Tambah Pengelola Baru</h2>
                    </div>
                    <button type="button" class="btn-close" @click="showCreateModal = false" aria-label="Tutup"></button>
                </div>

                <form method="post" action="{{ route('admin.users.store') }}">
                    @csrf
                    <div class="ui-modal-body">
                        <div class="mb-3">
                            <label for="create-name" class="form-label fw-semibold">Nama Lengkap *</label>
                            <input id="create-name" name="name" type="text" class="form-control" required maxlength="255" placeholder="Contoh: Budi Santoso, M.Pd">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="create-login" class="form-label fw-semibold">ID Login *</label>
                                <input id="create-login" name="login_id" type="text" class="form-control" required maxlength="64" placeholder="Contoh: operator1" pattern="^[a-z0-9._-]+$">
                                <div class="form-text small">Huruf kecil, angka, titik, strip.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="create-role" class="form-label fw-semibold">Peran Akses *</label>
                                <select id="create-role" name="role" class="form-select" required>
                                    <option value="operator">Operator Periode</option>
                                    <option value="administrator">Administrator</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="create-email" class="form-label fw-semibold">Alamat Email (Opsional)</label>
                            <input id="create-email" name="email" type="email" class="form-control" maxlength="255" placeholder="nama@iaknpky.ac.id">
                        </div>

                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label for="create-password" class="form-label fw-semibold">Kata Sandi Awal *</label>
                                <x-password-input id="create-password" name="password" required autocomplete="new-password" />
                                <div class="form-text small">Tanpa batas minimal karakter.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="create-confirm" class="form-label fw-semibold">Ulangi Sandi *</label>
                                <x-password-input id="create-confirm" name="password_confirmation" required autocomplete="new-password" />
                            </div>
                        </div>
                    </div>

                    <div class="modal-actions d-flex align-items-center justify-content-end gap-2 p-3 bg-light border-top">
                        <button type="button" class="btn btn-light border px-3" @click="showCreateModal = false">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-purple px-4 fw-semibold d-inline-flex align-items-center gap-2">
                            <i class="bi bi-check-circle" aria-hidden="true"></i>
                            <span>Buat Akun Pengelola</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL POP UP: EDIT PENGELOLA               -->
        <!-- ========================================== -->
        <div 
            x-cloak 
            x-show="showEditModal" 
            class="ui-modal-backdrop" 
            @keydown.escape.window="showEditModal = false" 
            @click.self="showEditModal = false"
            style="display: none;"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-edit-title"
        >
            <div 
                class="ui-modal"
                x-show="showEditModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                style="max-width: 560px;"
            >
                <div class="ui-modal-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="dayak-shield-badge" style="width: 32px; height: 32px; font-size: 0.85rem; background: #2b579a; color: #fff;">
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                        </span>
                        <h2 id="modal-edit-title" class="h6 mb-0 fw-bold text-dark">Edit Data Pengelola</h2>
                    </div>
                    <button type="button" class="btn-close" @click="showEditModal = false" aria-label="Tutup"></button>
                </div>

                <form method="post" :action="'/admin/pengguna/' + editUser.id">
                    @csrf
                    @method('put')
                    <div class="ui-modal-body">
                        <div class="mb-3">
                            <label for="edit-name" class="form-label fw-semibold">Nama Lengkap *</label>
                            <input id="edit-name" name="name" type="text" class="form-control" required maxlength="255" x-model="editUser.name">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="edit-login" class="form-label fw-semibold">ID Login *</label>
                                <input id="edit-login" name="login_id" type="text" class="form-control" required maxlength="64" x-model="editUser.login_id" pattern="^[a-z0-9._-]+$">
                                <div class="form-text small">Huruf kecil, angka, titik, strip.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit-role" class="form-label fw-semibold">Peran Akses *</label>
                                <select id="edit-role" name="role" class="form-select" required x-model="editUser.role">
                                    <option value="operator">Operator Periode</option>
                                    <option value="administrator">Administrator</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit-email" class="form-label fw-semibold">Alamat Email (Opsional)</label>
                            <input id="edit-email" name="email" type="email" class="form-control" maxlength="255" x-model="editUser.email">
                        </div>

                        <div class="p-3 rounded-3 border bg-light mb-2">
                            <span class="fw-semibold small d-block mb-2 text-dark">
                                <i class="bi bi-key-fill me-1 text-purple"></i> Ubah Kata Sandi (Opsional)
                            </span>
                            <p class="text-muted small mb-2">Kosongkan kolom berikut jika tidak ingin mengubah kata sandi pengguna ini.</p>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <x-password-input id="edit-password" name="password" small placeholder="Kata sandi baru" autocomplete="new-password" />
                                </div>
                                <div class="col-md-6">
                                    <x-password-input id="edit-confirm" name="password_confirmation" small placeholder="Ulangi kata sandi baru" autocomplete="new-password" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-actions d-flex align-items-center justify-content-end gap-2 p-3 bg-light border-top">
                        <button type="button" class="btn btn-light border px-3" @click="showEditModal = false">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-purple px-4 fw-semibold d-inline-flex align-items-center gap-2">
                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                            <span>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL POP UP: HAPUS PENGELOLA              -->
        <!-- ========================================== -->
        <div 
            x-cloak 
            x-show="showDeleteModal" 
            class="ui-modal-backdrop" 
            @keydown.escape.window="showDeleteModal = false" 
            @click.self="showDeleteModal = false"
            style="display: none;"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-delete-title"
        >
            <div 
                class="ui-modal"
                x-show="showDeleteModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                style="max-width: 500px;"
            >
                <div class="ui-modal-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="dayak-shield-badge" style="width: 32px; height: 32px; font-size: 0.85rem; background: #dc3545; color: #fff;">
                            <i class="bi bi-trash-fill" aria-hidden="true"></i>
                        </span>
                        <h2 id="modal-delete-title" class="h6 mb-0 fw-bold text-dark">Hapus Akun Pengelola</h2>
                    </div>
                    <button type="button" class="btn-close" @click="showDeleteModal = false" aria-label="Tutup"></button>
                </div>

                <form method="post" :action="'/admin/pengguna/' + deleteUser.id">
                    @csrf
                    @method('delete')
                    <div class="ui-modal-body">
                        <p class="text-muted small mb-3">
                            Apakah Anda yakin ingin menghapus akun pengelola berikut secara permanen?
                        </p>

                        <div class="p-3 mb-3 rounded-3 border bg-light">
                            <div class="row g-2 small">
                                <div class="col-sm-4 text-muted">Nama Pengguna</div>
                                <div class="col-sm-8 fw-semibold text-dark" x-text="deleteUser.name"></div>

                                <div class="col-sm-4 text-muted">ID Login</div>
                                <div class="col-sm-8 text-dark"><code x-text="deleteUser.login_id"></code></div>

                                <div class="col-sm-4 text-muted">Peran</div>
                                <div class="col-sm-8 text-dark" x-text="deleteUser.role"></div>
                            </div>
                        </div>

                        <div class="alert alert-danger d-flex align-items-start gap-2 py-2 px-3 small mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-6 mt-1 flex-shrink-0 text-danger"></i>
                            <div>
                                Akun yang dihapus tidak akan dapat masuk kembali ke sistem. Riwayat audit dan penugasan operator yang bersangkutan akan dilepas.
                            </div>
                        </div>
                    </div>

                    <div class="modal-actions d-flex align-items-center justify-content-end gap-2 p-3 bg-light border-top">
                        <button type="button" class="btn btn-light border px-3" @click="showDeleteModal = false">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-danger px-4 fw-semibold d-inline-flex align-items-center gap-2">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                            <span>Ya, Hapus Akun</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts::app>

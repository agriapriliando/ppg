<div class="ui-modal-backdrop" x-show="applicantMode === 'edit'" x-cloak style="display: none;"
    x-trap.inert.noscroll="applicantMode === 'edit'" @keydown.escape.window="closeApplicant()" @click.self="closeApplicant()"
    role="dialog" aria-modal="true" aria-labelledby="edit-applicant-title" x-transition.opacity.duration.200ms>
    <div class="ui-modal" style="max-width: 600px;">
        <div class="ui-modal-header d-flex justify-content-between align-items-center gap-3">
            <h2 id="edit-applicant-title" class="h6 fw-bold mb-0">Edit Akun Pendaftar</h2>
            <button type="button" class="btn-close" @click="closeApplicant()" :disabled="applicantBusy" aria-label="Tutup"></button>
        </div>
        <form method="post" :action="applicantAccount.update_url" @submit="applicantBusy = true">
            @csrf
            @method('put')
            <input type="hidden" name="expected_login_id" :value="applicantAccount.expected_login_id">
            <div class="ui-modal-body">
                <p class="small text-muted">ID akun #<span x-text="applicantAccount.id"></span>. Peran dan status akun tetap. Perubahan ID login atau email akan mengakhiri sesi login akun ini.</p>
                <div class="mb-3"><label for="applicant-name" class="form-label fw-semibold">Nama *</label><input id="applicant-name" name="name" x-model="applicantAccount.name" class="form-control" required maxlength="255"></div>
                <div class="mb-3"><label for="applicant-login" class="form-label fw-semibold">ID login</label><input id="applicant-login" name="login_id" x-model="applicantAccount.login_id" class="form-control" maxlength="64" pattern="[A-Za-z0-9._-]+"><div class="form-text">Huruf, angka, titik, garis bawah, atau tanda hubung. Mengganti ID login akan membebaskan ID lama.</div></div>
                <div><label for="applicant-email" class="form-label fw-semibold">Email (opsional)</label><input id="applicant-email" type="email" name="email" x-model="applicantAccount.email" class="form-control" maxlength="255"></div>
            </div>
            <div class="modal-actions p-3 border-top bg-light d-flex flex-column flex-sm-row justify-content-end gap-2">
                <button type="button" class="btn btn-light border" @click="closeApplicant()" :disabled="applicantBusy">Batal</button>
                <button type="submit" class="btn btn-purple" :disabled="applicantBusy" x-text="applicantBusy ? 'Menyimpan…' : 'Simpan Perubahan'">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="ui-modal-backdrop" x-show="applicantMode === 'delete'" x-cloak style="display: none;"
    x-trap.inert.noscroll="applicantMode === 'delete'" @keydown.escape.window="closeApplicant()" @click.self="closeApplicant()"
    role="dialog" aria-modal="true" aria-labelledby="delete-applicant-title" x-transition.opacity.duration.200ms>
    <div class="ui-modal" style="max-width: 600px;">
        <div class="ui-modal-header d-flex justify-content-between align-items-center gap-3">
            <h2 id="delete-applicant-title" class="h6 fw-bold text-danger mb-0">Hapus Akun Pendaftar?</h2>
            <button type="button" class="btn-close" @click="closeApplicant()" :disabled="applicantBusy" aria-label="Tutup"></button>
        </div>
        <form method="post" :action="applicantAccount.delete_url" @submit="applicantBusy = true">
            @csrf
            @method('delete')
            <input type="hidden" name="expected_login_id" :value="applicantAccount.expected_login_id">
            <div class="ui-modal-body">
                <div class="border rounded-3 bg-light p-3 mb-3"><strong x-text="applicantAccount.name"></strong><span class="d-block small">ID akun #<span x-text="applicantAccount.id"></span> · ID login: <span x-text="applicantAccount.login_id || 'Belum tersedia'"></span></span></div>
                <div class="alert alert-danger">Akun akan dihapus permanen beserta sesi dan akses loginnya. ID login dan email dapat digunakan kembali. Tindakan ini tidak dapat dipulihkan melalui aplikasi.</div>
                <p class="small">Sistem akan memeriksa seluruh keterkaitan, termasuk riwayat yang tidak terlihat pada daftar. Jika akun masih dipakai pada data lain, penghapusan dibatalkan.</p>
                <label class="d-flex align-items-start gap-2"><input type="checkbox" name="confirmed" value="1" class="form-check-input" x-model="applicantConfirmed" required><span>Saya sudah memeriksa akun ini dan menyetujui penghapusan permanen.</span></label>
            </div>
            <div class="modal-actions p-3 border-top bg-light d-flex flex-column flex-sm-row justify-content-end gap-2">
                <button type="button" class="btn btn-light border" @click="closeApplicant()" :disabled="applicantBusy">Batal</button>
                <button type="submit" class="btn btn-danger" :disabled="!applicantConfirmed || applicantBusy" x-text="applicantBusy ? 'Menghapus…' : 'Hapus Permanen'">Hapus Permanen</button>
            </div>
        </form>
    </div>
</div>

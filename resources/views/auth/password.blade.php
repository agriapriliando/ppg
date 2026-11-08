<x-layouts::app title="Keamanan Akun">
<div class="row">
    <div class="col-xl-7">
        <section class="workspace-card">
            <div class="panel-heading">
                <h2>Ganti kata sandi</h2>
                <p>Gunakan kata sandi baru yang hanya Anda ketahui.</p>
            </div>
            <div class="panel-body">
                <form method="post" action="{{ route('password.update') }}">
                    @csrf
                    @method('put')

                    <div class="mb-3" x-data="{ showCurrent: false }">
                        <label class="form-label" for="current_password">Kata sandi saat ini</label>
                        <div class="input-group">
                            <input class="form-control" id="current_password" name="current_password" :type="showCurrent ? 'text' : 'password'" type="password" autocomplete="current-password" required>
                            <button type="button" class="btn btn-password-toggle" @click="showCurrent = !showCurrent" :aria-label="showCurrent ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" :title="showCurrent ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                                <i class="bi" :class="showCurrent ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3" x-data="{ showNew: false }">
                        <label class="form-label" for="new_password">Kata sandi baru</label>
                        <div class="input-group">
                            <input class="form-control" id="new_password" name="password" :type="showNew ? 'text' : 'password'" type="password" autocomplete="new-password" required maxlength="255">
                            <button type="button" class="btn btn-password-toggle" @click="showNew = !showNew" :aria-label="showNew ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" :title="showNew ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                                <i class="bi" :class="showNew ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="form-text">Tanpa batas minimal karakter.</div>
                    </div>

                    <div class="mb-4" x-data="{ showConfirm: false }">
                        <label class="form-label" for="password_confirmation">Ulangi kata sandi baru</label>
                        <div class="input-group">
                            <input class="form-control" id="password_confirmation" name="password_confirmation" :type="showConfirm ? 'text' : 'password'" type="password" autocomplete="new-password" required>
                            <button type="button" class="btn btn-password-toggle" @click="showConfirm = !showConfirm" :aria-label="showConfirm ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" :title="showConfirm ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                                <i class="bi" :class="showConfirm ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button class="btn btn-purple">Simpan Kata Sandi</button>
                </form>
            </div>
        </section>
    </div>
</div>
</x-layouts::app>

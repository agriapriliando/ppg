@if(auth()->check() && blank(auth()->user()->phone_number))
    <div class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="required-phone-title" aria-describedby="required-phone-description"
        x-data="{ busy: false }" x-trap.inert.noscroll="true" @keydown.escape.window.prevent.stop style="z-index: 3000;">
        <div class="ui-modal" style="max-width: 480px;">
            <div class="ui-modal-header">
                <h2 id="required-phone-title" class="h5 fw-bold text-purple mb-0">Lengkapi Nomor HP</h2>
            </div>
            <form method="post" action="{{ route('account.phone.update') }}" @submit="busy = true">
                @csrf
                @method('put')
                <div class="ui-modal-body">
                    <p id="required-phone-description">Isi nomor HP WhatsApp aktif Anda untuk melanjutkan penggunaan aplikasi.</p>
                    <label for="required-account-phone" class="form-label fw-semibold">Nomor HP WhatsApp aktif <span class="text-danger">*</span></label>
                    <input id="required-account-phone" name="phone_number" type="tel" inputmode="numeric" autocomplete="tel"
                        class="form-control" value="{{ \App\Support\FormInput::old('phone_number') }}" required maxlength="30"
                        pattern="628[0-9]{7,12}" placeholder="Contoh: 6281234567890" aria-describedby="required-phone-help required-phone-error"
                        x-data="{ normalize() { let number = $el.value.replace(/[^0-9]/g, ''); if (number.startsWith('08')) number = '62' + number.slice(1); else if (number.startsWith('8')) number = '62' + number; $el.value = number; } }"
                        x-init="normalize()" @input="normalize()" @blur="normalize()">
                    <div id="required-phone-help" class="form-text">Nomor HP WhatsApp aktif dengan awalan 628, tanpa tanda + atau spasi. Awalan 08 atau +628 diubah otomatis.</div>
                    @error('phone_number', 'accountPhone')<div id="required-phone-error" class="text-danger small mt-2" role="alert">{{ $message }}</div>@enderror
                </div>
                <div class="p-3 border-top">
                    <button class="btn btn-purple w-100" :disabled="busy" x-text="busy ? 'Menyimpan...' : 'Simpan dan Lanjutkan'">Simpan dan Lanjutkan</button>
                </div>
            </form>
        </div>
    </div>
@endif

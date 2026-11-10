<x-layouts::public title="Pendaftaran Akun">
    <section class="auth-section">
        <div class="container">
            <div class="row g-4 justify-content-center">
                <div class="col-lg-5">
                    <section class="auth-intro">
                        <span class="eyebrow">AKUN PORTAL PMB PPG</span>
                        <i class="bi bi-whatsapp" aria-hidden="true"></i>
                        <h1>Daftar akun Portal PMB PPG.</h1>
                        <p class="mt-3">Isi data sesuai SK dan nomor WhatsApp aktif. Setelah data diperiksa dan
                            disetujui, pengelola akan mengirimkan ID login dan kata sandi melalui WhatsApp.</p>
                    </section>
                </div>
                <div class="col-lg-6">
                    <section class="auth-card">
                        <h2 class="h3">Pendaftaran Akun</h2>
                        <p class="text-muted">Lengkapi data berikut untuk mengajukan akun. Pendaftaran akan diperiksa
                            oleh pengelola sebelum akun dapat digunakan.</p>
                        @if($errors->any())
                            <div class="alert alert-danger border-start border-4 rounded-3 mb-4" role="alert" tabindex="-1" x-init="$el.focus()">
                                <h3 class="h6 fw-bold">Pendaftaran akun belum dapat dikirim</h3>
                                <p class="mb-2">Periksa pesan berikut dan perbaiki data sebelum mengirim kembali.</p>
                                <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                            </div>
                        @else
                            <x-notice />
                        @endif

                        @if (session('claim_submitted'))
                            <div class="alert alert-success border-success border-start border-4 rounded-3 p-4 mb-4"
                                role="status" aria-label="Informasi pengiriman akun">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="bi bi-whatsapp fs-2" aria-hidden="true"></i>
                                    <div>
                                        <h3 class="h5 fw-bold mb-2">Tunggu ID login dan kata sandi melalui WhatsApp</h3>
                                        <p class="mb-2">Pendaftaran akun Anda berhasil dikirim. Setelah data diperiksa
                                            dan disetujui, <strong>operator akan mengirimkan ID login dan kata sandi ke
                                                nomor WhatsApp yang Anda daftarkan.</strong></p>
                                        <p class="mb-0">Pastikan nomor WhatsApp tersebut aktif dan dapat menerima
                                            pesan. Anda dapat login setelah menerima informasi akun dari operator.</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($periods->isEmpty())
                            <div class="notice-box info p-4 my-3">
                                <i class="bi bi-calendar-x fs-3 text-purple" aria-hidden="true"></i>
                                <div>
                                    <h3 class="h6 fw-bold mb-1">Pendaftaran Belum Dibuka</h3>
                                    <p class="mb-0">Saat ini belum ada periode pendaftaran mahasiswa baru yang aktif
                                        atau dipublikasikan. Pantau informasi resmi penerimaan atau hubungi pengelola
                                        untuk jadwal gelombang berikutnya.</p>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <a href="{{ route('home') }}" class="btn btn-purple">
                                    <i class="bi bi-arrow-left me-1"></i>Kembali ke Beranda
                                </a>
                                <a href="{{ route('guide') }}" class="btn btn-outline-purple">
                                    <i class="bi bi-book me-1"></i>Panduan Pendaftaran
                                </a>
                            </div>
                        @elseif($consent)
                            <form method="post" action="{{ route('public.claim.store') }}"
                                @if($pegIdRequired)
                                x-data="publicClaimForm(@js(['identifier' => \App\Support\FormInput::old('identifier'), 'phone' => \App\Support\FormInput::old('whatsapp_number'), 'periodId' => \App\Support\FormInput::old('period_id'), 'lookupUrl' => route('public.claim.lookup'), 'csrf' => csrf_token()]))"
                                @submit="submit($event)"
                                @else
                                x-data="{
                                showConfirmation: false,
                                agreed: false,
                                submitting: false,
                                closeConfirmation() {
                                    if (!this.submitting) {
                                        this.showConfirmation = false;
                                        this.agreed = false;
                                    }
                                }
                            }"
                                @submit="if (!showConfirmation || !agreed) { $event.preventDefault(); showConfirmation = true; } else { submitting = true; }"
                                @endif>
                                @csrf
                                <input type="hidden" name="request_key"
                                    value="{{ \App\Support\FormInput::old('request_key', $requestKey) }}">
                                <input type="hidden" name="consent_id" value="{{ $consent->id }}">
                                @if($pegIdRequired)
                                    <p class="small text-muted">Isi PEG ID sesuai surat dan nomor HP aktif untuk WhatsApp. Periode dan nama akan muncul otomatis dari daftar peserta SK.</p>
                                    <div class="mb-3">
                                        <label for="claim-identifier" class="form-label">PEG ID <span class="text-danger">*</span></label>
                                        <input id="claim-identifier" name="identifier" type="text" maxlength="100" required
                                            @class(['form-control', 'is-invalid' => $errors->has('identifier')])
                                            x-model="identifier" @input="changed()" autocomplete="off" aria-describedby="claim-lookup-status identifier-error">
                                        <x-field-error name="identifier" />
                                    </div>
                                    <div class="mb-3">
                                        <label for="claim-whatsapp_number" class="form-label">Nomor HP (WhatsApp aktif) <span class="text-danger">*</span></label>
                                        <input id="claim-whatsapp_number" name="whatsapp_number" type="tel" inputmode="numeric" autocomplete="tel"
                                            @class(['form-control', 'is-invalid' => $errors->has('whatsapp_number')])
                                            x-model="phone" @input="changed()" required maxlength="30" pattern="628[0-9]{7,12}"
                                            placeholder="Contoh: 6281234567890" aria-describedby="claim-phone-help whatsapp_number-error">
                                        <div id="claim-phone-help" class="form-text">Awalan 08 atau +628 diubah otomatis menjadi 628.</div>
                                        <x-field-error name="whatsapp_number" />
                                    </div>
                                    <div id="claim-lookup-status" aria-live="polite" :aria-busy="loading">
                                        <p x-cloak x-show="loading" class="small text-muted" role="status">Memeriksa PEG ID dan periode pendaftaran...</p>
                                        <div x-cloak x-show="error" class="alert alert-danger" role="alert">
                                            <p class="mb-2" x-text="error"></p>
                                            <button type="button" class="btn btn-sm btn-outline-danger" @click="retry()">Coba lagi</button>
                                        </div>
                                    </div>
                                    <div x-cloak x-show="participants.length > 0">
                                        <div class="mb-3">
                                            <label for="claim-period" class="form-label">Periode</label>
                                            <input type="hidden" name="period_id" :value="periodId">
                                            <template x-if="participants.length === 1">
                                                <input id="claim-period" class="form-control bg-light" type="text" :value="periodName" readonly>
                                            </template>
                                            <template x-if="participants.length > 1">
                                                <select id="claim-period" class="form-select" x-model="periodId" @change="selectPeriod()" required>
                                                    <option value="">Pilih periode sesuai surat</option>
                                                    <template x-for="participant in participants" :key="participant.period_id">
                                                        <option :value="String(participant.period_id)" x-text="participant.period_name"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <p x-show="participants.length > 1" class="form-text">PEG ID ditemukan pada beberapa periode yang dibuka. Pilih periode sesuai surat Anda.</p>
                                            <x-field-error name="period_id" />
                                        </div>
                                        <div class="mb-3" x-show="name">
                                            <label for="claim-name" class="form-label">Nama persis sesuai SK atau Surat</label>
                                            <input id="claim-name" type="text" class="form-control bg-light" :value="name" readonly>
                                            <div class="form-text">Periksa nama yang muncul. Jika tidak sesuai, hubungi operator sebelum mendaftar.</div>
                                        </div>
                                    </div>
                                    <noscript><div class="alert alert-warning">Aktifkan JavaScript pada browser untuk memeriksa PEG ID dan menampilkan data peserta.</div></noscript>
                                @else
                                <div class="mb-3">
                                    <label for="claim-period" class="form-label">Periode *</label>
                                    <select id="claim-period" class="form-select" name="period_id" required>
                                        <option value="">Pilih periode</option>
                                        @foreach ($periods as $period)
                                            <option value="{{ $period->id }}" @disabled(!$period->acceptsApplications())
                                                @selected($period->acceptsApplications() && \App\Support\FormInput::old('period_id', $defaultPeriodId) == $period->id)>{{ $period->name }} ·
                                                {{ $period->availabilityLabel() }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Hanya periode dengan pendaftaran dibuka yang dapat dipilih.
                                    </div>
                                    <x-field-error name="period_id" />
                                </div>
                                @foreach (['name' => 'Nama persis sesuai SK atau Surat', 'whatsapp_number' => 'Nomor WhatsApp aktif', 'identifier' => $pegIdRequired ? 'Nomor Peg ID (sesuai surat)' : 'Nomor Peg ID (sesuai surat, kosongkan jika belum menerima surat)'] as $key => $label)
                                    <div class="mb-3">
                                        <label for="claim-{{ $key }}"
                                            class="form-label">{{ $label }} @if($key === 'identifier' && $pegIdRequired)<span class="text-danger">*</span>@endif</label>
                                        <input id="claim-{{ $key }}" name="{{ $key }}"
                                            @class(['form-control', 'is-invalid' => $errors->has($key)]) value="{{ \App\Support\FormInput::old($key) }}"
                                            @if($errors->has($key)) aria-invalid="true" aria-describedby="{{ $key }}-error" @endif
                                            @required($key !== 'identifier' || $pegIdRequired)
                                            maxlength="{{ $key === 'name' ? 255 : ($key === 'identifier' ? 100 : 30) }}"
                                            type="{{ $key === 'whatsapp_number' ? 'tel' : 'text' }}"
                                            @if ($key === 'whatsapp_number') inputmode="numeric" autocomplete="tel" placeholder="Contoh : 6281234567890" pattern="628[0-9]{7,12}"
                                                x-data="{ normalize() { let number = $el.value.replace(/[^0-9]/g, ''); if (number.startsWith('08')) number = '62' + number.slice(1); else if (number.startsWith('8')) number = '62' + number; $el.value = number; } }"
                                                x-init="normalize()" @input="normalize()" @blur="normalize()" @endif>
                                        @if ($key === 'whatsapp_number')
                                            <div class="form-text">Gunakan format 628…, tanpa tanda +, spasi, atau tanda
                                                baca.</div>
                                        @endif
                                        <x-field-error :name="$key" />
                                    </div>
                                @endforeach
                                @endif
                                <p class="small text-muted">Peg ID yang sama hanya dapat didaftarkan satu kali pada periode yang sama. Kombinasi nama dan nomor WhatsApp yang sama juga tidak dapat didaftarkan ulang.</p>
                                <button class="btn btn-gold w-100" @if($pegIdRequired) :disabled="!ready || submitting" @endif>Daftar Akun</button>
                                <div x-cloak x-show="showConfirmation" x-trap.inert.noscroll="showConfirmation"
                                    @keydown.escape.window="closeConfirmation()" @click.self="closeConfirmation()"
                                    class="ui-modal-backdrop" role="dialog" aria-modal="true"
                                    aria-labelledby="claim-confirmation-title" aria-describedby="claim-consent-text">
                                    <div class="ui-modal">
                                        <div class="ui-modal-header">
                                            <h2 id="claim-confirmation-title" class="h6 fw-bold text-purple">Konfirmasi
                                                Pendaftaran Akun</h2>
                                            <button type="button" class="btn-close" @click="closeConfirmation()"
                                                :disabled="submitting" aria-label="Tutup"></button>
                                        </div>
                                        <div class="ui-modal-body">
                                            <div class="alert alert-warning border-warning border-start border-4 mb-3">
                                                <h3 class="h6 fw-bold"><i class="bi bi-whatsapp me-2"
                                                        aria-hidden="true"></i>ID login dan kata sandi dikirim melalui
                                                    WhatsApp</h3>
                                                <p class="mb-0">Setelah data diperiksa dan disetujui, <strong>operator
                                                        akan mengirimkan ID login dan kata sandi ke nomor WhatsApp yang
                                                        Anda daftarkan.</strong> Pastikan nomor tersebut benar, aktif,
                                                    dan dapat menerima pesan.</p>
                                            </div>
                                            <p>Baca dan centang persetujuan berikut sebelum mengirim pendaftaran akun.
                                            </p>
                                            <label class="consent-box mb-0" for="claim-consent">
                                                <input id="claim-consent" type="checkbox" class="form-check-input"
                                                    name="consent" value="1" x-model="agreed"
                                                    :required="showConfirmation" :disabled="submitting && !agreed">
                                                <span id="claim-consent-text">{{ $consent->body }}<small
                                                        class="d-block text-muted mt-2">Versi
                                                        {{ $consent->version }}</small></span>
                                            </label>
                                        </div>
                                        <div
                                            class="d-flex flex-column flex-sm-row justify-content-end gap-2 border-top p-3 bg-light">
                                            <button type="button" class="btn btn-outline-purple"
                                                @click="closeConfirmation()" :disabled="submitting">Batal</button>
                                            <button type="submit" class="btn btn-gold"
                                                :disabled="!agreed || submitting">
                                                <span x-show="!submitting">Setujui dan Daftar Akun</span>
                                                <span x-show="submitting" role="status">Mengirim…</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        @else
                            <div class="notice-box info">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                <p class="mb-0">Pendaftaran akun belum tersedia. Pantau informasi resmi penerimaan
                                    atau hubungi pengelola.</p>
                            </div>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </section>
</x-layouts::public>

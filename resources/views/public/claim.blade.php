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
                        <x-notice />

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
                            <form method="post" action="{{ route('public.claim.store') }}" x-data="{
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
                                @submit="if (!showConfirmation || !agreed) { $event.preventDefault(); showConfirmation = true; } else { submitting = true; }">
                                @csrf
                                <input type="hidden" name="request_key"
                                    value="{{ \App\Support\FormInput::old('request_key', $requestKey) }}">
                                <input type="hidden" name="consent_id" value="{{ $consent->id }}">
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
                                </div>
                                @foreach (['name' => 'Nama persis sesuai SK atau Surat', 'whatsapp_number' => 'Nomor WhatsApp aktif', 'identifier' => 'Nomor Peg ID (sesuai surat, kosongkan jika belum menerima surat)'] as $key => $label)
                                    <div class="mb-3">
                                        <label for="claim-{{ $key }}"
                                            class="form-label">{{ $label }}</label>
                                        <input id="claim-{{ $key }}" name="{{ $key }}"
                                            class="form-control" value="{{ \App\Support\FormInput::old($key) }}"
                                            @required($key !== 'identifier')
                                            maxlength="{{ $key === 'name' ? 255 : ($key === 'identifier' ? 100 : 30) }}"
                                            type="{{ $key === 'whatsapp_number' ? 'tel' : 'text' }}"
                                            @if ($key === 'whatsapp_number') inputmode="numeric" autocomplete="tel" placeholder="Contoh : 6281234567890" pattern="628[0-9]{7,12}"
                                                x-data="{ normalize() { let number = $el.value.replace(/[^0-9]/g, ''); if (number.startsWith('08')) number = '62' + number.slice(1); else if (number.startsWith('8')) number = '62' + number; $el.value = number; } }"
                                                x-init="normalize()" @input="normalize()" @blur="normalize()" @endif>
                                        @if ($key === 'whatsapp_number')
                                            <div class="form-text">Gunakan format 628…, tanpa tanda +, spasi, atau tanda
                                                baca.</div>
                                        @endif
                                    </div>
                                @endforeach
                                <p class="small text-muted">Kombinasi nama dan nomor WhatsApp yang sama hanya dapat didaftarkan satu kali pada periode yang sama.</p>
                                <button class="btn btn-gold w-100">Daftar Akun</button>
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

<x-layouts::public title="Klaim Kepesertaan SK">
    <section class="auth-section">
        <div class="container">
            <div class="row g-4 justify-content-center">
                <div class="col-lg-5">
                    <section class="auth-intro">
                        <span class="eyebrow">KEPESERTAAN BERDASARKAN SK</span>
                        <i class="bi bi-whatsapp" aria-hidden="true"></i>
                        <h1>Awali pendaftaran dengan pencocokan identitas.</h1>
                        <p class="mt-3">Operator memeriksa kepesertaan resmi dan memastikan nomor aktif sebelum menyerahkan akses secara manual.</p>
                    </section>
                </div>
                <div class="col-lg-6">
                    <section class="auth-card">
                        <h2 class="h3">Ajukan pencocokan SK</h2>
                        <p class="text-muted">Permohonan belum memberikan akses atau mengunci kepesertaan SK.</p>
                        <x-notice />

                        @if($periods->isEmpty())
                            <div class="notice-box info p-4 my-3">
                                <i class="bi bi-calendar-x fs-3 text-purple" aria-hidden="true"></i>
                                <div>
                                    <h3 class="h6 fw-bold mb-1">Pendaftaran Belum Dibuka</h3>
                                    <p class="mb-0">Saat ini belum ada periode pendaftaran mahasiswa baru yang aktif atau dipublikasikan. Pantau informasi resmi penerimaan atau hubungi pengelola untuk jadwal gelombang berikutnya.</p>
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
                            <form method="post" action="{{ route('public.claim.store') }}">
                                @csrf
                                <input type="hidden" name="request_key" value="{{ \App\Support\FormInput::old('request_key', $requestKey) }}">
                                <input type="hidden" name="consent_id" value="{{ $consent->id }}">
                                <div class="mb-3">
                                    <label for="claim-period" class="form-label">Periode *</label>
                                    <select id="claim-period" class="form-select" name="period_id" required>
                                        <option value="">Pilih periode</option>
                                        @foreach($periods as $period)
                                            <option value="{{ $period->id }}" @selected(\App\Support\FormInput::old('period_id')==$period->id)>{{ $period->name }} · {{ $period->availabilityLabel() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @foreach(['name'=>'Nama persis sesuai SK','whatsapp_number'=>'Nomor WhatsApp aktif','identifier'=>'Pengenal resmi SK (jika tersedia)'] as $key=>$label)
                                    <div class="mb-3">
                                        <label for="claim-{{ $key }}" class="form-label">{{ $label }}</label>
                                        <input id="claim-{{ $key }}" name="{{ $key }}" class="form-control" value="{{ \App\Support\FormInput::old($key) }}" @required($key!=='identifier') maxlength="{{ $key==='name' ? 255 : ($key==='identifier' ? 100 : 30) }}" type="{{ $key==='whatsapp_number' ? 'tel' : 'text' }}">
                                    </div>
                                @endforeach
                                <label class="consent-box mb-4">
                                    <input type="checkbox" class="form-check-input" name="consent" value="1" required>
                                    <span>{{ $consent->body }}<small class="d-block text-muted mt-2">Versi {{ $consent->version }}</small></span>
                                </label>
                                <button class="btn btn-gold w-100">Ajukan Permohonan</button>
                            </form>
                        @else
                            <div class="notice-box info">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                <p class="mb-0">Pengajuan kepesertaan belum tersedia. Pantau informasi resmi penerimaan atau hubungi pengelola.</p>
                            </div>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </section>
</x-layouts::public>

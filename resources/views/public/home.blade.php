<x-layouts::public>
    <section class="hero-section position-relative py-5">
        <div class="container py-4">
            <div class="row align-items-center g-4">
                <div class="col-lg-8"><span class="badge bg-warning text-dark mb-4">PPG DALAM JABATAN · BIMAS
                        KRISTEN</span>
                    <h1 class="hero-title">Penerimaan Mahasiswa Baru<br><span class="text-warning">PPG IAKN Palangka
                            Raya</span></h1>
                    <p class="text-white-50 my-4 fs-5">Satu portal untuk mencocokkan kepesertaan SK, melengkapi biodata,
                        dan memantau pemeriksaan administrasi PPG.</p>
                    <div class="d-flex flex-wrap gap-3"><a href="{{ route('login') }}" class="btn btn-gold btn-lg"><i
                                class="bi bi-person-check me-2" aria-hidden="true"></i>Masuk Akun</a><a
                            href="{{ route('guide') }}" class="btn btn-outline-light btn-lg">Panduan Pendaftaran</a><a
                            href="{{ route('public.claim') }}" class="btn btn-outline-light btn-lg"><i
                                class="bi bi-pencil-square me-2" aria-hidden="true"></i>Pendaftaran</a></div>
                </div>
                <div class="col-lg-4 text-center"><img src="{{ asset('favicon_io_new/android-chrome-512x512.png') }}"
                        alt="Logo PMB PPG IAKN" class="img-fluid hero-emblem-img mb-3"
                        style="max-width: 175px; height: auto; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.3));">
                    <div class="text-white mt-1 fw-semibold">Terarah · Terverifikasi · Terlayani</div>
                </div>
            </div>
        </div>
    </section>
    <section class="container py-5"><x-notice />
        <div class="workspace-card">
            <div class="panel-body">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8"><span class="section-badge mb-3">INFORMASI PENERIMAAN</span>
                        <h2 class="h4">{{ $period?->name ?? 'Informasi periode penerimaan' }}</h2>
                        @if ($period)
                            <p class="text-muted mb-2">{{ $period->academic_year }} · Gelombang {{ $period->wave }}</p>
                            <p class="mb-3">
                                {{ $period->opens_at->timezone('Asia/Jakarta')->translatedFormat('d F Y H:i') }} –
                                {{ $period->closes_at->timezone('Asia/Jakarta')->translatedFormat('d F Y H:i') }} WIB
                        </p>@else<p class="text-muted mb-3">Periode penerimaan belum diumumkan. Pantau halaman ini
                                untuk informasi resmi dari pengelola.</p>
                        @endif
                        <div class="mt-3">
                            <a href="{{ route('public.claim') }}" class="btn btn-purple"><i
                                    class="bi bi-pencil-square me-2" aria-hidden="true"></i>Pendaftaran</a>
                        </div>
                    </div>
                    <div class="col-lg-4 text-lg-end"><span
                            class="badge rounded-pill text-bg-{{ $period?->acceptsApplications() ? 'success' : 'secondary' }} p-3">{{ $period?->availabilityLabel() ?? 'Menunggu pengumuman' }}</span>
                    </div>
                </div>
                @if (!empty($settings['notice']))
                    <div class="public-announcement-card mt-4" role="region" aria-label="Pengumuman Resmi PMB">
                        <div class="d-flex align-items-start gap-3">
                            <div class="announcement-icon-badge flex-shrink-0" aria-hidden="true">
                                <i class="bi bi-megaphone-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <span class="announcement-tag">
                                        <span class="announcement-live-dot" aria-hidden="true"></span>
                                        <span>PENGUMUMAN PENTING</span>
                                    </span>
                                    <small class="text-muted fw-semibold">Pengelola PMB PPG IAKN Palangka Raya</small>
                                </div>
                                <div class="announcement-text text-break">
                                    {{ $settings['notice'] }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
    <section id="alur-pendaftaran" class="py-5 bg-white">
        <div class="container">
            <div class="text-center mb-5"><span class="section-badge">ALUR PENDAFTARAN</span>
                <h2 class="section-title mt-3">Dari kepesertaan SK hingga diterima lengkap</h2>
                <p class="text-muted">Kepemilikan akun dan kepesertaan dalam SK diperiksa secara terpisah.</p>
            </div>
            <div class="row g-4">
                @foreach (['Cocokkan kepesertaan SK' => 'Gunakan nama dan pengenal resmi. Operator akan memeriksa kecocokan identitas Anda.', 'Lengkapi biodata' => 'Isi identitas, alamat, informasi tambahan, serta perguruan tinggi dan program studi asal.', 'Tinjau dan kirim' => 'Periksa kembali data dan pernyataan sebelum mengirim untuk pemeriksaan operator.', 'Pantau hasil pemeriksaan' => 'Perbaiki data sesuai catatan operator sampai dinyatakan diterima lengkap.'] as $heading => $description)
                    <div class="col-md-6 col-lg-3">
                        <article class="workspace-card h-100">
                            <div class="panel-body"><span class="avatar-mini mb-3">{{ $loop->iteration }}</span>
                                <h3 class="h5">{{ $heading }}</h3>
                                <p class="text-muted small mb-0">{{ $description }}</p>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <section class="container py-5">
        <div class="row g-4">
            <div class="col-lg-7">
                <h2 class="h4 mb-4">Pertanyaan yang sering diajukan</h2>
                @foreach (['Apakah semua orang dapat mendaftar?' => 'Pendaftaran ditujukan kepada peserta dalam SK resmi. Operator memeriksa identitas dan kepesertaan sebelum formulir dapat dikirim.', 'Mengapa WhatsApp harus diperiksa?' => 'Nomor aktif dibuktikan melalui balasan kepada operator resmi kampus, agar komunikasi administrasi dapat dilakukan.', 'Apakah diterima lengkap berarti pendaftaran sudah berhasil?' => 'Benar, diterima lengkap berarti pendaftaran Anda telah berhasil.'] as $question => $answer)
                    <details class="help-detail">
                        <summary>{{ $question }}</summary>
                        <p>{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
            <div class="col-lg-5">
                <div class="workspace-card">
                    <div class="panel-body">
                        <h2 class="h5">Kontak & Layanan</h2>
                        <p>{{ $settings['helpdesk_name'] ?? 'Pengelola PMB PPG IAKN Palangka Raya' }}</p>
                        @if (!empty($settings['helpdesk_whatsapp']))
                            <p><i class="bi bi-whatsapp me-2"
                                    aria-hidden="true"></i>{{ $settings['helpdesk_whatsapp'] }}</p>
                            @endif @if (!empty($settings['service_hours']))
                                <p class="text-muted">{{ $settings['service_hours'] }} WIB</p>
                            @endif
                            <a href="{{ route('guide') }}" class="btn btn-outline-purple">Lihat panduan bantuan</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts::public>

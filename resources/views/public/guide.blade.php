<x-layouts::public 
    title="Panduan Pendaftaran Mahasiswa Baru · PMB PPG IAKN Palangka Raya"
    description="Panduan lengkap alur pendaftaran peserta PPG Dalam Jabatan Bimas Kristen IAKN Palangka Raya: pendaftaran akun, verifikasi WhatsApp, login, dan unggah berkas persyaratan."
>
    <!-- Hero / Header Section -->
    <section class="container py-4 py-lg-5">
        <div class="guide-hero-banner p-4 p-md-5 mb-4 position-relative">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-3 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-book-half" aria-hidden="true"></i> PANDUAN CALON MAHASISWA
                    </span>
                    <h1 class="text-white fw-bold mb-3" style="font-size: clamp(1.75rem, 3vw, 2.5rem);">
                        Alur Pendaftaran Peserta PPG
                    </h1>
                    <p class="text-white-50 fs-6 mb-4 lead" style="max-width: 650px;">
                        Ikuti tahapan pendaftaran program Pendidikan Profesi Guru (PPG) Dalam Jabatan Bimas Kristen IAKN Palangka Raya secara mudah, runtut, dan terpadu.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('public.claim') }}" class="btn btn-gold fw-semibold">
                            <i class="bi bi-person-plus-fill me-1" aria-hidden="true"></i> Daftar Akun Baru
                        </a>
                        <a href="{{ route('login') }}" class="btn btn-outline-light fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Masuk Akun
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 text-center d-none d-lg-block">
                    <img src="{{ asset('favicon_io_new/android-chrome-192x192.png') }}" alt="Logo PPG IAKN" width="130" height="130" style="object-fit: contain; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));">
                    <div class="text-warning small fw-bold mt-2">IAKN PALANGKA RAYA</div>
                </div>
            </div>
        </div>

        <!-- PERHATIAN PENTING: NOMOR WHATSAPP AKTIF -->
        <div class="whatsapp-alert-card p-3 p-md-4 mb-5">
            <div class="d-flex align-items-start gap-3">
                <div class="whatsapp-icon-badge">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fw-bold px-2 py-1 small">
                            WAJIB DIPERHATIKAN
                        </span>
                        <h2 class="h5 fw-bold text-success-emphasis mb-0">
                            Pastikan Nomor WhatsApp Anda Benar-benar Aktif & Bisa Dihubungi
                        </h2>
                    </div>
                    <p class="text-secondary small mb-0 mt-1" style="line-height: 1.6;">
                        Operator resmi kami akan mengirimkan <strong>Username</strong> dan <strong>Password</strong> akun portal secara langsung ke nomor WhatsApp yang Anda daftarkan. Pastikan nomor ponsel yang Anda cantumkan benar, aktif, dan dapat menerima pesan dari panitia PMB agar proses aktivasi akun Anda berjalan lancar.
                    </p>
                </div>
            </div>
        </div>

        <!-- TAHAPAN PENDAFTARAN (GRID LANGKAH 1 - 4) -->
        <div class="text-center mb-4">
            <span class="section-badge">TAHAPAN PENDAFTARAN</span>
            <h2 class="h3 fw-bold mt-2 text-purple">4 Langkah Mudah Menuju Pendaftaran Berhasil</h2>
            <p class="text-muted small">Semua tahapan dilakukan secara daring melalui portal ini dan kanal resmi WhatsApp panitia.</p>
        </div>

        <div class="row g-4 mb-5">
            <!-- LANGKAH 1 -->
            <div class="col-md-6 col-lg-3">
                <article class="guide-step-card accent-purple p-4">
                    <div class="step-header-box">
                        <div class="step-pill-badge step-purple">
                            <span class="step-circle-num">1</span>
                            <span>Langkah 1</span>
                        </div>
                        <div class="step-mini-icon purple">
                            <i class="bi bi-person-vcard-fill" aria-hidden="true"></i>
                        </div>
                    </div>
                    <h3 class="h5 fw-bold text-purple mb-2">Pendaftaran Akun Baru</h3>
                    <p class="text-muted small mb-3">
                        Calon peserta melakukan pendaftaran awal dengan mencocokkan identitas kepesertaan.
                    </p>
                    <ul class="step-points-list flex-grow-1 mb-4">
                        <li>Buka formulir pendaftaran akun melalui portal PMB.</li>
                        <li>Masukkan identitas diri sesuai data SK penetapan resmi.</li>
                        <li>Isi nomor WhatsApp aktif yang akan digunakan untuk menerima akun.</li>
                    </ul>
                    <a href="{{ route('public.claim') }}" class="btn btn-purple btn-sm w-100 mt-auto fw-semibold">
                        <i class="bi bi-pencil-square me-1"></i> Form Pendaftaran Akun
                    </a>
                </article>
            </div>

            <!-- LANGKAH 2 -->
            <div class="col-md-6 col-lg-3">
                <article class="guide-step-card accent-gold p-4">
                    <div class="step-header-box">
                        <div class="step-pill-badge step-gold">
                            <span class="step-circle-num">2</span>
                            <span>Langkah 2</span>
                        </div>
                        <div class="step-mini-icon gold">
                            <i class="bi bi-whatsapp" aria-hidden="true"></i>
                        </div>
                    </div>
                    <h3 class="h5 fw-bold text-purple mb-2">Pengiriman Akun via WhatsApp</h3>
                    <p class="text-muted small mb-3">
                        Operator resmi akan memeriksa data dan mengirimkan akses masuk akun pendaftar.
                    </p>
                    <ul class="step-points-list gold-checks flex-grow-1 mb-4">
                        <li>Operator mencocokkan pendaftaran dengan data kepesertaan SK.</li>
                        <li>Operator mengirimkan <strong>Username</strong> & <strong>Password</strong> ke nomor WhatsApp Anda.</li>
                        <li>Simpan dan jaga kerahasiaan akun login yang Anda terima.</li>
                    </ul>
                    <div class="step-card-status-badge status-info mt-auto">
                        <i class="bi bi-shield-lock-fill text-success"></i> Dikirim resmi via WA
                    </div>
                </article>
            </div>

            <!-- LANGKAH 3 -->
            <div class="col-md-6 col-lg-3">
                <article class="guide-step-card accent-purple p-4">
                    <div class="step-header-box">
                        <div class="step-pill-badge step-purple">
                            <span class="step-circle-num">3</span>
                            <span>Langkah 3</span>
                        </div>
                        <div class="step-mini-icon purple">
                            <i class="bi bi-file-earmark-arrow-up-fill" aria-hidden="true"></i>
                        </div>
                    </div>
                    <h3 class="h5 fw-bold text-purple mb-2">Login dan Unggah Berkas</h3>
                    <p class="text-muted small mb-3">
                        Masuk ke portal dengan akun tersebut untuk mengisi formulir dan unggah dokumen.
                    </p>
                    <ul class="step-points-list flex-grow-1 mb-4">
                        <li>Login ke portal menggunakan username & password dari operator.</li>
                        <li>Lengkapi data diri, riwayat pendidikan, dan informasi berkas.</li>
                        <li>Unggah seluruh dokumen persyaratan (PDF/JPG) sesuai petunjuk.</li>
                    </ul>
                    <a href="{{ route('login') }}" class="btn btn-purple btn-sm w-100 mt-auto fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Akun
                    </a>
                </article>
            </div>

            <!-- LANGKAH 4 -->
            <div class="col-md-6 col-lg-3">
                <article class="guide-step-card accent-gold p-4">
                    <div class="step-header-box">
                        <div class="step-pill-badge step-gold">
                            <span class="step-circle-num">4</span>
                            <span>Langkah 4</span>
                        </div>
                        <div class="step-mini-icon gold">
                            <i class="bi bi-megaphone-fill" aria-hidden="true"></i>
                        </div>
                    </div>
                    <h3 class="h5 fw-bold text-purple mb-2">Informasi & Pengumuman Lanjutan</h3>
                    <p class="text-muted small mb-3">
                        Pantau status pemeriksaan berkas dan ikuti perkembangan pengumuman resmi.
                    </p>
                    <ul class="step-points-list gold-checks flex-grow-1 mb-4">
                        <li>Pantau status verifikasi berkas secara berkala di ruang kerja akun Anda.</li>
                        <li>Segera perbaiki berkas jika operator memberikan catatan perbaikan.</li>
                        <li>Informasi lanjutan kelulusan dan tahapan perkuliahan akan diumumkan di website ini atau kanal resmi lainnya.</li>
                    </ul>
                    <div class="step-card-status-badge status-amber mt-auto">
                        <i class="bi bi-bell-fill text-warning"></i> Update via Website
                    </div>
                </article>
            </div>
        </div>

        <!-- BANTUAN & KEAMANAN -->
        <div class="row g-4 align-items-center">
            <div class="col-lg-8">
                <div class="notice-box info m-0 d-flex gap-3 align-items-start">
                    <i class="bi bi-shield-check text-purple fs-2" aria-hidden="true"></i>
                    <div>
                        <strong class="d-block mb-1 text-purple">Keamanan & Keaslian Kanal Informasi</strong>
                        <p class="mb-0 text-muted small">
                            Seluruh proses penerimaan mahasiswa baru PPG IAKN Palangka Raya tidak dipungut biaya perantara. Pastikan Anda hanya bertukar data dan menerima pesan dari kontak resmi pengelola PMB PPG IAKN Palangka Raya.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Ke Beranda
                    </a>
                    <a href="{{ route('public.claim') }}" class="btn btn-gold">
                        <i class="bi bi-person-plus-fill me-1"></i> Daftar Sekarang
                    </a>
                </div>
            </div>
        </div>
    </section>
</x-layouts::public>

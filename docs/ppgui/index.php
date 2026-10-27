<?php
/**
 * HOMEPAGE PMB PPG IAKN PALANGKA RAYA
 * Desain: HTML5 Bootstrap 5.3 + Alpine.js
 * Nuansa: Ungu & Kuning / Emas Dominan + Ornamen Dayak Kalimantan
 * Modular: Memakai header.php, navbar.php, dan footer.php terpusat
 */

$pageTitle = 'PMB PPG IAKN Palangka Raya | Portal Resmi Penerimaan Mahasiswa Baru';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="public-demo-strip"><i class="bi bi-eye me-1"></i> Pratinjau aplikasi · Jadwal, SK, dan kontak menggunakan data contoh. <a href="panduan.php" class="text-purple fw-bold ms-1">Jelajahi seluruh alur & peran <i class="bi bi-arrow-right"></i></a></div>

<!-- ========================================================================
     HERO SECTION DENGAN ORNAMEN DAYAK & DUAL CTA
     ======================================================================== -->
<section class="hero-section position-relative">
    <div class="hero-overlay-ornament"></div>

    <div class="container position-relative z-1">
        <div class="row align-items-center g-5">
            <!-- Kolom Teks Sambutan & CTA -->
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 hero-badge mb-3 badge-shimmer">
                    <!-- SVG Ukiran Dayak Mini -->
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2L19 7V17L12 22L5 17V7L12 2Z" fill="#FFB300"/>
                        <circle cx="12" cy="12" r="3" fill="#320D5C"/>
                    </svg>
                    <span>Gelombang I &bull; PPG Daljab Bimas Kristen Kemenag RI</span>
                </div>

                <h1 class="hero-title">
                    Penerimaan Mahasiswa Baru <br>
                    <span class="highlight-gold">PPG Dalam Jabatan</span> <br>
                    IAKN Palangka Raya
                </h1>

                <p class="hero-subtitle mb-4">
                    Portal resmi verifikasi dan registrasi bagi calon mahasiswa guru agama Kristen yang tercantum dalam <strong>Surat Keputusan (SK) Resmi Kementerian Agama Republik Indonesia</strong>.
                </p>

                <!-- Dual Call to Action Buttons -->
                <div class="d-flex flex-wrap gap-3 hero-btn-group mb-4">
                    <!-- Tombol Masuk Google -->
                    <a href="login.php" class="btn btn-gold btn-lg d-inline-flex align-items-center gap-2 anim-pulse-glow">
                        <svg width="20" height="20" viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                        </svg>
                        <span>Masuk dengan Google</span>
                    </a>

                    <!-- Tombol Klaim Jalur WhatsApp Non-Gmail -->
                    <button type="button" class="btn btn-outline-light btn-lg d-inline-flex align-items-center gap-2 border-2" data-bs-toggle="modal" data-bs-target="#modalKlaimWA">
                        <i class="bi bi-whatsapp text-success fs-5"></i>
                        <span>Daftar Lewat WhatsApp</span>
                    </button>
                </div>

                <!-- Info Ringkas Jalur -->
                <div class="d-flex align-items-center gap-3 pt-2 text-white-50 small">
                    <span class="d-flex align-items-center gap-1">
                        <i class="bi bi-shield-lock-fill text-warning"></i> Akses Terproteksi SK
                    </span>
                    <span>&bull;</span>
                    <span class="d-flex align-items-center gap-1">
                        <i class="bi bi-person-check-fill text-warning"></i> Verifikasi Operator Resmi
                    </span>
                </div>
            </div>

            <!-- Kolom Visual Card Interaktif (Glassmorphism & Dayak Motif) -->
            <div class="col-lg-5">
                <div class="hero-visual-container anim-float">
                    <!-- Glass Card -->
                    <div class="hero-glass-card">
                        <!-- Top Decorator Motif Dayak -->
                        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-light border-opacity-25">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill">
                                    <span x-data="{open: ppgRead().periodOpen}"><i class="bi bi-circle-fill me-1 small" :class="open ? 'text-success' : 'text-secondary'"></i><span x-text="open ? 'Periode Terbuka' : 'Periode Ditutup'"></span></span>
                                </span>
                            </div>
                            <span class="text-white-50 small">Tahun Ajaran 2026/2027</span>
                        </div>

                        <!-- Card Body Stats -->
                        <div class="mb-4">
                            <h5 class="text-white fw-bold mb-1">Status Kuota & Antrean Peserta</h5>
                            <p class="text-light-purple small mb-3" style="color: #D1C4E9;">Pembagian otomatis round-robin 4 Operator Kampus.</p>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="hero-stat-pill">
                                        <div class="text-warning fw-extrabold fs-3">400</div>
                                        <div class="text-white-50 small">Kapasitas SK Resmi</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="hero-stat-pill">
                                        <div class="text-warning fw-extrabold fs-3">4</div>
                                        <div class="text-white-50 small">Operator Verifikator</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress Bar Kuota -->
                            <div class="mb-2 d-flex justify-content-between text-white-50 small">
                                <span>Verifikasi Berjalan</span>
                                <span class="text-warning fw-bold">100 Peserta / Operator</span>
                            </div>
                            <div class="progress" style="height: 10px; background: rgba(255,255,255,0.15);">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 65%; background: linear-gradient(90deg, #FFB300, #FFE082);"></div>
                            </div>
                        </div>

                        <!-- Quick Checklist -->
                        <div class="bg-black bg-opacity-25 p-3 rounded-3 small">
                            <div class="text-warning fw-bold mb-2 d-flex align-items-center gap-1">
                                <i class="bi bi-lightning-charge-fill"></i> Langkah Cepat Peserta:
                            </div>
                            <ul class="list-unstyled mb-0 text-white-50 d-flex flex-column gap-1">
                                <li><i class="bi bi-check-circle-fill text-warning me-1"></i> Cari & cocokkan nama pada daftar SK</li>
                                <li><i class="bi bi-check-circle-fill text-warning me-1"></i> Masuk akun atau ajukan klaim kode WA</li>
                                <li><i class="bi bi-check-circle-fill text-warning me-1"></i> Isi biodata & kirim untuk verifikasi</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================
     FITUR INTERAKTIF 1: PENCARIAN & PENGECEKAN NAMA SK (ALPINE JS)
     ======================================================================== -->
<section id="cek-sk" class="py-5 bg-white position-relative" x-data="skChecker">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="lookup-box p-4 p-md-5">
                    <div class="row align-items-center mb-4">
                        <div class="col-md-8">
                            <span class="dayak-header-badge mb-2">
                                <i class="bi bi-shield-shaded text-warning"></i> Pengecekan Mandiri Peserta
                            </span>
                            <h3 class="fw-extrabold text-purple-darker mb-1" style="color: var(--ppg-purple-darker);">
                                Cek Kepesertaan SK Anda
                            </h3>
                            <p class="text-muted small mb-0">
                                Masukkan nama lengkap sesuai dokumen SK Ditjen Bimas Kristen Kemenag RI untuk memeriksa status penugasan operator Anda.
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <span class="badge bg-purple-subtle text-purple p-2 border" style="background-color: var(--ppg-purple-light); color: var(--ppg-purple-primary);">
                                <i class="bi bi-info-circle me-1"></i> Data diperbarui berkala
                            </span>
                        </div>
                    </div>

                    <!-- Input Form Alpine JS -->
                    <form @submit.prevent="checkName" class="mb-3">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light border-end-0 text-muted">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" x-model="searchQuery" class="form-control border-start-0 ps-0" placeholder="Ketik nama Anda (misal: Yohanes, Maria, Daniel...)" required>
                                </div>
                            </div>
                            <div class="col-md-4 d-grid">
                                <button type="submit" class="btn btn-gold btn-lg fw-bold d-flex align-items-center justify-content-center gap-2" :disabled="isSearching || !searchQuery.trim()">
                                    <span x-show="!isSearching"><i class="bi bi-check2-circle fs-5"></i> Cek Data SK</span>
                                    <span x-show="isSearching"><span class="spinner-border spinner-border-sm me-2"></span> Mencari...</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Hint pencarian -->
                    <div class="d-flex flex-wrap align-items-center gap-2 small text-muted mb-3">
                        <span>Coba contoh nama:</span>
                        <button type="button" @click="searchQuery = 'Yohanes Kristian'; checkName()" class="badge bg-light text-dark border border-secondary-subtle btn-sm py-1">Yohanes Kristian</button>
                        <button type="button" @click="searchQuery = 'Maria Magdalena'; checkName()" class="badge bg-light text-dark border border-secondary-subtle btn-sm py-1">Maria Magdalena</button>
                        <button type="button" @click="searchQuery = 'Daniel Sitorus'; checkName()" class="badge bg-light text-dark border border-secondary-subtle btn-sm py-1">Daniel Sitorus</button>
                    </div>

                    <!-- HASIL PENCARIAN (REAKTIF ALPINE JS) -->
                    <div x-show="hasSearched" x-cloak class="mt-4 pt-3 border-top">
                        <!-- Jika Ditemukan -->
                        <template x-if="result && result.found">
                            <div class="card border-success border-2 bg-success-subtle p-3 p-md-4 rounded-4">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge bg-success text-white px-3 py-1">
                                                <i class="bi bi-patch-check-fill me-1"></i> Terdaftar di SK Resmi
                                            </span>
                                            <span class="badge bg-warning text-dark fw-bold" x-text="result.nomor_sk"></span>
                                        </div>
                                        <h4 class="fw-bold text-dark mb-1" x-text="result.nama"></h4>
                                        <div class="text-secondary small d-flex flex-wrap gap-3">
                                            <span><i class="bi bi-card-heading text-primary me-1"></i> No. Peserta: <strong class="text-dark" x-text="result.nomor_peserta"></strong></span>
                                            <span><i class="bi bi-mortarboard text-primary me-1"></i> Prodi: <strong class="text-dark" x-text="result.prodi"></strong></span>
                                            <span><i class="bi bi-person-badge text-primary me-1"></i> Penugasan: <strong class="text-dark" x-text="result.operator"></strong></span>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column gap-2">
                                        <a href="login.php" class="btn btn-purple btn-sm py-2 px-3 fw-bold text-center">
                                            <i class="bi bi-google me-1"></i> Lanjutkan Masuk Google
                                        </a>
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#modalKlaimWA" class="btn btn-outline-success btn-sm py-2 px-3 fw-bold">
                                            <i class="bi bi-whatsapp me-1"></i> Klaim Nomor WhatsApp
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Jika Tidak Ditemukan -->
                        <template x-if="result && !result.found">
                            <div class="alert alert-warning border-0 p-4 rounded-4 d-flex gap-3 align-items-start" style="background-color: var(--ppg-gold-soft);">
                                <i class="bi bi-exclamation-triangle-fill text-warning fs-2 mt-1"></i>
                                <div>
                                    <h5 class="fw-bold text-dark mb-1">Nama Tidak Ditemukan Dalam Daftar Sementara</h5>
                                    <p class="small text-secondary mb-2" x-text="result.pesan"></p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#modalKlaimWA" class="btn btn-gold btn-sm fw-bold">
                                            <i class="bi bi-whatsapp me-1"></i> Minta Pemeriksaan Manual ke Operator
                                        </button>
                                        <button type="button" @click="resetCheck" class="btn btn-outline-secondary btn-sm">
                                            Cari Ulang
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================
     PILIHAN 2 JALUR AKSES PENDAFTARAN
     ======================================================================== -->
<section class="py-5 bg-light position-relative">
    <div class="container">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="section-badge mb-2">
                <i class="bi bi-signpost-split-fill text-warning"></i> Dua Jalur Akses
            </span>
            <h2 class="section-title">Pilih Metode Masuk Sesuai Kebutuhan Anda</h2>
            <p class="text-muted">
                Kementerian Agama menyediakan fleksibilitas akses baik bagi guru yang memiliki akun Gmail maupun yang hanya memiliki nomor WhatsApp aktif.
            </p>
        </div>

        <div class="row g-4 justify-content-center">
            <!-- Kartu Jalur 1: Akun Google -->
            <div class="col-lg-5 col-md-6">
                <div class="ppg-card card-accent-purple h-100 p-4 p-md-5 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="feature-icon-wrapper feature-icon-purple">
                                <i class="bi bi-google"></i>
                            </div>
                            <span class="badge bg-purple text-white px-3 py-1 rounded-pill" style="background-color: var(--ppg-purple-primary);">Jalur Utama (Rekomendasi)</span>
                        </div>

                        <h4 class="fw-bold text-dark mb-2">Jalur Masuk Akun Google</h4>
                        <p class="text-secondary small mb-4">
                            Login instan dan otomatis tanpa perlu menunggu kredensial manual. Cukup hubungkan dengan akun Gmail pribadi Anda.
                        </p>

                        <div class="d-flex flex-column gap-2 mb-4">
                            <div class="d-flex align-items-center gap-2 small">
                                <i class="bi bi-check-circle-fill text-success fs-6"></i>
                                <span>Aktivasi dashboard secara <strong>langsung & seketika</strong></span>
                            </div>
                            <div class="d-flex align-items-center gap-2 small">
                                <i class="bi bi-check-circle-fill text-success fs-6"></i>
                                <span>Klaim satu baris nama resmi dari SK Penetapan</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 small">
                                <i class="bi bi-check-circle-fill text-success fs-6"></i>
                                <span>Wajib mengisi nomor WhatsApp aktif untuk verifikasi operator</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <a href="login.php" class="btn btn-purple w-100 py-3 fw-bold d-flex align-items-center justify-content-center gap-2">
                            <span>Masuk Sekarang dengan Google</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Kartu Jalur 2: WhatsApp Non-Gmail -->
            <div class="col-lg-5 col-md-6">
                <div class="ppg-card card-accent-gold h-100 p-4 p-md-5 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="feature-icon-wrapper feature-icon-gold">
                                <i class="bi bi-whatsapp"></i>
                            </div>
                            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold">Jalur Alternatif</span>
                        </div>

                        <h4 class="fw-bold text-dark mb-2">Jalur WhatsApp (Non-Gmail)</h4>
                        <p class="text-secondary small mb-4">
                            Bagi calon peserta yang tidak memakai akun Gmail. Operator akan memverifikasi nomor dan mengirimkan kredensial sementara.
                        </p>

                        <div class="d-flex flex-column gap-2 mb-4">
                            <div class="d-flex align-items-center gap-2 small">
                                <i class="bi bi-check-circle-fill text-warning fs-6"></i>
                                <span>Kirim formulir klaim: Nama sesuai SK & No. WhatsApp</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 small">
                                <i class="bi bi-check-circle-fill text-warning fs-6"></i>
                                <span>Operator mengirimkan kode verifikasi ke nomor Anda</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 small">
                                <i class="bi bi-check-circle-fill text-warning fs-6"></i>
                                <span>Terima <strong>Login ID & Sandi Sementara</strong> manual via WA</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <button type="button" class="btn btn-gold w-100 py-3 fw-bold d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#modalKlaimWA">
                            <i class="bi bi-whatsapp"></i>
                            <span>Ajukan Klaim via WhatsApp</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================
     ALUR 6 TAHAP PENDAFTARAN (TIMELINE & INTERACTIVE STEPPER)
     ======================================================================== -->
<section id="alur-pendaftaran" class="py-5 bg-white position-relative">
    <div class="container">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="section-badge mb-2">
                <i class="bi bi-bezier2 text-warning"></i> Tata Alur Sistem
            </span>
            <h2 class="section-title">Alur Pendaftaran Hingga Diterima Lengkap</h2>
            <p class="text-muted">
                Proses terstruktur dirancang sesuai SOP MVP PMB PPG IAKN Palangka Raya untuk menjamin validitas berkas dan integrasi Neo Feeder.
            </p>
        </div>

        <div class="row g-4">
            <!-- Langkah 1 -->
            <div class="col-lg-4 col-md-6">
                <div class="ppg-card h-100 p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="step-number-badge">1</div>
                        <div>
                            <span class="badge bg-light text-muted border">Tahap Awal</span>
                            <h5 class="fw-bold text-dark mb-0">Cek SK & Pilih Jalur</h5>
                        </div>
                    </div>
                    <p class="small text-secondary mb-0">
                        Pastikan nama Anda ada pada SK Penetapan Bimas Kristen. Masuk menggunakan akun Google atau ajukan klaim jalur nomor WhatsApp aktif.
                    </p>
                </div>
            </div>

            <!-- Langkah 2 -->
            <div class="col-lg-4 col-md-6">
                <div class="ppg-card h-100 p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="step-number-badge">2</div>
                        <div>
                            <span class="badge bg-light text-muted border">Verifikasi Kontak</span>
                            <h5 class="fw-bold text-dark mb-0">Verifikasi WhatsApp</h5>
                        </div>
                    </div>
                    <p class="small text-secondary mb-0">
                        Operator menghubungi nomor WhatsApp Anda untuk mencocokkan kode balasan. Nomor yang terbukti aktif menjadi syarat wajib diterima lengkap.
                    </p>
                </div>
            </div>

            <!-- Langkah 3 -->
            <div class="col-lg-4 col-md-6">
                <div class="ppg-card h-100 p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="step-number-badge">3</div>
                        <div>
                            <span class="badge bg-light text-muted border">Formulir Draf</span>
                            <h5 class="fw-bold text-dark mb-0">Isi Biodata & Riwayat</h5>
                        </div>
                    </div>
                    <p class="small text-secondary mb-0">
                        Lengkapi NIK, data alamat, data kontak, serta pencarian perguruan tinggi dan program studi asal dari referensi terintegrasi.
                    </p>
                </div>
            </div>

            <!-- Langkah 4 -->
            <div class="col-lg-4 col-md-6">
                <div class="ppg-card h-100 p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="step-number-badge">4</div>
                        <div>
                            <span class="badge bg-light text-muted border">Pernyataan Sah</span>
                            <h5 class="fw-bold text-dark mb-0">Tinjau & Kirim Data</h5>
                        </div>
                    </div>
                    <p class="small text-secondary mb-0">
                        Tinjau seluruh ringkasan isian, setujui pernyataan kebenaran data, lalu tekan <em>Kirim Pendaftaran</em> untuk menghasilkan tanggal daftar resmi.
                    </p>
                </div>
            </div>

            <!-- Langkah 5 -->
            <div class="col-lg-4 col-md-6">
                <div class="ppg-card h-100 p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="step-number-badge">5</div>
                        <div>
                            <span class="badge bg-light text-muted border">Pemeriksaan Berkas</span>
                            <h5 class="fw-bold text-dark mb-0">Verifikasi Operator</h5>
                        </div>
                    </div>
                    <p class="small text-secondary mb-0">
                        Operator memeriksa kesesuaian data. Jika ada ketidakcocokan, pendaftar menerima instruksi perbaikan spesifik tanpa mengubah tanggal submit awal.
                    </p>
                </div>
            </div>

            <!-- Langkah 6 -->
            <div class="col-lg-4 col-md-6">
                <div class="ppg-card h-100 p-4 border-2" style="border-color: var(--ppg-gold-primary);">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="step-number-badge" style="background: linear-gradient(135deg, #FFB300, #F59E0B); color: #1E0836;">
                            <i class="bi bi-award-fill"></i>
                        </div>
                        <div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Tahap Akhir</span>
                            <h5 class="fw-bold text-dark mb-0">Diterima Lengkap</h5>
                        </div>
                    </div>
                    <p class="small text-secondary mb-0">
                        Status final pendaftaran berhasil ditetapkan! Berkas siap diproses untuk penetapan NIM dan sinkronisasi ke PDDikti Neo Feeder.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================
     JADWAL & AGENDA PENTING
     ======================================================================== -->
<section id="jadwal" class="py-5 bg-light position-relative">
    <div class="container">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="section-badge mb-2">
                <i class="bi bi-calendar-check-fill text-warning"></i> Timeline Kegiatan
            </span>
            <h2 class="section-title">Jadwal Pelaksanaan PMB PPG 2026</h2>
            <p class="text-muted">
                Perhatikan tanggal-tanggal penting agar proses verifikasi dan registrasi tidak melewati batas waktu yang telah ditentukan.
            </p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="text-white" style="background-color: var(--ppg-purple-dark);">
                                <tr>
                                    <th class="py-3 px-4" style="width: 80px;">No</th>
                                    <th class="py-3">Tahapan Kegiatan</th>
                                    <th class="py-3">Rentang Waktu (WIB)</th>
                                    <th class="py-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="py-3 px-4 fw-bold text-muted">01</td>
                                    <td>
                                        <div class="fw-bold text-dark">Publikasi Daftar Peserta SK & Pembagian Operator</div>
                                        <small class="text-muted">Penyusunan 400 nama peserta dan penetapan kuota 4 operator</small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">01 - 05 Oktober 2026</span></td>
                                    <td class="text-center"><span class="badge bg-success-subtle text-success"><i class="bi bi-check2"></i> Selesai</span></td>
                                </tr>
                                <tr class="table-warning bg-opacity-25">
                                    <td class="py-3 px-4 fw-bold text-warning-emphasis">02</td>
                                    <td>
                                        <div class="fw-bold text-dark">Aktivasi Akun & Klaim Jalur WhatsApp</div>
                                        <small class="text-muted">Calon peserta mulai mengklaim nama dan memverifikasi kontak WA</small>
                                    </td>
                                    <td><span class="badge bg-warning text-dark fw-bold">06 - 20 Oktober 2026</span></td>
                                    <td class="text-center"><span class="badge bg-warning text-dark fw-bold anim-pulse-glow"><i class="bi bi-clock-history me-1"></i> Sedang Berlangsung</span></td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-4 fw-bold text-muted">03</td>
                                    <td>
                                        <div class="fw-bold text-dark">Pengisian Biodata & Pendidikan Asal</div>
                                        <small class="text-muted">Penyimpanan draf dan pengiriman formulir resmi oleh pendaftar</small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">08 - 25 Oktober 2026</span></td>
                                    <td class="text-center"><span class="badge bg-secondary-subtle text-secondary">Akan Datang</span></td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-4 fw-bold text-muted">04</td>
                                    <td>
                                        <div class="fw-bold text-dark">Verifikasi Berkas & Masa Perbaikan</div>
                                        <small class="text-muted">Pemeriksaan oleh operator penugasan dan perbaikan data peserta</small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">15 - 30 Oktober 2026</span></td>
                                    <td class="text-center"><span class="badge bg-secondary-subtle text-secondary">Akan Datang</span></td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-4 fw-bold text-muted">05</td>
                                    <td>
                                        <div class="fw-bold text-dark">Penetapan Keputusan Diterima Lengkap</div>
                                        <small class="text-muted">Pengumuman final kelulusan administrasi dan persiapan Neo Feeder</small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">05 November 2026</span></td>
                                    <td class="text-center"><span class="badge bg-secondary-subtle text-secondary">Akan Datang</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================
     FAQ ACCORDION (INTERAKTIF ALPINE JS)
     ======================================================================== -->
<section id="faq" class="py-5 bg-white position-relative" x-data="faqAccordion">
    <div class="container">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="dayak-header-badge mb-2">
                <i class="bi bi-question-diamond-fill text-warning"></i> Pusat Informasi
            </span>
            <h2 class="section-title">Pertanyaan Sering Diajukan (FAQ)</h2>
            <p class="text-muted">
                Temukan jawaban cepat seputar kendala login, verifikasi WhatsApp, dan ketentuan SK resmi.
            </p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- FAQ Item 1 -->
                <div class="faq-item" :class="{ 'active': activeFaq === 1 }">
                    <div class="faq-header" @click="toggle(1)">
                        <span>Apakah orang yang namanya tidak ada di SK boleh mendaftar?</span>
                        <i class="bi fs-5 text-warning" :class="activeFaq === 1 ? 'bi-dash-circle-fill' : 'bi-plus-circle-fill'"></i>
                    </div>
                    <div class="faq-body" x-show="activeFaq === 1" x-collapse>
                        Tidak. Sesuai ketentuan Kementerian Agama RI, PMB PPG Dalam Jabatan ini dikhususkan bagi guru yang namanya tercantum dalam Surat Keputusan (SK) resmi yang telah diimpor oleh Administrator kampus. Jika nama Anda belum ada, silakan konfirmasi ke bidang Bimas Kristen Kemenag wilayah Anda.
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="faq-item" :class="{ 'active': activeFaq === 2 }">
                    <div class="faq-header" @click="toggle(2)">
                        <span>Bagaimana jika ejaan nama saya di KTP berbeda sedikit dengan SK?</span>
                        <i class="bi fs-5 text-warning" :class="activeFaq === 2 ? 'bi-dash-circle-fill' : 'bi-plus-circle-fill'"></i>
                    </div>
                    <div class="faq-body" x-show="activeFaq === 2" x-collapse>
                        Anda tetap dapat mengklaim entri SK tersebut. Pada formulir isian biodata, Anda dapat memasukkan nama resmi KTP dan catatan pendukung. Operator yang ditugaskan akan meninjau perbedaan tersebut dan mengesahkan kecocokannya.
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="faq-item" :class="{ 'active': activeFaq === 3 }">
                    <div class="faq-header" @click="toggle(3)">
                        <span>Mengapa nomor WhatsApp harus diverifikasi secara khusus?</span>
                        <i class="bi fs-5 text-warning" :class="activeFaq === 3 ? 'bi-dash-circle-fill' : 'bi-plus-circle-fill'"></i>
                    </div>
                    <div class="faq-body" x-show="activeFaq === 3" x-collapse>
                        Verifikasi WhatsApp adalah syarat mutlak untuk status <em>Diterima Lengkap</em>. Hal ini memastikan bahwa operator kampus dapat berkomunikasi langsung dengan Anda untuk konfirmasi berkas, perkuliahan, dan pengiriman informasi penting.
                    </div>
                </div>

                <!-- FAQ Item 4 -->
                <div class="faq-item" :class="{ 'active': activeFaq === 4 }">
                    <div class="faq-header" @click="toggle(4)">
                        <span>Bagaimana cara mendapatkan ID Login jika mendaftar tanpa Gmail?</span>
                        <i class="bi fs-5 text-warning" :class="activeFaq === 4 ? 'bi-dash-circle-fill' : 'bi-plus-circle-fill'"></i>
                    </div>
                    <div class="faq-body" x-show="activeFaq === 4" x-collapse>
                        Setelah mengajukan klaim nama SK dan nomor WhatsApp di form Jalur WhatsApp, operator resmi IAKN Palangka Raya akan menghubungi WhatsApp Anda secara manual untuk mengirimkan kode dan memberikan <strong>ID Login serta Sandi Sementara</strong>. Anda diwajibkan mengganti kata sandi pada login pertama.
                    </div>
                </div>

                <!-- FAQ Item 5 -->
                <div class="faq-item" :class="{ 'active': activeFaq === 5 }">
                    <div class="faq-header" @click="toggle(5)">
                        <span>Apakah status 'Diterima Lengkap' berarti langsung masuk ke PDDikti?</span>
                        <i class="bi fs-5 text-warning" :class="activeFaq === 5 ? 'bi-dash-circle-fill' : 'bi-plus-circle-fill'"></i>
                    </div>
                    <div class="faq-body" x-show="activeFaq === 5" x-collapse>
                        Status <em>Diterima Lengkap</em> menandakan kelulusan seleksi administrasi PMB internal kampus. Proses sinkronisasi data ke PDDikti (Neo Feeder) dijalankan secara terpisah oleh Administrator kampus setelah seluruh berkas valid.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================
     BANNER CALL TO ACTION DENGAN SENTUHAN ETNIK KALIMANTAN
     ======================================================================== -->
<section class="py-5 position-relative text-white" style="background: linear-gradient(135deg, var(--ppg-purple-dark) 0%, var(--ppg-purple-primary) 100%);">
    <!-- Dayak decorative side borders -->
    <div class="container position-relative z-1">
        <div class="row align-items-center justify-content-between g-4">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-2 text-warning mb-2 fw-semibold">
                    <i class="bi bi-mortarboard-fill"></i> Program Profesi Guru Dalam Jabatan
                </div>
                <h3 class="fw-bold mb-2 text-white">Siap Menuntaskan Pendaftaran Anda?</h3>
                <p class="text-light-purple mb-0" style="color: #E2D9F3;">
                    Periksa nama Anda sekarang dan lengkapi berkas untuk mendapatkan penetapan sebagai mahasiswa PPG IAKN Palangka Raya.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="#cek-sk" class="btn btn-gold fw-bold px-4 py-2">
                        <i class="bi bi-search me-1"></i> Cek Nama Sekarang
                    </a>
                    <button type="button" class="btn btn-outline-light px-4 py-2" data-bs-toggle="modal" data-bs-target="#modalKlaimWA">
                        <i class="bi bi-whatsapp text-success me-1"></i> Bantuan WA
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

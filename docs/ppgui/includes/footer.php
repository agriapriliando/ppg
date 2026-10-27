<?php
/**
 * Footer Template Terpusat
 * PMB PPG IAKN Palangka Raya
 */
?>
<!-- Modal Interaktif: Klaim Jalur WhatsApp (Alpine.js Enabled) -->
<div class="modal fade" id="modalKlaimWA" tabindex="-1" aria-labelledby="modalKlaimWALabel" aria-hidden="true" x-data="waClaimModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--ppg-radius-lg); overflow: hidden;">
            <div class="dayak-ribbon"></div>
            <div class="modal-header border-0 bg-light pb-2 pt-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="dayak-shield-badge" style="width: 36px; height: 36px;">
                        <i class="bi bi-whatsapp text-warning"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalKlaimWALabel">Klaim Jalur WhatsApp</h5>
                        <small class="text-muted">Bagi peserta tanpa akun Google / Gmail</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- STEP 1: Formulir Input Klaim -->
                <div x-show="step === 1">
                    <div class="alert alert-warning border-0 d-flex gap-2 align-items-start small p-3 mb-3" style="background-color: var(--ppg-gold-soft); color: #8C5B00;">
                        <i class="bi bi-info-circle-fill fs-5 mt-1"></i>
                        <div>
                            <strong>Penting:</strong> Pastikan Nama sama persis dengan yang tercantum dalam <strong>SK Penetapan PPG Bimas Kristen</strong>. Operator akan mengirim kredensial login sementara secara manual ke nomor WhatsApp Anda.
                        </div>
                    </div>

                    <p class="small text-muted">Pratinjau tampilan · Permohonan hanya disimpan pada browser ini.</p>
                    <p class="small text-danger" x-show="successMessage" x-text="successMessage" role="alert" x-cloak></p>
                    <form @submit.prevent="submitClaim">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary">NAMA PERSIS SESUAI SK *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-person text-purple"></i></span>
                                <input type="text" x-model="namaLengkap" required class="form-control border-start-0 ps-0" placeholder="Contoh: Yohanes Kristian, S.Pd.K">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary">NOMOR WHATSAPP AKTIF *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-whatsapp text-success"></i></span>
                                <input type="tel" x-model="nomorWA" pattern="[+0-9 ()-]{10,18}" required class="form-control border-start-0 ps-0" placeholder="Contoh: 081200000142">
                            </div>
                            <small class="text-muted" style="font-size: 0.78rem;">Nomor harus aktif dan bisa menerima pesan verifikasi dari kampus.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-secondary">NOMOR PESERTA SK (JIKA TERSEDIA)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-card-text text-muted"></i></span>
                                <input type="text" x-model="nomorPeserta" class="form-control border-start-0 ps-0" placeholder="Jika tercantum di dokumen SK">
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-gold py-2" :disabled="isSubmitting || !namaLengkap || !nomorWA">
                                <span x-show="!isSubmitting"><i class="bi bi-send-fill me-1"></i> Ajukan Klaim ke Operator</span>
                                <span x-show="isSubmitting"><span class="spinner-border spinner-border-sm me-1"></span> Mengirim permohonan...</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- STEP 2: Konfirmasi Berhasil -->
                <div x-show="step === 2" class="text-center py-3">
                    <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                        <i class="bi bi-check2-circle fs-1"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Permohonan Klaim Diterima!</h5>
                    <p class="text-muted small px-2">
                        Data calon pendaftar <strong><span x-text="namaLengkap"></span></strong> telah masuk ke antrean operator yang ditugaskan.
                    </p>
                    <div class="card bg-light border-0 p-3 text-start small mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Target Nomor WA:</span>
                            <strong class="text-dark" x-text="nomorWA.replace(/.(?=.{4})/g, '•')"></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Status:</span>
                            <span class="badge bg-warning text-dark">Menunggu Verifikasi Operator</span>
                        </div>
                    </div>
                    <p class="small text-secondary mb-3">
                        Operator resmi IAKN Palangka Raya akan menghubungi WhatsApp Anda untuk mencocokkan kode verifikasi dan mengirimkan <strong>ID Login & Kata Sandi Sementara</strong>.
                    </p>
                    <a href="portal.php?role=operator&amp;page=klaim" class="btn btn-outline-purple w-100 mb-2">Lihat antrean operator (demo)</a>
                    <a href="login.php?mode=lokal" class="d-block small text-purple mb-3">Sudah menerima kredensial? Masuk akun</a>
                    <button type="button" class="btn btn-light border w-100" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer Terpusat -->
<footer class="ppg-footer pt-5 pb-4 mt-5">
    <!-- Subtle Ornamen Dayak Background Watermark -->
    <div class="dayak-pattern-bg position-absolute w-100 h-100 top-0 start-0 opacity-25 pointer-events-none"></div>

    <div class="container position-relative">
        <div class="row g-4 mb-5">
            <!-- Kolom 1: Profil & Identitas Kampus -->
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="dayak-shield-badge">
                        <svg width="28" height="28" viewBox="0 0 40 40" fill="none">
                            <path d="M20 2L34 10V28L20 38L6 28V10L20 2Z" fill="#FFB300" stroke="#FFE082" stroke-width="1.5"/>
                            <path d="M20 6L30 12V26L20 34L10 26V12L20 6Z" fill="#320D5C"/>
                            <circle cx="20" cy="20" r="4" fill="#FFB300"/>
                        </svg>
                    </div>
                    <div>
                        <h5 class="text-white fw-bold mb-0">PMB PPG IAKN</h5>
                        <small class="text-warning text-uppercase fw-semibold" style="letter-spacing: 0.05em;">Palangka Raya &bull; Kalimantan Tengah</small>
                    </div>
                </div>
                <p class="small text-light-purple pe-lg-3" style="color: #D1C4E9;">
                    Sistem Penerimaan Mahasiswa Baru Program Profesi Guru (PPG) Dalam Jabatan berbasis SK Penetapan Ditjen Bimbingan Masyarakat Kristen Kementerian Agama RI.
                </p>
                <div class="d-flex gap-2">
                    <span class="badge border border-warning text-warning" style="background: rgba(255, 179, 0, 0.1);">
                        <i class="bi bi-shield-check me-1"></i> Terakreditasi BAN-PT
                    </span>
                    <span class="badge border border-light text-light" style="background: rgba(255, 255, 255, 0.1);">
                        <i class="bi bi-building me-1"></i> PTKN Kemenag
                    </span>
                </div>
            </div>

            <!-- Kolom 2: Tautan Cepat -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="text-warning fw-bold text-uppercase mb-3" style="letter-spacing: 0.05em;">Navigasi</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><a href="index.php" class="footer-link small"><i class="bi bi-chevron-right text-warning small me-1"></i> Beranda</a></li>
                    <li class="mb-2"><a href="index.php#cek-sk" class="footer-link small"><i class="bi bi-chevron-right text-warning small me-1"></i> Cek Nama SK</a></li>
                    <li class="mb-2"><a href="index.php#alur-pendaftaran" class="footer-link small"><i class="bi bi-chevron-right text-warning small me-1"></i> Alur Pendaftaran</a></li>
                    <li class="mb-2"><a href="index.php#jadwal" class="footer-link small"><i class="bi bi-chevron-right text-warning small me-1"></i> Jadwal PMB</a></li>
                    <li class="mb-2"><a href="index.php#faq" class="footer-link small"><i class="bi bi-chevron-right text-warning small me-1"></i> Bantuan & FAQ</a></li>
                </ul>
            </div>

            <!-- Kolom 3: Layanan Peserta & Jalur Masuk -->
            <div class="col-lg-3 col-md-6 col-6">
                <h6 class="text-warning fw-bold text-uppercase mb-3" style="letter-spacing: 0.05em;">Akses Masuk</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <a href="login.php" class="footer-link small d-flex align-items-center gap-1">
                            <i class="bi bi-google text-danger small"></i> Masuk Akun Google
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="klaim.php" class="footer-link small d-flex align-items-center gap-1">
                            <i class="bi bi-whatsapp text-success small"></i> Klaim Nomor WhatsApp
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="panduan.php" class="footer-link small d-flex align-items-center gap-1">
                            <i class="bi bi-file-earmark-pdf text-warning small"></i> Unduh SK Bimas Kristen
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="login.php?mode=pengelola" class="footer-link small d-flex align-items-center gap-1">
                            <i class="bi bi-person-lock text-info small"></i> Portal Operator & Admin
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Kolom 4: Kontak Kampus & Bantuan -->
            <div class="col-lg-3 col-md-6">
                <h6 class="text-warning fw-bold text-uppercase mb-3" style="letter-spacing: 0.05em;">Kontak & Layanan</h6>
                <div class="small mb-2 d-flex gap-2 align-items-start" style="color: #D1C4E9;">
                    <i class="bi bi-geo-alt-fill text-warning mt-1"></i>
                    <span>Jl. Tampung Penyang, RTA Milono Km. 6, Palangka Raya, Kalimantan Tengah 73111</span>
                </div>
                <div class="small mb-2 d-flex gap-2 align-items-center" style="color: #D1C4E9;">
                    <i class="bi bi-envelope-fill text-warning"></i>
                    <span>ppg@iaknpky.ac.id</span>
                </div>
                <div class="small mb-3 d-flex gap-2 align-items-center" style="color: #D1C4E9;">
                    <i class="bi bi-whatsapp text-success"></i>
                    <span>+62 812-3456-7890 (Helpdesk)</span>
                </div>
                <div class="p-2 rounded" style="background: rgba(255, 179, 0, 0.1); border: 1px dashed rgba(255, 179, 0, 0.4);">
                    <small class="text-warning d-block fw-bold"><i class="bi bi-info-circle me-1"></i> Zona Waktu Resmi:</small>
                    <small class="text-light" style="font-size: 0.78rem;">WIB (Waktu Indonesia Barat / UTC+7)</small>
                </div>
            </div>
        </div>

        <!-- Garis Pemisah & Hak Cipta -->
        <hr style="border-color: rgba(255, 179, 0, 0.25);">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small gap-2 text-center text-md-start" style="color: #B39DDB;">
            <div>
                &copy; <?= date('Y'); ?> <strong>PPG IAKN Palangka Raya</strong>. Hak Cipta Dilindungi Undang-Undang.
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-warning-emphasis">Kementerian Agama Republik Indonesia</span>
                <span>&bull;</span>
                <span>Kalimantan Tengah</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3.3 Bundle JS (with Popper) -->
<script src="assets/vendor/bootstrap.bundle.min.js"></script>

</body>
</html>

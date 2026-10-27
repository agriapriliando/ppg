<?php
/**
 * Navbar Template Terpusat
 * PMB PPG IAKN Palangka Raya
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Top Bar Info Kontak -->
<header class="bg-white border-bottom py-1 d-none d-md-block text-muted small">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <span class="d-inline-flex align-items-center gap-1 text-dark fw-medium">
                <i class="bi bi-geo-alt-fill text-warning"></i> Kampus IAKN Palangka Raya, Kalimantan Tengah
            </span>
            <span class="text-secondary">|</span>
            <span class="badge bg-purple-subtle text-purple border" style="background-color: var(--ppg-purple-light); color: var(--ppg-purple-primary);">
                <i class="bi bi-patch-check-fill text-warning me-1"></i> SK Ditjen Bimas Kristen Kemenag RI
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-secondary"><i class="bi bi-clock me-1 text-warning"></i> Layanan Verifikasi: 08.00 - 16.00 WIB</span>
            <a href="panduan.php" class="text-decoration-none fw-semibold text-success d-inline-flex align-items-center gap-1">
                <i class="bi bi-whatsapp"></i> Helpdesk Operator
            </a>
        </div>
    </div>
</header>

<!-- Main Navigation Bar -->
<nav class="navbar navbar-expand-xl ppg-navbar sticky-top">
    <div class="container-xl">
        <!-- Logo & Brand dengan Sentuhan Etnik Dayak (Talawang Shield Icon) -->
        <a class="navbar-brand d-flex align-items-center gap-2 py-1" href="index.php">
            <!-- SVG Talawang / Perisai Dayak Kalimantan Custom -->
            <div class="dayak-shield-badge flex-shrink-0" title="Perisai Dayak Talawang - Pelindung & Keteguhan">
                <svg width="24" height="24" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Bentuk Rangka Talawang Dayak -->
                    <path d="M20 2L34 10V28L20 38L6 28V10L20 2Z" fill="#FFB300" stroke="#FFE082" stroke-width="1.5"/>
                    <path d="M20 6L30 12V26L20 34L10 26V12L20 6Z" fill="#320D5C"/>
                    <!-- Motif Salur Ukir Dayak / Kamang -->
                    <circle cx="20" cy="20" r="4" fill="#FFB300"/>
                    <path d="M20 10V16M20 24V30M14 20H10M30 20H26" stroke="#FFB300" stroke-width="2" stroke-linecap="round"/>
                    <path d="M15 15L17 17M23 23L25 25M25 15L23 17M15 25L17 23" stroke="#FFE082" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="navbar-brand-text">
                <div class="brand-title">PPG <span style="color: var(--ppg-gold-primary);">IAKN</span></div>
                <div class="brand-subtitle d-none d-sm-block">Palangka Raya &bull; Kalteng</div>
            </div>
        </a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler border-0 shadow-none px-2" type="button" data-bs-toggle="collapse" data-bs-target="#ppgNavbarMenu" aria-controls="ppgNavbarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Links & CTA Buttons -->
        <div class="collapse navbar-collapse" id="ppgNavbarMenu">
            <ul class="navbar-nav mx-auto mb-2 mb-xl-0 py-2 py-xl-0 align-items-xl-center">
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= ($currentPage === 'index.php') ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-house-door me-1 text-warning"></i>Beranda
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom" href="index.php#cek-sk">
                        <i class="bi bi-search me-1 text-warning"></i>Cek SK
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom" href="index.php#alur-pendaftaran">
                        <i class="bi bi-diagram-3 me-1 text-warning"></i>Alur
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom" href="index.php#jadwal">
                        <i class="bi bi-calendar-event me-1 text-warning"></i>Jadwal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom" href="index.php#faq">
                        <i class="bi bi-question-circle me-1 text-warning"></i>FAQ
                    </a>
                </li>
            </ul>

            <!-- Dual Login / Akses Action (Google & WhatsApp) -->
            <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-xl-center gap-2 mt-3 mt-xl-0">
                <!-- Tombol Jalur WhatsApp Non-Gmail (Alpine Modal Trigger) -->
                <button type="button" class="btn btn-sm btn-outline-gold d-inline-flex align-items-center justify-content-center gap-1 px-3 py-2" data-bs-toggle="modal" data-bs-target="#modalKlaimWA">
                    <i class="bi bi-whatsapp text-success"></i>
                    <span>Jalur WA</span>
                </button>

                <!-- Tombol Masuk dengan Google -->
                <a href="login.php" class="btn btn-sm btn-purple d-inline-flex align-items-center justify-content-center gap-2 px-3 py-2 text-nowrap">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                    </svg>
                    <span>Masuk Akun</span>
                </a>
            </div>
        </div>
    </div>
</nav>

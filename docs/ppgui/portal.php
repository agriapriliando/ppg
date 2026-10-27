<?php
require __DIR__ . '/includes/demo.php';
$role = isset($menus[$_GET['role'] ?? '']) ? $_GET['role'] : 'pendaftar';
$page = $_GET['page'] ?? 'dashboard';
if (!isset($menus[$role][$page]) && !($page === 'detail' && $role !== 'pendaftar')) $page = 'dashboard';
$title = $page === 'detail' ? 'Detail Pendaftaran' : $menus[$role][$page][0];
$pageTitle = $title . ' | PMB PPG IAKN Palangka Raya';
require __DIR__ . '/includes/header.php';
?>
<div class="portal-shell" x-data="portalDemo('<?= e($role) ?>')" @keydown.escape.window="modal = ''">
    <aside class="portal-sidebar" :class="{'is-open': sidebar}" aria-label="Menu <?= e($roleNames[$role]) ?>">
        <a href="index.php" class="portal-brand"><span class="dayak-shield-badge"><i class="bi bi-mortarboard-fill"></i></span><span>PPG <b>IAKN</b><small>PALANGKA RAYA</small></span></a>
        <div class="sidebar-period"><span class="live-dot"></span> Gelombang I <small>TA 2026/2027</small></div>
        <div class="sidebar-label">RUANG <?= e(strtoupper($roleNames[$role])) ?></div>
        <nav class="sidebar-nav">
        <?php foreach ($menus[$role] as $key => [$label, $icon]): ?>
            <a href="<?= portal_url($role, $key) ?>" class="<?= $page === $key || ($page === 'detail' && $key === 'antrean') ? 'active' : '' ?>" <?= $page === $key ? 'aria-current="page"' : '' ?>><i class="bi bi-<?= $icon ?>"></i><span><?= e($label) ?></span><?php if ($key === 'klaim' && $role !== 'pendaftar'): ?><span class="menu-count" x-text="pendingClaims"></span><?php endif ?></a>
        <?php endforeach ?>
        </nav>
        <div class="sidebar-bottom"><div class="sidebar-help"><i class="bi bi-shield-check"></i><strong>PMB berbasis SK resmi</strong><small>Terarah, terverifikasi, terlayani.</small></div><a href="index.php"><i class="bi bi-box-arrow-left me-2"></i>Kembali ke portal publik</a></div>
    </aside>
    <button class="sidebar-scrim" x-cloak x-show="sidebar" @click="sidebar = false" aria-label="Tutup menu"></button>
    <div class="portal-main">
        <header class="portal-topbar"><div class="d-flex align-items-center gap-3"><button class="btn sidebar-toggle" @click="sidebar = !sidebar" :aria-expanded="sidebar" aria-label="Buka menu"><i class="bi bi-list fs-4"></i></button><div class="small text-muted d-none d-sm-block">Portal Penerimaan Mahasiswa Baru <span class="text-purple ms-2 fw-semibold">/ <?= e($roleNames[$role]) ?></span></div></div><div class="d-flex align-items-center gap-3"><a href="panduan.php" class="topbar-help" aria-label="Panduan aplikasi"><i class="bi bi-question-circle"></i></a><div class="user-avatar"><?= $role === 'pendaftar' ? 'YK' : ($role === 'operator' ? 'O1' : 'AD') ?></div><div class="small d-none d-md-block"><strong><?= $role === 'pendaftar' ? 'Yohanes Kristian' : ($role === 'operator' ? 'Operator 1' : 'Admin PMB') ?></strong><div class="text-muted" style="font-size:.72rem"><?= e($roleNames[$role]) ?></div></div></div></header>
        <div class="demo-toolbar"><span><i class="bi bi-eye me-2"></i>Pratinjau interaktif · Data contoh</span><div class="d-flex align-items-center gap-2"><label for="role-switch" class="d-none d-sm-inline">Lihat sebagai</label><select id="role-switch" class="form-select form-select-sm" onchange="location.href='portal.php?role='+this.value"><?php foreach ($roleNames as $key => $label): ?><option value="<?= $key ?>" <?= $key === $role ? 'selected' : '' ?>><?= $label ?></option><?php endforeach ?></select><button class="btn btn-sm" @click="modal = 'reset'" title="Ulangi data simulasi" aria-label="Ulangi data simulasi"><i class="bi bi-arrow-counterclockwise"></i></button></div></div>
        <main class="workspace-content" id="main-content">
            <div class="page-heading"><div><div class="breadcrumb-line"><a href="<?= portal_url($role) ?>">Ruang <?= e($roleNames[$role]) ?></a><i class="bi bi-chevron-right"></i><?= e($title) ?></div><h1><?= e($title) ?></h1><p><?= $role === 'pendaftar' ? 'Selamat datang, Yohanes. Siapkan langkah Anda menuju PPG.' : 'Kelola penerimaan PPG Dalam Jabatan dengan satu alur yang terarah.' ?></p></div><span class="period-chip"><i class="bi bi-calendar3"></i> Gelombang I · 2026/2027</span></div>
            <?php require __DIR__ . '/includes/views/' . ($role === 'pendaftar' ? 'pendaftar' : 'pengelola') . '.php'; ?>
        </main>
        <footer class="workspace-footer">© 2026 PPG IAKN Palangka Raya <span>Waktu layanan & tanggal pendaftaran: WIB (UTC+7)</span></footer>
    </div>
    <div class="demo-toast" role="status" x-show="toastMessage" x-cloak x-transition><i class="bi bi-check-circle-fill"></i><span x-text="toastMessage"></span><button @click="toastMessage = ''" aria-label="Tutup notifikasi">×</button></div>
    <?php require __DIR__ . '/includes/views/modals.php'; ?>
</div>
<script src="assets/vendor/bootstrap.bundle.min.js"></script>
</body></html>

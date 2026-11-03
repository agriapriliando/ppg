@props(['title' => 'Ruang Kerja PMB'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#4A154B">
    <title>{{ $title }} | PMB PPG IAKN</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<a href="#main-content" class="skip-link btn btn-gold">Lewati ke konten</a>
<div class="portal-shell" x-data="{sidebar:false}" @keydown.escape.window="sidebar=false">
    <aside class="portal-sidebar" :class="{'is-open':sidebar}" x-trap.noscroll.inert="sidebar" aria-label="Menu ruang kerja">
        <a href="{{ route('home') }}" class="portal-brand"><span class="dayak-shield-badge"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span><span>PPG <b>IAKN</b><small>PALANGKA RAYA</small></span></a>
        <div class="sidebar-period">Portal Penerimaan<small>PPG</small></div>
        <div class="sidebar-label">RUANG {{ mb_strtoupper(auth()->user()->role->label()) }}</div>
        <nav class="sidebar-nav">
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])><i class="bi bi-grid-1x2" aria-hidden="true"></i>Beranda</a>
            @if(in_array(auth()->user()->role, [App\Enums\UserRole::Operator, App\Enums\UserRole::Administrator], true))
                <a href="{{ route('participants.index') }}" @class(['active'=>request()->routeIs('participants.*')])><i class="bi bi-people" aria-hidden="true"></i>Peserta SK</a>
                <a href="{{ route('claims.index') }}" @class(['active'=>request()->routeIs('claims.*')])><i class="bi bi-whatsapp" aria-hidden="true"></i>Klaim & WhatsApp</a>
                <a href="{{ route('reviews.index') }}" @class(['active'=>request()->routeIs('reviews.*')])><i class="bi bi-list-check" aria-hidden="true"></i>Antrean Verifikasi</a>
                <a href="{{ route('admin.document-policies.uploads') }}" @class(['active'=>request()->routeIs('admin.document-policies.uploads*', 'admin.uploads*')])><i class="bi bi-files" aria-hidden="true"></i>Daftar Unggahan</a>
                <a href="{{ route('admin.imports') }}" @class(['active'=>request()->routeIs('admin.imports*')])><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i>Impor SK</a>
                <a href="{{ route('admin.assignments') }}" @class(['active'=>request()->routeIs('admin.assignments*')])><i class="bi bi-diagram-3" aria-hidden="true"></i>Pembagian Operator</a>
                <a href="{{ route('admin.periods') }}" @class(['active' => request()->routeIs('admin.periods')])><i class="bi bi-calendar-range" aria-hidden="true"></i>Periode & Gelombang</a>
                <a href="{{ route('admin.document-policies.index') }}" @class(['active'=>request()->routeIs('admin.document-policies.index', 'admin.document-policies.store', 'admin.workspaces.documents.*')])><i class="bi bi-folder-check" aria-hidden="true"></i>Pengaturan Berkas</a>
                <a href="{{ route('admin.exports') }}" @class(['active'=>request()->routeIs('admin.exports*')])><i class="bi bi-download" aria-hidden="true"></i>Ekspor Peserta</a>
                <a href="{{ route('admin.settings') }}" @class(['active' => request()->routeIs('admin.settings')])><i class="bi bi-sliders" aria-hidden="true"></i>Pengaturan Aplikasi</a>
                @if(!auth()->user()->isAdministrator())<a href="{{ route('activity') }}" @class(['active'=>request()->routeIs('activity')])><i class="bi bi-clock-history" aria-hidden="true"></i>Riwayat Aktivitas</a>@endif
            @endif
            @if(auth()->user()->isAdministrator())
                <a href="{{ route('admin.configuration-history') }}" @class(['active'=>request()->routeIs('admin.configuration-history')])><i class="bi bi-clock-history" aria-hidden="true"></i>Riwayat Konfigurasi</a>
                <a href="{{ route('admin.references') }}" @class(['active'=>request()->routeIs('admin.references*')])><i class="bi bi-mortarboard" aria-hidden="true"></i>Referensi & Pernyataan</a>
                <a href="{{ route('admin.users') }}" @class(['active' => request()->routeIs('admin.users*')])><i class="bi bi-person-badge" aria-hidden="true"></i>Kelola Pengguna</a>
                <a href="{{ route('admin.audit') }}" @class(['active' => request()->routeIs('admin.audit')])><i class="bi bi-shield-check" aria-hidden="true"></i>Audit Aktivitas</a>
            @endif
            <a href="{{ route('account') }}" @class(['active' => request()->routeIs('account', 'password.*')])><i class="bi bi-person-gear" aria-hidden="true"></i>Akun & Keamanan</a>
            <a href="{{ route('guide') }}"><i class="bi bi-question-circle" aria-hidden="true"></i>Pusat Bantuan</a>
        </nav>
        <div class="sidebar-bottom"><div class="sidebar-help"><i class="bi bi-shield-check" aria-hidden="true"></i><strong>PMB berbasis SK resmi</strong><small>Terarah, terverifikasi, terlayani.</small></div><form method="post" action="{{ route('logout') }}">@csrf<button class="btn w-100 text-start"><i class="bi bi-box-arrow-left me-2" aria-hidden="true"></i>Keluar Akun</button></form></div>
    </aside>
    <button class="sidebar-scrim" x-cloak x-show="sidebar" @click="sidebar=false" aria-label="Tutup menu"></button>
    <div class="portal-main">
        <header class="portal-topbar"><div class="d-flex align-items-center gap-3"><button class="btn sidebar-toggle" @click="sidebar=!sidebar" :aria-expanded="sidebar" aria-label="Buka menu"><i class="bi bi-list fs-4" aria-hidden="true"></i></button><span class="small text-muted">Portal PMB PPG <span class="text-purple ms-2 fw-semibold">/ {{ auth()->user()->role->label() }}</span></span></div><div class="d-flex align-items-center gap-2"><span class="user-avatar">{{ auth()->user()->initials() }}</span><strong class="small d-none d-md-block">{{ auth()->user()->name }}</strong></div></header>
        <main class="workspace-content" id="main-content"><div class="page-heading"><div><div class="breadcrumb-line">Ruang {{ auth()->user()->role->label() }}</div><h1>{{ $title }}</h1></div><span class="period-chip"><i class="bi bi-clock" aria-hidden="true"></i>Asia/Jakarta · WIB</span></div><x-notice />{{ $slot }}</main>
        <footer class="workspace-footer">PPG IAKN Palangka Raya <span>Seluruh jadwal menggunakan WIB (UTC+7)</span></footer>
    </div>
</div>
@livewireScripts
</body>
</html>

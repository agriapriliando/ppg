@props(['title' => 'PMB PPG IAKN Palangka Raya'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#4A154B">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<a href="#main-content" class="skip-link btn btn-gold">Lewati ke konten</a>
<div class="topbar py-2"><div class="container d-flex justify-content-between small"><span>PPG Dalam Jabatan · IAKN Palangka Raya</span><span>Waktu layanan: WIB</span></div></div>
<nav class="navbar navbar-expand-lg ppg-navbar bg-white" aria-label="Navigasi utama">
    <div class="container">
        <a class="navbar-brand d-flex gap-2 align-items-center" href="{{ route('home') }}"><span class="dayak-shield-badge"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span><span class="fw-bold text-purple">PPG IAKN<small class="d-block text-muted fs-6">PALANGKA RAYA</small></span></a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#public-nav" aria-controls="public-nav" aria-expanded="false" aria-label="Buka navigasi"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="public-nav"><div class="navbar-nav ms-auto align-items-lg-center gap-2">
            <a class="nav-link" href="{{ route('home') }}">Beranda</a><a class="nav-link" href="{{ route('home') }}#alur-pendaftaran">Alur Pendaftaran</a><a class="nav-link" href="{{ route('guide') }}">Panduan</a>
            @auth<a href="{{ route('dashboard') }}" class="btn btn-purple">Ruang Kerja Saya</a>@else<a href="{{ route('login') }}" class="btn btn-purple"><i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Masuk Akun</a>@endauth
        </div></div>
    </div>
</nav>
<main id="main-content">{{ $slot }}</main>
<footer class="py-4 text-center text-muted border-top"><div class="container"><strong>PPG IAKN Palangka Raya</strong><div class="small mt-1">Penerimaan berdasarkan SK resmi · Tanggal dan jadwal menggunakan WIB</div></div></footer>
@livewireScripts
</body>
</html>

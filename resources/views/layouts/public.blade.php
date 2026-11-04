@props([
    'title' => 'PMB PPG IAKN Palangka Raya · Penerimaan Mahasiswa Baru',
    'description' => 'Portal resmi Penerimaan Mahasiswa Baru (PMB) Pendidikan Profesi Guru (PPG) Dalam Jabatan Bimas Kristen Institut Agama Kristen Negeri (IAKN) Palangka Raya.',
    'keywords' => 'PPG IAKN Palangka Raya, PMB PPG, PPG Bimas Kristen, Pendaftaran PPG, Guru Agama Kristen, IAKN Palangka Raya, Sertifikasi Guru',
    'image' => asset('assets/' . rawurlencode('ppg iaknpky seo.jpg')),
    'type' => 'website',
    'canonical' => url()->current(),
])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4A154B">

    <!-- SEO Primary Meta Tags -->
    <title>{{ $title }}</title>
    <meta name="title" content="{{ $title }}">
    <meta name="description" content="{{ $description }}">
    <meta name="keywords" content="{{ $keywords }}">
    <meta name="author" content="IAKN Palangka Raya">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="{{ $canonical }}">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="PPG IAKN Palangka Raya">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:secure_url" content="{{ $image }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="943">
    <meta property="og:image:height" content="530">
    <meta property="og:image:alt" content="{{ $title }}">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ $canonical }}">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">
    <meta name="twitter:image:alt" content="{{ $title }}">

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {!! json_encode([
        chr(64) . 'context' => 'https://schema.org',
        chr(64) . 'graph' => [
            [
                chr(64) . 'type' => 'EducationalOrganization',
                chr(64) . 'id' => url('/') . '#organization',
                'name' => 'PPG IAKN Palangka Raya',
                'alternateName' => 'Pendidikan Profesi Guru Institut Agama Kristen Negeri Palangka Raya',
                'url' => url('/'),
                'logo' => [
                    chr(64) . 'type' => 'ImageObject',
                    'url' => asset('favicon_io_new/android-chrome-512x512.png'),
                ],
                'image' => $image,
                'description' => 'Program Pendidikan Profesi Guru (PPG) Dalam Jabatan Bimas Kristen pada Institut Agama Kristen Negeri (IAKN) Palangka Raya.',
            ],
            [
                chr(64) . 'type' => 'WebSite',
                chr(64) . 'id' => url('/') . '#website',
                'url' => url('/'),
                'name' => 'PMB PPG IAKN Palangka Raya',
                'description' => $description,
                'publisher' => [
                    chr(64) . 'id' => url('/') . '#organization',
                ],
                'inLanguage' => 'id-ID',
            ],
            [
                chr(64) . 'type' => 'WebPage',
                chr(64) . 'id' => $canonical . '#webpage',
                'url' => $canonical,
                'name' => $title,
                'description' => $description,
                'isPartOf' => [
                    chr(64) . 'id' => url('/') . '#website',
                ],
                'primaryImageOfPage' => [
                    chr(64) . 'type' => 'ImageObject',
                    'url' => $image,
                ],
                'inLanguage' => 'id-ID',
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
    </script>

    <!-- Favicon & Touch Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon_io_new/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon_io_new/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon_io_new/favicon-16x16.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon_io_new/favicon.ico') }}">
    <link rel="manifest" href="{{ asset('favicon_io_new/site.webmanifest') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<a href="#main-content" class="skip-link btn btn-gold">Lewati ke konten</a>
<nav class="navbar navbar-expand-lg ppg-navbar bg-white" aria-label="Navigasi utama">
    <div class="container">
        <a class="navbar-brand d-flex gap-2 align-items-center" href="{{ route('home') }}">
            <img src="{{ asset('favicon_io_new/android-chrome-192x192.png') }}" alt="Logo PPG IAKN" width="38" height="38" style="object-fit: contain; flex-shrink: 0;" class="brand-logo">
            <span class="fw-bold text-purple">PPG IAKN<small class="d-block text-muted fs-6">PALANGKA RAYA</small></span>
        </a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#public-nav" aria-controls="public-nav" aria-expanded="false" aria-label="Buka navigasi"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="public-nav"><div class="navbar-nav ms-auto align-items-lg-center gap-2">
            <a class="nav-link" href="{{ route('home') }}">Beranda</a><a class="nav-link" href="{{ route('home') }}#alur-pendaftaran">Alur Pendaftaran</a><a class="nav-link" href="{{ route('guide') }}">Panduan</a>
            @auth<a href="{{ route('dashboard') }}" class="btn btn-purple"><i class="bi bi-person-circle me-1" aria-hidden="true"></i>Akun Saya</a>@else<a href="{{ route('login') }}" class="btn btn-purple"><i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Masuk Akun</a>@endauth
        </div></div>
    </div>
</nav>
<main id="main-content">{{ $slot }}</main>
<footer class="py-4 text-center text-muted border-top"><div class="container"><strong>PPG IAKN Palangka Raya</strong><div class="small mt-1">Penerimaan berdasarkan SK resmi · Tanggal dan jadwal menggunakan WIB</div></div></footer>
@livewireScripts
</body>
</html>

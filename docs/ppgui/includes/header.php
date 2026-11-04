<?php
/**
 * Header Template Terpusat
 * PMB PPG IAKN Palangka Raya
 */
if (!isset($pageTitle)) {
    $pageTitle = 'PMB PPG IAKN Palangka Raya | Portal Penerimaan Mahasiswa Baru';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="Portal Resmi Penerimaan Mahasiswa Baru Program Profesi Guru (PPG) Dalam Jabatan - Institut Agama Kristen Negeri (IAKN) Palangka Raya.">
    <meta name="theme-color" content="#4A154B">

    <!-- Favicon & Icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon_io_new/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon_io_new/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon_io_new/favicon-16x16.png">
    <link rel="shortcut icon" href="/favicon_io_new/favicon.ico">
    <link rel="manifest" href="/favicon_io_new/site.webmanifest">

    <!-- Google Fonts: Plus Jakarta Sans & Cinzel Decorative for Dayak Touch -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Cinzel:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="assets/vendor/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons.min.css">

    <!-- Custom Style PPG (Ungu, Kuning, Ornamen Dayak) -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/portal.css">

    <!-- Alpine.js Collapse Plugin & Core -->
    <script defer src="assets/vendor/alpine-collapse.min.js"></script>
    <script defer src="assets/vendor/alpine.min.js"></script>
    <script src="assets/js/portal.js"></script>
    <script src="assets/js/app.js"></script>
</head>
<body x-data>

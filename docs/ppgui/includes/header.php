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
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><polygon points='50,5 90,25 90,75 50,95 10,75 10,25' fill='%234A154B'/><polygon points='50,15 80,30 80,70 50,85 20,70 20,30' fill='%23FFB300'/><text x='50' y='58' font-size='24' font-weight='bold' text-anchor='middle' fill='%231E0836'>PPG</text></svg>">

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

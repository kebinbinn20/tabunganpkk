<?php
/**
 * TabunganPKK - Header Component
 *
 * Komponen ini membuka dokumen HTML dan memuat library tampilan yang dipakai
 * halaman dashboard: Bootstrap, Font Awesome, Chart.js, font, dan CSS aplikasi.
 * Setiap halaman menetapkan $pageTitle sebelum komponen ini dipanggil.
 */
if (!defined('APP_NAME')) {
    define('APP_NAME', 'TabunganPKK');
}
$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Favicon / PKK Icon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2315803d'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg>">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js for Dashboards -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS TabunganPKK -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<div class="app-wrapper">

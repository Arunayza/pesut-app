<?php
/**
 * Header Component
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';
$flash = getFlash();
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="PESUT - Sistem Pengajuan Elektronik Surat Izin & Cuti Terpadu PTUN Samarinda">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/icon_logo.png">
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('pesut-theme') || 'dark');</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tom Select CSS untuk Searchable Dropdown -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script>window.PESUT_BASE_URL = '<?= BASE_URL ?>';</script>
</head>
    <?php
    $designVersion = 'v2'; // V2 is now the default — V1 backup in cleanup/v1/
    if ($designVersion === 'v2'): 
    ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style_v2.css?v=<?= filemtime(__DIR__ . '/../assets/css/style_v2.css') ?>">
    <style>
        body.v2-layout {
            /* Background ditangani oleh style_v2.css (hero-bg-2.jpg) */
        }
    </style>
    <?php endif; ?>
</head>
<body class="<?= ($designVersion === 'v2') ? 'v2-layout' : '' ?>">
<?php if ($designVersion === 'v2'): ?>
    <?php require_once __DIR__ . '/header_v2.php'; ?>
    <div class="main-container-v2">
        <!-- V2 Flash Messages -->
        <?php if ($flash): ?>
            <div class="flash-message <?= $flash['type'] ?>" style="margin-bottom: 0;">
                <?= $flash['type'] === 'success' ? '✅' : '❌' ?>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
<?php else: ?>
    <div class="app-layout">
<?php endif; ?>

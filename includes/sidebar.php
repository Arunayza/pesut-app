<?php
/**
 * Sidebar Navigation Component
 */
$currentPage = basename($_SERVER['PHP_SELF']);

// Hitung notifikasi belum dibaca
require_once __DIR__ . '/../helpers/notifikasi.php';
require_once __DIR__ . '/../helpers/role_helper.php';
$notifCount = hitungNotifBelumDibaca($pdo, $_SESSION['user_id']);
$showKelolaPengajuan = canAccessKelolaPengajuan($_SESSION['role'], $pdo, $_SESSION['user_id']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo-badge-sidebar">
            <img src="<?= BASE_URL ?>/assets/icon_logo.png" alt="<?= APP_NAME ?> Logo" class="brand-logo">
        </div>
        <div>
            <h2><?= APP_NAME ?></h2>
            <small><?= APP_INSTANSI ?></small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Menu Utama</div>
            <a href="<?= BASE_URL ?>/index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" title="Dashboard">
                <span class="nav-icon">🏠</span> <span class="nav-link-text">Dashboard</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/riwayat.php" class="nav-link <?= $currentPage === 'riwayat.php' ? 'active' : '' ?>" title="Riwayat Pengajuan">
                <span class="nav-icon">📄</span> <span class="nav-link-text">Riwayat Pengajuan</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Buat Pengajuan</div>
            <a href="<?= BASE_URL ?>/pages/form_cuti.php" class="nav-link <?= $currentPage === 'form_cuti.php' ? 'active' : '' ?>" title="Pengajuan Cuti">
                <span class="nav-icon">🏖️</span> <span class="nav-link-text">Pengajuan Cuti</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/form_izin.php" class="nav-link <?= $currentPage === 'form_izin.php' ? 'active' : '' ?>" title="Pengajuan Izin">
                <span class="nav-icon">📝</span> <span class="nav-link-text">Pengajuan Izin</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/form_pulang_cepat.php" class="nav-link <?= $currentPage === 'form_pulang_cepat.php' ? 'active' : '' ?>" title="Terlambat / Pulang Cepat">
                <span class="nav-icon">⏰</span> <span class="nav-link-text">Terlambat / Pulang Cepat</span>
            </a>
        </div>

        <?php if ($showKelolaPengajuan || $_SESSION['role'] === 'admin'): ?>
        <div class="nav-section">
            <div class="nav-section-title"><?php 
                if ($_SESSION['role'] === 'admin') echo 'Admin IT';
                elseif ($_SESSION['role'] === 'staf_kpot') echo 'Kepegawaian';
                else echo 'Pejabat Struktural';
            ?></div>
            
            <?php if ($showKelolaPengajuan): ?>
            <a href="<?= BASE_URL ?>/pages/kelola_pengajuan.php" class="nav-link <?= $currentPage === 'kelola_pengajuan.php' ? 'active' : '' ?>" title="Kelola Pengajuan">
                <span class="nav-icon">📌</span> <span class="nav-link-text">Kelola Pengajuan</span>
            </a>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="<?= BASE_URL ?>/pages/kelola_user.php" class="nav-link <?= $currentPage === 'kelola_user.php' || $currentPage === 'tambah_user.php' ? 'active' : '' ?>" title="Kelola Pegawai">
                <span class="nav-icon">👥</span> <span class="nav-link-text">Kelola Pegawai</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/pages/profil.php" class="user-info-link" title="Lihat Profil">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'] ?? 'U', 0, 1)) ?></div>
                <div style="flex: 1; min-width: 0;">
                    <div class="user-name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($_SESSION['nama'] ?? 'User') ?></div>
                    <div class="user-role" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($_SESSION['jabatan'] ?? '-') ?></div>
                </div>
            </div>
        </a>
        <a href="<?= BASE_URL ?>/proses/logout.php" class="sidebar-logout-btn" title="Keluar Akun">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="20" height="20">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
            </svg>
        </a>
    </div>
</aside>

<div class="main-content">
    <header class="header">
        <div class="header-left">
            <button class="menu-toggle" id="menu-toggle">☰</button>
            <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <div class="header-right">
            <!-- Notification Bell -->
            <div class="notif-wrapper" id="notif-wrapper">
                <button class="notif-bell" id="notif-bell" onclick="toggleNotifDropdown()" title="Notifikasi">
                    🔔
                    <?php if ($notifCount > 0): ?>
                    <span class="notif-badge" id="notif-badge"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notif-dropdown">
                    <div class="notif-dropdown-header">
                        <h4>Notifikasi</h4>
                        <button onclick="bacaSemuaNotif()" class="notif-mark-all" title="Tandai semua dibaca">✓ Baca Semua</button>
                    </div>
                    <div class="notif-list" id="notif-list">
                        <div class="notif-loading">Memuat...</div>
                    </div>
                </div>
            </div>

            <!-- Theme Toggle -->
            <button class="theme-toggle" id="theme-toggle" onclick="toggleTheme()" title="Ganti tema terang/gelap">
                <span class="theme-icon" id="theme-icon">🌙</span>
            </button>

            <span class="header-date"><?= namaHari(date('Y-m-d')) . ', ' . formatTanggal(date('Y-m-d')) ?></span>
        </div>
    </header>

    <div class="content-area">
        <?php if ($flash): ?>
            <div class="flash-message <?= $flash['type'] ?>">
                <?= $flash['type'] === 'success' ? '✅' : '❌' ?>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

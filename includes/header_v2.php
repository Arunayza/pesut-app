<?php
if (!isset($showPending)) {
    if (!function_exists('canAccessKelolaPengajuan')) {
        require_once __DIR__ . '/../helpers/role_helper.php';
    }
    $showPending = canAccessKelolaPengajuan($_SESSION['role'], $pdo, $_SESSION['user_id']);
}
?>
<!-- Mobile Overlay & Sidebar -->
<div class="mobile-overlay" id="mobile-overlay" onclick="toggleMobileMenuV2()"></div>
<div class="mobile-sidebar-v2" id="mobile-sidebar">
    <div class="mobile-sidebar-header">
        <img src="<?= BASE_URL ?>/assets/icon_logo.png" alt="Logo" width="32">
        <h3>Menu Utama</h3>
        <button onclick="toggleMobileMenuV2()" style="background:none; border:none; font-size:18px; color:var(--text-muted); cursor:pointer;">✕</button>
    </div>
    <div class="mobile-sidebar-nav">
        <?php if ($showPending || $_SESSION['role'] === 'admin'): ?>
            <a href="<?= BASE_URL ?>/pages/kelola_pengajuan.php" class="mobile-nav-link">📌 Kelola Pengajuan</a>
        <?php endif; ?>
        <?php if (in_array($_SESSION['role'], ['admin', 'staf_kpot'])): ?>
            <a href="<?= BASE_URL ?>/pages/kelola_user.php" class="mobile-nav-link">👥 Kelola Pegawai</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/pages/riwayat.php" class="mobile-nav-link">📄 Riwayat Pengajuan</a>
    </div>
    <div class="mobile-sidebar-footer">
        <a href="<?= BASE_URL ?>/pages/profil.php" class="mobile-nav-link">👤 Edit Profil</a>
        <button class="mobile-nav-link theme-btn" onclick="toggleTheme()">
            <span id="theme-icon-mobile">🌙</span> Ganti Tema
        </button>
        <a href="<?= BASE_URL ?>/proses/logout.php" class="mobile-nav-link logout-btn">🚪 Keluar Akun</a>
    </div>
</div>

<!-- Glassmorphism Navbar -->
<nav class="navbar-v2">
    <!-- Burger Menu for Mobile -->
    <button class="burger-menu-v2" onclick="toggleMobileMenuV2()">☰</button>

    <a href="<?= BASE_URL ?>/dashboard_v2.php" class="navbar-brand">
        <img src="<?= BASE_URL ?>/assets/icon_logo.png" alt="Logo">
        <h2>PENGADILAN TATA USAHA NEGARA SAMARINDA</h2>
    </a>

    <!-- Mobile Notif Container (Visible only on mobile) -->
    <div class="mobile-notif-container" id="mobile-notif-container"></div>

    <div class="navbar-right">
        <?php
        $currentPage = basename($_SERVER['PHP_SELF']);
        $isDashboard = in_array($currentPage, ['dashboard_v2.php', 'index.php']);
        ?>

        <!-- Nav Links (hidden on mobile, burger menu used instead) -->
        <div class="navbar-nav-links">
            <?php if ($showPending || $_SESSION['role'] === 'admin'): ?>
                <a href="<?= BASE_URL ?>/pages/kelola_pengajuan.php" class="nav-link-v2 <?= $currentPage === 'kelola_pengajuan.php' ? 'active' : '' ?>">Kelola</a>
            <?php endif; ?>
            <?php if (in_array($_SESSION['role'], ['admin', 'staf_kpot'])): ?>
                <a href="<?= BASE_URL ?>/pages/kelola_user.php" class="nav-link-v2 <?= $currentPage === 'kelola_user.php' || $currentPage === 'tambah_user.php' ? 'active' : '' ?>">Pegawai</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/pages/riwayat.php" class="nav-link-v2 <?= $currentPage === 'riwayat.php' ? 'active' : '' ?>">Riwayat</a>
        </div>

        <?php
        // Notifikasi count for global use
        if (!function_exists('hitungNotifBelumDibaca')) {
            require_once __DIR__ . '/../helpers/notifikasi.php';
        }
        if (!isset($notifCount)) {
            $notifCount = hitungNotifBelumDibaca($pdo, $_SESSION['user_id']);
        }
        ?>

        <?php if ($isDashboard): ?>
        <!-- Notif Container - Only on Dashboard -->
        <div id="navbar-notif-container" style="display: flex; align-items: center;">
            <div class="notif-wrapper" id="notif-wrapper" style="position: relative;">
            <button class="notif-bell" id="notif-bell-btn" onclick="toggleNotifDropdown()" style="background:none; border:none; color:inherit; font-size:20px; cursor:pointer; padding:8px; display:flex; align-items:center; justify-content:center; position:relative; transition:all 0.3s;">
                🔔
                <?php if ($notifCount > 0): ?>
                <span class="notif-badge" id="notif-badge" style="position:absolute; top:-4px; right:-4px; background:var(--red-500); color:#fff; font-size:11px; font-weight:700; padding:3px 7px; border-radius:12px; border:2px solid var(--navy-900);"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
                <?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notif-dropdown" style="top:calc(100% + 12px); right:0; z-index: 100;">
                <div class="notif-dropdown-header" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--glass-border);">
                    <h3 style="font-size: 15px; font-weight: 700; margin: 0; color: var(--text-primary);">Notifikasi</h3>
                    <a href="javascript:void(0)" onclick="bacaSemuaNotif()" style="color: var(--gold-400); text-decoration: none; font-size: 13px; font-weight: 700;">✓ Baca Semua</a>
                </div>
                <div class="notif-list" id="notif-list" style="max-height: 350px; overflow-y: auto;">
                    <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 13px;">Memuat notifikasi...</div>
                </div>
            </div>
        </div>
        </div>
        <?php endif; ?>

        <!-- User Info / Dropdown -->
        <div class="profile-wrapper" id="profile-wrapper">
            <div class="user-profile-v2" onclick="toggleProfileDropdown(event)">
                <div class="info">
                    <span class="name"><?= htmlspecialchars(explode(',', $_SESSION['nama'])[0]) ?></span>
                    <span class="role"><?= htmlspecialchars($_SESSION['jabatan']) ?></span>
                </div>
                <div class="avatar"><?= strtoupper(substr($_SESSION['nama'] ?? 'U', 0, 1)) ?></div>
                <span class="dropdown-icon">▼</span>
            </div>
            <div class="profile-dropdown-menu" id="profile-dropdown-menu">
                <a href="<?= BASE_URL ?>/pages/profil.php" class="profile-menu-item">
                    <span>👤</span> Edit Profil
                </a>
                <div class="profile-menu-item theme-switch-item" onclick="toggleTheme()">
                    <span id="theme-icon-dropdown">🌙</span>
                    <span style="flex:1;">Mode Gelap</span>
                    <div class="theme-toggle-switch" id="theme-toggle-switch">
                        <div class="theme-toggle-knob"></div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/proses/logout.php" class="profile-menu-item logout">
                    <span>🚪</span> Keluar Akun
                </a>
            </div>
        </div>
    </div>
</nav>

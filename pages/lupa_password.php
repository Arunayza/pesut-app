<?php
/**
 * Halaman Lupa Password
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

if (isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/index.php');
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password — <?= APP_NAME ?></title>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('pesut-theme') || 'dark');</script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <div class="logo-icon">🔑</div>
                <h1>Lupa Password</h1>
                <p>Masukkan email yang terdaftar pada akun Anda</p>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message <?= $flash['type'] ?>" style="margin-bottom: 20px;">
                    <?= $flash['type'] === 'success' ? '✅' : '❌' ?>
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/proses/lupa_password.php" method="POST">
                <div class="form-group">
                    <label for="email">Alamat Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="contoh: budi@gmail.com" required autofocus>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;" id="btn-kirim">
                    Kirim Link Reset
                </button>
            </form>

            <div style="text-align: center; margin-top: 24px;">
                <a href="<?= BASE_URL ?>/pages/login.php" style="font-size: 13px; color: var(--text-muted); text-decoration: none;">
                    ← Kembali ke Login
                </a>
            </div>
        </div>
    </div>

    <!-- Theme Toggle -->
    <button class="theme-toggle" id="theme-toggle" onclick="toggleTheme()" style="position: fixed; top: 20px; right: 20px; z-index: 100;">
        <span class="theme-icon" id="theme-icon">🌙</span>
    </button>

    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.getElementById('btn-kirim');
            btn.textContent = '⏳ Mengirim email...';
            btn.disabled = true;
        });
    </script>
</body>
</html>

<?php
/**
 * Halaman Form Reset Password Baru
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

if (isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/index.php');
}

$token = $_GET['token'] ?? '';
$isValid = false;

if (!empty($token)) {
    // Validasi token dan expiry
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $isValid = true;
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — <?= APP_NAME ?></title>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('pesut-theme') || 'dark');</script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <div class="logo-icon">🔒</div>
                <h1>Buat Password Baru</h1>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message <?= $flash['type'] ?>" style="margin-bottom: 20px;">
                    <?= $flash['type'] === 'success' ? '✅' : '❌' ?>
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if (!$isValid): ?>
                <div class="flash-message error" style="margin-bottom: 20px; text-align: center;">
                    ❌ Link reset password tidak valid atau sudah kedaluwarsa.
                </div>
                <div style="text-align: center;">
                    <a href="<?= BASE_URL ?>/pages/lupa_password.php" class="btn btn-secondary">Request Link Baru</a>
                </div>
            <?php else: ?>
                <form action="<?= BASE_URL ?>/proses/reset_password.php" method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <div class="form-group">
                        <label for="password_baru">Password Baru</label>
                        <div style="position: relative;">
                            <input type="password" id="password_baru" name="password_baru" class="form-control" placeholder="Minimal 6 karakter" required minlength="6" autofocus style="padding-right: 48px;">
                            <button type="button" class="toggle-pwd" onclick="togglePwd('password_baru', this)" style="
                                position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
                                background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 18px;
                                display: flex; align-items: center;
                            ">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="konfirmasi_password">Konfirmasi Password Baru</label>
                        <div style="position: relative;">
                            <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control" placeholder="Ketik ulang password baru" required minlength="6" style="padding-right: 48px;">
                            <button type="button" class="toggle-pwd" onclick="togglePwd('konfirmasi_password', this)" style="
                                position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
                                background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 18px;
                                display: flex; align-items: center;
                            ">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;">
                        Simpan Password Baru
                    </button>
                </form>
            <?php endif; ?>

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
        const svgEye = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`;
        const svgEyeSlash = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>`;

        function togglePwd(inputId, btn) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                btn.innerHTML = svgEyeSlash;
            } else {
                input.type = 'password';
                btn.innerHTML = svgEye;
            }
        }

        <?php if ($isValid): ?>
        document.querySelector('form').addEventListener('submit', function(e) {
            const pwd1 = document.getElementById('password_baru').value;
            const pwd2 = document.getElementById('konfirmasi_password').value;
            if (pwd1 !== pwd2) {
                e.preventDefault();
                alert('Konfirmasi password tidak cocok!');
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>

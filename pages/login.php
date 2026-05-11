<?php
/**
 * Halaman Login
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

// Jika sudah login, redirect ke dashboard
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
    <meta name="description" content="Login - PESUT PTUN Samarinda">
    <title>Login — <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/icon_logo.png">
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('pesut-theme') || 'dark');</script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <style>
        /* Override background for Login */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('<?= BASE_URL ?>/assets/hero-bg-2.jpg');
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
            background-repeat: no-repeat;
            opacity: 0.05;
            z-index: 0;
            pointer-events: none;
        }
        [data-theme="light"] body::before {
            filter: invert(1) hue-rotate(180deg);
        }

        /* ===== Login Page Overrides ===== */

        /* Logo badge — iOS-style icon, works di dark & light */
        .logo-badge {
            width: 96px;
            height: 96px;
            background: #ffffff;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow:
                0 4px 24px rgba(0, 0, 0, 0.25),
                0 1px 4px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255,255,255,0.8);
            overflow: hidden;
            transition: box-shadow 0.3s, transform 0.3s;
        }
        .logo-badge:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.3),
                0 2px 8px rgba(0, 0, 0, 0.2);
        }
        [data-theme="light"] .logo-badge {
            box-shadow:
                0 4px 20px rgba(29, 78, 216, 0.2),
                0 1px 4px rgba(0, 0, 0, 0.08),
                inset 0 1px 0 rgba(255,255,255,0.9);
        }
        .login-brand-logo {
            width: 78px;
            height: 78px;
            object-fit: contain;
        }

        /* Card lebih rapi */
        .login-card {
            padding: 44px 40px 40px !important;
        }

        /* Logo section spacing */
        .login-logo {
            margin-bottom: 28px !important;
        }
        .login-logo h1 {
            margin-bottom: 4px;
        }
        .login-logo p {
            margin-top: 3px !important;
        }

        /* Divider antara logo & form */
        .login-divider {
            height: 1px;
            background: var(--glass-border);
            margin: 0 0 24px;
            border: none;
        }

        /* Form groups lebih konsisten */
        .login-form-group {
            margin-bottom: 18px;
        }
        .login-form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 7px;
            letter-spacing: 0.3px;
        }

        /* Input wrapper (password) */
        .input-wrapper {
            position: relative;
        }
        .input-wrapper .form-control {
            padding-right: 48px;
        }
        .input-eye-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 6px;
            color: var(--text-muted);
            transition: color 0.2s;
            display: flex;
            align-items: center;
            border-radius: 6px;
        }
        .input-eye-btn:hover {
            color: var(--text-primary);
            background: rgba(255,255,255,0.06);
        }
        [data-theme="light"] .input-eye-btn:hover {
            background: rgba(0,0,0,0.05);
        }

        /* Forgot password row */
        .forgot-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }
        .forgot-link {
            font-size: 12px;
            color: var(--blue-400);
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .forgot-link:hover {
            opacity: 0.8;
            color: var(--blue-400);
        }

        /* Submit button */
        .login-submit {
            width: 100%;
            margin-top: 6px;
            padding: 13px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.5px;
            border-radius: 12px !important;
        }

        /* Footer login */
        .login-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 11.5px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Sidebar logo blending */
        .brand-logo {
            mix-blend-mode: screen;
        }
        [data-theme="light"] .brand-logo {
            filter: brightness(0) invert(1);
            mix-blend-mode: normal;
            opacity: 0.92;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">

            <!-- Logo & App Identity -->
            <div class="login-logo">
                <div class="logo-badge">
                    <img src="<?= BASE_URL ?>/assets/icon_logo.png"
                         alt="<?= APP_NAME ?> Logo"
                         class="login-brand-logo">
                </div>
                <h1><?= APP_NAME ?></h1>
                <p><?= APP_FULL_NAME ?></p>
                <p style="font-size: 12px; color: var(--text-muted);"><?= APP_INSTANSI ?></p>
            </div>

            <hr class="login-divider">

            <?php if ($flash): ?>
                <div class="flash-message <?= $flash['type'] ?>" style="margin-bottom: 20px;">
                    <?= $flash['type'] === 'success' ? '✅' : '❌' ?>
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <!-- Form Login -->
            <form action="<?= BASE_URL ?>/proses/login_proses.php" method="POST" id="login-form">

                <div class="login-form-group">
                    <label for="nip">NIP</label>
                    <input type="text" id="nip" name="nip"
                           class="form-control"
                           placeholder="Masukkan NIP Anda"
                           required autofocus>
                </div>

                <div class="login-form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password"
                               class="form-control"
                               placeholder="Masukkan password"
                               required>
                        <button type="button" id="toggle-password"
                                class="input-eye-btn"
                                onclick="togglePassword()"
                                title="Lihat password">
                            <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </button>
                    </div>
                    <div class="forgot-row">
                        <a href="<?= BASE_URL ?>/pages/lupa_password.php" class="forgot-link">Lupa Password?</a>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary login-submit">
                    Masuk
                </button>
            </form>

            <!-- Footer -->
            <div class="login-footer">
                © <?= date('Y') ?> <?= APP_INSTANSI ?>
            </div>

        </div>
    </div>

    <!-- Theme Toggle -->
    <button class="theme-toggle" id="theme-toggle" onclick="toggleTheme()"
            title="Ganti tema"
            style="position: fixed; top: 20px; right: 20px; z-index: 100;">
        <span class="theme-icon" id="theme-icon">🌙</span>
    </button>

    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
    <script>
        const svgEye = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`;
        const svgEyeSlash = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>`;

        function togglePassword() {
            const input = document.getElementById('password');
            const btn   = document.getElementById('toggle-password');
            if (input.type === 'password') {
                input.type   = 'text';
                btn.innerHTML = svgEyeSlash;
                btn.title    = 'Sembunyikan password';
            } else {
                input.type   = 'password';
                btn.innerHTML = svgEye;
                btn.title    = 'Lihat password';
            }
        }
    </script>
</body>
</html>

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
    <style>
        /* Override background for Login/Lupa Password */
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

        .login-card {
            padding: 44px 40px 40px !important;
        }

        .login-logo {
            margin-bottom: 28px !important;
        }
        .login-logo h1 {
            margin-bottom: 4px;
            font-size: 24px;
        }
        .login-logo p {
            margin-top: 3px !important;
        }

        .login-divider {
            height: 1px;
            background: var(--glass-border);
            margin: 0 0 24px;
            border: none;
        }

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

        .login-submit {
            width: 100%;
            margin-top: 6px;
            padding: 13px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.5px;
            border-radius: 12px !important;
        }

        .login-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 11.5px;
            color: var(--text-muted);
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="login-logo">
                <div class="logo-badge">
                    <img src="<?= BASE_URL ?>/assets/icon_logo.png"
                         alt="<?= APP_NAME ?> Logo"
                         class="login-brand-logo">
                </div>
                <h1>Lupa Password</h1>
                <p style="font-size: 12px; color: var(--text-muted);">Masukkan email yang terdaftar pada akun Anda</p>
            </div>

            <hr class="login-divider">

            <?php if ($flash): ?>
                <div class="flash-message <?= $flash['type'] ?>" style="margin-bottom: 20px;">
                    <?= $flash['type'] === 'success' ? '✅' : '❌' ?>
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/proses/lupa_password.php" method="POST">
                <div class="login-form-group">
                    <label for="email">Alamat Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="contoh: budi@gmail.com" required autofocus>
                </div>

                <button type="submit" class="btn btn-primary login-submit" id="btn-kirim">
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
    <button class="theme-toggle" id="theme-toggle" onclick="toggleTheme()" style="position: fixed; top: 20px; right: 20px; z-index: 100;" title="Ganti Tema">
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

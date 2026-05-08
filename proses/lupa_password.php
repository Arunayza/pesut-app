<?php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../config/mail.php'; // Includes PHPMailer setup

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/lupa_password.php');
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setFlash('error', 'Format email tidak valid!');
    redirect(BASE_URL . '/pages/lupa_password.php');
}

// Cek apakah email ada di database
$stmt = $pdo->prepare("SELECT id, nama FROM users WHERE email = ? AND aktif = 1 LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    // Generate token acak yang aman (32 bytes = 64 karakter hex)
    $token = bin2hex(random_bytes(32));
    
    // Set expiry time (misal: 1 jam dari sekarang)
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Simpan token ke database
    $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $updateStmt->execute([$token, $expires, $user['id']]);

    // Kirim email
    $mailer = getMailer();
    if ($mailer) {
        try {
            $mailer->addAddress($email, $user['nama']);
            $mailer->Subject = 'Reset Password PESUT PTUN Samarinda';
            
            $resetLink = 'http://localhost' . BASE_URL . '/pages/reset_password.php?token=' . $token;
            
            // Format HTML Email
            $mailer->isHTML(true);
            $mailer->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                    <h2 style='color: #2c3e50;'>Halo, {$user['nama']}</h2>
                    <p>Kami menerima permintaan untuk mereset password akun PESUT Anda.</p>
                    <p>Silakan klik tombol di bawah ini untuk membuat password baru. Link ini hanya berlaku selama <strong>1 jam</strong>.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetLink}' style='background-color: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;'>
                            Reset Password Sekarang
                        </a>
                    </div>
                    <p style='color: #7f8c8d; font-size: 14px;'>Jika Anda tidak merasa meminta reset password, abaikan email ini. Akun Anda akan tetap aman.</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='color: #95a5a6; font-size: 12px;'>Jika tombol tidak berfungsi, copy dan paste link berikut ke browser Anda:<br>
                    <a href='{$resetLink}'>{$resetLink}</a></p>
                </div>
            ";
            
            $mailer->send();
            
        } catch (Exception $e) {
            error_log("Gagal mengirim email reset password ke $email: " . $mailer->ErrorInfo);
            // Tetap berikan pesan sukses agar penyerang tidak bisa menebak email mana yang valid/tidak, 
            // ATAU bisa juga kita beri tahu kalau gagal kirim email. Kita pilih kasih tahu saja untuk UX yg baik.
            setFlash('error', 'Gagal mengirim email. Silakan coba lagi nanti atau hubungi Administrator.');
            redirect(BASE_URL . '/pages/lupa_password.php');
            exit;
        }
    } else {
        setFlash('error', 'Sistem email belum dikonfigurasi dengan benar.');
        redirect(BASE_URL . '/pages/lupa_password.php');
        exit;
    }
}

// Selalu berikan pesan sukses yang ambigu demi keamanan (Security Best Practice)
// Jadi orang jahat tidak tahu apakah email ini terdaftar atau tidak.
setFlash('success', 'Jika email Anda terdaftar, link untuk reset password telah dikirimkan. Silakan cek inbox atau folder spam Anda.');
redirect(BASE_URL . '/pages/lupa_password.php');

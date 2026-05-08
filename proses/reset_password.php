<?php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/login.php');
}

$token = $_POST['token'] ?? '';
$passwordBaru = $_POST['password_baru'] ?? '';
$konfirmasi = $_POST['konfirmasi_password'] ?? '';

if (empty($token) || empty($passwordBaru) || empty($konfirmasi)) {
    setFlash('error', 'Semua field wajib diisi!');
    redirect(BASE_URL . '/pages/reset_password.php?token=' . urlencode($token));
}

if ($passwordBaru !== $konfirmasi) {
    setFlash('error', 'Konfirmasi password tidak cocok!');
    redirect(BASE_URL . '/pages/reset_password.php?token=' . urlencode($token));
}

if (strlen($passwordBaru) < 6) {
    setFlash('error', 'Password minimal 6 karakter!');
    redirect(BASE_URL . '/pages/reset_password.php?token=' . urlencode($token));
}

// Validasi token dan expiry
$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'Link reset password tidak valid atau sudah kedaluwarsa!');
    redirect(BASE_URL . '/pages/login.php');
}

// Update password dan hapus token
$hashedPassword = password_hash($passwordBaru, PASSWORD_DEFAULT);

$updateStmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
$success = $updateStmt->execute([$hashedPassword, $user['id']]);

if ($success) {
    setFlash('success', 'Password berhasil direset! Silakan login menggunakan password baru Anda.');
    redirect(BASE_URL . '/pages/login.php');
} else {
    setFlash('error', 'Terjadi kesalahan sistem, silakan coba lagi.');
    redirect(BASE_URL . '/pages/reset_password.php?token=' . urlencode($token));
}

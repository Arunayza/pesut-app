<?php
/**
 * Verifikasi Email
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    setFlash('error', 'Token verifikasi tidak valid!');
    redirect(BASE_URL . '/pages/profil.php');
    exit;
}

// Cari user berdasarkan token
$stmt = $pdo->prepare("SELECT id FROM users WHERE email_verify_token = ? AND email IS NOT NULL LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'Token verifikasi tidak valid atau sudah kadaluarsa!');
    redirect(BASE_URL . '/pages/profil.php');
    exit;
}

// Update status verifikasi
try {
    $stmt = $pdo->prepare("UPDATE users SET email_verified_at = NOW(), email_verify_token = NULL WHERE id = ?");
    $stmt->execute([$user['id']]);
    setFlash('success', 'Email Anda berhasil diverifikasi! Sekarang Anda dapat mengubah password.');
} catch (Exception $e) {
    setFlash('error', 'Terjadi kesalahan saat memverifikasi email.');
}

redirect(BASE_URL . '/pages/profil.php');
?>

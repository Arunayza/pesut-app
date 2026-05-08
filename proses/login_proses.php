<?php
/**
 * Proses Login
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/login.php');
}

$nip      = trim($_POST['nip'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($nip) || empty($password)) {
    setFlash('error', 'NIP dan Password wajib diisi!');
    redirect(BASE_URL . '/pages/login.php');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ? AND aktif = 1");
$stmt->execute([$nip]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    setFlash('error', 'NIP atau Password salah!');
    redirect(BASE_URL . '/pages/login.php');
}

// Set session
$_SESSION['user_id']    = $user['id'];
$_SESSION['nip']        = $user['nip'];
$_SESSION['nama']       = $user['nama'];
$_SESSION['jabatan']    = $user['jabatan'];
$_SESSION['pangkat']    = $user['pangkat'];
$_SESSION['unit_kerja'] = $user['unit_kerja'];
$_SESSION['role']       = $user['role'];
$_SESSION['foto']       = $user['foto'];

setFlash('success', 'Selamat datang, ' . $user['nama'] . '!');
redirect(BASE_URL . '/index.php');

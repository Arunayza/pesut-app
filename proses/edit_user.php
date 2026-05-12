<?php
/**
 * Proses Edit Pegawai
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/role_helper.php';

requireLogin();

if ($_SESSION['role'] !== 'admin') {
    setFlash('error', 'Anda tidak memiliki akses!');
    redirect(BASE_URL . '/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/kelola_user.php');
}

$id = (int)($_POST['id'] ?? 0);
$nip = trim($_POST['nip'] ?? '');
$nama = trim($_POST['nama'] ?? '');
$jenis_kelamin = trim($_POST['jenis_kelamin'] ?? 'L');
$status_pegawai = trim($_POST['status_pegawai'] ?? 'PNS');
$tgl_mulai_kerja = trim($_POST['tgl_mulai_kerja'] ?? date('Y-m-d'));
$jabatan = trim($_POST['jabatan'] ?? '');
$pangkat = trim($_POST['pangkat'] ?? '');
$role = trim($_POST['role'] ?? 'staf_umk');
$atasanId = !empty($_POST['atasan_id']) ? (int) $_POST['atasan_id'] : null;

// Jika atasan tidak diisi manual, auto-assign berdasarkan role
if ($atasanId === null) {
    $atasanId = getDefaultAtasanId($role, $pdo);
}
$aktif = isset($_POST['aktif']) ? 1 : 0;
$resetPassword = isset($_POST['reset_password']) ? 1 : 0;

// Saldo cuti sekarang dikelola dari Kontrol Cuti

if ($id <= 0) {
    setFlash('error', 'ID User tidak valid!');
    redirect(BASE_URL . '/pages/kelola_user.php');
}

// Validasi input dasar
if (empty($nip) || empty($nama) || empty($jabatan)) {
    setFlash('error', 'Field NIP, Nama, dan Jabatan wajib diisi!');
    redirect(BASE_URL . '/pages/edit_user.php?id=' . $id);
}

// Cek apakah NIP sudah terdaftar oleh user lain
$stmt = $pdo->prepare("SELECT id FROM users WHERE nip = ? AND id != ?");
$stmt->execute([$nip, $id]);
if ($stmt->fetch()) {
    setFlash('error', 'NIP sudah terdaftar pada pegawai lain!');
    redirect(BASE_URL . '/pages/edit_user.php?id=' . $id);
}

try {
    $pdo->beginTransaction();

    if ($resetPassword) {
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET nip=?, nama=?, jenis_kelamin=?, status_pegawai=?, tgl_mulai_kerja=?, password=?, jabatan=?, pangkat=?, role=?, atasan_id=?, aktif=?
            WHERE id=?
        ");
        $stmt->execute([$nip, $nama, $jenis_kelamin, $status_pegawai, $tgl_mulai_kerja, $hashedPassword, $jabatan, $pangkat, $role, $atasanId, $aktif, $id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET nip=?, nama=?, jenis_kelamin=?, status_pegawai=?, tgl_mulai_kerja=?, jabatan=?, pangkat=?, role=?, atasan_id=?, aktif=?
            WHERE id=?
        ");
        $stmt->execute([$nip, $nama, $jenis_kelamin, $status_pegawai, $tgl_mulai_kerja, $jabatan, $pangkat, $role, $atasanId, $aktif, $id]);
    }

    // Saldo cuti dikelola melalui halaman edit_saldo_cuti secara terpisah

    $pdo->commit();
    setFlash('success', 'Data pegawai berhasil diperbarui!');
    redirect(BASE_URL . '/pages/kelola_user.php');

} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect(BASE_URL . '/pages/edit_user.php?id=' . $id);
}

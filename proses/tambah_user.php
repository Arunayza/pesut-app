<?php
/**
 * Proses Tambah Pegawai Baru
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

$nips = $_POST['nip'] ?? [];
$namas = $_POST['nama'] ?? [];
$jenis_kelamins = $_POST['jenis_kelamin'] ?? [];
$status_pegawais = $_POST['status_pegawai'] ?? [];
$jabatans = $_POST['jabatan'] ?? [];
$pangkats = $_POST['pangkat'] ?? [];
$roles = $_POST['role'] ?? [];
$tgls = $_POST['tgl_mulai_kerja'] ?? [];

if (empty($nips)) {
    setFlash('error', 'Tidak ada data pegawai yang ditambahkan.');
    redirect(BASE_URL . '/pages/tambah_user.php');
}

$defaultPassword = 'password123';
$hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
$tahun = (int) TAHUN_AKTIF;

try {
    $pdo->beginTransaction();

    $stmtInsert = $pdo->prepare("
        INSERT INTO users (nip, nama, jenis_kelamin, status_pegawai, tgl_mulai_kerja, password, jabatan, pangkat, role, aktif)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmtCek = $pdo->prepare("SELECT id FROM users WHERE nip = ?");

    $berhasil = 0;
    foreach ($nips as $i => $nip) {
        $nip = trim($nip);
        $nama = trim($namas[$i]);
        $jk = $jenis_kelamins[$i] ?? 'L';
        $status_pegawai = $status_pegawais[$i] ?? 'PNS';
        $tgl = $tgls[$i] ?? date('Y-m-d');
        $jabatan = trim($jabatans[$i]);
        $pangkat = trim($pangkats[$i]);
        $role = $roles[$i] ?? 'staf_umk';

        if (empty($nip) || empty($nama) || empty($jabatan)) {
            continue; // Skip data tidak lengkap
        }

        // Cek jika NIP sudah ada
        $stmtCek->execute([$nip]);
        if ($stmtCek->fetch()) {
            continue; // Skip jika duplikat
        }

        $stmtInsert->execute([$nip, $nama, $jk, $status_pegawai, $tgl, $hashedPassword, $jabatan, $pangkat, $role]);
        $userId = $pdo->lastInsertId();

        // Auto-assign atasan berdasarkan role
        $defaultAtasanId = getDefaultAtasanId($role, $pdo);
        if ($defaultAtasanId) {
            $stmtAtasan = $pdo->prepare("UPDATE users SET atasan_id = ? WHERE id = ?");
            $stmtAtasan->execute([$defaultAtasanId, $userId]);
        }
        
        $berhasil++;
    }

    $pdo->commit();
    
    if ($berhasil > 0) {
        setFlash('success', "$berhasil Pegawai baru berhasil ditambahkan! Password default: password123");
    } else {
        setFlash('error', 'Gagal menambahkan pegawai. Pastikan NIP tidak duplikat dan data lengkap.');
    }
    redirect(BASE_URL . '/pages/kelola_user.php');

} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect(BASE_URL . '/pages/tambah_user.php');
}

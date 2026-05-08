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

$jatah_tahunan_lalu = (int)($_POST['jatah_tahunan_lalu'] ?? 0);
$jatah_tahunan = (int)($_POST['jatah_tahunan'] ?? 0);
$jatah_sakit = (int)($_POST['jatah_sakit'] ?? 0);
$jatah_alasan_penting = (int)($_POST['jatah_alasan_penting'] ?? 0);
$jatah_melahirkan = (int)($_POST['jatah_melahirkan'] ?? 0);

$terpakai_tahunan_lalu = (int)($_POST['terpakai_tahunan_lalu'] ?? 0);
$terpakai_tahunan = (int)($_POST['terpakai_tahunan'] ?? 0);
$terpakai_sakit = (int)($_POST['terpakai_sakit'] ?? 0);
$terpakai_alasan_penting = (int)($_POST['terpakai_alasan_penting'] ?? 0);
$terpakai_melahirkan = (int)($_POST['terpakai_melahirkan'] ?? 0);

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

    // Update Saldo Cuti
    $tahun = (int) TAHUN_AKTIF;
    $stmtCuti = $pdo->prepare("
        INSERT INTO saldo_cuti (
            user_id, tahun, 
            jatah_tahunan_lalu, jatah_tahunan, jatah_sakit, jatah_melahirkan, jatah_alasan_penting,
            terpakai_tahunan_lalu, terpakai_tahunan, terpakai_sakit, terpakai_melahirkan, terpakai_alasan_penting
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            jatah_tahunan_lalu = VALUES(jatah_tahunan_lalu),
            jatah_tahunan = VALUES(jatah_tahunan),
            jatah_sakit = VALUES(jatah_sakit),
            jatah_melahirkan = VALUES(jatah_melahirkan),
            jatah_alasan_penting = VALUES(jatah_alasan_penting),
            terpakai_tahunan_lalu = VALUES(terpakai_tahunan_lalu),
            terpakai_tahunan = VALUES(terpakai_tahunan),
            terpakai_sakit = VALUES(terpakai_sakit),
            terpakai_melahirkan = VALUES(terpakai_melahirkan),
            terpakai_alasan_penting = VALUES(terpakai_alasan_penting)
    ");
    $stmtCuti->execute([
        $id, $tahun, 
        $jatah_tahunan_lalu, $jatah_tahunan, $jatah_sakit, $jatah_melahirkan, $jatah_alasan_penting,
        $terpakai_tahunan_lalu, $terpakai_tahunan, $terpakai_sakit, $terpakai_melahirkan, $terpakai_alasan_penting
    ]);

    $pdo->commit();
    setFlash('success', 'Data pegawai berhasil diperbarui!');
    redirect(BASE_URL . '/pages/kelola_user.php');

} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect(BASE_URL . '/pages/edit_user.php?id=' . $id);
}

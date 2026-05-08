<?php
/**
 * Proses Pengajuan Izin (Bisa Multi Hari)
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';
require_once __DIR__ . '/../helpers/file_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/form_izin.php');
}

$userId   = $_SESSION['user_id'];
$mulai    = trim($_POST['tanggal_mulai'] ?? '');
$jamMulai = trim($_POST['jam_mulai'] ?? '');
$jamSelesai = trim($_POST['jam_selesai'] ?? '');
$alasan   = trim($_POST['alasan'] ?? '');

if (empty($mulai) || empty($jamMulai) || empty($jamSelesai) || empty($alasan)) {
    setFlash('error', 'Semua field wajib diisi!');
    redirect(BASE_URL . '/pages/form_izin.php');
}

if ($jamMulai >= $jamSelesai) {
    setFlash('error', 'Jam keluar harus sebelum jam kembali!');
    redirect(BASE_URL . '/pages/form_izin.php');
}

// Handle Upload Lampiran (Opsional) — path terstruktur per nama user
$lampiranPath = null;
$lampiranErr  = null;
$namaUser     = $_SESSION['nama'] ?? 'user';
$lampiranPath = handleUploadLampiran('lampiran', $namaUser, 'izin', $mulai, $lampiranErr);
if ($lampiranErr) {
    setFlash('error', $lampiranErr);
    redirect(BASE_URL . '/pages/form_izin.php');
}

try {
    $pdo->beginTransaction();

    // Simpan ke database
    $stmt = $pdo->prepare("
        INSERT INTO pengajuan (user_id, jenis_pengajuan, tanggal_mulai, jam_mulai, jam_selesai, alasan, lampiran, status)
        VALUES (?, 'izin', ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$userId, $mulai, $jamMulai, $jamSelesai, $alasan, $lampiranPath]);
    $pengajuanId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO log_aktivitas (pengajuan_id, user_id, aksi, status_baru, catatan)
        VALUES (?, ?, 'pengajuan_baru', 'pending', 'Pengajuan izin keluar kantor baru')
    ");
    $stmt->execute([$pengajuanId, $userId]);

    // Notifikasi ke Pejabat & Atasan
    notifKePejabat($pdo, $userId,
        'Pengajuan Izin Keluar Kantor Baru 📝',
        htmlspecialchars($_SESSION['nama']) . " mengajukan izin keluar kantor pada {$mulai} jam {$jamMulai}-{$jamSelesai}",
        BASE_URL . '/pages/review_pengajuan.php?id=' . $pengajuanId
    );

    $pdo->commit();
    setFlash('success', "Pengajuan izin keluar kantor berhasil diajukan! ({$jamMulai} - {$jamSelesai})");
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
}

redirect(BASE_URL . '/pages/riwayat.php');

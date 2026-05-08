<?php
/**
 * Proses Batalkan Pengajuan
 * User bisa membatalkan pengajuan sendiri selama status masih 'pending'
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/riwayat.php');
}

$userId      = $_SESSION['user_id'];
$pengajuanId = (int) ($_POST['pengajuan_id'] ?? 0);

if ($pengajuanId <= 0) {
    setFlash('error', 'Data pengajuan tidak valid!');
    redirect(BASE_URL . '/pages/riwayat.php');
}

// Ambil data pengajuan — hanya milik user sendiri dan masih pending
$stmt = $pdo->prepare("SELECT * FROM pengajuan WHERE id = ? AND user_id = ? AND status = 'pending'");
$stmt->execute([$pengajuanId, $userId]);
$pengajuan = $stmt->fetch();

if (!$pengajuan) {
    setFlash('error', 'Pengajuan tidak ditemukan, bukan milik Anda, atau sudah diproses!');
    redirect(BASE_URL . '/pages/riwayat.php');
}

try {
    $pdo->beginTransaction();

    // Update status menjadi 'dibatalkan'
    $stmt = $pdo->prepare("
        UPDATE pengajuan
        SET status = 'dibatalkan', catatan_approval = 'Dibatalkan oleh pemohon', tanggal_approval = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$pengajuanId]);

    // Hapus TTD terkait jika sudah ada
    $stmt = $pdo->prepare("DELETE FROM ttd_pengajuan WHERE pengajuan_id = ?");
    $stmt->execute([$pengajuanId]);

    // Log aktivitas
    $stmt = $pdo->prepare("
        INSERT INTO log_aktivitas (pengajuan_id, user_id, aksi, status_lama, status_baru, catatan)
        VALUES (?, ?, 'pembatalan', 'pending', 'dibatalkan', 'Pengajuan dibatalkan oleh pemohon')
    ");
    $stmt->execute([$pengajuanId, $userId]);

    // Notifikasi ke pejabat bahwa pengajuan dibatalkan
    $jenisLabel = ['cuti' => 'Cuti', 'izin' => 'Izin', 'pulang_cepat' => 'Pulang Cepat'];
    $jenis = $jenisLabel[$pengajuan['jenis_pengajuan']] ?? $pengajuan['jenis_pengajuan'];
    
    notifKePejabat($pdo, $userId,
        "Pengajuan {$jenis} Dibatalkan ❌",
        htmlspecialchars($_SESSION['nama']) . " membatalkan pengajuan {$jenis}.",
        BASE_URL . '/pages/kelola_pengajuan.php'
    );

    $pdo->commit();
    setFlash('success', "Pengajuan {$jenis} berhasil dibatalkan.");
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
}

redirect(BASE_URL . '/pages/riwayat.php');

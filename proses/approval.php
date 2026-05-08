<?php
/**
 * Proses Approval (Setujui / Tolak pengajuan)
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';
require_once __DIR__ . '/../helpers/role_helper.php';

requireLogin();

// Cek apakah user bisa akses kelola pengajuan
if (!canAccessKelolaPengajuan($_SESSION['role'], $pdo, $_SESSION['user_id'])) {
    setFlash('error', 'Anda tidak memiliki akses untuk memproses pengajuan!');
    redirect(BASE_URL . '/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/index.php');
}

$pengajuanId = (int) ($_POST['pengajuan_id'] ?? 0);
$aksi        = $_POST['aksi'] ?? ''; // 'setujui' atau 'tolak'
$catatan     = trim($_POST['catatan_approval'] ?? '');
$approverId  = $_SESSION['user_id'];

if ($pengajuanId <= 0 || !in_array($aksi, ['setujui', 'tolak'])) {
    setFlash('error', 'Data tidak valid!');
    redirect(BASE_URL . '/pages/kelola_pengajuan.php');
}

// Ambil data pengajuan
$stmt = $pdo->prepare("SELECT * FROM pengajuan WHERE id = ? AND status = 'pending'");
$stmt->execute([$pengajuanId]);
$pengajuan = $stmt->fetch();

if (!$pengajuan) {
    setFlash('error', 'Pengajuan tidak ditemukan atau sudah diproses!');
    redirect(BASE_URL . '/pages/kelola_pengajuan.php');
}

// Validasi hak tolak pengajuan ini
$stmt = $pdo->prepare("SELECT atasan_id FROM users WHERE id = ?");
$stmt->execute([$pengajuan['user_id']]);
$atasanId = $stmt->fetchColumn();

// Ambil status_pegawai pemohon untuk cek pejabat berwenang
$stmtStatus = $pdo->prepare("SELECT status_pegawai FROM users WHERE id = ?");
$stmtStatus->execute([$pengajuan['user_id']]);
$statusPegawaiPemohon = $stmtStatus->fetchColumn() ?: 'PNS';

$berhakTolak = canReject($approverId, $_SESSION['role'], $atasanId, $statusPegawaiPemohon);

if (!$berhakTolak) {
    setFlash('error', 'Anda tidak berhak menolak pengajuan ini!');
    redirect(BASE_URL . '/pages/kelola_pengajuan.php');
}

$statusBaru = ($aksi === 'setujui') ? 'disetujui' : 'ditolak';

try {
    $pdo->beginTransaction();

    // Update status pengajuan
    $stmt = $pdo->prepare("
        UPDATE pengajuan
        SET status = ?, disetujui_oleh = ?, catatan_approval = ?, tanggal_approval = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$statusBaru, $approverId, $catatan, $pengajuanId]);

    // Jika cuti disetujui, update saldo cuti
    if ($aksi === 'setujui' && $pengajuan['jenis_pengajuan'] === 'cuti') {
        $tahun = (int) date('Y', strtotime($pengajuan['tanggal_mulai']));
        $stmt = $pdo->prepare("
            UPDATE saldo_cuti
            SET total_terpakai = total_terpakai + ?, sisa = sisa - ?
            WHERE user_id = ? AND tahun = ?
        ");
        $stmt->execute([
            $pengajuan['jumlah_hari'],
            $pengajuan['jumlah_hari'],
            $pengajuan['user_id'],
            $tahun,
        ]);
    }

    // Log aktivitas
    $stmt = $pdo->prepare("
        INSERT INTO log_aktivitas (pengajuan_id, user_id, aksi, status_lama, status_baru, catatan)
        VALUES (?, ?, ?, 'pending', ?, ?)
    ");
    $stmt->execute([$pengajuanId, $approverId, $aksi, $statusBaru, $catatan]);

    $pdo->commit();

    // Kirim notifikasi ke pegawai
    $jenisLabel = ['cuti' => 'Cuti', 'izin' => 'Izin', 'pulang_cepat' => 'Pulang Cepat'];
    $jenis = $jenisLabel[$pengajuan['jenis_pengajuan']] ?? $pengajuan['jenis_pengajuan'];
    if ($aksi === 'tolak') {
        buatNotifikasi($pdo, $pengajuan['user_id'],
            "Pengajuan {$jenis} Ditolak ❌",
            "Pengajuan {$jenis} Anda ditolak." . ($catatan ? " Alasan: {$catatan}" : ''),
            BASE_URL . '/pages/riwayat.php'
        );
    }

    $msg = ($aksi === 'setujui') ? 'Pengajuan berhasil disetujui!' : 'Pengajuan berhasil ditolak.';
    setFlash('success', $msg);
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
}

redirect(BASE_URL . '/pages/kelola_pengajuan.php');

<?php
/**
 * Proses Pengajuan Pulang Cepat
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';
require_once __DIR__ . '/../helpers/file_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/form_pulang_cepat.php');
}

$userId       = $_SESSION['user_id'];
$tanggal      = trim($_POST['tanggal_pulang'] ?? '');
$jamDiajukan  = trim($_POST['jam_pulang_diajukan'] ?? '');
// Ambil dari radio button (tipe_izin_waktu) langsung
$tipe         = trim($_POST['tipe_izin_waktu'] ?? 'datang_terlambat');
$alasan       = trim($_POST['alasan'] ?? '');

if (empty($tanggal) || empty($jamDiajukan) || empty($alasan)) {
    setFlash('error', 'Semua field wajib diisi!');
    redirect(BASE_URL . '/pages/form_pulang_cepat.php');
}

if (!isHariKerja($tanggal, $pdo)) {
    setFlash('error', 'Tanggal yang dipilih bukan hari kerja!');
    redirect(BASE_URL . '/pages/form_pulang_cepat.php');
}

if ($tipe === 'datang_terlambat') {
    $jamResmi = '08:00';
    $selisihMenit = hitungSelisihMenit($jamDiajukan, $jamResmi);
    if ($selisihMenit <= 0) {
        setFlash('error', 'Jam kedatangan yang diajukan harus lebih lambat dari jam masuk resmi (' . $jamResmi . ')!');
        redirect(BASE_URL . '/pages/form_pulang_cepat.php');
    }
} else {
    $jamResmi     = getJamPulangResmi($tanggal);
    $selisihMenit = hitungSelisihMenit($jamResmi, $jamDiajukan);
    if ($selisihMenit <= 0) {
        setFlash('error', 'Jam pulang yang diajukan harus lebih awal dari jam pulang resmi (' . $jamResmi . ')!');
        redirect(BASE_URL . '/pages/form_pulang_cepat.php');
    }
}

// Handle Lampiran Opsional — path terstruktur per nama user
$lampiranPath = null;
$lampiranErr  = null;
$namaUser     = $_SESSION['nama'] ?? 'user';
$jenisTipe    = ($tipe === 'datang_terlambat') ? 'terlambat' : 'pulang_cepat';
$lampiranPath = handleUploadLampiran('lampiran', $namaUser, $jenisTipe, $tanggal, $lampiranErr);
if ($lampiranErr) {
    setFlash('error', $lampiranErr);
    redirect(BASE_URL . '/pages/form_pulang_cepat.php');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO pengajuan (user_id, jenis_pengajuan, tipe_izin_waktu, tanggal_pulang, jam_pulang_resmi, jam_pulang_diajukan, selisih_menit, alasan, lampiran, status)
        VALUES (?, 'pulang_cepat', ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$userId, $tipe, $tanggal, $jamResmi, $jamDiajukan, $selisihMenit, $alasan, $lampiranPath]);
    $pengajuanId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO log_aktivitas (pengajuan_id, user_id, aksi, status_baru, catatan)
        VALUES (?, ?, 'pengajuan_baru', 'pending', 'Pengajuan pulang cepat baru')
    ");
    $stmt->execute([$pengajuanId, $userId]);

    // Notifikasi ke Pejabat & Atasan
    notifKePejabat($pdo, $userId,
        'Pengajuan Pulang Cepat Baru 🕐',
        htmlspecialchars($_SESSION['nama']) . ' mengajukan pulang cepat pada ' . $tanggal . ' (selisih ' . formatSelisihWaktu($selisihMenit) . ')',
        BASE_URL . '/pages/review_pengajuan.php?id=' . $pengajuanId
    );

    $pdo->commit();
    setFlash('success', 'Pengajuan pulang cepat berhasil! (Selisih: ' . formatSelisihWaktu($selisihMenit) . ')');
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
}

redirect(BASE_URL . '/pages/riwayat.php');

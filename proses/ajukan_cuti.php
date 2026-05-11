<?php
/**
 * Proses Pengajuan Cuti
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';
require_once __DIR__ . '/../helpers/file_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/form_cuti.php');
}

$userId   = $_SESSION['user_id'];
$tipeCuti = $_POST['tipe_cuti'] ?? 'tahunan';
$mulai    = trim($_POST['tanggal_mulai'] ?? '');
$selesai  = trim($_POST['tanggal_selesai'] ?? '');
$alasan   = trim($_POST['alasan'] ?? '');
$telepon  = trim($_POST['telepon'] ?? '');
$alamatCuti = trim($_POST['alamat_cuti'] ?? '');
$ttdPengaju = $_POST['ttd_pengaju'] ?? '';

// Validasi input
if (empty($mulai) || empty($selesai) || empty($alasan) || empty($ttdPengaju)) {
    setFlash('error', 'Semua field wajib diisi, termasuk tanda tangan!');
    redirect(BASE_URL . '/pages/form_cuti.php');
}

if ($mulai > $selesai) {
    setFlash('error', 'Tanggal mulai harus sebelum tanggal selesai!');
    redirect(BASE_URL . '/pages/form_cuti.php');
}

// Hitung hari kerja
$jumlahHari = hitungHariKerja($mulai, $selesai, $pdo);

if ($jumlahHari <= 0) {
    setFlash('error', 'Tidak ada hari kerja dalam rentang tanggal yang dipilih!');
    redirect(BASE_URL . '/pages/form_cuti.php');
}

// ===== CEK OVERLAP TANGGAL =====
// Cegah pengajuan ganda di hari yang sama (status pending/disetujui)
$stmtOverlap = $pdo->prepare("
    SELECT id, tipe_cuti, tanggal_mulai, tanggal_selesai, status
    FROM pengajuan
    WHERE user_id = ?
      AND jenis_pengajuan = 'cuti'
      AND status IN ('pending', 'disetujui')
      AND tanggal_mulai <= ?
      AND tanggal_selesai >= ?
    LIMIT 1
");
$stmtOverlap->execute([$userId, $selesai, $mulai]);
$overlapData = $stmtOverlap->fetch();

if ($overlapData) {
    $tgl = formatTanggal($overlapData['tanggal_mulai']) . ' s/d ' . formatTanggal($overlapData['tanggal_selesai']);
    $st  = ucfirst($overlapData['status']);
    setFlash('error', "⚠️ Tanggal bertabrakan dengan pengajuan cuti yang sudah ada ({$tgl} — {$st}). Silakan pilih tanggal lain.");
    redirect(BASE_URL . '/pages/form_cuti.php');
}

// Cek sisa cuti berdasarkan tipe — selalu gunakan TAHUN_AKTIF
$tahun = (int) TAHUN_AKTIF;
$saldo = cekSisaCuti($userId, $tahun, $pdo);

$sisaCuti = 0;
if ($tipeCuti === 'tahunan') {
    $sisaCuti = max(0, $saldo['jatah_tahunan'] - $saldo['terpakai_tahunan']);
} elseif ($tipeCuti === 'tahunan_lalu') {
    $sisaCuti = max(0, $saldo['jatah_tahunan_lalu'] - $saldo['terpakai_tahunan_lalu']);
} elseif ($tipeCuti === 'sakit') {
    $sisaCuti = $saldo['jatah_sakit'] - $saldo['terpakai_sakit'];
} elseif ($tipeCuti === 'melahirkan') {
    $sisaCuti = $saldo['jatah_melahirkan'] - $saldo['terpakai_melahirkan'];
} elseif ($tipeCuti === 'alasan_penting') {
    $sisaCuti = $saldo['jatah_alasan_penting'] - $saldo['terpakai_alasan_penting'];
}

if ($jumlahHari > $sisaCuti) {
    $labelTipe = $tipeCuti === 'tahunan_lalu' ? 'tahunan tahun lalu' : $tipeCuti;
    setFlash('error', "Kuota cuti {$labelTipe} tidak mencukupi! Sisa: {$sisaCuti} hari, dibutuhkan: {$jumlahHari} hari.");
    redirect(BASE_URL . '/pages/form_cuti.php');
}

// Proses Tanda Tangan Pengaju
$ttdDataRaw = str_replace('data:image/png;base64,', '', $ttdPengaju);
$ttdDataRaw = str_replace(' ', '+', $ttdDataRaw);
$imageData  = base64_decode($ttdDataRaw);

if (!$imageData) {
    setFlash('error', 'Format tanda tangan tidak valid!');
    redirect(BASE_URL . '/pages/form_cuti.php');
}

// Path TTD terstruktur: assets/ttd/{slug_nama}/ttd_cuti_nama_tgl.png
$namaPengaju = $_SESSION['nama'] ?? 'user';
$ttdInfo     = generateTtdPath($namaPengaju, 'cuti', $mulai);

if (!file_put_contents($ttdInfo['abs'], $imageData)) {
    setFlash('error', 'Gagal menyimpan file tanda tangan!');
    redirect(BASE_URL . '/pages/form_cuti.php');
}
$ttdPath = $ttdInfo['relative'];

// Handle Lampiran Opsional — path terstruktur per nama user
$lampiranPath = null;
$lampiranErr  = null;
$lampiranPath = handleUploadLampiran('lampiran', $namaPengaju, 'cuti', $mulai, $lampiranErr);
if ($lampiranErr) {
    setFlash('error', $lampiranErr);
    redirect(BASE_URL . '/pages/form_cuti.php');
}

// Untuk tipe tahunan_lalu, simpan ke DB sebagai 'tahunan' tapi tandai kolom khusus
// (DB enum hanya punya 'tahunan', jadi kita map ke 'tahunan' dan tandai via catatan)
$tipeCutiDb = ($tipeCuti === 'tahunan_lalu') ? 'tahunan' : $tipeCuti;
$paketTahunan = ($tipeCuti === 'tahunan_lalu') ? 'lalu' : 'ini'; // untuk deduction yang benar

try {
    $pdo->beginTransaction();

    // Insert pengajuan
    $stmt = $pdo->prepare("
        INSERT INTO pengajuan (user_id, jenis_pengajuan, tipe_cuti, tanggal_mulai, tanggal_selesai, jumlah_hari, alasan, telepon, alamat_cuti, ttd_pengaju, lampiran, status)
        VALUES (?, 'cuti', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$userId, $tipeCutiDb, $mulai, $selesai, $jumlahHari, $alasan, $telepon, $alamatCuti, $ttdPath, $lampiranPath]);
    $pengajuanId = $pdo->lastInsertId();

    // Log aktivitas (catatan menyimpan tipe asli agar deduction bisa benar)
    $catatanLog = 'Pengajuan cuti baru - ' . ($tipeCuti === 'tahunan_lalu' ? 'tahunan_lalu' : $tipeCutiDb);
    $stmt = $pdo->prepare("
        INSERT INTO log_aktivitas (pengajuan_id, user_id, aksi, status_baru, catatan)
        VALUES (?, ?, 'pengajuan_baru', 'pending', ?)
    ");
    $stmt->execute([$pengajuanId, $userId, $catatanLog]);

    // Notifikasi ke Pejabat & Atasan
    notifKePejabat($pdo, $userId, 
        'Pengajuan Cuti Baru 🏖️',
        htmlspecialchars($_SESSION['nama']) . ' mengajukan cuti ' . $jumlahHari . ' hari kerja (' . $mulai . ' s/d ' . $selesai . ')',
        BASE_URL . '/pages/review_pengajuan.php?id=' . $pengajuanId
    );

    $pdo->commit();
    setFlash('success', "Pengajuan cuti berhasil diajukan! ({$jumlahHari} hari kerja)");
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
}

redirect(BASE_URL . '/pages/riwayat.php');

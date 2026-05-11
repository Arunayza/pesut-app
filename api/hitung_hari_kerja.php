<?php
/**
 * API: Hitung Hari Kerja (AJAX)
 * Input: tanggal_mulai, tanggal_selesai, tipe_cuti
 * Output: JSON { hari_kerja, sisa_cuti, valid, message, overlap }
 */
ob_start();
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['valid' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId   = $_SESSION['user_id'];
$mulai    = $_GET['tanggal_mulai'] ?? '';
$selesai  = $_GET['tanggal_selesai'] ?? '';
$tipeCuti = $_GET['tipe_cuti'] ?? 'tahunan';

// ---- Helper: hitung sisa kuota ----
function getSisaCuti(array $saldo, string $tipe): int {
    return match($tipe) {
        'tahunan'       => max(0, $saldo['jatah_tahunan'] - $saldo['terpakai_tahunan']),
        'tahunan_lalu'  => max(0, $saldo['jatah_tahunan_lalu'] - $saldo['terpakai_tahunan_lalu']),
        'sakit'         => max(0, $saldo['jatah_sakit'] - $saldo['terpakai_sakit']),
        'melahirkan'    => max(0, $saldo['jatah_melahirkan'] - $saldo['terpakai_melahirkan']),
        'alasan_penting'=> max(0, $saldo['jatah_alasan_penting'] - $saldo['terpakai_alasan_penting']),
        default         => 0,
    };
}

// ---- Helper: cek overlap tanggal ----
function cekOverlapCuti(PDO $pdo, int $userId, string $mulai, string $selesai): array {
    $stmt = $pdo->prepare("
        SELECT id, tipe_cuti, tanggal_mulai, tanggal_selesai, jumlah_hari, status
        FROM pengajuan
        WHERE user_id = ?
          AND jenis_pengajuan = 'cuti'
          AND status IN ('pending', 'disetujui')
          AND tanggal_mulai <= ?
          AND tanggal_selesai >= ?
        ORDER BY tanggal_mulai ASC
        LIMIT 5
    ");
    $stmt->execute([$userId, $selesai, $mulai]);
    return $stmt->fetchAll();
}

// Jika tanggal belum diisi, kembalikan sisa saldo saja
if (empty($mulai) || empty($selesai)) {
    $tahun  = (int) TAHUN_AKTIF;
    $saldo  = cekSisaCuti($userId, $tahun, $pdo);
    $sisaCuti = getSisaCuti($saldo, $tipeCuti);
    echo json_encode(['valid' => false, 'message' => 'Tanggal tidak boleh kosong', 'sisa_cuti' => $sisaCuti]);
    exit;
}

if ($mulai > $selesai) {
    echo json_encode(['valid' => false, 'message' => 'Tanggal mulai harus sebelum tanggal selesai']);
    exit;
}

$hasilHari = hitungHariKerja($mulai, $selesai, $pdo, true);
$hariKerja = $hasilHari['jumlah'];
$tanggalList = $hasilHari['tanggal_list'];
// Selalu gunakan TAHUN_AKTIF untuk ambil saldo, karena 'tahunan_lalu' disimpan
// di kolom jatah_tahunan_lalu pada baris saldo tahun aktif
$saldo     = cekSisaCuti($userId, (int) TAHUN_AKTIF, $pdo);
$sisaCuti  = getSisaCuti($saldo, $tipeCuti);

// Format tanggal list untuk display
$tanggalFormatted = array_map(function($tgl) {
    return formatTanggal($tgl);
}, $tanggalList);

// Cek overlap dengan pengajuan yang sudah ada
$overlapList = cekOverlapCuti($pdo, $userId, $mulai, $selesai);
$hasOverlap  = count($overlapList) > 0;

// Pesan overlap yang informatif
$overlapMsg = '';
if ($hasOverlap) {
    $items = array_map(fn($r) => formatTanggal($r['tanggal_mulai']) . ' s/d ' . formatTanggal($r['tanggal_selesai'])
        . ' (' . ucfirst($r['status']) . ')', $overlapList);
    $overlapMsg = 'Tanggal bertabrakan dengan pengajuan yang sudah ada: ' . implode(', ', $items) . '.';
}

// Valid = punya hari kerja, saldo cukup, dan tidak overlap
$valid   = ($hariKerja > 0) && ($hariKerja <= $sisaCuti) && !$hasOverlap;
$message = '';
if ($hasOverlap) {
    $message = $overlapMsg;
} elseif ($hariKerja <= 0) {
    $message = 'Tidak ada hari kerja dalam rentang tanggal ini.';
} elseif ($hariKerja > $sisaCuti) {
    $tipLabel = str_replace('_', ' ', $tipeCuti);
    $message = "Kuota cuti {$tipLabel} tidak mencukupi! Sisa: {$sisaCuti} hari, dibutuhkan: {$hariKerja} hari.";
}

echo json_encode([
    'hari_kerja'       => $hariKerja,
    'tanggal_list'     => $tanggalFormatted,
    'sisa_cuti'        => $sisaCuti,
    'valid'            => $valid,
    'message'          => $message,
    'overlap'          => $hasOverlap,
    'overlap_list'     => $overlapList,
]);

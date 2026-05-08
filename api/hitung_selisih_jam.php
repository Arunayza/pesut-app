<?php
/**
 * API: Hitung Selisih Jam Pulang Cepat (AJAX)
 * Input: tanggal, jam_pulang
 * Output: JSON { jam_resmi, selisih_menit, selisih_format, valid, message }
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['valid' => false, 'message' => 'Unauthorized']);
    exit;
}

$tanggal   = $_GET['tanggal'] ?? '';
$jamPulang = $_GET['jam_pulang'] ?? '';
$tipe      = $_GET['tipe'] ?? 'pulang_cepat';

if (empty($tanggal) || empty($jamPulang)) {
    echo json_encode(['valid' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

// Cek apakah hari kerja
if (!isHariKerja($tanggal, $pdo)) {
    echo json_encode(['valid' => false, 'message' => 'Tanggal yang dipilih bukan hari kerja']);
    exit;
}

if ($tipe === 'datang_terlambat') {
    $jamResmi = '08:00';
    $selisihMenit = hitungSelisihMenit($jamPulang, $jamResmi); // jam_diajukan - jam_resmi
    if ($selisihMenit <= 0) {
        echo json_encode([
            'valid'   => false,
            'message' => 'Jam kedatangan harus lebih lambat dari jam masuk resmi (' . $jamResmi . ')',
        ]);
        exit;
    }
} else {
    $jamResmi      = getJamPulangResmi($tanggal);
    $selisihMenit  = hitungSelisihMenit($jamResmi, $jamPulang); // jam_resmi - jam_diajukan
    if ($selisihMenit <= 0) {
        echo json_encode([
            'valid'   => false,
            'message' => 'Jam pulang harus lebih awal dari jam pulang resmi (' . $jamResmi . ')',
        ]);
        exit;
    }
}

$namaHariIni = namaHari($tanggal);

echo json_encode([
    'hari'            => $namaHariIni,
    'jam_resmi'       => $jamResmi,
    'selisih_menit'   => $selisihMenit,
    'selisih_format'  => formatSelisihWaktu($selisihMenit),
    'valid'           => true,
    'message'         => '',
]);

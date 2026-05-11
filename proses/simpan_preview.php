<?php
/**
 * Simpan data form ke SESSION untuk preview surat
 * Sebelum benar-benar masuk DB, user bisa lihat dulu hasilnya
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/dashboard_v2.php');
}

$jenis = trim($_POST['jenis_form'] ?? '');
$userId = $_SESSION['user_id'];

// Ambil data profil user
$stmtProfil = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtProfil->execute([$userId]);
$profil = $stmtProfil->fetch();

// Ambil data atasan
$atasan = null;
if (!empty($profil['atasan_id'])) {
    $stmtAtasan = $pdo->prepare("SELECT nama, nip, jabatan, role FROM users WHERE id = ?");
    $stmtAtasan->execute([$profil['atasan_id']]);
    $atasan = $stmtAtasan->fetch();
}

if ($jenis === 'cuti') {
    // ==================== CUTI ====================
    $tipeCuti = $_POST['tipe_cuti'] ?? 'tahunan';
    $mulai    = trim($_POST['tanggal_mulai'] ?? '');
    $selesai  = trim($_POST['tanggal_selesai'] ?? '');
    $alasan   = trim($_POST['alasan'] ?? '');
    $telepon  = trim($_POST['telepon'] ?? '');
    $alamatCuti = trim($_POST['alamat_cuti'] ?? '');
    // Validasi dasar
    if (empty($mulai) || empty($selesai) || empty($alasan)) {
        setFlash('error', 'Semua field wajib diisi!');
        redirect(BASE_URL . '/pages/form_cuti.php');
    }
    if ($mulai > $selesai) {
        setFlash('error', 'Tanggal mulai harus sebelum tanggal selesai!');
        redirect(BASE_URL . '/pages/form_cuti.php');
    }

    $jumlahHari = hitungHariKerja($mulai, $selesai, $pdo);
    if ($jumlahHari <= 0) {
        setFlash('error', 'Tidak ada hari kerja dalam rentang tanggal yang dipilih!');
        redirect(BASE_URL . '/pages/form_cuti.php');
    }

    // Cek overlap
    $stmtOverlap = $pdo->prepare("
        SELECT id FROM pengajuan
        WHERE user_id = ? AND jenis_pengajuan = 'cuti'
          AND status IN ('pending', 'disetujui')
          AND tanggal_mulai <= ? AND tanggal_selesai >= ?
        LIMIT 1
    ");
    $stmtOverlap->execute([$userId, $selesai, $mulai]);
    if ($stmtOverlap->fetch()) {
        setFlash('error', 'Tanggal bertabrakan dengan pengajuan cuti yang sudah ada!');
        redirect(BASE_URL . '/pages/form_cuti.php');
    }

    // Cek saldo
    $tahun = (int) date('Y', strtotime($mulai));
    $saldo = cekSisaCuti($userId, $tahun, $pdo);
    $sisaCuti = 0;
    if ($tipeCuti === 'tahunan') $sisaCuti = max(0, $saldo['jatah_tahunan'] - $saldo['terpakai_tahunan']);
    elseif ($tipeCuti === 'tahunan_lalu') $sisaCuti = max(0, $saldo['jatah_tahunan_lalu'] - $saldo['terpakai_tahunan_lalu']);
    elseif ($tipeCuti === 'sakit') $sisaCuti = $saldo['jatah_sakit'] - $saldo['terpakai_sakit'];
    elseif ($tipeCuti === 'melahirkan') $sisaCuti = $saldo['jatah_melahirkan'] - $saldo['terpakai_melahirkan'];
    elseif ($tipeCuti === 'alasan_penting') $sisaCuti = $saldo['jatah_alasan_penting'] - $saldo['terpakai_alasan_penting'];

    if ($jumlahHari > $sisaCuti) {
        setFlash('error', "Kuota cuti tidak mencukupi! Sisa: {$sisaCuti} hari, dibutuhkan: {$jumlahHari} hari.");
        redirect(BASE_URL . '/pages/form_cuti.php');
    }

    // Handle file lampiran ke temp
    $lampiranTempName = null;
    $lampiranOrigName = null;
    if (!empty($_FILES['lampiran']['name']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
        $lampiranOrigName = $_FILES['lampiran']['name'];
        $lampiranTempName = $_FILES['lampiran']['tmp_name'];
        // Move to a persistent temp location
        $tmpDir = __DIR__ . '/../assets/ttd/temp/';
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
        $tmpFile = $tmpDir . 'preview_' . session_id() . '_' . basename($lampiranOrigName);
        move_uploaded_file($lampiranTempName, $tmpFile);
        $lampiranTempName = $tmpFile;
    }

    $_SESSION['preview_data'] = [
        'jenis' => 'cuti',
        'tipe_cuti' => $tipeCuti,
        'tanggal_mulai' => $mulai,
        'tanggal_selesai' => $selesai,
        'jumlah_hari' => $jumlahHari,
        'alasan' => $alasan,
        'telepon' => $telepon,
        'alamat_cuti' => $alamatCuti,
        'lampiran_temp' => $lampiranTempName,
        'lampiran_orig' => $lampiranOrigName,
        'saldo' => $saldo,
        'profil' => [
            'nama' => $profil['nama'],
            'nip' => $profil['nip'],
            'jabatan' => $profil['jabatan'],
            'pangkat' => $profil['pangkat'] ?? '-',
            'status_pegawai' => $profil['status_pegawai'] ?? 'PNS',
            'tgl_mulai_kerja' => $profil['tgl_mulai_kerja'] ?? '2020-01-01',
            'unit_kerja' => $profil['unit_kerja'] ?? APP_INSTANSI,
        ],
        'atasan' => $atasan ? [
            'nama' => $atasan['nama'],
            'nip' => $atasan['nip'],
            'jabatan' => $atasan['jabatan'],
        ] : null,
    ];

    redirect(BASE_URL . '/pages/preview_surat.php');

} elseif ($jenis === 'izin') {
    // ==================== IZIN ====================
    $mulai     = trim($_POST['tanggal_mulai'] ?? '');
    $jamMulai  = trim($_POST['jam_mulai'] ?? '');
    $jamSelesai = trim($_POST['jam_selesai'] ?? '');
    $alasan    = trim($_POST['alasan'] ?? '');

    if (empty($mulai) || empty($jamMulai) || empty($jamSelesai) || empty($alasan)) {
        setFlash('error', 'Semua field wajib diisi!');
        redirect(BASE_URL . '/pages/form_izin.php');
    }
    if ($jamMulai >= $jamSelesai) {
        setFlash('error', 'Jam keluar harus sebelum jam kembali!');
        redirect(BASE_URL . '/pages/form_izin.php');
    }

    // Handle lampiran
    $lampiranTempName = null;
    $lampiranOrigName = null;
    if (!empty($_FILES['lampiran']['name']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
        $lampiranOrigName = $_FILES['lampiran']['name'];
        $tmpDir = __DIR__ . '/../assets/ttd/temp/';
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
        $tmpFile = $tmpDir . 'preview_' . session_id() . '_' . basename($lampiranOrigName);
        move_uploaded_file($_FILES['lampiran']['tmp_name'], $tmpFile);
        $lampiranTempName = $tmpFile;
    }

    $_SESSION['preview_data'] = [
        'jenis' => 'izin',
        'tanggal_mulai' => $mulai,
        'jam_mulai' => $jamMulai,
        'jam_selesai' => $jamSelesai,
        'alasan' => $alasan,
        'lampiran_temp' => $lampiranTempName,
        'lampiran_orig' => $lampiranOrigName,
        'profil' => [
            'nama' => $profil['nama'],
            'nip' => $profil['nip'],
            'jabatan' => $profil['jabatan'],
            'pangkat' => $profil['pangkat'] ?? '-',
            'status_pegawai' => $profil['status_pegawai'] ?? 'PNS',
            'unit_kerja' => $profil['unit_kerja'] ?? APP_INSTANSI,
        ],
        'atasan' => $atasan ? [
            'nama' => $atasan['nama'],
            'nip' => $atasan['nip'],
            'jabatan' => $atasan['jabatan'],
        ] : null,
    ];

    redirect(BASE_URL . '/pages/preview_surat.php');

} elseif ($jenis === 'pulang_cepat') {
    // ==================== PULANG CEPAT / TERLAMBAT ====================
    $tanggal     = trim($_POST['tanggal_pulang'] ?? '');
    $jamDiajukan = trim($_POST['jam_pulang_diajukan'] ?? '');
    $tipe        = trim($_POST['tipe_izin_waktu'] ?? 'datang_terlambat');
    $alasan      = trim($_POST['alasan'] ?? '');

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
            setFlash('error', 'Jam kedatangan harus lebih lambat dari jam masuk resmi (08:00)!');
            redirect(BASE_URL . '/pages/form_pulang_cepat.php');
        }
    } else {
        $jamResmi = getJamPulangResmi($tanggal);
        $selisihMenit = hitungSelisihMenit($jamResmi, $jamDiajukan);
        if ($selisihMenit <= 0) {
            setFlash('error', 'Jam pulang harus lebih awal dari jam pulang resmi (' . $jamResmi . ')!');
            redirect(BASE_URL . '/pages/form_pulang_cepat.php');
        }
    }

    // Handle lampiran
    $lampiranTempName = null;
    $lampiranOrigName = null;
    if (!empty($_FILES['lampiran']['name']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
        $lampiranOrigName = $_FILES['lampiran']['name'];
        $tmpDir = __DIR__ . '/../assets/ttd/temp/';
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
        $tmpFile = $tmpDir . 'preview_' . session_id() . '_' . basename($lampiranOrigName);
        move_uploaded_file($_FILES['lampiran']['tmp_name'], $tmpFile);
        $lampiranTempName = $tmpFile;
    }

    $_SESSION['preview_data'] = [
        'jenis' => 'pulang_cepat',
        'tipe_izin_waktu' => $tipe,
        'tanggal_pulang' => $tanggal,
        'jam_pulang_resmi' => $jamResmi,
        'jam_pulang_diajukan' => $jamDiajukan,
        'selisih_menit' => $selisihMenit,
        'alasan' => $alasan,
        'lampiran_temp' => $lampiranTempName,
        'lampiran_orig' => $lampiranOrigName,
        'profil' => [
            'nama' => $profil['nama'],
            'nip' => $profil['nip'],
            'jabatan' => $profil['jabatan'],
            'pangkat' => $profil['pangkat'] ?? '-',
            'status_pegawai' => $profil['status_pegawai'] ?? 'PNS',
            'unit_kerja' => $profil['unit_kerja'] ?? APP_INSTANSI,
        ],
        'atasan' => $atasan ? [
            'nama' => $atasan['nama'],
            'nip' => $atasan['nip'],
            'jabatan' => $atasan['jabatan'],
        ] : null,
    ];

    redirect(BASE_URL . '/pages/preview_surat.php');

} else {
    setFlash('error', 'Jenis form tidak dikenali!');
    redirect(BASE_URL . '/dashboard_v2.php');
}

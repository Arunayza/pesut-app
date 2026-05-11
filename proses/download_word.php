<?php
/**
 * Download Surat Word (.docx)
 * Generate dan download surat dalam format Word
 * 
 * CUTI:
 * - Hakim/PNS → template Contoh Cuti PNSHakim.docx
 * - PPPK → template Contoh Cuti PPPK.docx
 * 
 * IZIN KELUAR KANTOR:
 * - Hakim → template Izin_Keluar_Hakim.docx
 * - PNS/PPPK → template Izin_Keluar_pegawaip3k.docx
 */
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireLogin();

$pengajuanId = (int) ($_GET['id'] ?? 0);

if ($pengajuanId <= 0) {
    setFlash('error', 'Pengajuan tidak ditemukan!');
    redirect(BASE_URL . '/pages/riwayat.php');
}

// Ambil data pengajuan + pegawai
$stmt = $pdo->prepare("
    SELECT p.*, u.nama, u.nip, u.jabatan, u.pangkat, u.unit_kerja, u.tgl_mulai_kerja, u.jenis_kelamin, u.status_pegawai
    FROM pengajuan p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$pengajuanId]);
$data = $stmt->fetch();

if (!$data) {
    setFlash('error', 'Pengajuan tidak ditemukan!');
    redirect(BASE_URL . '/pages/riwayat.php');
}

// Cek akses: admin & pejabat struktural bisa lihat semua, pegawai hanya miliknya sendiri
require_once __DIR__ . '/../helpers/role_helper.php';
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ROLE_KELOLA) && $data['user_id'] != $_SESSION['user_id']) {
    setFlash('error', 'Anda tidak memiliki akses ke surat ini!');
    redirect(BASE_URL . '/pages/riwayat.php');
}

// Cek status harus disetujui
if ($data['status'] !== 'disetujui') {
    setFlash('error', 'Surat hanya bisa diunduh setelah pengajuan disetujui!');
    redirect(BASE_URL . '/pages/riwayat.php');
}

// Cek jenis pengajuan yang didukung
if (!in_array($data['jenis_pengajuan'], ['cuti', 'izin', 'pulang_cepat'])) {
    setFlash('error', 'Cetak Word hanya tersedia untuk Cuti, Izin Keluar, dan Pulang Cepat/Datang Terlambat!');
    redirect(BASE_URL . '/pages/riwayat.php');
}

$statusPegawai = $data['status_pegawai'] ?? 'PPPK';

// ============================================================
// IZIN KELUAR KANTOR
// ============================================================
if ($data['jenis_pengajuan'] === 'izin') {

    // Pilih template: Hakim → Izin_Keluar_Hakim, PNS/PPPK → Izin_Keluar_pegawaip3k
    if ($statusPegawai === 'Hakim') {
        $templatePath = __DIR__ . '/../templates/Izin_Keluar_Hakim.docx';
    } else {
        $templatePath = __DIR__ . '/../templates/Izin_Keluar_pegawaip3k.docx';
    }

    if (!file_exists($templatePath)) {
        setFlash('error', 'File template Word Izin Keluar tidak ditemukan!');
        redirect(BASE_URL . '/pages/riwayat.php');
    }

    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

    // Ambil data tanda tangan (pemberi izin)
    $stmtTtd = $pdo->prepare("SELECT t.*, u.nama, u.nip, u.jabatan FROM ttd_pengajuan t JOIN users u ON t.user_id = u.id WHERE t.pengajuan_id = ? ORDER BY t.id ASC");
    $stmtTtd->execute([$pengajuanId]);
    $ttdList = $stmtTtd->fetchAll();

    $pemberi = $ttdList[0] ?? null;

    // 1. Data Pemberi Izin (Yang bertanda tangan / Selaku)
    $jabPemberi = $pemberi ? ucwords(strtolower($pemberi['jabatan_ttd'])) : '.........................';
    if (stripos($jabPemberi, 'Pengadilan') === false && $jabPemberi !== '.........................') {
        $jabPemberi .= ' Pengadilan Tata Usaha Negara Samarinda';
    }
    $templateProcessor->setValue('jab_pemberi', $jabPemberi);
    $templateProcessor->setValue('nama_pemberi', $pemberi ? $pemberi['nama'] : '.......................................');
    $templateProcessor->setValue('nip_pemberi', $pemberi ? $pemberi['nip'] : '.........................');

    // 2. Data Pegawai (Yang diberi izin)
    $templateProcessor->setValue('nama', $data['nama']);
    $templateProcessor->setValue('nip', $data['nip']);

    // 3. Data Izin Keluar
    $templateProcessor->setValue('hari_tanggal', formatTanggalHari($data['tanggal_mulai']));
    $templateProcessor->setValue('jam_mulai', substr($data['jam_mulai'] ?? '00:00', 0, 5));
    $templateProcessor->setValue('jam_selesai', substr($data['jam_selesai'] ?? '00:00', 0, 5));
    $templateProcessor->setValue('keperluan', htmlspecialchars($data['alasan']));

    // 4. Tanggal Surat
    $tglSurat = $data['tanggal_approval'] ? date('Y-m-d', strtotime($data['tanggal_approval'])) : date('Y-m-d');
    $templateProcessor->setValue('tgl_surat', formatTanggal($tglSurat));

    // 5. Tanda Tangan Image
    if ($pemberi && !empty($pemberi['ttd_path']) && file_exists(__DIR__ . '/../' . $pemberi['ttd_path'])) {
        $templateProcessor->setImageValue('ttd_pemberi', ['path' => __DIR__ . '/../' . $pemberi['ttd_path'], 'width' => 150, 'height' => 60, 'ratio' => false]);
    } else {
        $templateProcessor->setValue('ttd_pemberi', '');
    }

    // Output
    $filename = 'Surat_Izin_Keluar_' . str_replace(' ', '_', $data['nama']) . '_' . $pengajuanId . '.docx';

// ============================================================
// PULANG CEPAT / DATANG TERLAMBAT
// ============================================================
} elseif ($data['jenis_pengajuan'] === 'pulang_cepat') {

    $templatePath = __DIR__ . '/../templates/Izin_telat_dan_pulangcepat.docx';

    if (!file_exists($templatePath)) {
        setFlash('error', 'File template Word Pulang Cepat/Datang Terlambat tidak ditemukan!');
        redirect(BASE_URL . '/pages/riwayat.php');
    }

    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

    // Ambil data tanda tangan (pemberi izin)
    $stmtTtd = $pdo->prepare("SELECT t.*, u.nama, u.nip, u.jabatan FROM ttd_pengajuan t JOIN users u ON t.user_id = u.id WHERE t.pengajuan_id = ? ORDER BY t.id ASC");
    $stmtTtd->execute([$pengajuanId]);
    $ttdList = $stmtTtd->fetchAll();
    $pemberi = $ttdList[0] ?? null;

    // 1. Kondisi: Datang Terlambat atau Pulang Cepat
    $tipeWaktu = $data['tipe_izin_waktu'] ?? 'pulang_cepat';
    if ($tipeWaktu === 'datang_terlambat') {
        $templateProcessor->setValue('izin1', 'DATANG TERLAMBAT');
        $templateProcessor->setValue('izin2', '');
    } else {
        $templateProcessor->setValue('izin1', '');
        $templateProcessor->setValue('izin2', 'PULANG CEPAT');
    }

    // 2. Data Pemberi Izin (Yang bertanda tangan - bagian atas)
    $templateProcessor->setValue('nama_pemberi_atas', $pemberi ? $pemberi['nama'] : '.......................................');
    $templateProcessor->setValue('nip_pemberi_atas', $pemberi ? $pemberi['nip'] : '.........................');
    $jabPemberiAtas = $pemberi ? ucwords(strtolower($pemberi['jabatan_ttd'])) : '.........................';
    $templateProcessor->setValue('jab_pemberi_atas', $jabPemberiAtas);

    // 3. Data Pegawai (Yang diberi izin)
    $templateProcessor->setValue('nama', $data['nama']);
    $templateProcessor->setValue('nip', $data['nip']);
    $templateProcessor->setValue('jabatan', $data['jabatan']);

    // 4. Data Waktu
    $templateProcessor->setValue('hari_tanggal', formatTanggalHari($data['tanggal_pulang']));
    $templateProcessor->setValue('pukul', substr($data['jam_pulang_diajukan'] ?? '00:00', 0, 5));
    $templateProcessor->setValue('alasan', htmlspecialchars($data['alasan']));

    // 5. Tanggal Surat
    $tglSurat = $data['tanggal_approval'] ? date('Y-m-d', strtotime($data['tanggal_approval'])) : date('Y-m-d');
    $templateProcessor->setValue('tgl_surat', formatTanggal($tglSurat));

    // 6. Data TTD bawah (pemberi izin)
    $jabPemberi = $jabPemberiAtas;
    if (stripos($jabPemberi, 'Pengadilan') === false && $jabPemberi !== '.........................') {
        $jabPemberi .= ' Pengadilan Tata Usaha Negara Samarinda';
    }
    $templateProcessor->setValue('jab_pemberi', $jabPemberi);
    $templateProcessor->setValue('nama_pemberi', $pemberi ? $pemberi['nama'] : '.......................................');
    $templateProcessor->setValue('nip_pemberi', $pemberi ? $pemberi['nip'] : '.........................');

    // 7. Tanda Tangan Image
    if ($pemberi && !empty($pemberi['ttd_path']) && file_exists(__DIR__ . '/../' . $pemberi['ttd_path'])) {
        $templateProcessor->setImageValue('ttd_pemberi', ['path' => __DIR__ . '/../' . $pemberi['ttd_path'], 'width' => 150, 'height' => 60, 'ratio' => false]);
    } else {
        $templateProcessor->setValue('ttd_pemberi', '');
    }

    // Output
    $jenisFile = ($tipeWaktu === 'datang_terlambat') ? 'Surat_Datang_Terlambat' : 'Surat_Pulang_Cepat';
    $filename = $jenisFile . '_' . str_replace(' ', '_', $data['nama']) . '_' . $pengajuanId . '.docx';

// ============================================================
// CUTI
// ============================================================
} else {

    $isPNSHakim = in_array($statusPegawai, ['Hakim', 'PNS']);

    if ($isPNSHakim) {
        $templatePath = __DIR__ . '/../templates/Contoh Cuti PNSHakim.docx';
    } else {
        $templatePath = __DIR__ . '/../templates/Contoh Cuti PPPK.docx';
    }

    if (!file_exists($templatePath)) {
        setFlash('error', 'File template Word tidak ditemukan!');
        redirect(BASE_URL . '/pages/riwayat.php');
    }

    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

    // 1. Data Pegawai
    $templateProcessor->setValue('nama', $data['nama']);
    $templateProcessor->setValue('nip', $data['nip']);
    $templateProcessor->setValue('jabatan', $data['jabatan']);
    $templateProcessor->setValue('golongan', $data['pangkat'] ?: '-');

    // Hitung Masa Kerja
    $masaKerja = hitungMasaKerja($data['tgl_mulai_kerja']);
    $templateProcessor->setValue('masa_kerja', $masaKerja);

    // 2. Data Cuti - Alasan & Lama
    $templateProcessor->setValue('alasan', htmlspecialchars($data['alasan']));
    $jumlahHari = (int)$data['jumlah_hari'];
    $terbilangHari = terbilang($jumlahHari);
    $lamaCutiText = $jumlahHari . ' (' . $terbilangHari . ') hari';
    $templateProcessor->setValue('lama_cuti_hari', $lamaCutiText);
    $templateProcessor->setValue('tgl_mulai', formatTanggal($data['tanggal_mulai']));
    $templateProcessor->setValue('tgl_selesai', formatTanggal($data['tanggal_selesai']));
    $templateProcessor->setValue('alamat_cuti', htmlspecialchars($data['alamat_cuti'] ?? ''));
    $templateProcessor->setValue('telepon', htmlspecialchars($data['telepon'] ?? ''));

    // 3. Saldo Cuti — selalu dari TAHUN_AKTIF
    $tahunCuti = (int) TAHUN_AKTIF;
    $saldo = cekSisaCuti($data['user_id'], $tahunCuti, $pdo);
    $tahunN = $tahunCuti;

    // Deteksi tipe cuti asli (tahunan vs tahunan_lalu) dari log_aktivitas
    // karena DB menyimpan keduanya sebagai 'tahunan'
    $realTipeCuti = $data['tipe_cuti'];
    if ($realTipeCuti === 'tahunan') {
        $stmtLog = $pdo->prepare("SELECT catatan FROM log_aktivitas WHERE pengajuan_id = ? AND aksi = 'pengajuan_baru' LIMIT 1");
        $stmtLog->execute([$pengajuanId]);
        $logCatatan = $stmtLog->fetchColumn() ?: '';
        if (strpos($logCatatan, 'tahunan_lalu') !== false) {
            $realTipeCuti = 'tahunan_lalu';
        }
    }

    $templateProcessor->setValue('thn_n', $tahunN);
    $templateProcessor->setValue('thn_n1', $tahunN - 1);
    $templateProcessor->setValue('thn_n2', $tahunN - 2);

    // Saldo saat ini sudah terpotong (terpakai sudah bertambah setelah approval)
    // Jadi $sisaN = saldo SETELAH dikurangi cuti ini
    $sisaN = max(0, $saldo['jatah_tahunan'] - $saldo['terpakai_tahunan']);
    $sisaN1 = max(0, $saldo['jatah_tahunan_lalu'] - $saldo['terpakai_tahunan_lalu']);

    // Kolom "Sisa" di template = saldo SEBELUM dikurangi (kembalikan jumlah hari jika cuti tahunan)
    if ($realTipeCuti === 'tahunan') {
        $sisaSebelumN = $sisaN + $jumlahHari;  // tambahkan kembali hari yg sudah dipotong
    } else {
        $sisaSebelumN = $saldo['jatah_tahunan'] - $saldo['terpakai_tahunan'];
    }
    $sisaSebelumN = max(0, $sisaSebelumN);

    // Jika tahunan_lalu, sisa N-1 SEBELUM dikurangi
    if ($realTipeCuti === 'tahunan_lalu') {
        $sisaSebelumN1 = $sisaN1 + $jumlahHari;
    } else {
        $sisaSebelumN1 = $sisaN1;
    }
    $sisaSebelumN1 = max(0, $sisaSebelumN1);

    $templateProcessor->setValue('sisa_n', $sisaSebelumN);
    $templateProcessor->setValue('sisa_n1', $sisaSebelumN1);
    $templateProcessor->setValue('sisa_n2', '-');

    // 4. Checkmark Jenis Cuti & Keterangan (BERBEDA per template)
    if ($isPNSHakim) {
        // === PNSHakim ===
        // Checkmark: k1=Tahunan, k2=Besar, k3=Sakit, k4=Melahirkan, k5=Alasan Penting, k6=Di Luar Tanggungan
        // tahunan_lalu juga dicentang di k1
        $pnsHakimMap = [
            'tahunan'                  => 'k1',
            'tahunan_lalu'             => 'k1',
            'besar'                    => 'k2',
            'sakit'                    => 'k3',
            'melahirkan'               => 'k4',
            'alasan_penting'           => 'k5',
            'di_luar_tanggungan_negara' => 'k6',
        ];

        foreach (['k1','k2','k3','k4','k5','k6'] as $tag) {
            $templateProcessor->setValue($tag, '');
        }
        $activeTag = $pnsHakimMap[$realTipeCuti] ?? null;
        if ($activeTag) {
            $templateProcessor->setValue($activeTag, 'V');
        }

        // Keterangan Cuti Tahunan per tahun: ket_th, ket_th1, ket_th2
        if ($realTipeCuti === 'tahunan') {
            $templateProcessor->setValue('ket_th', "diambil {$jumlahHari} sisa {$sisaN} hari");
            $templateProcessor->setValue('ket_th1', '-');
            $templateProcessor->setValue('ket_th2', '-');
        } elseif ($realTipeCuti === 'tahunan_lalu') {
            $templateProcessor->setValue('ket_th', '-');
            $templateProcessor->setValue('ket_th1', "diambil {$jumlahHari} sisa {$sisaN1} hari");
            $templateProcessor->setValue('ket_th2', '-');
        } else {
            $templateProcessor->setValue('ket_th', '-');
            $templateProcessor->setValue('ket_th1', '-');
            $templateProcessor->setValue('ket_th2', '-');
        }

    } else {
        // === PPPK ===
        // Checkmark: k1=Tahunan, k3=Sakit, k4=Melahirkan
        $pppkCheckMap = [
            'tahunan'    => 'k1',
            'sakit'      => 'k3',
            'melahirkan' => 'k4',
        ];

        foreach ($pppkCheckMap as $jenis => $tag) {
            $mark = ($data['tipe_cuti'] === $jenis) ? 'V' : '';
            $templateProcessor->setValue($tag, $mark);
        }

        // Keterangan: ket_tahunan, ket_sakit, ket_melahirkan
        // Format: "diambil X sisa Y hari"
        $templateProcessor->setValue('ket_tahunan', $data['tipe_cuti'] === 'tahunan' ? "diambil {$jumlahHari} sisa {$sisaN} hari" : '-');

        $sisaSakitSetelah = max(0, $saldo['jatah_sakit'] - $saldo['terpakai_sakit']);
        $templateProcessor->setValue('ket_sakit', $data['tipe_cuti'] === 'sakit' ? "diambil {$jumlahHari} sisa {$sisaSakitSetelah} hari" : '-');

        $sisaMelahirkanSetelah = max(0, $saldo['jatah_melahirkan'] - $saldo['terpakai_melahirkan']);
        $templateProcessor->setValue('ket_melahirkan', $data['tipe_cuti'] === 'melahirkan' ? "diambil {$jumlahHari} sisa {$sisaMelahirkanSetelah} hari" : '-');
    }

    // 5. Data Tanda Tangan
    $stmtTtd = $pdo->prepare("SELECT t.*, u.nama, u.nip, u.jabatan FROM ttd_pengajuan t JOIN users u ON t.user_id = u.id WHERE t.pengajuan_id = ? ORDER BY t.id ASC");
    $stmtTtd->execute([$pengajuanId]);
    $ttdList = $stmtTtd->fetchAll();

    $atasan = null;
    $pejabat = null;

    if (count($ttdList) >= 2) {
        $atasan = $ttdList[0];
        $pejabat = $ttdList[1];
    } elseif (count($ttdList) === 1) {
        $pejabat = $ttdList[0];
    }

    // Set nilai Atasan
    if ($atasan) {
        $templateProcessor->setValue('jab_atasan', $atasan['jabatan_ttd']);
        $templateProcessor->setValue('nama_atasan', $atasan['nama']);
        $templateProcessor->setValue('nip_atasan', $atasan['nip']);
    } else {
        $templateProcessor->setValue('jab_atasan', '');
        $templateProcessor->setValue('nama_atasan', '');
        $templateProcessor->setValue('nip_atasan', '');
    }

    // Set nilai Pejabat Berwenang
    if ($pejabat) {
        $templateProcessor->setValue('jab_pejabat', $pejabat['jabatan_ttd']);
        $templateProcessor->setValue('nama_pejabat', $pejabat['nama']);
        $templateProcessor->setValue('nip_pejabat', $pejabat['nip']);
    } else {
        $templateProcessor->setValue('jab_pejabat', '');
        $templateProcessor->setValue('nama_pejabat', '');
        $templateProcessor->setValue('nip_pejabat', '');
    }

    // Set Tanggal Surat
    $tglSurat = date('Y-m-d');
    $templateProcessor->setValue('tgl_surat', formatTanggal($tglSurat));

    // 6. Inject Images (TTD) — ukuran diperbesar
    if (!empty($data['ttd_pengaju']) && file_exists(__DIR__ . '/../' . $data['ttd_pengaju'])) {
        $templateProcessor->setImageValue('ttd_pengaju', ['path' => __DIR__ . '/../' . $data['ttd_pengaju'], 'width' => 150, 'height' => 60, 'ratio' => false]);
    } else {
        $templateProcessor->setValue('ttd_pengaju', '');
    }

    if ($atasan && !empty($atasan['ttd_path']) && file_exists(__DIR__ . '/../' . $atasan['ttd_path'])) {
        $templateProcessor->setImageValue('ttd_atasan', ['path' => __DIR__ . '/../' . $atasan['ttd_path'], 'width' => 150, 'height' => 60, 'ratio' => false]);
    } else {
        $templateProcessor->setValue('ttd_atasan', '');
    }

    if ($pejabat && !empty($pejabat['ttd_path']) && file_exists(__DIR__ . '/../' . $pejabat['ttd_path'])) {
        $templateProcessor->setImageValue('ttd_pejabat', ['path' => __DIR__ . '/../' . $pejabat['ttd_path'], 'width' => 150, 'height' => 60, 'ratio' => false]);
    } else {
        $templateProcessor->setValue('ttd_pejabat', '');
    }

    // Output filename cuti
    $filename = 'Surat_Cuti_' . str_replace(' ', '_', $data['nama']) . '_' . $pengajuanId . '.docx';
}

// Output File ke Browser
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

$templateProcessor->saveAs('php://output');
exit;

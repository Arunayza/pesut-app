<?php
/**
 * Download Surat PDF
 * Generate dan download surat yang sudah disetujui
 * - Cuti: Word template → PDF (PNSHakim atau PPPK)
 * - Izin/Pulang Cepat: TCPDF langsung
 */
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';
require_once __DIR__ . '/../helpers/pdf_generator.php';

requireLogin();

$pengajuanId = (int) ($_GET['id'] ?? 0);

if ($pengajuanId <= 0) {
    setFlash('error', 'Pengajuan tidak ditemukan!');
    redirect(BASE_URL . '/pages/riwayat.php');
}

// Ambil data pengajuan + pegawai
$stmt = $pdo->prepare("
    SELECT p.*, u.nama, u.nip, u.jabatan, u.pangkat, u.unit_kerja, u.email, u.tgl_mulai_kerja, u.jenis_kelamin, u.no_telp, u.alamat, u.status_pegawai
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

// Cek akses: admin bisa lihat semua, pegawai hanya miliknya sendiri
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

// Ambil TTD
$ttdList = ambilTTDPengajuan($pdo, $pengajuanId);

// Generate PDF
$statusPegawai = $data['status_pegawai'] ?? 'PPPK';

if ($data['jenis_pengajuan'] === 'cuti') {
    // ============================================================
    // CUTI — Word template → PDF
    // ============================================================
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
    
    // Data Pegawai
    $templateProcessor->setValue('nama', $data['nama']);
    $templateProcessor->setValue('nip', $data['nip']);
    $templateProcessor->setValue('jabatan', $data['jabatan']);
    $templateProcessor->setValue('golongan', $data['pangkat'] ?: '-');
    $templateProcessor->setValue('masa_kerja', hitungMasaKerja($data['tgl_mulai_kerja'] ?? '2020-01-01'));
    
    // Data Cuti
    $templateProcessor->setValue('alasan', htmlspecialchars($data['alasan']));
    $jumlahHari = (int)$data['jumlah_hari'];
    $terbilangHari = function_exists('terbilang') ? terbilang($jumlahHari) : '';
    $templateProcessor->setValue('lama_cuti_hari', $jumlahHari . ' (' . $terbilangHari . ') hari');
    $templateProcessor->setValue('tgl_mulai', formatTanggal($data['tanggal_mulai']));
    $templateProcessor->setValue('tgl_selesai', formatTanggal($data['tanggal_selesai']));
    $templateProcessor->setValue('alamat_cuti', htmlspecialchars($data['alamat_cuti'] ?? ''));
    $templateProcessor->setValue('telepon', htmlspecialchars($data['telepon'] ?? ''));
    
    // Saldo Cuti — selalu dari TAHUN_AKTIF
    $tahunCuti = (int) TAHUN_AKTIF;
    $saldo = cekSisaCuti($data['user_id'], $tahunCuti, $pdo);
    $tahunN = $tahunCuti;

    $templateProcessor->setValue('thn_n', $tahunN);
    $templateProcessor->setValue('thn_n1', $tahunN - 1);
    $templateProcessor->setValue('thn_n2', $tahunN - 2);

    // Saldo saat ini sudah terpotong (terpakai sudah bertambah setelah approval)
    $sisaN = max(0, $saldo['jatah_tahunan'] - $saldo['terpakai_tahunan']);
    $sisaN1 = max(0, $saldo['jatah_tahunan_lalu'] - $saldo['terpakai_tahunan_lalu']);
    
    // Kolom "Sisa" = saldo SEBELUM dikurangi
    if ($data['tipe_cuti'] === 'tahunan') {
        $sisaSebelumN = $sisaN + $jumlahHari;
    } else {
        $sisaSebelumN = $saldo['jatah_tahunan'] - $saldo['terpakai_tahunan'];
    }
    $sisaSebelumN = max(0, $sisaSebelumN);

    // Jika tahunan_lalu, sisa N-1 SEBELUM dikurangi
    if ($data['tipe_cuti'] === 'tahunan_lalu') {
        $sisaSebelumN1 = $sisaN1 + $jumlahHari;
    } else {
        $sisaSebelumN1 = $sisaN1;
    }
    $sisaSebelumN1 = max(0, $sisaSebelumN1);

    $templateProcessor->setValue('sisa_n', $sisaSebelumN);
    $templateProcessor->setValue('sisa_n1', $sisaSebelumN1);
    $templateProcessor->setValue('sisa_n2', '-');

    // Checkmark & Keterangan per template
    // Gunakan karakter checkmark ✓ (bukan 'V') untuk dokumen Word
    $CEKLIS = '✓';
    $KOSONG = '';

    if ($isPNSHakim) {
        // PNSHakim: k1-k6 = checkmark saja
        // tahunan_lalu juga dicentang di k1 (Cuti Tahunan)
        $pnsHakimMap = [
            'tahunan'                   => 'k1',
            'tahunan_lalu'              => 'k1',
            'besar'                     => 'k2',
            'sakit'                     => 'k3',
            'melahirkan'                => 'k4',
            'alasan_penting'            => 'k5',
            'di_luar_tanggungan_negara' => 'k6',
        ];

        foreach (['k1','k2','k3','k4','k5','k6'] as $tag) {
            $templateProcessor->setValue($tag, $KOSONG);
        }
        $activeTag = $pnsHakimMap[$data['tipe_cuti']] ?? null;
        if ($activeTag) {
            $templateProcessor->setValue($activeTag, $CEKLIS);
        }

        // Keterangan: ket_th, ket_th1, ket_th2
        if ($data['tipe_cuti'] === 'tahunan') {
            $templateProcessor->setValue('ket_th', "diambil {$jumlahHari} sisa {$sisaN} hari");
            $templateProcessor->setValue('ket_th1', '-');
            $templateProcessor->setValue('ket_th2', '-');
        } elseif ($data['tipe_cuti'] === 'tahunan_lalu') {
            $templateProcessor->setValue('ket_th', '-');
            $templateProcessor->setValue('ket_th1', "diambil {$jumlahHari} sisa {$sisaN1} hari");
            $templateProcessor->setValue('ket_th2', '-');
        } else {
            $templateProcessor->setValue('ket_th', '-');
            $templateProcessor->setValue('ket_th1', '-');
            $templateProcessor->setValue('ket_th2', '-');
        }
    } else {
        // PPPK: k1/k3/k4 = checkmark
        $pppkCheckMap = ['tahunan' => 'k1', 'sakit' => 'k3', 'melahirkan' => 'k4'];
        foreach ($pppkCheckMap as $jenis => $tag) {
            $templateProcessor->setValue($tag, ($data['tipe_cuti'] === $jenis) ? $CEKLIS : $KOSONG);
        }

        $templateProcessor->setValue('ket_tahunan', $data['tipe_cuti'] === 'tahunan' ? "diambil {$jumlahHari} sisa {$sisaN} hari" : '-');
        $sisaSakitSetelah = max(0, $saldo['jatah_sakit'] - $saldo['terpakai_sakit']);
        $templateProcessor->setValue('ket_sakit', $data['tipe_cuti'] === 'sakit' ? "diambil {$jumlahHari} sisa {$sisaSakitSetelah} hari" : '-');
        $sisaMelahirkanSetelah = max(0, $saldo['jatah_melahirkan'] - $saldo['terpakai_melahirkan']);
        $templateProcessor->setValue('ket_melahirkan', $data['tipe_cuti'] === 'melahirkan' ? "diambil {$jumlahHari} sisa {$sisaMelahirkanSetelah} hari" : '-');
    }

    // ↓ Blok status checkmark dipindah ke bawah setelah $atasan/$pejabat di-assign

    
    // TTD
    $atasan = null;
    $pejabat = null;
    if (count($ttdList) >= 2) {
        $atasan = $ttdList[0];
        $pejabat = $ttdList[1];
    } elseif (count($ttdList) === 1) {
        $pejabat = $ttdList[0];
    }
    
    $templateProcessor->setValue('jab_atasan', $atasan ? $atasan['jabatan_ttd'] : '');
    $templateProcessor->setValue('nama_atasan', $atasan ? $atasan['nama'] : '');
    $templateProcessor->setValue('nip_atasan', $atasan ? $atasan['nip'] : '');
    $templateProcessor->setValue('jab_pejabat', $pejabat ? $pejabat['jabatan_ttd'] : '');
    $templateProcessor->setValue('nama_pejabat', $pejabat ? $pejabat['nama'] : '');
    $templateProcessor->setValue('nip_pejabat', $pejabat ? $pejabat['nip'] : '');
    $templateProcessor->setValue('tgl_surat', formatTanggal(date('Y-m-d')));
    
    // Ukuran TTD seragam: lebar 150, tinggi 60 — semua kolom TTD
    $ttdSize = ['width' => 150, 'height' => 60, 'ratio' => false];

    // TTD Pengaju (tanda tangan pemohon saat submit form)
    if (!empty($data['ttd_pengaju']) && file_exists(__DIR__ . '/../' . $data['ttd_pengaju'])) {
        $templateProcessor->setImageValue('ttd_pengaju', array_merge(['path' => __DIR__ . '/../' . $data['ttd_pengaju']], $ttdSize));
    } else {
        $templateProcessor->setValue('ttd_pengaju', '');
    }

    // TTD Atasan Langsung (TTD ke-1)
    if ($atasan && !empty($atasan['ttd_path']) && file_exists(__DIR__ . '/../' . $atasan['ttd_path'])) {
        $templateProcessor->setImageValue('ttd_atasan', array_merge(['path' => __DIR__ . '/../' . $atasan['ttd_path']], $ttdSize));
    } else {
        $templateProcessor->setValue('ttd_atasan', '');
    }

    // TTD Pejabat Berwenang (TTD ke-2)
    if ($pejabat && !empty($pejabat['ttd_path']) && file_exists(__DIR__ . '/../' . $pejabat['ttd_path'])) {
        $templateProcessor->setImageValue('ttd_pejabat', array_merge(['path' => __DIR__ . '/../' . $pejabat['ttd_path']], $ttdSize));
    } else {
        $templateProcessor->setValue('ttd_pejabat', '');
    }

    // ---- STATUS DISETUJUI / TIDAK DISETUJUI (kolom VII & VIII) ----
    // Harus setelah $atasan/$pejabat di-assign agar kondisi if() benar
    $statusPengajuan = $data['status'] ?? 'pending';

    // Atasan Langsung (kolom VII) — ceklis jika sudah TTD dan status disetujui/ditolak
    if ($atasan) {
        $templateProcessor->setValue('disetujui_atasan',       $statusPengajuan === 'disetujui' ? $CEKLIS : $KOSONG);
        $templateProcessor->setValue('tidak_disetujui_atasan', $statusPengajuan === 'ditolak'   ? $CEKLIS : $KOSONG);
    } else {
        $templateProcessor->setValue('disetujui_atasan',       $KOSONG);
        $templateProcessor->setValue('tidak_disetujui_atasan', $KOSONG);
    }

    // Pejabat Berwenang (kolom VIII) — ceklis setelah TTD ke-2
    if ($pejabat) {
        $templateProcessor->setValue('disetujui_pejabat',       $statusPengajuan === 'disetujui' ? $CEKLIS : $KOSONG);
        $templateProcessor->setValue('tidak_disetujui_pejabat', $statusPengajuan === 'ditolak'   ? $CEKLIS : $KOSONG);
    } else {
        $templateProcessor->setValue('disetujui_pejabat',       $KOSONG);
        $templateProcessor->setValue('tidak_disetujui_pejabat', $KOSONG);
    }
    
    $tempDocx = sys_get_temp_dir() . '/temp_cuti_' . time() . '_' . $pengajuanId . '.docx';
    $tempPdf = sys_get_temp_dir() . '/temp_cuti_' . time() . '_' . $pengajuanId . '.pdf';
    $pdfFilename = 'Surat_Cuti_' . str_replace(' ', '_', $data['nama']) . '_' . $pengajuanId . '.pdf';

} elseif ($data['jenis_pengajuan'] === 'izin') {
    // ============================================================
    // IZIN KELUAR KANTOR — Word template → PDF
    // ============================================================
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

    $tempDocx = sys_get_temp_dir() . '/temp_izin_' . time() . '_' . $pengajuanId . '.docx';
    $tempPdf = sys_get_temp_dir() . '/temp_izin_' . time() . '_' . $pengajuanId . '.pdf';
    $pdfFilename = 'Surat_Izin_Keluar_' . str_replace(' ', '_', $data['nama']) . '_' . $pengajuanId . '.pdf';

} elseif ($data['jenis_pengajuan'] === 'pulang_cepat') {
    // ============================================================
    // PULANG CEPAT / DATANG TERLAMBAT — Word template → PDF
    // ============================================================
    $templatePath = __DIR__ . '/../templates/Izin_telat_dan_pulangcepat.docx';

    if (!file_exists($templatePath)) {
        setFlash('error', 'File template Word Pulang Cepat/Datang Terlambat tidak ditemukan!');
        redirect(BASE_URL . '/pages/riwayat.php');
    }

    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

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

    $jenisFile = ($tipeWaktu === 'datang_terlambat') ? 'Surat_Datang_Terlambat' : 'Surat_Pulang_Cepat';
    $tempDocx = sys_get_temp_dir() . '/temp_pc_' . time() . '_' . $pengajuanId . '.docx';
    $tempPdf = sys_get_temp_dir() . '/temp_pc_' . time() . '_' . $pengajuanId . '.pdf';
    $pdfFilename = $jenisFile . '_' . str_replace(' ', '_', $data['nama']) . '_' . $pengajuanId . '.pdf';

} else {
    setFlash('error', 'Jenis pengajuan tidak dikenali!');
    redirect(BASE_URL . '/pages/riwayat.php');
}

// ============================================================
// Konversi Word → PDF (untuk cuti, izin keluar kantor, dan pulang cepat/datang terlambat)
// ============================================================
$templateProcessor->saveAs($tempDocx);

$vbsScript = realpath(__DIR__ . '/../helpers/convert_pdf.vbs');
$cmd = 'cscript //nologo "' . $vbsScript . '" "' . $tempDocx . '" "' . $tempPdf . '"';
shell_exec($cmd);

if (file_exists($tempPdf)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="'.$pdfFilename.'"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($tempPdf);
    
    // Clean up
    @unlink($tempDocx);
    @unlink($tempPdf);
    exit;
} else {
    @unlink($tempDocx);
    setFlash('error', 'Gagal mengonversi file ke PDF.');
    redirect(BASE_URL . '/pages/riwayat.php');
}

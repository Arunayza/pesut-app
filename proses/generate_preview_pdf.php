<?php
/**
 * Generate Preview PDF — Sama persis kayak download_surat.php
 * Tapi: TTD atasan/pejabat KOSONG, data dari SESSION bukan dari DB
 * Output: PDF inline di browser
 */
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';

requireLogin();

if (empty($_SESSION['preview_data'])) {
    http_response_code(400);
    echo 'Tidak ada data preview.';
    exit;
}

$data = $_SESSION['preview_data'];
$jenis = $data['jenis'];
$profil = $data['profil'];
$statusPegawai = $profil['status_pegawai'] ?? 'PNS';

if ($jenis === 'cuti') {
    // ============================================================
    // CUTI — Word template → PDF (sama persis kayak download_surat.php)
    // ============================================================
    $isPNSHakim = in_array($statusPegawai, ['Hakim', 'PNS']);

    if ($isPNSHakim) {
        $templatePath = __DIR__ . '/../templates/Contoh Cuti PNSHakim.docx';
    } else {
        $templatePath = __DIR__ . '/../templates/Contoh Cuti PPPK.docx';
    }

    if (!file_exists($templatePath)) {
        http_response_code(500);
        echo 'File template Word tidak ditemukan!';
        exit;
    }

    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

    // Data Pegawai
    $templateProcessor->setValue('nama', $profil['nama']);
    $templateProcessor->setValue('nip', $profil['nip']);
    $templateProcessor->setValue('jabatan', $profil['jabatan']);
    $templateProcessor->setValue('golongan', $profil['pangkat'] ?: '-');
    $templateProcessor->setValue('masa_kerja', hitungMasaKerja($profil['tgl_mulai_kerja'] ?? '2020-01-01'));

    // Data Cuti
    $templateProcessor->setValue('alasan', htmlspecialchars($data['alasan']));
    $jumlahHari = (int)$data['jumlah_hari'];
    $terbilangHari = function_exists('terbilang') ? terbilang($jumlahHari) : '';
    $templateProcessor->setValue('lama_cuti_hari', $jumlahHari . ' (' . $terbilangHari . ') hari');
    $templateProcessor->setValue('tgl_mulai', formatTanggal($data['tanggal_mulai']));
    $templateProcessor->setValue('tgl_selesai', formatTanggal($data['tanggal_selesai']));
    $templateProcessor->setValue('alamat_cuti', htmlspecialchars($data['alamat_cuti'] ?? ''));
    $templateProcessor->setValue('telepon', htmlspecialchars($data['telepon'] ?? ''));

    // Saldo Cuti
    $saldo = $data['saldo'];
    $tahunN = (int)date('Y', strtotime($data['tanggal_mulai']));
    $tipeCuti = $data['tipe_cuti'];

    $templateProcessor->setValue('thn_n', $tahunN);
    $templateProcessor->setValue('thn_n1', $tahunN - 1);
    $templateProcessor->setValue('thn_n2', $tahunN - 2);

    $sisaN = max(0, $saldo['jatah_tahunan'] - $saldo['terpakai_tahunan']);
    $sisaN1 = max(0, $saldo['jatah_tahunan_lalu'] - $saldo['terpakai_tahunan_lalu']);

    // Kolom "Sisa" = saldo sebelum dikurangi (belum submit, jadi masih utuh)
    $templateProcessor->setValue('sisa_n', $sisaN);
    $templateProcessor->setValue('sisa_n1', $sisaN1);
    $templateProcessor->setValue('sisa_n2', '-');

    // Checkmark
    $CEKLIS = '✓';
    $KOSONG = '';
    $actualTipe = ($tipeCuti === 'tahunan_lalu') ? 'tahunan' : $tipeCuti;

    if ($isPNSHakim) {
        $pnsHakimMap = [
            'tahunan' => 'k1', 'besar' => 'k2', 'sakit' => 'k3',
            'melahirkan' => 'k4', 'alasan_penting' => 'k5',
            'di_luar_tanggungan_negara' => 'k6',
        ];
        foreach ($pnsHakimMap as $j => $tag) {
            $templateProcessor->setValue($tag, ($actualTipe === $j) ? $CEKLIS : $KOSONG);
        }
        if ($actualTipe === 'tahunan') {
            $sisaSetelah = max(0, $sisaN - $jumlahHari);
            $templateProcessor->setValue('ket_th', "diambil {$jumlahHari} sisa {$sisaSetelah} hari");
            $templateProcessor->setValue('ket_th1', '-');
            $templateProcessor->setValue('ket_th2', '-');
        } else {
            $templateProcessor->setValue('ket_th', '-');
            $templateProcessor->setValue('ket_th1', '-');
            $templateProcessor->setValue('ket_th2', '-');
        }
    } else {
        $pppkCheckMap = ['tahunan' => 'k1', 'sakit' => 'k3', 'melahirkan' => 'k4'];
        foreach ($pppkCheckMap as $j => $tag) {
            $templateProcessor->setValue($tag, ($actualTipe === $j) ? $CEKLIS : $KOSONG);
        }
        $sisaSetelah = max(0, $sisaN - $jumlahHari);
        $templateProcessor->setValue('ket_tahunan', $actualTipe === 'tahunan' ? "diambil {$jumlahHari} sisa {$sisaSetelah} hari" : '-');
        $sisaSakitSetelah = max(0, $saldo['jatah_sakit'] - $saldo['terpakai_sakit']);
        $templateProcessor->setValue('ket_sakit', $actualTipe === 'sakit' ? "diambil {$jumlahHari} sisa {$sisaSakitSetelah} hari" : '-');
        $sisaMelahirkanSetelah = max(0, $saldo['jatah_melahirkan'] - $saldo['terpakai_melahirkan']);
        $templateProcessor->setValue('ket_melahirkan', $actualTipe === 'melahirkan' ? "diambil {$jumlahHari} sisa {$sisaMelahirkanSetelah} hari" : '-');
    }

    // TTD Atasan — KOSONG (belum submit, preview only)
    $atasanData = $data['atasan'];
    $templateProcessor->setValue('jab_atasan', $atasanData ? $atasanData['jabatan'] : '');
    $templateProcessor->setValue('nama_atasan', $atasanData ? $atasanData['nama'] : '');
    $templateProcessor->setValue('nip_atasan', $atasanData ? $atasanData['nip'] : '');
    $templateProcessor->setValue('ttd_atasan', ''); // KOSONG — belum TTD

    // TTD Pejabat — KOSONG
    $templateProcessor->setValue('jab_pejabat', '');
    $templateProcessor->setValue('nama_pejabat', '.........................................');
    $templateProcessor->setValue('nip_pejabat', '.........................');
    $templateProcessor->setValue('ttd_pejabat', '');

    // Status checkmark — KOSONG (belum diproses)
    $templateProcessor->setValue('disetujui_atasan', $KOSONG);
    $templateProcessor->setValue('tidak_disetujui_atasan', $KOSONG);
    $templateProcessor->setValue('disetujui_pejabat', $KOSONG);
    $templateProcessor->setValue('tidak_disetujui_pejabat', $KOSONG);

    $templateProcessor->setValue('tgl_surat', formatTanggal(date('Y-m-d')));

    // TTD Pengaju — dari base64 session
    $ttdPengajuBase64 = $data['ttd_pengaju'] ?? '';
    if (!empty($ttdPengajuBase64)) {
        $ttdRaw = str_replace('data:image/png;base64,', '', $ttdPengajuBase64);
        $ttdRaw = str_replace(' ', '+', $ttdRaw);
        $imgData = base64_decode($ttdRaw);
        $tmpTtdFile = sys_get_temp_dir() . '/preview_ttd_' . session_id() . '.png';
        file_put_contents($tmpTtdFile, $imgData);
        $templateProcessor->setImageValue('ttd_pengaju', [
            'path' => $tmpTtdFile, 'width' => 150, 'height' => 60, 'ratio' => false
        ]);
    } else {
        $templateProcessor->setValue('ttd_pengaju', '');
    }

    $tempDocx = sys_get_temp_dir() . '/preview_cuti_' . session_id() . '.docx';
    $tempPdf = sys_get_temp_dir() . '/preview_cuti_' . session_id() . '.pdf';
    $pdfFilename = 'Preview_Cuti_' . str_replace(' ', '_', $profil['nama']) . '.pdf';

} elseif ($jenis === 'izin') {
    // ============================================================
    // IZIN KELUAR KANTOR — Word template → PDF
    // ============================================================
    if ($statusPegawai === 'Hakim') {
        $templatePath = __DIR__ . '/../templates/Izin_Keluar_Hakim.docx';
    } else {
        $templatePath = __DIR__ . '/../templates/Izin_Keluar_pegawaip3k.docx';
    }

    if (!file_exists($templatePath)) {
        http_response_code(500);
        echo 'File template Word Izin Keluar tidak ditemukan!';
        exit;
    }

    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

    // Pemberi izin = atasan langsung (TTD kosong di preview)
    $atasanData = $data['atasan'];
    $jabPemberi = $atasanData ? ucwords(strtolower($atasanData['jabatan'])) : '.........................';
    if ($atasanData && stripos($jabPemberi, 'Pengadilan') === false) {
        $jabPemberi .= ' Pengadilan Tata Usaha Negara Samarinda';
    }
    $templateProcessor->setValue('jab_pemberi', $jabPemberi);
    $templateProcessor->setValue('nama_pemberi', $atasanData ? $atasanData['nama'] : '.......................................');
    $templateProcessor->setValue('nip_pemberi', $atasanData ? $atasanData['nip'] : '.........................');

    // Data Pegawai
    $templateProcessor->setValue('nama', $profil['nama']);
    $templateProcessor->setValue('nip', $profil['nip']);

    // Data Izin
    $templateProcessor->setValue('hari_tanggal', formatTanggalHari($data['tanggal_mulai']));
    $templateProcessor->setValue('jam_mulai', substr($data['jam_mulai'] ?? '00:00', 0, 5));
    $templateProcessor->setValue('jam_selesai', substr($data['jam_selesai'] ?? '00:00', 0, 5));
    $templateProcessor->setValue('keperluan', htmlspecialchars($data['alasan']));

    // Tanggal surat
    $templateProcessor->setValue('tgl_surat', formatTanggal(date('Y-m-d')));

    // TTD pemberi — KOSONG (preview)
    $templateProcessor->setValue('ttd_pemberi', '');

    $tempDocx = sys_get_temp_dir() . '/preview_izin_' . session_id() . '.docx';
    $tempPdf = sys_get_temp_dir() . '/preview_izin_' . session_id() . '.pdf';
    $pdfFilename = 'Preview_Izin_Keluar_' . str_replace(' ', '_', $profil['nama']) . '.pdf';

} elseif ($jenis === 'pulang_cepat') {
    // ============================================================
    // PULANG CEPAT / DATANG TERLAMBAT — Word template → PDF
    // ============================================================
    $templatePath = __DIR__ . '/../templates/Izin_telat_dan_pulangcepat.docx';

    if (!file_exists($templatePath)) {
        http_response_code(500);
        echo 'File template Word Pulang Cepat tidak ditemukan!';
        exit;
    }

    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

    $atasanData = $data['atasan'];
    $tipeWaktu = $data['tipe_izin_waktu'] ?? 'pulang_cepat';

    // Kondisi: Datang Terlambat atau Pulang Cepat
    if ($tipeWaktu === 'datang_terlambat') {
        $templateProcessor->setValue('izin1', 'DATANG TERLAMBAT');
        $templateProcessor->setValue('izin2', '');
    } else {
        $templateProcessor->setValue('izin1', '');
        $templateProcessor->setValue('izin2', 'PULANG CEPAT');
    }

    // Data pemberi izin (atas)
    $templateProcessor->setValue('nama_pemberi_atas', $atasanData ? $atasanData['nama'] : '.......................................');
    $templateProcessor->setValue('nip_pemberi_atas', $atasanData ? $atasanData['nip'] : '.........................');
    $jabPemberiAtas = $atasanData ? ucwords(strtolower($atasanData['jabatan'])) : '.........................';
    $templateProcessor->setValue('jab_pemberi_atas', $jabPemberiAtas);

    // Data Pegawai
    $templateProcessor->setValue('nama', $profil['nama']);
    $templateProcessor->setValue('nip', $profil['nip']);
    $templateProcessor->setValue('jabatan', $profil['jabatan']);

    // Data Waktu
    $templateProcessor->setValue('hari_tanggal', formatTanggalHari($data['tanggal_pulang']));
    $templateProcessor->setValue('pukul', substr($data['jam_pulang_diajukan'] ?? '00:00', 0, 5));
    $templateProcessor->setValue('alasan', htmlspecialchars($data['alasan']));

    // Tanggal surat
    $templateProcessor->setValue('tgl_surat', formatTanggal(date('Y-m-d')));

    // Data TTD bawah (pemberi izin)
    $jabPemberi = $jabPemberiAtas;
    if ($atasanData && stripos($jabPemberi, 'Pengadilan') === false) {
        $jabPemberi .= ' Pengadilan Tata Usaha Negara Samarinda';
    }
    $templateProcessor->setValue('jab_pemberi', $jabPemberi);
    $templateProcessor->setValue('nama_pemberi', $atasanData ? $atasanData['nama'] : '.......................................');
    $templateProcessor->setValue('nip_pemberi', $atasanData ? $atasanData['nip'] : '.........................');

    // TTD pemberi — KOSONG (preview)
    $templateProcessor->setValue('ttd_pemberi', '');

    $tempDocx = sys_get_temp_dir() . '/preview_pc_' . session_id() . '.docx';
    $tempPdf = sys_get_temp_dir() . '/preview_pc_' . session_id() . '.pdf';
    $jenisFile = ($tipeWaktu === 'datang_terlambat') ? 'Preview_Datang_Terlambat' : 'Preview_Pulang_Cepat';
    $pdfFilename = $jenisFile . '_' . str_replace(' ', '_', $profil['nama']) . '.pdf';

} else {
    http_response_code(400);
    echo 'Jenis pengajuan tidak dikenali!';
    exit;
}

// ============================================================
// Konversi Word → PDF (sama persis kayak download_surat.php)
// ============================================================
$templateProcessor->saveAs($tempDocx);

$vbsScript = realpath(__DIR__ . '/../helpers/convert_pdf.vbs');
$cmd = 'cscript //nologo "' . $vbsScript . '" "' . $tempDocx . '" "' . $tempPdf . '"';
shell_exec($cmd);

if (file_exists($tempPdf)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $pdfFilename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($tempPdf);

    // Clean up temp files
    @unlink($tempDocx);
    @unlink($tempPdf);
    if (isset($tmpTtdFile) && file_exists($tmpTtdFile)) @unlink($tmpTtdFile);
    exit;
} else {
    @unlink($tempDocx);
    http_response_code(500);
    echo 'Gagal mengonversi file ke PDF. Pastikan Microsoft Word terinstall di server.';
    exit;
}

<?php
/**
 * PESUT - PDF Generator
 * Generate surat dinas resmi menggunakan TCPDF
 */
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Generate PDF surat izin/cuti/pulang cepat
 */
function generateSuratPDF(array $pengajuan, array $pegawai, array $ttdList, PDO $pdo): TCPDF
{
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 15, 20);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);
    $pdf->AddPage();

    $tanggalSurat = formatTanggalPDF($pengajuan['tanggal_approval'] ?? date('Y-m-d'));
    
    // Jarak spasi dari atas
    if ($pengajuan['jenis_pengajuan'] === 'izin' && ($pegawai['status_pegawai'] ?? '') === 'Hakim') {
        $pdf->Ln(5); // Khusus hakim izin keluar kantor, margin atas ~2cm (15mm base + 5mm)
    } else {
        $pdf->Ln(30); // Sekitar 4.5cm (15mm base + 30mm)
    }

    // Judul
    $ttd = $ttdList[0] ?? null; // Pejabat pemberi izin
    
    $pemberiNama = $ttd ? $ttd['nama'] : '.......................................';
    $pemberiNip = $ttd ? $ttd['nip'] : '.........................';
    $pemberiJabatan = $ttd ? ucwords(strtolower($ttd['jabatan_ttd'])) : '.........................';
    
    $jabatanSelaku = $pemberiJabatan;
    if (stripos($jabatanSelaku, 'Pengadilan') === false && $jabatanSelaku !== '.........................') {
        $jabatanSelaku .= ' Pengadilan Tata Usaha Negara Samarinda';
    }
    
    $htmlBody = '';

    if ($pengajuan['jenis_pengajuan'] === 'izin') {
        
        $hariTanggal = formatTanggalPDF($pengajuan['tanggal_mulai']);
        $jamMulai = substr($pengajuan['jam_mulai'] ?? '00:00', 0, 5);
        $jamSelesai = substr($pengajuan['jam_selesai'] ?? '00:00', 0, 5);
        $alasan = htmlspecialchars($pengajuan['alasan']);
        
        $lampiranHakim = '';
        if (($pegawai['status_pegawai'] ?? '') === 'Hakim') {
            $lampiranHakim = '
            <table border="0" width="100%" style="font-family:times; font-size:10pt;">
                <tr>
                    <td width="50%"></td>
                    <td width="50%" style="text-align:justify;">
                        Lampiran II : Formulir Izin Keluar Kantor<br><br>
                        Peraturan Mahkamah Agung Nomor 7 Tahun 2016 tentang Penegakan Disiplin Kerja Hakim pada Mahkamah Agung dan Badan Peradilan yang Berada di Bawahnya
                    </td>
                </tr>
            </table><br><br>
            ';
        }
        
        $htmlBody = $lampiranHakim . '
        <div style="text-align:center; font-family:times; font-size:14pt; font-weight:bold;">
            SURAT IZIN KELUAR KANTOR
        </div>
        <br><br>
        <div style="font-family:times; font-size:12pt; text-align:justify; line-height: 1.5;">
            Yang bertanda tangan di bawah ini:<br>
            <table border="0" cellpadding="2" width="100%" style="margin-left: 20px;">
                <tr><td width="20%">Selaku</td><td width="5%">:</td><td width="75%"><b>' . $jabatanSelaku . '</b>,</td></tr>
            </table>
            dengan ini memberikan izin kepada:<br>
            <table border="0" cellpadding="2" width="100%" style="margin-left: 20px;">
                <tr><td width="20%">Nama</td><td width="5%">:</td><td width="75%">' . htmlspecialchars($pegawai['nama']) . '</td></tr>
                <tr><td>NIP</td><td>:</td><td>' . htmlspecialchars($pegawai['nip']) . '</td></tr>
            </table>
            <br>
            Untuk keluar kantor pada :<br>
            <table border="0" cellpadding="2" width="100%" style="margin-left: 20px;">
                <tr><td width="20%">Hari / Tanggal</td><td width="5%">:</td><td width="75%">' . $hariTanggal . '</td></tr>
                <tr><td>Pukul</td><td>:</td><td>' . $jamMulai . ' WITA s.d. ' . $jamSelesai . ' WITA</td></tr>
                <tr><td>Untuk Keperluan</td><td>:</td><td>' . $alasan . '</td></tr>
            </table>
            <br>
            Demikian izin ini diberikan kepada yang bersangkutan untuk digunakan sebagaimana mestinya.
        </div>
        <br><br>
        ';
    } else {
        $jenisTeks = '';
        $judulSurat = '';
        if (($pengajuan['tipe_izin_waktu'] ?? '') === 'datang_terlambat') {
            $jenisTeks = 'datang terlambat';
            $judulSurat = 'SURAT IZIN DATANG TERLAMBAT';
            $hariTanggal = formatTanggalPDF($pengajuan['tanggal_pulang']);
            $jam = substr($pengajuan['jam_pulang_diajukan'], 0, 5);
            $alasan = htmlspecialchars($pengajuan['alasan']);
        } else {
            $jenisTeks = 'pulang cepat';
            $judulSurat = 'SURAT IZIN PULANG CEPAT';
            $hariTanggal = formatTanggalPDF($pengajuan['tanggal_pulang']);
            $jam = substr($pengajuan['jam_pulang_diajukan'], 0, 5);
            $alasan = htmlspecialchars($pengajuan['alasan']);
        }

        $htmlBody = '
        <div style="text-align:center; font-family:times; font-size:14pt; font-weight:bold;">
            ' . $judulSurat . '
        </div>
        <br><br>
        <div style="font-family:times; font-size:12pt; text-align:justify; line-height: 1.5;">
            Yang bertanda tangan di bawah ini:<br>
            <table border="0" cellpadding="2" width="100%" style="margin-left: 20px;">
                <tr><td width="20%">Nama</td><td width="5%">:</td><td width="75%">' . $pemberiNama . '</td></tr>
                <tr><td>NIP</td><td>:</td><td>' . $pemberiNip . '</td></tr>
                <tr><td>Jabatan</td><td>:</td><td>' . $pemberiJabatan . '</td></tr>
                <tr><td>Unit Kerja</td><td>:</td><td>Pengadilan Tata Usaha Negara Samarinda</td></tr>
            </table>
            <br>
            Memberikan izin ' . $jenisTeks . ' kepada:<br>
            <table border="0" cellpadding="2" width="100%" style="margin-left: 20px;">
                <tr><td width="20%">Nama</td><td width="5%">:</td><td width="75%">' . htmlspecialchars($pegawai['nama']) . '</td></tr>
                <tr><td>NIP</td><td>:</td><td>' . htmlspecialchars($pegawai['nip']) . '</td></tr>
                <tr><td>Jabatan</td><td>:</td><td>' . htmlspecialchars($pegawai['jabatan']) . '</td></tr>
                <tr><td>Unit Kerja</td><td>:</td><td>Pengadilan Tata Usaha Negara Samarinda</td></tr>
            </table>
            <br>
            <table border="0" cellpadding="2" width="100%" style="margin-left: 20px;">
                <tr><td width="20%">Tanggal</td><td width="5%">:</td><td width="75%">' . $hariTanggal . '</td></tr>
                <tr><td>Pukul</td><td>:</td><td>' . $jam . ' WITA</td></tr>
                <tr><td>Alasan</td><td>:</td><td>' . $alasan . '</td></tr>
            </table>
            <br>
            Demikian izin ini diberikan kepada yang bersangkutan untuk digunakan sebagaimana mestinya.
        </div>
        <br><br>
        ';
    }

    $pdf->writeHTML($htmlBody, true, false, false, false, '');

    // TTD HANYA 1 ORANG (Pejabat Berwenang yang Menyetujui)
    $htmlTtd = '
    <table width="100%" border="0" cellpadding="2" style="font-family:times; font-size:12pt; text-align:center;">
        <tr>
            <td width="60%"></td>
            <td width="40%">Samarinda, ' . $tanggalSurat . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Yang Memberi Izin,</td>
        </tr>
        <tr>
            <td></td>
            <td>' . $pemberiJabatan . '</td>
        </tr>
        <tr><td colspan="2"><br><br><br><br></td></tr>
        <tr>
            <td></td>
            <td><b><u>' . $pemberiNama . '</u></b><br>NIP. ' . $pemberiNip . '</td>
        </tr>
    </table>
    ';

    $startY = $pdf->GetY();
    $pdf->writeHTML($htmlTtd, true, false, false, false, '');

    // Embed TTD image if exists
    if ($ttd && file_exists(__DIR__ . '/../' . $ttd['ttd_path'])) {
        // Position it over the signature area (lowered slightly because of the extra "Yang Memberi Izin" text)
        $pdf->Image(__DIR__ . '/../' . $ttd['ttd_path'], 135, $startY + 20, 40, 20, 'PNG');
    }

    return $pdf;
}

/**
 * Generate nomor surat otomatis
 */
function generateNomorSurat(array $pengajuan): string
{
    $jenisKode = [
        'cuti' => 'CT',
        'izin' => 'IZ',
        'pulang_cepat' => 'PC',
    ];
    $kode = $jenisKode[$pengajuan['jenis_pengajuan']] ?? 'XX';
    $bulan = toRomawi((int) date('m', strtotime($pengajuan['created_at'])));
    $tahun = date('Y', strtotime($pengajuan['created_at']));
    $id = str_pad($pengajuan['id'], 4, '0', STR_PAD_LEFT);
    return "W18-A/{$id}/KP.04.02/{$kode}/{$bulan}/{$tahun}";
}

/**
 * Angka ke Romawi
 */
function toRomawi(int $angka): string
{
    $romawi = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    return $romawi[$angka] ?? (string) $angka;
}

/**
 * Format tanggal untuk PDF
 */
function formatTanggalPDF(string $tanggal): string
{
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $dt = new DateTime($tanggal);
    return $dt->format('j') . ' ' . $bulan[(int) $dt->format('n')] . ' ' . $dt->format('Y');
}

/**
 * Format selisih waktu untuk PDF
 */
function formatSelisihWaktuPDF(int $menit): string
{
    $jam = floor($menit / 60);
    $sisa = $menit % 60;
    if ($jam > 0 && $sisa > 0) return "{$jam} jam {$sisa} menit";
    if ($jam > 0) return "{$jam} jam";
    return "{$sisa} menit";
}

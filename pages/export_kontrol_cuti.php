<?php
/**
 * Export Kontrol Cuti ke Excel (Format HTML)
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireLogin();

if (!in_array($_SESSION['role'], ['kepegawaian', 'admin', 'staf_kpot'])) {
    die("Akses ditolak.");
}

$id = (int) ($_GET['id'] ?? 0);
$tahun = (int) ($_GET['tahun'] ?? TAHUN_AKTIF);

if ($id <= 0) {
    die("Data pegawai tidak valid.");
}

// Ambil data user
$stmt = $pdo->prepare("SELECT nip, nama, jabatan, status_pegawai, jenis_kelamin FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    die("Pegawai tidak ditemukan.");
}

// Ambil saldo
$saldo = cekSisaCuti($id, $tahun, $pdo);

// Ambil riwayat cuti disetujui di tahun tsb
$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT catatan FROM log_aktivitas l WHERE l.pengajuan_id = p.id AND l.aksi = 'pengajuan_baru' LIMIT 1) as log_catatan
    FROM pengajuan p
    WHERE p.user_id = ? AND p.jenis_pengajuan = 'cuti' AND p.status = 'disetujui' AND YEAR(p.tanggal_mulai) = ?
    ORDER BY p.tanggal_mulai ASC
");
$stmt->execute([$id, $tahun]);
$riwayat = $stmt->fetchAll();

// Header Excel
$filename = "Kontrol_Cuti_" . str_replace(' ', '_', $user['nama']) . "_{$tahun}.xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$nipLabel = ($user['status_pegawai'] === 'PPPK') ? 'NIPPPK' : 'NIP';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }
        th, td { border: 1px solid black; padding: 5px; text-align: center; vertical-align: middle; }
        .no-border { border: none !important; text-align: left; }
        .text-left { text-align: left; }
        .header-title { font-size: 16px; font-weight: bold; text-align: center; text-decoration: underline; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header-title">KONTROL CUTI</div>
    
    <table>
        <tr>
            <th colspan="2" class="text-left" style="background-color: #e2efda;">Periode <?= $tahun ?></th>
            <td colspan="12" class="no-border"></td>
        </tr>
        <tr><td colspan="14" class="no-border"></td></tr>
        <tr>
            <td colspan="2" class="text-left">NAMA</td>
            <td colspan="5" class="text-left"><?= htmlspecialchars($user['nama']) ?></td>
            <td colspan="7" class="no-border"></td>
        </tr>
        <tr>
            <td colspan="2" class="text-left"><?= $nipLabel ?></td>
            <td colspan="5" class="text-left"><?= htmlspecialchars($user['nip']) ?></td>
            <td colspan="7" class="no-border"></td>
        </tr>
        <tr>
            <td colspan="2" class="text-left">JABATAN</td>
            <td colspan="5" class="text-left"><?= htmlspecialchars($user['jabatan']) ?></td>
            <td colspan="7" class="no-border"></td>
        </tr>
        <tr><td colspan="14" class="no-border"></td></tr>
        
        <tr style="background-color: #f2f2f2;">
            <th rowspan="2">NO</th>
            <th colspan="3">LAMANYA CUTI</th>
            <th colspan="8">JENIS CUTI</th>
            <th rowspan="2">ALAMAT CUTI</th>
            <th colspan="2">SK CUTI</th>
        </tr>
        <tr style="background-color: #f2f2f2;">
            <th>DARI</th>
            <th>SAMPAI</th>
            <th>JUMLAH (hari)</th>
            <th>CUTI TAHUNAN</th>
            <th>JATAH CUTI TAHUN LALU</th>
            <th>CUTI BESAR</th>
            <th>CUTI SAKIT</th>
            <th>CUTI MELAHIRKAN</th>
            <th>CUTI ALASAN PENTING</th>
            <th>CLTN</th>
            <th>CUTI TUGAS</th>
            <th>NOMOR</th>
            <th>TANGGAL</th>
        </tr>
        
        <?php 
        $no = 1;
        $tot_hari = 0;
        $tot_tahunan = 0;
        $tot_lalu = 0;
        $tot_besar = 0;
        $tot_sakit = 0;
        $tot_melahirkan = 0;
        $tot_penting = 0;
        $tot_cltn = 0;
        $tot_tugas = 0;
        
        foreach ($riwayat as $r): 
            $hari = (int) $r['jumlah_hari'];
            $tot_hari += $hari;
            
            $is_lalu = ($r['tipe_cuti'] === 'tahunan' && strpos($r['log_catatan'], 'tahunan_lalu') !== false);
            
            $v_tahunan = ($r['tipe_cuti'] === 'tahunan' && !$is_lalu) ? $hari : '';
            $v_lalu = $is_lalu ? $hari : '';
            $v_besar = '';
            $v_sakit = ($r['tipe_cuti'] === 'sakit') ? $hari : '';
            $v_melahirkan = ($r['tipe_cuti'] === 'melahirkan') ? $hari : '';
            $v_penting = ($r['tipe_cuti'] === 'alasan_penting') ? $hari : '';
            $v_cltn = '';
            $v_tugas = '';
            
            if ($v_tahunan) $tot_tahunan += $hari;
            if ($v_lalu) $tot_lalu += $hari;
            if ($v_sakit) $tot_sakit += $hari;
            if ($v_melahirkan) $tot_melahirkan += $hari;
            if ($v_penting) $tot_penting += $hari;
        ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= date('d/m/Y', strtotime($r['tanggal_mulai'])) ?></td>
            <td><?= date('d/m/Y', strtotime($r['tanggal_selesai'])) ?></td>
            <td><?= $hari ?></td>
            <td><?= $v_tahunan ?></td>
            <td><?= $v_lalu ?></td>
            <td><?= $v_besar ?></td>
            <td><?= $v_sakit ?></td>
            <td><?= $v_melahirkan ?></td>
            <td><?= $v_penting ?></td>
            <td><?= $v_cltn ?></td>
            <td><?= $v_tugas ?></td>
            <td class="text-left"><?= htmlspecialchars(explode('Alamat Cuti:', $r['alasan'])[1] ?? explode("\n", $r['alasan'])[0] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['nomor_sk'] ?? '-') ?></td>
            <td><?= $r['tanggal_approval'] ? date('d/m/Y', strtotime($r['tanggal_approval'])) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        
        <!-- Baris kosong agar tabel ada space jika riwayat kosong -->
        <?php for($i=$no; $i<=max(10, $no); $i++): ?>
        <tr>
            <td><?= $i ?></td>
            <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        </tr>
        <?php endfor; ?>
        
        <tr style="font-weight: bold; background-color: #f2f2f2;">
            <td colspan="3">JUMLAH</td>
            <td><?= $tot_hari ?: '' ?></td>
            <td><?= $tot_tahunan ?: '' ?></td>
            <td><?= $tot_lalu ?: '' ?></td>
            <td><?= $tot_besar ?: '0' ?></td>
            <td><?= $tot_sakit ?: '' ?></td>
            <td><?= $tot_melahirkan ?: '' ?></td>
            <td><?= $tot_penting ?: '' ?></td>
            <td><?= $tot_cltn ?: '0' ?></td>
            <td><?= $tot_tugas ?: '0' ?></td>
            <td colspan="3"></td>
        </tr>
    </table>
    
    <br><br>
    
    <table style="width: 30%;">
        <tr style="background-color: #b4c6e7;">
            <th>JENIS CUTI</th>
            <th>JATAH</th>
            <th>SISA</th>
        </tr>
        <?php if ($user['status_pegawai'] !== 'PPPK'): ?>
        <tr>
            <td class="text-left">Jatah Cuti Tahun Lalu</td>
            <td><?= $saldo['jatah_tahunan_lalu'] ?></td>
            <td><?= $saldo['jatah_tahunan_lalu'] - $saldo['terpakai_tahunan_lalu'] ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td class="text-left">Cuti Tahunan</td>
            <td><?= $saldo['jatah_tahunan'] ?></td>
            <td><?= $saldo['jatah_tahunan'] - $saldo['terpakai_tahunan'] ?></td>
        </tr>
        <tr>
            <td class="text-left">Cuti Sakit</td>
            <td><?= $saldo['jatah_sakit'] ?></td>
            <td><?= $saldo['jatah_sakit'] - $saldo['terpakai_sakit'] ?></td>
        </tr>
        <?php if ($user['jenis_kelamin'] === 'P'): ?>
        <tr>
            <td class="text-left">Cuti Melahirkan</td>
            <td><?= $saldo['jatah_melahirkan'] ?: '-' ?></td>
            <td><?= $saldo['jatah_melahirkan'] ? ($saldo['jatah_melahirkan'] - $saldo['terpakai_melahirkan']) : '' ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($user['status_pegawai'] !== 'PPPK'): ?>
        <tr>
            <td class="text-left">Cuti Alasan Penting</td>
            <td><?= $saldo['jatah_alasan_penting'] ?></td>
            <td><?= $saldo['jatah_alasan_penting'] - $saldo['terpakai_alasan_penting'] ?></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <br><br>
    <table style="border: none; width: 100%;">
        <tr>
            <td class="no-border" style="width: 70%;">
                Mengetahui Atasan Langsung,<br><br><br><br><br>
                <b>______________________</b><br>
                NIP. 
            </td>
            <td class="no-border" style="width: 30%;">
                Samarinda, <?= date('d F Y') ?><br>
                Yang Membuat,<br><br><br><br><br>
                <b>______________________</b><br>
                NIP.
            </td>
        </tr>
    </table>

</body>
</html>

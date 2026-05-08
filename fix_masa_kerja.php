<?php
/**
 * One-time fix: Update tgl_mulai_kerja dari NIP untuk PNS & Hakim
 * Jalankan sekali, lalu hapus file ini.
 *
 * Format NIP PNS/Hakim (18 digit):
 *   Digit 1-8  : tgl lahir (YYYYMMDD)
 *   Digit 9-14 : awal kerja (YYYYMM)  ← yang dipakai
 *   Digit 15-18: kode lainnya
 */

session_start();
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// Keamanan: hanya admin yang bisa jalankan
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('<h2 style="color:red">⛔ Akses ditolak. Login sebagai admin terlebih dahulu.</h2>');
}

// Konfirmasi sebelum jalan
$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === '1';

// Ambil semua PNS & Hakim
$stmt = $pdo->query("SELECT id, nama, nip, status_pegawai, tgl_mulai_kerja FROM users WHERE status_pegawai IN ('PNS', 'Hakim') ORDER BY nama ASC");
$users = $stmt->fetchAll();

$preview = [];
foreach ($users as $u) {
    $nip   = preg_replace('/\D/', '', $u['nip']);
    $result = ['id' => $u['id'], 'nama' => $u['nama'], 'nip' => $u['nip'],
               'status' => $u['status_pegawai'], 'tgl_lama' => $u['tgl_mulai_kerja'],
               'tgl_baru' => null, 'info' => ''];

    if (strlen($nip) < 14) {
        $result['info'] = '⚠️ NIP terlalu pendek (<14 digit), skip';
        $preview[] = $result;
        continue;
    }

    $year  = substr($nip, 8, 4);
    $month = substr($nip, 12, 2);
    $y = (int)$year;
    $m = (int)$month;

    if ($y < 1950 || $y > 2100 || $m < 1 || $m > 12) {
        $result['info'] = "⚠️ Tahun/bulan tidak valid (Y={$y}, M={$m}), skip";
        $preview[] = $result;
        continue;
    }

    $tglBaru = sprintf('%04d-%02d-01', $y, $m);
    $result['tgl_baru'] = $tglBaru;

    if ($tglBaru === $u['tgl_mulai_kerja']) {
        $result['info'] = '✅ Sudah benar, tidak perlu update';
    } else {
        $result['info'] = '🔄 Akan diupdate';
        if ($confirmed) {
            $upd = $pdo->prepare("UPDATE users SET tgl_mulai_kerja = ? WHERE id = ?");
            $upd->execute([$tglBaru, $u['id']]);
            $result['info'] = '✅ Berhasil diupdate';
        }
    }

    $preview[] = $result;
}

$totalAkanUpdate = count(array_filter($preview, fn($r) => str_contains($r['info'] ?? '', 'Akan diupdate')));
$totalUpdated    = count(array_filter($preview, fn($r) => str_contains($r['info'] ?? '', 'Berhasil diupdate')));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Fix Masa Kerja dari NIP</title>
<style>
    body { font-family: Arial, sans-serif; max-width: 1000px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
    h2 { color: #333; }
    table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.1); border-radius: 8px; overflow: hidden; }
    th { background: #1a1a2e; color: #fff; padding: 10px 12px; text-align: left; font-size: 13px; }
    td { padding: 9px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
    tr:last-child td { border-bottom: none; }
    .info-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
    .btn { display: inline-block; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; }
    .btn-danger { background: #dc3545; color: #fff; }
    .btn-secondary { background: #6c757d; color: #fff; }
    .badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .badge-success { background: #d4edda; color: #155724; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-danger  { background: #f8d7da; color: #721c24; }
    .badge-update  { background: #cce5ff; color: #004085; }
</style>
</head>
<body>
<h2>🔧 Fix Tgl Mulai Kerja dari NIP (PNS & Hakim)</h2>

<?php if ($confirmed): ?>
    <div class="info-box" style="background:#d4edda; border-color:#28a745;">
        ✅ <strong><?= $totalUpdated ?> data berhasil diupdate!</strong> Halaman ini bisa dihapus sekarang.
    </div>
<?php else: ?>
    <div class="info-box">
        ℹ️ Berikut preview perubahan. <strong><?= $totalAkanUpdate ?> data akan diupdate</strong>.
        Klik tombol <em>Jalankan Update</em> untuk melanjutkan.
    </div>
    <?php if ($totalAkanUpdate > 0): ?>
    <form method="POST" onsubmit="return confirm('Yakin update <?= $totalAkanUpdate ?> data?')">
        <input type="hidden" name="confirm" value="1">
        <button type="submit" class="btn btn-danger">⚡ Jalankan Update (<?= $totalAkanUpdate ?> data)</button>
        &nbsp;&nbsp;
        <a href="<?= BASE_URL ?>/pages/kelola_user.php" class="btn btn-secondary">Batal</a>
    </form><br>
    <?php endif; ?>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Nama</th>
            <th>NIP</th>
            <th>Status</th>
            <th>Tgl Lama</th>
            <th>Tgl Baru</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($preview as $i => $r): ?>
    <tr>
        <td><?= $i + 1 ?></td>
        <td><?= htmlspecialchars($r['nama']) ?></td>
        <td style="font-family:monospace; font-size:12px"><?= htmlspecialchars($r['nip']) ?></td>
        <td><?= $r['status'] ?></td>
        <td><?= $r['tgl_lama'] ?></td>
        <td><?= $r['tgl_baru'] ?? '<span style="color:#999">-</span>' ?></td>
        <td>
            <?php
            $info = $r['info'];
            $cls = str_contains($info, '✅') ? 'badge-success'
                 : (str_contains($info, '⚠️') ? 'badge-danger'
                 : 'badge-update');
            echo "<span class='badge $cls'>$info</span>";
            ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($confirmed && $totalUpdated > 0): ?>
<br>
<a href="<?= BASE_URL ?>/pages/kelola_user.php" class="btn btn-secondary">← Kembali ke Kelola Pegawai</a>
<?php endif; ?>

</body>
</html>

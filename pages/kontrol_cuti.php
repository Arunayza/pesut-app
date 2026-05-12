<?php
/**
 * Kontrol Cuti
 * Hanya untuk Kasubag / Staf KPOT (role: kepegawaian)
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/role_helper.php';

requireLogin();

if (!in_array($_SESSION['role'], ['kepegawaian', 'admin', 'staf_kpot'])) {
    setFlash('error', 'Anda tidak memiliki akses ke halaman Kontrol Cuti!');
    redirect(BASE_URL . '/dashboard_v2.php');
}

$pageTitle = 'Kontrol Cuti';
$flash = getFlash();

$search = trim($_GET['search'] ?? '');
$filterTahun = $_GET['tahun'] ?? TAHUN_AKTIF;
$filterStatus = $_GET['status_pegawai'] ?? '';

$sql = "SELECT u.id, u.nip, u.nama, u.status_pegawai, u.jabatan, u.pangkat, u.jenis_kelamin,
        s.jatah_tahunan_lalu, s.terpakai_tahunan_lalu, 
        s.jatah_tahunan, s.terpakai_tahunan,
        s.jatah_sakit, s.terpakai_sakit,
        s.jatah_melahirkan, s.terpakai_melahirkan,
        s.jatah_alasan_penting, s.terpakai_alasan_penting
        FROM users u 
        LEFT JOIN saldo_cuti s ON u.id = s.user_id AND s.tahun = ?
        WHERE u.aktif = 1 AND u.role != 'admin'";

$params = [$filterTahun];

if (!empty($search)) {
    $sql .= " AND (u.nama LIKE ? OR u.nip LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filterStatus)) {
    $sql .= " AND u.status_pegawai = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY u.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<a href="<?= BASE_URL ?>/dashboard_v2.php" class="btn-back-dashboard" style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); text-decoration: none; font-size: 13px; font-weight: 600; margin-bottom: 16px; padding: 6px 14px; border-radius: 8px; transition: all 0.2s; border: 1px solid transparent;">
    ← Kembali ke Dashboard
</a>
<style>
    .btn-back-dashboard:hover {
        color: var(--text-primary);
        background: rgba(255,255,255,0.06);
        border-color: rgba(255,255,255,0.1);
    }
    [data-theme="light"] .btn-back-dashboard:hover {
        background: rgba(0,0,0,0.04);
        border-color: rgba(0,0,0,0.08);
    }
</style>

<div class="card">
    <div class="card-header">
        <h3>Kontrol Cuti Pegawai</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="" style="display: flex; gap: 12px; margin-bottom: 20px; align-items: center; flex-wrap: wrap;">
            <input type="text" name="search" class="form-control" placeholder="Cari nama atau NIP..." value="<?= htmlspecialchars($search) ?>" style="max-width: 300px; margin-bottom: 0;">
            <select name="status_pegawai" class="form-control" style="max-width: 200px; margin-bottom: 0;">
                <option value="">Semua Kategori</option>
                <option value="Hakim" <?= $filterStatus === 'Hakim' ? 'selected' : '' ?>>Hakim</option>
                <option value="PNS" <?= $filterStatus === 'PNS' ? 'selected' : '' ?>>PNS</option>
                <option value="PPPK" <?= $filterStatus === 'PPPK' ? 'selected' : '' ?>>PPPK</option>
            </select>
            <input type="number" name="tahun" class="form-control" placeholder="Tahun" value="<?= htmlspecialchars($filterTahun) ?>" style="max-width: 100px; margin-bottom: 0;">
            <button type="submit" class="btn btn-primary">Cari</button>
            <?php if (!empty($search) || !empty($filterStatus) || $filterTahun != TAHUN_AKTIF): ?>
                <a href="<?= BASE_URL ?>/pages/kontrol_cuti.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIP / Nama</th>
                        <th>Jabatan</th>
                        <th style="text-align: center;">Thn Lalu</th>
                        <th style="text-align: center;">Tahunan</th>
                        <th style="text-align: center;">Sakit</th>
                        <th style="text-align: center;">Melahirkan</th>
                        <th style="text-align: center;">Penting</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted);">Belum ada data.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($users as $u): 
                            $sisaTahunLalu = ($u['jatah_tahunan_lalu'] ?? 0) - ($u['terpakai_tahunan_lalu'] ?? 0);
                            $sisaTahunan = ($u['jatah_tahunan'] ?? 0) - ($u['terpakai_tahunan'] ?? 0);
                            $sisaSakit = ($u['jatah_sakit'] ?? 0) - ($u['terpakai_sakit'] ?? 0);
                            $sisaMelahirkan = ($u['jatah_melahirkan'] ?? 0) - ($u['terpakai_melahirkan'] ?? 0);
                            $sisaPenting = ($u['jatah_alasan_penting'] ?? 0) - ($u['terpakai_alasan_penting'] ?? 0);
                        ?>
                        <tr>
                            <td data-label="No"><?= $no++ ?></td>
                            <td data-label="NIP / Nama">
                                <strong><?= htmlspecialchars($u['nama']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($u['nip']) ?> - <?= htmlspecialchars($u['status_pegawai']) ?></small>
                            </td>
                            <td data-label="Jabatan">
                                <?= htmlspecialchars($u['jabatan']) ?>
                            </td>
                            <td data-label="Thn Lalu" style="text-align: center;">
                                <?php if ($u['status_pegawai'] === 'PPPK'): ?>
                                    <span class="badge badge-secondary">-</span>
                                <?php else: ?>
                                    <span class="badge <?= $sisaTahunLalu > 0 ? 'badge-success' : 'badge-secondary' ?>"><?= $sisaTahunLalu ?> hr</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Tahunan" style="text-align: center;">
                                <span class="badge <?= $sisaTahunan > 0 ? 'badge-info' : 'badge-secondary' ?>"><?= $sisaTahunan ?> hr</span>
                            </td>
                            <td data-label="Sakit" style="text-align: center;">
                                <span class="badge <?= $sisaSakit > 0 ? 'badge-warning' : 'badge-secondary' ?>"><?= $sisaSakit ?> hr</span>
                            </td>
                            <td data-label="Melahirkan" style="text-align: center;">
                                <?php if ($u['jenis_kelamin'] !== 'P'): ?>
                                    <span class="badge badge-secondary">-</span>
                                <?php else: ?>
                                    <span class="badge <?= $sisaMelahirkan > 0 ? 'badge-danger' : 'badge-secondary' ?>"><?= $sisaMelahirkan ?> hr</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Penting" style="text-align: center;">
                                <?php if ($u['status_pegawai'] === 'PPPK'): ?>
                                    <span class="badge badge-secondary">-</span>
                                <?php else: ?>
                                    <span class="badge" style="<?= $sisaPenting > 0 ? 'background: rgba(168, 85, 247, 0.15); color: #c084fc;' : 'background: rgba(100, 116, 139, 0.15); color: #94a3b8;' ?>"><?= $sisaPenting ?> hr</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Aksi" style="white-space: nowrap;">
                                <a href="<?= BASE_URL ?>/pages/edit_saldo_cuti.php?id=<?= $u['id'] ?>&tahun=<?= htmlspecialchars($filterTahun) ?>" class="btn btn-secondary btn-sm" title="Edit Saldo">📝 Edit</a>
                                <a href="<?= BASE_URL ?>/pages/export_kontrol_cuti.php?id=<?= $u['id'] ?>&tahun=<?= htmlspecialchars($filterTahun) ?>" class="btn btn-success btn-sm" title="Export Excel" target="_blank">📊 Excel</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Kelola Pegawai (Admin)
 * Menampilkan daftar pegawai dan tombol tambah pegawai baru.
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/role_helper.php';

requireLogin();

if ($_SESSION['role'] !== 'admin') {
    setFlash('error', 'Anda tidak memiliki akses!');
    redirect(BASE_URL . '/index.php');
}

$pageTitle = 'Kelola Pegawai';
$flash = getFlash();

// Tangkap parameter pencarian
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status_pegawai'] ?? '';

$sql = "SELECT id, nip, nama, status_pegawai, jabatan, pangkat, unit_kerja, role, aktif FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (nama LIKE ? OR nip LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filterStatus)) {
    $sql .= " AND status_pegawai = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>Daftar Pegawai / User</h3>
        <a href="<?= BASE_URL ?>/pages/tambah_user.php" class="btn btn-primary btn-sm">+ Tambah Pegawai</a>
    </div>
    <div class="card-body">
        <!-- Form Filter & Pencarian -->
        <form method="GET" action="" style="display: flex; gap: 12px; margin-bottom: 20px; align-items: center; flex-wrap: wrap;">
            <input type="text" name="search" class="form-control" placeholder="Cari nama atau NIP..." value="<?= htmlspecialchars($search) ?>" style="max-width: 300px; margin-bottom: 0;">
            <select name="status_pegawai" class="form-control" style="max-width: 200px; margin-bottom: 0;">
                <option value="">Semua Status</option>
                <option value="Hakim" <?= $filterStatus === 'Hakim' ? 'selected' : '' ?>>Hakim</option>
                <option value="PNS" <?= $filterStatus === 'PNS' ? 'selected' : '' ?>>PNS</option>
                <option value="PPPK" <?= $filterStatus === 'PPPK' ? 'selected' : '' ?>>PPPK</option>
            </select>
            <button type="submit" class="btn btn-primary">Cari</button>
            <?php if (!empty($search) || !empty($filterStatus)): ?>
                <a href="<?= BASE_URL ?>/pages/kelola_user.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIP</th>
                        <th>Nama</th>
                        <th>Jabatan / Pangkat</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted);">Belum ada data pegawai.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($users as $u): ?>
                        <tr>
                            <td data-label="No"><?= $no++ ?></td>
                            <td data-label="NIP"><?= htmlspecialchars($u['nip']) ?></td>
                            <td data-label="Nama">
                                <strong><?= htmlspecialchars($u['nama']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($u['status_pegawai'] ?? '-') ?></small>
                            </td>
                            <td data-label="Jabatan / Pangkat">
                                <?= htmlspecialchars($u['jabatan']) ?><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($u['pangkat'] ?? '-') ?></small>
                            </td>
                            <td data-label="Role">
                                <?= roleBadge($u['role']) ?>
                            </td>
                            <td data-label="Status">
                                <?php if ($u['aktif']): ?>
                                    <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Non-Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Aksi">
                                <a href="<?= BASE_URL ?>/pages/edit_user.php?id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" title="Edit Pegawai">✏️ Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards (tampil hanya di HP) -->
        <div class="mobile-cards">
            <?php if (empty($users)): ?>
                <div style="text-align:center; padding: 32px; color: var(--text-muted);">Belum ada data pegawai.</div>
            <?php else: ?>
                <?php $no = 1; foreach ($users as $u): ?>
                <div class="mobile-card">
                    <div class="mobile-card-left">
                        <div class="mobile-card-no">#<?= $no++ ?></div>
                        <div class="mobile-card-nama"><?= htmlspecialchars($u['nama']) ?></div>
                        <div class="mobile-card-nip"><?= htmlspecialchars($u['nip']) ?></div>
                        <div class="mobile-card-jabatan">
                            <?= htmlspecialchars($u['jabatan']) ?>
                            <small><?= htmlspecialchars($u['pangkat'] ?? '-') ?></small>
                        </div>
                        <div class="mobile-card-chips">
                            <?= roleBadge($u['role']) ?>
                            <span class="badge <?= $u['aktif'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $u['aktif'] ? 'Aktif' : 'Non-Aktif' ?>
                            </span>
                            <span class="badge badge-info" style="font-size:10px;"><?= htmlspecialchars($u['status_pegawai'] ?? '-') ?></span>
                        </div>
                    </div>
                    <div class="mobile-card-right">
                        <a href="<?= BASE_URL ?>/pages/edit_user.php?id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

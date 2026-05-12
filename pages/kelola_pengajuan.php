<?php
/**
 * Kelola Pengajuan - Admin Panel
 * Daftar semua pengajuan yang membutuhkan review/TTD
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';
require_once __DIR__ . '/../helpers/role_helper.php';

requireLogin();

// Cek akses
if (!canAccessKelolaPengajuan($_SESSION['role'], $pdo, $_SESSION['user_id'])) {
    setFlash('error', 'Anda tidak memiliki akses ke halaman ini!');
    redirect(BASE_URL . '/index.php');
}

$userId = $_SESSION['user_id'];

// Filter
$filterStatus = $_GET['status'] ?? 'pending';

// Query pengajuan dengan info user dan hitung TTD
$sql = "
    SELECT p.*, u.nama, u.nip, u.jabatan, u.pangkat, u.atasan_id,
           (SELECT COUNT(*) FROM ttd_pengajuan t WHERE t.pengajuan_id = p.id) as jumlah_ttd,
           (SELECT COUNT(*) FROM ttd_pengajuan t WHERE t.pengajuan_id = p.id AND t.user_id = ?) as sudah_ttd_saya,
           (SELECT COUNT(*) FROM ttd_pengajuan t WHERE t.pengajuan_id = p.id AND t.user_id = ? AND t.urutan_ttd = 2) as sudah_ttd_kedua
    FROM pengajuan p
    JOIN users u ON p.user_id = u.id
    WHERE 1=1
";

$params = [$userId, $userId];

// Admin, Staf KPOT, Kasubbag KPOT bisa lihat semua pengajuan
$canSeeAll = in_array($_SESSION['role'], ['admin', 'staf_kpot', 'kasubbag_kpot']);

if (!$canSeeAll) {
    // Selain itu, HANYA bisa melihat pengajuan jika:
    // 1. Dia adalah atasan pemohon
    // 2. Dia sudah menandatangani pengajuan tersebut
    // 3. Dia adalah Pejabat Berwenang untuk cuti pemohon tersebut
    $sql .= " AND (
        u.atasan_id = ? 
        OR EXISTS(SELECT 1 FROM ttd_pengajuan tp WHERE tp.pengajuan_id = p.id AND tp.user_id = ?)
        OR (
            p.jenis_pengajuan = 'cuti' AND (
                (? IN ('ketua', 'wakil_ketua') AND u.status_pegawai IN ('Hakim', 'PNS')) OR
                (? IN ('sekretaris', 'panitera') AND u.status_pegawai = 'PPPK')
            )
        )
    )";
    $params[] = $userId;
    $params[] = $userId;
    $params[] = $_SESSION['role'];
    $params[] = $_SESSION['role'];
}

if ($filterStatus === 'pending') {
    $sql .= " AND p.status = 'pending'";
} elseif ($filterStatus === 'disetujui') {
    $sql .= " AND p.status = 'disetujui'";
} elseif ($filterStatus === 'ditolak') {
    $sql .= " AND p.status = 'ditolak'";
} elseif ($filterStatus === 'dibatalkan') {
    $sql .= " AND p.status = 'dibatalkan'";
} else {
    // semua
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pengajuan = $stmt->fetchAll();

$pageTitle = 'Kelola Pengajuan';
$flash = getFlash();
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
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
        <h3>📌 Kelola Pengajuan</h3>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="?status=pending" class="btn btn-sm <?= $filterStatus === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">
                ⏳ Menunggu TTD
            </a>
            <a href="?status=disetujui" class="btn btn-sm <?= $filterStatus === 'disetujui' ? 'btn-primary' : 'btn-secondary' ?>">
                ✅ Disetujui
            </a>
            <a href="?status=ditolak" class="btn btn-sm <?= $filterStatus === 'ditolak' ? 'btn-primary' : 'btn-secondary' ?>">
                ❌ Ditolak
            </a>
            <a href="?status=dibatalkan" class="btn btn-sm <?= $filterStatus === 'dibatalkan' ? 'btn-primary' : 'btn-secondary' ?>">
                🚫 Dibatalkan
            </a>
            <a href="?status=semua" class="btn btn-sm <?= $filterStatus === 'semua' ? 'btn-primary' : 'btn-secondary' ?>">
                📋 Semua
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($pengajuan)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h4>Tidak ada pengajuan</h4>
                <p>Belum ada pengajuan yang sesuai dengan filter.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Pegawai</th>
                            <th>Jenis</th>
                            <th>Detail</th>
                            <th>Alasan</th>
                            <th>TTD</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pengajuan as $p): ?>
                        <tr>
                            <td data-label="Pegawai">
                                <strong><?= htmlspecialchars($p['nama']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($p['jabatan']) ?></small><br>
                                <small style="color: var(--text-muted);">NIP: <?= htmlspecialchars($p['nip']) ?></small>
                            </td>
                            <td data-label="Jenis"><?= jenisBadge($p['jenis_pengajuan'], $p['tipe_izin_waktu'] ?? null) ?></td>
                            <td data-label="Detail">
                                <?php if ($p['jenis_pengajuan'] === 'cuti'): ?>
                                    <?= formatTanggal($p['tanggal_mulai']) ?> — <?= formatTanggal($p['tanggal_selesai']) ?>
                                    <br><small style="color: var(--text-muted);"><?= $p['jumlah_hari'] ?> hari kerja (<?= ucwords(str_replace('_', ' ', $p['tipe_cuti'] ?? 'tahunan')) ?>)</small>
                                <?php elseif ($p['jenis_pengajuan'] === 'izin'): ?>
                                    <?= formatTanggal($p['tanggal_mulai']) ?>
                                    <br><small style="color: var(--text-muted);">Jam: <?= substr($p['jam_mulai'] ?? '00:00', 0, 5) ?> - <?= substr($p['jam_selesai'] ?? '00:00', 0, 5) ?></small>
                                <?php else: ?>
                                    <?= formatTanggal($p['tanggal_pulang']) ?>
                                    <br><small style="color: var(--text-muted);">
                                        <?php if (($p['tipe_izin_waktu'] ?? '') === 'datang_terlambat'): ?>
                                            Datang: <?= substr($p['jam_pulang_diajukan'] ?? '', 0, 5) ?>
                                            (Resmi: <?= substr($p['jam_pulang_resmi'] ?? '', 0, 5) ?>)
                                        <?php else: ?>
                                            Pulang: <?= substr($p['jam_pulang_diajukan'] ?? '', 0, 5) ?>
                                            (Resmi: <?= substr($p['jam_pulang_resmi'] ?? '', 0, 5) ?>)
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Alasan" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($p['alasan']) ?>
                                <?php if (!empty($p['lampiran'])): ?>
                                    <br><a href="<?= BASE_URL ?>/<?= $p['lampiran'] ?>" target="_blank" class="badge badge-purple" style="text-decoration:none; margin-top:4px; display:inline-block;">📎 Surat Dokter</a>
                                <?php endif; ?>
                            </td>
                            <td data-label="TTD">
                                <?php
                                $ttdCount = (int) $p['jumlah_ttd'];
                                $butuhTtd = in_array($p['jenis_pengajuan'], ['izin', 'pulang_cepat']) ? 1 : 2;
                                if ($ttdCount >= $butuhTtd):
                                ?>
                                    <span class="badge badge-success">✅ <?= $ttdCount ?>/<?= $butuhTtd ?></span>
                                <?php elseif ($ttdCount > 0): ?>
                                    <span class="badge badge-warning">⏳ <?= $ttdCount ?>/<?= $butuhTtd ?></span>
                                <?php else: ?>
                                    <span class="badge badge-danger">⏳ 0/<?= $butuhTtd ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status"><?= statusBadge($p['status']) ?></td>
                            <td data-label="Aksi" style="white-space: nowrap;">
                                <?php if ($p['status'] === 'pending'): ?>
                                    <?php
                                    // Cek apakah user ini bisa TTD lagi (double-TTD untuk Ketua)
                                    $bisakTtdLagi = false;
                                    if ($p['sudah_ttd_saya'] && $p['jenis_pengajuan'] === 'cuti') {
                                        // Ketua yang juga atasan langsung bisa TTD ke-2
                                        // jika: belum punya ttd urutan 2, ttd baru 1, dia adalah atasan pemohon, dan dia pejabat berwenang
                                        $stmtStatusP = $pdo->prepare("SELECT status_pegawai FROM users WHERE id = ?");
                                        $stmtStatusP->execute([$p['user_id']]);
                                        $spP = $stmtStatusP->fetchColumn() ?: 'PNS';
                                        if (!$p['sudah_ttd_kedua'] &&
                                            (int)$p['jumlah_ttd'] === 1 &&
                                            $userId == $p['atasan_id'] &&
                                            isPejabatBerwenang($_SESSION['role'], $spP)) {
                                            $bisakTtdLagi = true;
                                        }
                                    }
                                    ?>
                                    <?php if (!$p['sudah_ttd_saya'] || $bisakTtdLagi): ?>
                                        <a href="<?= BASE_URL ?>/pages/review_pengajuan.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">
                                            <?= $bisakTtdLagi ? '✍️ TTD Pejabat' : '✍️ Review & TTD' ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-success">✅ Sudah TTD</span>
                                    <?php endif; ?>
                                    <button class="btn btn-danger btn-sm" onclick="openTolakModal(<?= $p['id'] ?>)" style="margin-left: 4px;">
                                        ✗ Tolak
                                    </button>
                                <?php elseif ($p['status'] === 'disetujui'): ?>
                                    <a href="<?= BASE_URL ?>/proses/download_surat.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" target="_blank">
                                        📥 Download PDF
                                    </a>
                                <?php else: ?>
                                    <small style="color: var(--text-muted);">—</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tolak -->
<div class="modal-overlay" id="tolak-modal">
    <div class="modal-content">
        <h3>❌ Tolak Pengajuan</h3>
        <form action="<?= BASE_URL ?>/proses/approval.php" method="POST">
            <input type="hidden" name="pengajuan_id" id="tolak-pengajuan-id">
            <input type="hidden" name="aksi" value="tolak">
            <div class="form-group">
                <label for="catatan_tolak">Alasan Penolakan</label>
                <textarea name="catatan_approval" id="catatan_tolak" class="form-control" placeholder="Jelaskan alasan penolakan..." required></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeTolakModal()">Batal</button>
                <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
            </div>
        </form>
    </div>
</div>


<script>
function openTolakModal(id) {
    document.getElementById('tolak-pengajuan-id').value = id;
    document.getElementById('tolak-modal').classList.add('show');
}
function closeTolakModal() {
    document.getElementById('tolak-modal').classList.remove('show');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

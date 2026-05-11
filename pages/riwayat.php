<?php
/**
 * Riwayat Pengajuan
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireLogin();

$userId = $_SESSION['user_id'];

// Filter
$filterJenis  = $_GET['jenis'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build query
$sql    = "SELECT * FROM pengajuan WHERE user_id = ?";
$params = [$userId];

if ($filterJenis && in_array($filterJenis, ['cuti', 'izin', 'pulang_cepat'])) {
    $sql .= " AND jenis_pengajuan = ?";
    $params[] = $filterJenis;
}

if ($filterStatus && in_array($filterStatus, ['pending', 'disetujui', 'ditolak', 'dibatalkan'])) {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pengajuan = $stmt->fetchAll();

$pageTitle = 'Riwayat Pengajuan';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
/* Cancel button styling */
.btn-cancel-pengajuan {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    color: #ef4444;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-cancel-pengajuan:hover {
    background: rgba(239,68,68,0.2);
    border-color: #ef4444;
    transform: translateY(-1px);
}

/* Cancel confirmation modal */
.cancel-modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
    display: none; align-items: center; justify-content: center;
    z-index: 10000; padding: 20px;
}
.cancel-modal-overlay.show { display: flex; }
.cancel-modal-box {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 28px;
    max-width: 420px; width: 100%;
    backdrop-filter: blur(20px);
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.25s ease;
}
@keyframes modalSlideIn {
    from { transform: translateY(20px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}
.cancel-modal-icon {
    width: 56px; height: 56px;
    border-radius: 50%;
    background: rgba(239,68,68,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; margin: 0 auto 16px;
}
.cancel-modal-box h3 {
    text-align: center; font-size: 18px; font-weight: 700;
    color: var(--text-primary); margin-bottom: 8px;
}
.cancel-modal-box p {
    text-align: center; font-size: 13px;
    color: var(--text-muted); margin-bottom: 24px; line-height: 1.5;
}
.cancel-modal-actions {
    display: flex; gap: 10px; justify-content: center;
}
</style>

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
        <h3>📄 Riwayat Pengajuan</h3>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="?jenis=&status=<?= $filterStatus ?>" class="btn btn-sm <?= !$filterJenis ? 'btn-primary' : 'btn-secondary' ?>">Semua</a>
            <a href="?jenis=cuti&status=<?= $filterStatus ?>" class="btn btn-sm <?= $filterJenis === 'cuti' ? 'btn-primary' : 'btn-secondary' ?>">Cuti</a>
            <a href="?jenis=izin&status=<?= $filterStatus ?>" class="btn btn-sm <?= $filterJenis === 'izin' ? 'btn-primary' : 'btn-secondary' ?>">Izin</a>
            <a href="?jenis=pulang_cepat&status=<?= $filterStatus ?>" class="btn btn-sm <?= $filterJenis === 'pulang_cepat' ? 'btn-primary' : 'btn-secondary' ?>">Pulang Cepat</a>
            <span style="border-left: 1px solid var(--glass-border); margin: 0 4px;"></span>
            <a href="?jenis=<?= $filterJenis ?>&status=" class="btn btn-sm <?= !$filterStatus ? 'btn-primary' : 'btn-secondary' ?>">All Status</a>
            <a href="?jenis=<?= $filterJenis ?>&status=pending" class="btn btn-sm <?= $filterStatus === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">Pending</a>
            <a href="?jenis=<?= $filterJenis ?>&status=disetujui" class="btn btn-sm <?= $filterStatus === 'disetujui' ? 'btn-primary' : 'btn-secondary' ?>">Disetujui</a>
            <a href="?jenis=<?= $filterJenis ?>&status=ditolak" class="btn btn-sm <?= $filterStatus === 'ditolak' ? 'btn-primary' : 'btn-secondary' ?>">Ditolak</a>
            <a href="?jenis=<?= $filterJenis ?>&status=dibatalkan" class="btn btn-sm <?= $filterStatus === 'dibatalkan' ? 'btn-primary' : 'btn-secondary' ?>">Dibatalkan</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($pengajuan)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h4>Tidak ada data pengajuan</h4>
                <p>Belum ada pengajuan yang sesuai dengan filter.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Jenis</th>
                            <th>Detail</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th>Catatan</th>
                            <th>Diajukan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pengajuan as $i => $p): ?>
                        <tr>
                            <td data-label="No"><?= $i + 1 ?></td>
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
                                            Datang: <?= substr($p['jam_pulang_diajukan'], 0, 5) ?> 
                                            (Resmi: <?= substr($p['jam_pulang_resmi'], 0, 5) ?>)
                                        <?php else: ?>
                                            Pulang: <?= substr($p['jam_pulang_diajukan'], 0, 5) ?> 
                                            (Resmi: <?= substr($p['jam_pulang_resmi'], 0, 5) ?>)
                                        <?php endif; ?>
                                        — Selisih: <?= formatSelisihWaktu($p['selisih_menit']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Alasan" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($p['alasan']) ?>
                                <?php if (!empty($p['lampiran'])): ?>
                                    <br><a href="<?= BASE_URL ?>/<?= $p['lampiran'] ?>" target="_blank" class="badge badge-purple" style="text-decoration:none; margin-top:4px; display:inline-block;">📎 Lihat Surat Dokter</a>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status"><?= statusBadge($p['status']) ?></td>
                            <td data-label="Catatan">
                                <?php if ($p['catatan_approval']): ?>
                                    <small><?= htmlspecialchars($p['catatan_approval']) ?></small>
                                <?php else: ?>
                                    <small style="color: var(--text-muted);">-</small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Diajukan"><?= formatTanggal($p['created_at']) ?></td>
                            <td data-label="Aksi">
                                <?php if ($p['status'] === 'disetujui'): ?>
                                    <div style="display: flex; gap: 4px;">
                                        <a href="<?= BASE_URL ?>/proses/download_surat.php?id=<?= $p['id'] ?>" 
                                           class="btn btn-primary btn-sm" target="_blank" title="Download surat PDF">
                                            📥 PDF
                                        </a>
                                        <?php if (in_array($p['jenis_pengajuan'], ['cuti', 'izin', 'pulang_cepat'])): ?>
                                            <a href="<?= BASE_URL ?>/proses/download_word.php?id=<?= $p['id'] ?>" 
                                               class="btn btn-success btn-sm" target="_blank" title="Download surat Word">
                                                📄 Word
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($p['status'] === 'pending'): ?>
                                    <button type="button" class="btn-cancel-pengajuan" onclick="openCancelModal(<?= $p['id'] ?>)">
                                        ✕ Batalkan
                                    </button>
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

<!-- Cancel Confirmation Modal -->
<div class="cancel-modal-overlay" id="cancel-modal">
    <div class="cancel-modal-box">
        <div class="cancel-modal-icon">⚠️</div>
        <h3>Batalkan Pengajuan?</h3>
        <p>Pengajuan yang sudah dibatalkan tidak dapat dikembalikan. Anda perlu membuat pengajuan baru jika ingin mengajukan kembali.</p>
        <form action="<?= BASE_URL ?>/proses/batalkan_pengajuan.php" method="POST">
            <input type="hidden" name="pengajuan_id" id="cancel-pengajuan-id">
            <div class="cancel-modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeCancelModal()">Kembali</button>
                <button type="submit" class="btn btn-danger">🗑️ Ya, Batalkan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCancelModal(id) {
    document.getElementById('cancel-pengajuan-id').value = id;
    document.getElementById('cancel-modal').classList.add('show');
}
function closeCancelModal() {
    document.getElementById('cancel-modal').classList.remove('show');
}
// Close on backdrop click
document.getElementById('cancel-modal').addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

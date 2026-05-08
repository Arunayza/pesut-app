<?php
/**
 * Dashboard - Halaman Utama
 */
session_start();
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/role_helper.php';

requireLogin();

$userId = $_SESSION['user_id'];

// Ambil saldo cuti
$saldo = cekSisaCuti($userId, (int) TAHUN_AKTIF, $pdo);

// Hitung pengajuan pending
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$pendingCount = $stmt->fetchColumn();

// Hitung total pengajuan bulan ini
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan WHERE user_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
$stmt->execute([$userId]);
$bulanIni = $stmt->fetchColumn();

// Hitung pengajuan disetujui
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan WHERE user_id = ? AND status = 'disetujui' AND YEAR(created_at) = YEAR(NOW())");
$stmt->execute([$userId]);
$disetujui = $stmt->fetchColumn();

// Pengajuan terbaru (5 terakhir)
$stmt = $pdo->prepare("SELECT * FROM pengajuan WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$recentPengajuan = $stmt->fetchAll();

// Jika pejabat/atasan, ambil pengajuan pending yang perlu di-review
$pendingAll = [];
$pendingNeedMyTTD = 0;
$firstPendingId = null;
$showPending = canAccessKelolaPengajuan($_SESSION['role'], $pdo, $userId);
if ($showPending) {
    if (in_array($_SESSION['role'], ROLE_KELOLA) || $_SESSION['role'] === 'admin') {
        // Pejabat struktural lihat semua pending
        $stmt = $pdo->query("
            SELECT p.*, u.nama, u.nip, u.jabatan
            FROM pengajuan p
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'pending'
            ORDER BY p.created_at DESC
            LIMIT 10
        ");
    } else {
        // Atasan non-pejabat hanya lihat bawahan langsung
        $stmt = $pdo->prepare("
            SELECT p.*, u.nama, u.nip, u.jabatan
            FROM pengajuan p
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'pending' AND u.atasan_id = ?
            ORDER BY p.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
    }
    $pendingAll = $stmt->fetchAll();

    // Hitung pending yang BELUM di-TTD oleh user ini (untuk pejabat struktural saja)
    if (isPejabatStruktural($_SESSION['role'])) {
        $stmtNeedTTD = $pdo->prepare("
            SELECT p.id
            FROM pengajuan p
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'pending'
              AND NOT EXISTS (
                  SELECT 1 FROM ttd_pengajuan t 
                  WHERE t.pengajuan_id = p.id AND t.user_id = ?
              )
            ORDER BY p.created_at ASC
        ");
        $stmtNeedTTD->execute([$userId]);
        $needTTDRows = $stmtNeedTTD->fetchAll(PDO::FETCH_COLUMN);
        $pendingNeedMyTTD = count($needTTDRows);
        $firstPendingId = $needTTDRows[0] ?? null;
    } else {
        $pendingNeedMyTTD = count($pendingAll);
        $firstPendingId = $pendingAll[0]['id'] ?? null;
    }
}

// Fetch jenis kelamin user
$stmtUser = $pdo->prepare("SELECT jenis_kelamin FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userJenisKelamin = $stmtUser->fetchColumn();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Greeting -->
<div style="margin-bottom: 28px;">
    <h2 style="font-size: 22px; font-weight: 700;">Selamat datang, <?= htmlspecialchars(explode(',', $_SESSION['nama'])[0]) ?>! 👋</h2>
    <p style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;"><?= htmlspecialchars($_SESSION['jabatan']) ?> — <?= APP_INSTANSI ?></p>
</div>

<?php if ($showPending && $pendingNeedMyTTD > 0 && isPejabatStruktural($_SESSION['role'])): ?>
<!-- =====================================================
     ACTION REQUIRED BANNER — Pejabat Struktural
     ===================================================== -->
<style>
.action-banner {
    background: linear-gradient(135deg, rgba(245,158,11,0.12) 0%, rgba(239,68,68,0.08) 100%);
    border: 1px solid rgba(245,158,11,0.3);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 18px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
}
.action-banner::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: linear-gradient(to bottom, #f59e0b, #ef4444);
    border-radius: 4px 0 0 4px;
}
.action-banner:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(245,158,11,0.2);
    border-color: rgba(245,158,11,0.5);
    color: inherit;
}
.action-banner-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(245,158,11,0.2), rgba(239,68,68,0.15));
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; flex-shrink: 0;
    animation: bannerPulse 2s ease-in-out infinite;
}
@keyframes bannerPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.08); }
}
.action-banner-count {
    font-size: 32px; font-weight: 900;
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; line-height: 1;
}
.action-banner-text h3 { font-size: 15px; font-weight: 700; margin-bottom: 2px; }
.action-banner-text p { font-size: 12px; color: var(--text-muted); }
.action-banner-arrow {
    margin-left: auto; font-size: 20px; color: var(--text-muted);
    transition: transform 0.2s;
}
.action-banner:hover .action-banner-arrow { transform: translateX(4px); color: #f59e0b; }

/* Popup toast notification */
.pending-toast {
    position: fixed; top: 20px; right: 20px;
    background: var(--glass-bg); backdrop-filter: blur(20px);
    border: 1px solid rgba(245,158,11,0.3);
    border-radius: 14px; padding: 16px 20px;
    display: flex; align-items: center; gap: 12px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.3);
    z-index: 9999; max-width: 380px;
    animation: toastSlideIn 0.4s ease-out;
    cursor: pointer;
    transition: all 0.3s;
}
.pending-toast:hover { transform: translateY(-2px); box-shadow: 0 16px 50px rgba(0,0,0,0.4); }
.pending-toast.hide { animation: toastSlideOut 0.3s ease-in forwards; }
@keyframes toastSlideIn {
    from { transform: translateX(120%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes toastSlideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(120%); opacity: 0; }
}
.pending-toast-icon {
    width: 42px; height: 42px; border-radius: 12px;
    background: linear-gradient(135deg, rgba(245,158,11,0.2), rgba(239,68,68,0.15));
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.pending-toast-body h4 { font-size: 13px; font-weight: 700; margin-bottom: 2px; }
.pending-toast-body p { font-size: 11px; color: var(--text-muted); line-height: 1.4; }
.pending-toast-close {
    position: absolute; top: 8px; right: 10px;
    background: none; border: none; color: var(--text-muted);
    font-size: 14px; cursor: pointer; padding: 2px 6px;
    border-radius: 4px; transition: all 0.2s;
}
.pending-toast-close:hover { background: rgba(255,255,255,0.1); color: var(--text-primary); }
.pending-toast-progress {
    position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
    background: rgba(245,158,11,0.15); border-radius: 0 0 14px 14px; overflow: hidden;
}
.pending-toast-progress-bar {
    height: 100%; background: linear-gradient(90deg, #f59e0b, #ef4444);
    border-radius: 0 0 14px 14px;
    animation: toastCountdown 8s linear forwards;
}
@keyframes toastCountdown { from { width: 100%; } to { width: 0%; } }
</style>

<?php
    // Smart link: 1 item → direct review, multiple → kelola pengajuan
    $bannerLink = ($pendingNeedMyTTD === 1 && $firstPendingId)
        ? BASE_URL . '/pages/review_pengajuan.php?id=' . $firstPendingId
        : BASE_URL . '/pages/kelola_pengajuan.php';
?>

<a href="<?= $bannerLink ?>" class="action-banner" id="action-banner">
    <div class="action-banner-icon">🔔</div>
    <div class="action-banner-count"><?= $pendingNeedMyTTD ?></div>
    <div class="action-banner-text">
        <h3>Pengajuan Menunggu Persetujuan Anda</h3>
        <p>
            <?php if ($pendingNeedMyTTD === 1): ?>
                Ada 1 pengajuan yang perlu Anda tandatangani. Klik untuk langsung review →
            <?php else: ?>
                <?= $pendingNeedMyTTD ?> pengajuan memerlukan tanda tangan Anda. Klik untuk melihat daftar →
            <?php endif; ?>
        </p>
    </div>
    <div class="action-banner-arrow">→</div>
</a>

<!-- Popup Toast -->
<div class="pending-toast" id="pending-toast" onclick="window.location.href='<?= $bannerLink ?>'">
    <button class="pending-toast-close" onclick="event.stopPropagation(); closeToast()">✕</button>
    <div class="pending-toast-icon">⚡</div>
    <div class="pending-toast-body">
        <h4>Ada <?= $pendingNeedMyTTD ?> pengajuan baru!</h4>
        <p>
            <?php if ($pendingNeedMyTTD === 1): ?>
                Butuh tanda tangan Anda. Klik untuk review sekarang.
            <?php else: ?>
                Menunggu persetujuan Anda. Klik untuk cek sekarang.
            <?php endif; ?>
        </p>
    </div>
    <div class="pending-toast-progress">
        <div class="pending-toast-progress-bar"></div>
    </div>
</div>

<script>
// Auto-hide toast after 8 seconds
setTimeout(function() { closeToast(); }, 8000);
function closeToast() {
    const t = document.getElementById('pending-toast');
    if (t && !t.classList.contains('hide')) {
        t.classList.add('hide');
        setTimeout(() => t.remove(), 400);
    }
}
</script>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card gold" style="cursor: pointer;" onclick="document.getElementById('modal-cuti').classList.add('show')">
        <div class="stat-icon">🏖️</div>
        <div class="stat-info">
            <h4>Cuti Tahunan</h4>
            <div class="stat-value"><?= ($saldo['jatah_tahunan'] - $saldo['terpakai_tahunan']) + ($saldo['jatah_tahunan_lalu'] - $saldo['terpakai_tahunan_lalu']) ?></div>
            <div class="stat-sub">Sisa hari aktif. <span style="text-decoration: underline;">Detail Kuota →</span></div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">⏳</div>
        <div class="stat-info">
            <h4>Menunggu</h4>
            <div class="stat-value"><?= $pendingCount ?></div>
            <div class="stat-sub">pengajuan pending</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">📊</div>
        <div class="stat-info">
            <h4>Bulan Ini</h4>
            <div class="stat-value"><?= $bulanIni ?></div>
            <div class="stat-sub">total pengajuan</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-info">
            <h4>Disetujui</h4>
            <div class="stat-value"><?= $disetujui ?></div>
            <div class="stat-sub">tahun <?= TAHUN_AKTIF ?></div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div style="margin-bottom: 8px;">
    <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px;">Buat Pengajuan Baru</h3>
</div>
<div class="quick-actions">
    <a href="<?= BASE_URL ?>/pages/form_cuti.php" class="action-card cuti">
        <div class="action-icon">🏖️</div>
        <h4>Pengajuan Cuti</h4>
        <p>Ajukan cuti tahunan</p>
    </a>
    <a href="<?= BASE_URL ?>/pages/form_izin.php" class="action-card izin">
        <div class="action-icon">📝</div>
        <h4>Pengajuan Izin</h4>
        <p>Izin 1 hari kerja</p>
    </a>
    <a href="<?= BASE_URL ?>/pages/form_pulang_cepat.php" class="action-card pulcep">
        <div class="action-icon">⏰</div>
        <h4>Terlambat / Pulang Cepat</h4>
        <p>Izin waktu jam kerja</p>
    </a>
</div>

<?php if ($showPending && count($pendingAll) > 0): ?>
<!-- Pejabat: Pengajuan Pending -->
<div class="card" style="margin-bottom: 32px;">
    <div class="card-header">
        <h3>📌 Pengajuan Menunggu TTD</h3>
        <a href="<?= BASE_URL ?>/pages/kelola_pengajuan.php" class="btn btn-secondary btn-sm">Lihat Semua →</a>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Pegawai</th>
                        <th>Jenis</th>
                        <th>Detail</th>
                        <th>Tanggal Ajuan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingAll as $p): ?>
                    <tr>
                        <td data-label="Pegawai">
                            <strong><?= htmlspecialchars($p['nama']) ?></strong><br>
                            <small style="color: var(--text-muted);"><?= htmlspecialchars($p['jabatan']) ?></small>
                        </td>
                        <td data-label="Jenis"><?= jenisBadge($p['jenis_pengajuan']) ?></td>
                        <td data-label="Detail">
                            <?php if ($p['jenis_pengajuan'] === 'cuti'): ?>
                                <?= formatTanggal($p['tanggal_mulai']) ?> — <?= formatTanggal($p['tanggal_selesai']) ?>
                                <br><small style="color: var(--text-muted);"><?= $p['jumlah_hari'] ?> hari kerja</small>
                            <?php elseif ($p['jenis_pengajuan'] === 'izin'): ?>
                                <?= formatTanggal($p['tanggal_mulai']) ?> — <?= formatTanggal($p['tanggal_selesai']) ?>
                                <br><small style="color: var(--text-muted);"><?= $p['jumlah_hari'] ?> hari kerja</small>
                            <?php else: ?>
                                <?= formatTanggal($p['tanggal_pulang']) ?>, Pulang: <?= substr($p['jam_pulang_diajukan'], 0, 5) ?>
                                <br><small style="color: var(--text-muted);">Selisih: <?= formatSelisihWaktu($p['selisih_menit']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Tanggal Ajuan"><?= formatTanggal($p['created_at']) ?></td>
                        <td data-label="Aksi">
                            <a href="<?= BASE_URL ?>/pages/review_pengajuan.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">✍️ Review & TTD</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Riwayat Terbaru -->
<div class="card">
    <div class="card-header">
        <h3>📋 Pengajuan Terbaru</h3>
        <a href="<?= BASE_URL ?>/pages/riwayat.php" class="btn btn-secondary btn-sm">Lihat Semua →</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentPengajuan)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h4>Belum ada pengajuan</h4>
                <p>Mulai dengan membuat pengajuan baru di atas.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Jenis</th>
                            <th>Detail</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPengajuan as $p): ?>
                        <tr>
                            <td data-label="Jenis"><?= jenisBadge($p['jenis_pengajuan'], $p['tipe_izin_waktu'] ?? null) ?></td>
                            <td data-label="Detail">
                                <?php if ($p['jenis_pengajuan'] === 'cuti'): ?>
                                    <?= formatTanggal($p['tanggal_mulai']) ?> — <?= formatTanggal($p['tanggal_selesai']) ?>
                                    <br><small style="color: var(--text-muted);"><?= $p['jumlah_hari'] ?> hari kerja (<?= ucwords(str_replace('_', ' ', $p['tipe_cuti'] ?? 'tahunan')) ?>)</small>
                                <?php elseif ($p['jenis_pengajuan'] === 'izin'): ?>
                                    <?= formatTanggal($p['tanggal_mulai']) ?>
                                    <br><small style="color: var(--text-muted);">Jam: <?= substr($p['jam_mulai'] ?? '00:00', 0, 5) ?> - <?= substr($p['jam_selesai'] ?? '00:00', 0, 5) ?></small>
                                <?php else: ?>
                                    <?= formatTanggal($p['tanggal_pulang']) ?><br>
                                    <small style="color: var(--text-muted);">
                                    <?php if (($p['tipe_izin_waktu'] ?? '') === 'datang_terlambat'): ?>
                                        Datang: <?= substr($p['jam_pulang_diajukan'], 0, 5) ?>
                                    <?php else: ?>
                                        Pulang: <?= substr($p['jam_pulang_diajukan'], 0, 5) ?>
                                    <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Alasan" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($p['alasan']) ?>
                                <?php if (!empty($p['lampiran'])): ?>
                                    <br><a href="<?= BASE_URL ?>/<?= $p['lampiran'] ?>" target="_blank" class="badge badge-purple" style="text-decoration:none; margin-top:4px; display:inline-block;">📎 Surat Dokter</a>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status"><?= statusBadge($p['status']) ?></td>
                            <td data-label="Tanggal"><?= formatTanggal($p['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Detail Cuti -->
<div class="modal-overlay" id="modal-cuti">
    <div class="modal-content" style="max-width: 600px;">
        <h3 style="margin-bottom: 20px;">📊 Detail Kuota & Sisa Cuti (Tahun <?= TAHUN_AKTIF ?>)</h3>
        
        <div class="stats-grid" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
            <div class="stat-card gold" style="box-shadow: none; border: 1px solid rgba(212,168,67,0.3); margin-bottom: 0;">
                <div class="stat-info">
                    <h4 style="font-size: 11px;">Cuti Tahunan Lalu</h4>
                    <div class="stat-value" style="font-size: 20px;"><?= ($saldo['jatah_tahunan_lalu'] - $saldo['terpakai_tahunan_lalu']) ?></div>
                    <div class="stat-sub">Sisa dari <?= $saldo['jatah_tahunan_lalu'] ?> hari</div>
                </div>
            </div>
            <div class="stat-card blue" style="box-shadow: none; border: 1px solid rgba(59,130,246,0.3); margin-bottom: 0;">
                <div class="stat-info">
                    <h4 style="font-size: 11px;">Cuti Tahunan Ini</h4>
                    <div class="stat-value" style="font-size: 20px;"><?= ($saldo['jatah_tahunan'] - $saldo['terpakai_tahunan']) ?></div>
                    <div class="stat-sub">Sisa dari <?= $saldo['jatah_tahunan'] ?> hari</div>
                </div>
            </div>
            <div class="stat-card green" style="box-shadow: none; border: 1px solid rgba(16,185,129,0.3); margin-bottom: 0;">
                <div class="stat-info">
                    <h4 style="font-size: 11px;">Cuti Sakit</h4>
                    <div class="stat-value" style="font-size: 20px;"><?= ($saldo['jatah_sakit'] - $saldo['terpakai_sakit']) ?></div>
                    <div class="stat-sub">Sisa dari <?= $saldo['jatah_sakit'] ?> hari</div>
                </div>
            </div>
            <div class="stat-card orange" style="box-shadow: none; border: 1px solid rgba(245,158,11,0.3); margin-bottom: 0;">
                <div class="stat-info">
                    <h4 style="font-size: 11px;">Alasan Penting</h4>
                    <div class="stat-value" style="font-size: 20px;"><?= ($saldo['jatah_alasan_penting'] - $saldo['terpakai_alasan_penting']) ?></div>
                    <div class="stat-sub">Sisa dari <?= $saldo['jatah_alasan_penting'] ?> hari</div>
                </div>
            </div>
            <?php if ($userJenisKelamin === 'P'): ?>
            <div class="stat-card" style="grid-column: 1 / -1; box-shadow: none; background: rgba(255, 105, 180, 0.1); border: 1px solid rgba(255, 105, 180, 0.3); margin-bottom: 0;">
                <div class="stat-info">
                    <h4 style="font-size: 11px; color: #ff69b4;">Cuti Melahirkan</h4>
                    <div class="stat-value" style="font-size: 20px; color: #ff69b4;"><?= ($saldo['jatah_melahirkan'] - $saldo['terpakai_melahirkan']) ?></div>
                    <div class="stat-sub">Sisa dari <?= $saldo['jatah_melahirkan'] ?> hari</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="modal-actions" style="justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-cuti').classList.remove('show')">Tutup</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Dashboard V2 - Desain Baru (Simplified & App-like)
 */
session_start();
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/role_helper.php';
require_once __DIR__ . '/helpers/notifikasi.php';

requireLogin();

$_SESSION['design_version'] = 'v2';

$userId = $_SESSION['user_id'];

// Ambil saldo cuti
$saldo = cekSisaCuti($userId, (int) TAHUN_AKTIF, $pdo);

// Hitung pengajuan pending milik user sendiri
$stmtMyPending = $pdo->prepare("SELECT COUNT(*) FROM pengajuan WHERE user_id = ? AND status = 'pending'");
$stmtMyPending->execute([$userId]);
$myPendingCount = $stmtMyPending->fetchColumn();

// Pengajuan terbaru (5 baris terakhir) milik user sendiri
$stmtRiwayat = $pdo->prepare("SELECT * FROM pengajuan WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmtRiwayat->execute([$userId]);
$recentRiwayat = $stmtRiwayat->fetchAll();

// Jika pejabat/atasan, ambil pengajuan pending yang perlu di-review
$pendingNeedMyTTD = 0;
$firstPendingId = null;
$showPending = canAccessKelolaPengajuan($_SESSION['role'], $pdo, $userId);
if ($showPending) {
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
    }
}

// Fetch jenis kelamin user
$stmtUser = $pdo->prepare("SELECT jenis_kelamin FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userJenisKelamin = $stmtUser->fetchColumn();

// Notifikasi count
$notifCount = hitungNotifBelumDibaca($pdo, $userId);

$pageTitle = 'Dashboard V2';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

    <!-- Header Greeting & Notif -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 22px; font-weight: 800; margin-bottom: 4px; color: #f8fafc;">Halo, <?= htmlspecialchars(explode(',', $_SESSION['nama'])[0]) ?> 👋</h1>
            <p style="color: #cbd5e1; font-size: 14px;">Selamat datang kembali di dashboard Anda.</p>
        </div>
        
        <!-- Desktop Notif Container -->
        <div class="desktop-notif-container" id="desktop-notif-container"></div>
    </div>

    <style>
        .dashboard-top-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 900px) {
            .dashboard-top-grid {
                grid-template-columns: 1fr;
            }
        }
        .progress-bar-bg {
            width: 100%; height: 6px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden; margin-top: 10px;
        }
        [data-theme="light"] .progress-bar-bg {
            background: rgba(0,0,0,0.06);
        }
    </style>

    <div class="dashboard-top-grid">
        <!-- Kolom Kiri: Sisa Kuota (Single Card List) -->
        <div>
            <h2 style="font-size: 14px; font-weight: 700; margin-bottom: 10px; color: #cbd5e1;">📊 Sisa Kuota Cuti (<?= TAHUN_AKTIF ?>)</h2>
            <div class="quota-item-v2" style="display: flex; flex-direction: column; align-items: stretch; height: calc(100% - 28px); padding: 20px 24px 12px 24px;">
                
                <?php 
                $quotas = [];
                // Tahunan Lalu pertama (jika bukan perempuan)
                if ($userJenisKelamin !== 'P') {
                    $quotas[] = [
                        'title' => 'Tahunan Lalu',
                        'terpakai' => $saldo['terpakai_tahunan_lalu'],
                        'jatah' => $saldo['jatah_tahunan_lalu'],
                        'sisa' => $saldo['jatah_tahunan_lalu'] - $saldo['terpakai_tahunan_lalu'],
                        'color' => '#d4a843'
                    ];
                }
                $quotas[] = [
                    'title' => 'Cuti Tahunan',
                    'terpakai' => $saldo['terpakai_tahunan'],
                    'jatah' => $saldo['jatah_tahunan'],
                    'sisa' => $saldo['jatah_tahunan'] - $saldo['terpakai_tahunan'],
                    'color' => '#3b82f6'
                ];
                $quotas[] = [
                    'title' => 'Cuti Sakit',
                    'terpakai' => $saldo['terpakai_sakit'],
                    'jatah' => $saldo['jatah_sakit'],
                    'sisa' => $saldo['jatah_sakit'] - $saldo['terpakai_sakit'],
                    'color' => '#10b981'
                ];
                $quotas[] = [
                    'title' => 'Alasan Penting',
                    'terpakai' => $saldo['terpakai_alasan_penting'],
                    'jatah' => $saldo['jatah_alasan_penting'],
                    'sisa' => $saldo['jatah_alasan_penting'] - $saldo['terpakai_alasan_penting'],
                    'color' => '#f59e0b'
                ];
                if ($userJenisKelamin === 'P') {
                    $quotas[] = [
                        'title' => 'Cuti Melahirkan',
                        'terpakai' => $saldo['terpakai_melahirkan'],
                        'jatah' => $saldo['jatah_melahirkan'],
                        'sisa' => $saldo['jatah_melahirkan'] - $saldo['terpakai_melahirkan'],
                        'color' => '#ec4899'
                    ];
                }
                ?>

                <?php foreach ($quotas as $idx => $q): 
                    $pct = $q['jatah'] > 0 ? ($q['sisa'] / $q['jatah']) * 100 : 0;
                    $isLast = ($idx === count($quotas) - 1);
                ?>
                <div style="<?= !$isLast ? 'border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px; margin-bottom: 10px;' : '' ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <div style="font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 2px;"><?= $q['title'] ?></div>
                            <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;"><?= $q['terpakai'] ?> / <?= $q['jatah'] ?> Terpakai</div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 11px; font-weight: 600; color: <?= $q['color'] ?>; text-transform: uppercase; letter-spacing: 0.5px;">Sisa</span>
                            <div style="font-size: 16px; font-weight: 800; width: 40px; height: 40px; border-radius: 50%; background: <?= $q['color'] ?>20; color: <?= $q['color'] ?>; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 0 0 1px <?= $q['color'] ?>40;">
                                <?= $q['sisa'] ?>
                            </div>
                        </div>
                    </div>
                    <!-- Background bar adalah abu-abu (sisa background), colored bar adalah progress (sisa cuti) -->
                    <div style="width: 100%; height: 8px; background: var(--glass-border); border-radius: 4px; overflow: hidden; position: relative;">
                        <div style="height: 100%; background: <?= $q['color'] ?>; width: <?= $pct ?>%; border-radius: 4px; transition: width 1s ease-out;"></div>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>

        <!-- Kolom Kanan: Pending Card -->
        <div>
            <h2 style="font-size: 14px; font-weight: 700; margin-bottom: 10px; color: #cbd5e1;">⏳ Sedang Diproses</h2>
            <div class="quota-item-v2" style="display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; height: calc(100% - 28px); padding: 24px 20px;">
                <div style="text-align: center; margin-bottom: 10px;">
                    <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 2px;">Pengajuan Pending</h4>
                    <p style="font-size: 12px; color: var(--text-muted);">Menunggu Persetujuan</p>
                </div>
                
                <!-- GEDE DITENGAH -->
                <div style="font-size: 72px; font-weight: 900; line-height: 1; color: var(--red-500); text-shadow: 0 8px 24px rgba(239,68,68,0.2); margin-bottom: 16px;">
                    <?= $myPendingCount ?>
                </div>

                <a href="<?= BASE_URL ?>/pages/riwayat.php" style="color: var(--blue-500); font-size: 13px; font-weight: 600; text-decoration: none; padding: 10px 24px; background: rgba(59,130,246,0.1); border-radius: 8px; transition: all 0.2s;">Cek Status Lengkap →</a>
            </div>
        </div>
    </div>

    <!-- Quick Menus -->
    <div style="margin-top: 8px;">
        <h2 style="font-size: 14px; font-weight: 700; margin-bottom: 10px; color: #cbd5e1;">🚀 Buat Pengajuan Baru</h2>
        <div class="quick-menus-grid">
            <a href="<?= BASE_URL ?>/pages/form_cuti.php" class="quick-card cuti">
                <div class="quick-icon">🏖️</div>
                <h3>Pengajuan Cuti</h3>
                <p>Ajukan cuti tahunan, sakit, melahirkan, atau alasan penting.</p>
            </a>
            
            <a href="<?= BASE_URL ?>/pages/form_izin.php" class="quick-card izin">
                <div class="quick-icon">📝</div>
                <h3>Izin Keluar Kantor</h3>
                <p>Ajukan izin untuk keluar pada jam kerja selama 1 hari.</p>
            </a>
            
            <a href="<?= BASE_URL ?>/pages/form_pulang_cepat.php" class="quick-card pulcep">
                <div class="quick-icon">⏰</div>
                <h3>Terlambat / Pulcep</h3>
                <p>Izin datang terlambat atau pulang lebih awal hari ini.</p>
            </a>
        </div>
    </div>

    <!-- Riwayat Terbaru (Full Width) -->
    <div style="margin-top: 12px;">
        <h2 style="font-size: 14px; font-weight: 700; margin-bottom: 10px; color: #cbd5e1;">📄 Riwayat Terbaru</h2>
        <div class="quota-item-v2" style="display: block; padding: 0; overflow: hidden;">
            <?php if (count($recentRiwayat) > 0): ?>
                <div class="table-wrapper" style="margin: 0; border: none; box-shadow: none; border-radius: 0;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--glass-border); font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">
                                <th style="padding: 16px;">Jenis</th>
                                <th style="padding: 16px;">Detail</th>
                                <th style="padding: 16px;">Alasan</th>
                                <th style="padding: 16px;">Status</th>
                                <th style="padding: 16px;">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRiwayat as $i => $p): if($i > 4) break; /* Tampilkan 5 di layout full width */ ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                                <td style="padding: 16px;" data-label="Jenis"><?= jenisBadge($p['jenis_pengajuan'], $p['tipe_izin_waktu'] ?? null) ?></td>
                                <td style="padding: 16px; font-size: 13px; color: var(--text-primary);" data-label="Detail">
                                    <?php if ($p['jenis_pengajuan'] === 'cuti'): ?>
                                        <?= formatTanggal($p['tanggal_mulai']) ?> — <?= formatTanggal($p['tanggal_selesai']) ?>
                                        <br><span style="font-size:11px; color:var(--text-muted);"><?= $p['jumlah_hari'] ?> hari kerja (<?= ucwords(str_replace('_', ' ', $p['tipe_cuti'] ?? 'tahunan')) ?>)</span>
                                    <?php elseif ($p['jenis_pengajuan'] === 'izin'): ?>
                                        <?= formatTanggal($p['tanggal_mulai']) ?>
                                        <br><span style="font-size:11px; color:var(--text-muted);">Jam: <?= substr($p['jam_mulai'] ?? '00:00', 0, 5) ?> - <?= substr($p['jam_selesai'] ?? '00:00', 0, 5) ?></span>
                                    <?php else: ?>
                                        <?= formatTanggal($p['tanggal_pulang']) ?>
                                        <br><span style="font-size:11px; color:var(--text-muted);">
                                            <?php if (($p['tipe_izin_waktu'] ?? '') === 'datang_terlambat'): ?>
                                                Datang: <?= substr($p['jam_pulang_diajukan'], 0, 5) ?> 
                                                (Resmi: <?= substr($p['jam_pulang_resmi'], 0, 5) ?>)
                                            <?php else: ?>
                                                Pulang: <?= substr($p['jam_pulang_diajukan'], 0, 5) ?> 
                                                (Resmi: <?= substr($p['jam_pulang_resmi'], 0, 5) ?>)
                                            <?php endif; ?>
                                            — Selisih: <?= formatSelisihWaktu($p['selisih_menit']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 16px; font-size: 13px; color: var(--text-secondary); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" data-label="Alasan">
                                    <?= htmlspecialchars($p['alasan'] ?? '-') ?>
                                </td>
                                <td style="padding: 16px;" data-label="Status"><?= statusBadge($p['status']) ?></td>
                                <td style="padding: 16px; font-size: 13px; color: var(--text-secondary);" data-label="Tanggal"><?= formatTanggal(substr($p['created_at'], 0, 10)) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align: center; padding: 16px; border-top: 1px solid var(--glass-border);">
                    <a href="<?= BASE_URL ?>/pages/riwayat.php" style="font-size: 13px; color: var(--blue-500); font-weight: 600; text-decoration: none;">Lihat Semua Riwayat</a>
                </div>
            <?php else: ?>
                <p style="font-size: 13px; color: var(--text-muted); text-align: center; margin: 32px 0;">Belum ada riwayat pengajuan.</p>
            <?php endif; ?>
        </div>
    </div>





<?php if ($showPending && $pendingNeedMyTTD > 0 && isPejabatStruktural($_SESSION['role'])): ?>
<!-- Popup Toast untuk Pejabat Struktural (WAJIB ADA) -->
<style>
/* Re-use toast style dari index.php */
.pending-toast {
    position: fixed; bottom: 30px; right: 30px;
    background: var(--glass-bg); backdrop-filter: blur(20px);
    border: 1px solid rgba(245,158,11,0.3);
    border-radius: 16px; padding: 20px 24px;
    display: flex; align-items: center; gap: 16px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.4);
    z-index: 9999; max-width: 400px;
    animation: toastSlideUp 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    transition: all 0.3s;
}
.pending-toast:hover { transform: translateY(-4px); box-shadow: 0 16px 50px rgba(0,0,0,0.5); }
.pending-toast.hide { animation: toastSlideDown 0.4s ease-in forwards; }
@keyframes toastSlideUp {
    from { transform: translateY(150%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
@keyframes toastSlideDown {
    from { transform: translateY(0); opacity: 1; }
    to { transform: translateY(150%); opacity: 0; }
}
.pending-toast-icon {
    width: 48px; height: 48px; border-radius: 14px;
    background: linear-gradient(135deg, rgba(245,158,11,0.2), rgba(239,68,68,0.15));
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; flex-shrink: 0;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}
.pending-toast-body h4 { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
.pending-toast-body p { font-size: 13px; color: var(--text-muted); line-height: 1.4; }
.pending-toast-close {
    position: absolute; top: 12px; right: 12px;
    background: none; border: none; color: var(--text-muted);
    font-size: 16px; cursor: pointer; padding: 4px 8px;
    border-radius: 6px; transition: all 0.2s;
}
.pending-toast-close:hover { background: rgba(255,255,255,0.1); color: var(--text-primary); }
</style>

<?php
    $bannerLink = ($pendingNeedMyTTD === 1 && $firstPendingId)
        ? BASE_URL . '/pages/review_pengajuan.php?id=' . $firstPendingId
        : BASE_URL . '/pages/kelola_pengajuan.php';
?>
<div class="pending-toast" id="pending-toast" onclick="window.location.href='<?= $bannerLink ?>'">
    <button class="pending-toast-close" onclick="event.stopPropagation(); closeToast()">✕</button>
    <div class="pending-toast-icon">⚡</div>
    <div class="pending-toast-body">
        <h4>Ada <?= $pendingNeedMyTTD ?> Pengajuan Baru!</h4>
        <p>
            <?php if ($pendingNeedMyTTD === 1): ?>
                Ada 1 pengajuan yang menunggu tanda tangan Anda. Klik untuk review.
            <?php else: ?>
                <?= $pendingNeedMyTTD ?> pengajuan menunggu persetujuan Anda. Klik untuk cek sekarang.
            <?php endif; ?>
        </p>
    </div>
</div>
<script>
// Auto-hide toast after 10 seconds
setTimeout(function() { closeToast(); }, 10000);
function closeToast() {
    const t = document.getElementById('pending-toast');
    if (t && !t.classList.contains('hide')) {
        t.classList.add('hide');
        setTimeout(() => t.remove(), 400);
    }
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

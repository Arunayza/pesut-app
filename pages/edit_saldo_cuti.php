<?php
/**
 * Edit Saldo Cuti Pegawai
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireLogin();

if (!in_array($_SESSION['role'], ['kepegawaian', 'admin', 'staf_kpot'])) {
    setFlash('error', 'Anda tidak memiliki akses!');
    redirect(BASE_URL . '/dashboard_v2.php');
}

$id = (int) ($_GET['id'] ?? 0);
$tahun = (int) ($_GET['tahun'] ?? TAHUN_AKTIF);

if ($id <= 0) {
    redirect(BASE_URL . '/pages/kontrol_cuti.php');
}

// Cek user exist
$stmt = $pdo->prepare("SELECT id, nip, nama, status_pegawai, jenis_kelamin FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'Pegawai tidak ditemukan!');
    redirect(BASE_URL . '/pages/kontrol_cuti.php');
}

// Ensure saldo exists
$saldo = cekSisaCuti($id, $tahun, $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jtl = isset($_POST['jatah_tahunan_lalu']) ? (int) $_POST['jatah_tahunan_lalu'] : $saldo['jatah_tahunan_lalu'];
    $ttl = isset($_POST['terpakai_tahunan_lalu']) ? (int) $_POST['terpakai_tahunan_lalu'] : $saldo['terpakai_tahunan_lalu'];
    
    $jt = isset($_POST['jatah_tahunan']) ? (int) $_POST['jatah_tahunan'] : $saldo['jatah_tahunan'];
    $tt = isset($_POST['terpakai_tahunan']) ? (int) $_POST['terpakai_tahunan'] : $saldo['terpakai_tahunan'];
    
    $js = isset($_POST['jatah_sakit']) ? (int) $_POST['jatah_sakit'] : $saldo['jatah_sakit'];
    $ts = isset($_POST['terpakai_sakit']) ? (int) $_POST['terpakai_sakit'] : $saldo['terpakai_sakit'];
    
    $jm = isset($_POST['jatah_melahirkan']) ? (int) $_POST['jatah_melahirkan'] : $saldo['jatah_melahirkan'];
    $tm = isset($_POST['terpakai_melahirkan']) ? (int) $_POST['terpakai_melahirkan'] : $saldo['terpakai_melahirkan'];
    
    $jap = isset($_POST['jatah_alasan_penting']) ? (int) $_POST['jatah_alasan_penting'] : $saldo['jatah_alasan_penting'];
    $tap = isset($_POST['terpakai_alasan_penting']) ? (int) $_POST['terpakai_alasan_penting'] : $saldo['terpakai_alasan_penting'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE saldo_cuti SET 
                jatah_tahunan_lalu = ?, terpakai_tahunan_lalu = ?,
                jatah_tahunan = ?, terpakai_tahunan = ?,
                jatah_sakit = ?, terpakai_sakit = ?,
                jatah_melahirkan = ?, terpakai_melahirkan = ?,
                jatah_alasan_penting = ?, terpakai_alasan_penting = ?
            WHERE user_id = ? AND tahun = ?
        ");
        $stmt->execute([$jtl, $ttl, $jt, $tt, $js, $ts, $jm, $tm, $jap, $tap, $id, $tahun]);
        
        setFlash('success', 'Saldo cuti berhasil diperbarui.');
        redirect(BASE_URL . '/pages/kontrol_cuti.php');
    } catch (Exception $e) {
        setFlash('error', 'Gagal memperbarui saldo: ' . $e->getMessage());
    }
}

$pageTitle = 'Edit Saldo Cuti';
$flash = getFlash();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<a href="<?= BASE_URL ?>/pages/kontrol_cuti.php" class="btn-back-dashboard" style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); text-decoration: none; font-size: 13px; font-weight: 600; margin-bottom: 16px; padding: 6px 14px; border-radius: 8px; transition: all 0.2s; border: 1px solid transparent;">
    ← Kembali ke Kontrol Cuti
</a>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3>Edit Saldo Cuti - <?= htmlspecialchars($user['nama']) ?></h3>
        <p style="color: var(--text-muted); font-size: 14px; margin-top: 5px;">Tahun: <?= $tahun ?></p>
    </div>
    <div class="card-body">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <?php if ($user['status_pegawai'] !== 'PPPK'): ?>
                <!-- Jatah Tahunan Lalu -->
                <div class="form-group" style="background: rgba(255,255,255,0.02); padding: 16px; border-radius: 8px; border: 1px solid var(--glass-border);">
                    <h4 style="margin-bottom: 12px; font-size: 14px; color: #d4a843;">Cuti Tahunan Lalu</h4>
                    <label>Jatah</label>
                    <input type="number" name="jatah_tahunan_lalu" class="form-control" value="<?= $saldo['jatah_tahunan_lalu'] ?>" required>
                    <label style="margin-top: 8px;">Terpakai</label>
                    <input type="number" name="terpakai_tahunan_lalu" class="form-control" value="<?= $saldo['terpakai_tahunan_lalu'] ?>" required>
                </div>
                <?php endif; ?>
                
                <!-- Jatah Tahunan -->
                <div class="form-group" style="background: rgba(255,255,255,0.02); padding: 16px; border-radius: 8px; border: 1px solid var(--glass-border);">
                    <h4 style="margin-bottom: 12px; font-size: 14px; color: #3b82f6;">Cuti Tahunan</h4>
                    <label>Jatah</label>
                    <input type="number" name="jatah_tahunan" class="form-control" value="<?= $saldo['jatah_tahunan'] ?>" required>
                    <label style="margin-top: 8px;">Terpakai</label>
                    <input type="number" name="terpakai_tahunan" class="form-control" value="<?= $saldo['terpakai_tahunan'] ?>" required>
                </div>

                <!-- Sakit -->
                <div class="form-group" style="background: rgba(255,255,255,0.02); padding: 16px; border-radius: 8px; border: 1px solid var(--glass-border);">
                    <h4 style="margin-bottom: 12px; font-size: 14px; color: #10b981;">Cuti Sakit</h4>
                    <label>Jatah</label>
                    <input type="number" name="jatah_sakit" class="form-control" value="<?= $saldo['jatah_sakit'] ?>" required>
                    <label style="margin-top: 8px;">Terpakai</label>
                    <input type="number" name="terpakai_sakit" class="form-control" value="<?= $saldo['terpakai_sakit'] ?>" required>
                </div>

                <?php if ($user['status_pegawai'] !== 'PPPK'): ?>
                <!-- Alasan Penting -->
                <div class="form-group" style="background: rgba(255,255,255,0.02); padding: 16px; border-radius: 8px; border: 1px solid var(--glass-border);">
                    <h4 style="margin-bottom: 12px; font-size: 14px; color: #f59e0b;">Cuti Alasan Penting</h4>
                    <label>Jatah</label>
                    <input type="number" name="jatah_alasan_penting" class="form-control" value="<?= $saldo['jatah_alasan_penting'] ?>" required>
                    <label style="margin-top: 8px;">Terpakai</label>
                    <input type="number" name="terpakai_alasan_penting" class="form-control" value="<?= $saldo['terpakai_alasan_penting'] ?>" required>
                </div>
                <?php endif; ?>

                <?php if ($user['jenis_kelamin'] === 'P'): ?>
                <!-- Melahirkan -->
                <div class="form-group" style="background: rgba(255,255,255,0.02); padding: 16px; border-radius: 8px; border: 1px solid var(--glass-border); grid-column: 1 / -1;">
                    <h4 style="margin-bottom: 12px; font-size: 14px; color: #ec4899;">Cuti Melahirkan</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label>Jatah</label>
                            <input type="number" name="jatah_melahirkan" class="form-control" value="<?= $saldo['jatah_melahirkan'] ?>" required>
                        </div>
                        <div>
                            <label>Terpakai</label>
                            <input type="number" name="terpakai_melahirkan" class="form-control" value="<?= $saldo['terpakai_melahirkan'] ?>" required>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 24px; text-align: right;">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

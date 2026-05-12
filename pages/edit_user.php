<?php
/**
 * Edit Pegawai (Admin)
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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'User tidak ditemukan!');
    redirect(BASE_URL . '/pages/kelola_user.php');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    setFlash('error', 'User tidak ditemukan!');
    redirect(BASE_URL . '/pages/kelola_user.php');
}

$pageTitle = 'Edit Pegawai';

// Ambil daftar atasan (kecuali dirinya sendiri)
$stmt = $pdo->prepare("SELECT id, nama, jabatan FROM users WHERE aktif = 1 AND id != ? ORDER BY nama ASC");
$stmt->execute([$id]);
$users = $stmt->fetchAll();

// Data saldo cuti telah dipindah ke halaman Kontrol Cuti

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="card" style="width: 100%; max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3>✏️ Edit Pegawai</h3>
    </div>
    <div class="card-body">
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="flash-message <?= $flash['type'] ?>" style="margin-bottom: 20px;">
                <?= $flash['type'] === 'success' ? '✅' : '❌' ?>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/proses/edit_user.php" method="POST">
            <input type="hidden" name="id" value="<?= $targetUser['id'] ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label for="nip">NIP <span style="color:red">*</span></label>
                    <input type="text" id="nip" name="nip" class="form-control" required value="<?= htmlspecialchars($targetUser['nip']) ?>">
                </div>
                <div class="form-group">
                    <label for="nama">Nama Lengkap <span style="color:red">*</span></label>
                    <input type="text" id="nama" name="nama" class="form-control" required value="<?= htmlspecialchars($targetUser['nama']) ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 16px;">
                <div class="form-group">
                    <label for="jenis_kelamin">Jenis Kelamin <span style="color:red">*</span></label>
                    <select id="jenis_kelamin" name="jenis_kelamin" class="form-control" required>
                        <option value="L" <?= $targetUser['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki (L)</option>
                        <option value="P" <?= $targetUser['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan (P)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status_pegawai">Status Pegawai <span style="color:red">*</span></label>
                    <select id="status_pegawai" name="status_pegawai" class="form-control" required>
                        <option value="PNS" <?= $targetUser['status_pegawai'] === 'PNS' ? 'selected' : '' ?>>PNS</option>
                        <option value="PPPK" <?= $targetUser['status_pegawai'] === 'PPPK' ? 'selected' : '' ?>>PPPK</option>
                        <option value="Hakim" <?= $targetUser['status_pegawai'] === 'Hakim' ? 'selected' : '' ?>>Hakim</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tgl_mulai_kerja">Tgl Mulai Kerja <span style="color:red">*</span></label>
                    <input type="date" id="tgl_mulai_kerja" name="tgl_mulai_kerja" class="form-control" required value="<?= htmlspecialchars($targetUser['tgl_mulai_kerja']) ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
                <div class="form-group">
                    <label for="jabatan">Jabatan <span style="color:red">*</span></label>
                    <input type="text" id="jabatan" name="jabatan" class="form-control" required value="<?= htmlspecialchars($targetUser['jabatan']) ?>">
                </div>
                <div class="form-group">
                    <label for="pangkat">Pangkat / Golongan</label>
                    <input type="text" id="pangkat" name="pangkat" class="form-control" value="<?= htmlspecialchars($targetUser['pangkat']) ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
                <div class="form-group">
                    <label for="role">Hak Akses (Role) <span style="color:red">*</span></label>
                    <select id="role" name="role" class="form-control" required>
                        <?php foreach (ROLE_LABELS as $roleVal => $roleLabel): ?>
                        <option value="<?= $roleVal ?>" <?= $targetUser['role'] === $roleVal ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="atasan_id">Atasan Langsung (Untuk Approval)</label>
                    <select id="atasan_id" name="atasan_id" class="form-control">
                        <option value="">-- Tidak Ada / Kosongkan --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $targetUser['atasan_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nama']) ?> - <?= htmlspecialchars($u['jabatan']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-top: 16px;">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="reset_password" value="1">
                    <span style="font-weight: normal;">Reset password user ini kembali menjadi default (<strong>password123</strong>)</span>
                </label>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="aktif" value="1" <?= $targetUser['aktif'] ? 'checked' : '' ?>>
                    <span style="font-weight: normal;">Akun Aktif (Bisa Login)</span>
                </label>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--glass-border); margin: 24px 0;">

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="<?= BASE_URL ?>/pages/kelola_user.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const nipInput    = document.getElementById('nip');
    const statusSel   = document.getElementById('status_pegawai');
    const tglInput    = document.getElementById('tgl_mulai_kerja');

    /**
     * Parse NIP PNS/Hakim:
     *   Digit 1-8  : tanggal lahir (YYYYMMDD)
     *   Digit 9-14 : awal masuk kerja (YYYYMM)
     * Kembalikan string 'YYYY-MM-01' atau null jika tidak valid.
     */
    function parseTglMulaiFromNip(nip) {
        const clean = nip.replace(/\D/g, '');
        if (clean.length < 14) return null;
        const year  = clean.substring(8, 12);
        const month = clean.substring(12, 14);
        const y = parseInt(year, 10);
        const m = parseInt(month, 10);
        if (y < 1950 || y > 2100 || m < 1 || m > 12) return null;
        return `${year}-${month.padStart(2,'0')}-01`;
    }

    function tryAutoFill() {
        const status = statusSel ? statusSel.value : '';
        if (status !== 'PNS' && status !== 'Hakim') return; // P3K skip
        const tgl = parseTglMulaiFromNip(nipInput.value);
        if (tgl) {
            tglInput.value = tgl;
            // Beri visual feedback sebentar
            tglInput.style.borderColor = 'var(--orange-400)';
            setTimeout(() => tglInput.style.borderColor = '', 1500);
        }
    }

    if (nipInput && tglInput && statusSel) {
        nipInput.addEventListener('input', tryAutoFill);
        nipInput.addEventListener('blur',  tryAutoFill);
        statusSel.addEventListener('change', tryAutoFill);
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

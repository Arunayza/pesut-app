<?php
/**
 * Halaman Profil Pegawai
 * Untuk melihat/mengedit detail profil dan mengganti password
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireLogin();

$userId = $_SESSION['user_id'];

// Ambil data user
$stmt = $pdo->prepare("
    SELECT u.*, a.nama as nama_atasan, a.jabatan as jabatan_atasan 
    FROM users u 
    LEFT JOIN users a ON u.atasan_id = a.id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Ambil daftar atasan (kecuali diri sendiri)
$stmtAtasan = $pdo->prepare("SELECT id, nama, jabatan FROM users WHERE id != ? ORDER BY nama ASC");
$stmtAtasan->execute([$userId]);
$daftarAtasan = $stmtAtasan->fetchAll();

$pageTitle = 'Profil Saya';
$flash = getFlash();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header" style="display: flex; gap: 16px; align-items: center; border-bottom: none; padding-bottom: 0;">
        <div class="user-avatar" style="width: 64px; height: 64px; font-size: 24px; border-radius: 16px;">
            <?= strtoupper(substr($user['nama'], 0, 1)) ?>
        </div>
        <div>
            <h2 style="margin: 0; color: var(--text-primary); font-size: 20px;"><?= htmlspecialchars($user['nama']) ?></h2>
            <div style="color: var(--text-muted); font-size: 14px; margin-top: 4px;"><?= htmlspecialchars($user['jabatan']) ?></div>
        </div>
    </div>

    <div class="card-body">
        <?php if ($flash): ?>
            <div class="flash-message <?= $flash['type'] ?>" style="margin-bottom: 20px;">
                <?= $flash['type'] === 'success' ? '✅' : '❌' ?>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="tabs" style="display: flex; gap: 16px; border-bottom: 1px solid var(--glass-border); margin-bottom: 24px;">
            <button class="tab-btn active" onclick="switchTab('tab-profil')" style="background: none; border: none; border-bottom: 2px solid var(--gold-400); color: var(--gold-400); padding: 8px 16px; cursor: pointer; font-weight: 600;">Edit Profil</button>
            <button class="tab-btn" onclick="switchTab('tab-password')" style="background: none; border: none; border-bottom: 2px solid transparent; color: var(--text-muted); padding: 8px 16px; cursor: pointer; font-weight: 600;">Ubah Password</button>
        </div>

        <!-- Tab 1: Profil & Edit -->
        <div id="tab-profil" class="tab-content" style="display: block;">
            <form action="<?= BASE_URL ?>/proses/profil.php" method="POST">
                <input type="hidden" name="aksi" value="update_profil">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>NIP</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['nip']) ?>" readonly style="background: var(--bg-body);">
                        <small style="color: var(--text-muted);">NIP tidak dapat diubah</small>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="Belum ada email">
                            <?php if (!empty($user['email']) && empty($user['email_verified_at'])): ?>
                                <span class="badge badge-warning" style="white-space: nowrap;">⚠️ Belum Verifikasi</span>
                            <?php elseif (!empty($user['email']) && !empty($user['email_verified_at'])): ?>
                                <span class="badge badge-success" style="white-space: nowrap;">✅ Terverifikasi</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($user['email']) && empty($user['email_verified_at'])): ?>
                            <small style="display:block; margin-top: 4px;">
                                <a href="<?= BASE_URL ?>/proses/profil.php?aksi=kirim_verifikasi" class="text-gold" style="text-decoration:none;">Kirim Ulang Link Verifikasi</a>
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Pangkat / Golongan</label>
                        <input type="text" name="pangkat" class="form-control" value="<?= htmlspecialchars($user['pangkat'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Status Pegawai</label>
                        <select name="status_pegawai" class="form-control">
                            <option value="PNS" <?= $user['status_pegawai'] === 'PNS' ? 'selected' : '' ?>>PNS / CPNS</option>
                            <option value="PPPK" <?= $user['status_pegawai'] === 'PPPK' ? 'selected' : '' ?>>PPPK</option>
                            <option value="Hakim" <?= $user['status_pegawai'] === 'Hakim' ? 'selected' : '' ?>>Hakim</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nomor Telepon (Opsional)</label>
                        <input type="text" name="no_telp" class="form-control" value="<?= htmlspecialchars($user['no_telp'] ?? '') ?>" placeholder="Misal: 08123456789">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Alamat Rumah (Opsional)</label>
                        <textarea name="alamat" class="form-control" rows="2" placeholder="Masukkan alamat lengkap"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Atasan Langsung (Untuk Persetujuan Cuti)</label>
                        <select name="atasan_id" class="form-control">
                            <option value="">-- Pilih Atasan Langsung --</option>
                            <?php foreach ($daftarAtasan as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= $user['atasan_id'] == $a['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['nama']) ?> (<?= htmlspecialchars($a['jabatan']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--text-muted);">Pilih atasan langsung Anda (Misal: Staf ke Kasubbag, Kasubbag ke Sekretaris/Ketua)</small>
                    </div>
                </div>

                <div style="margin-top: 24px; text-align: right;">
                    <button type="submit" class="btn btn-primary">Simpan Profil</button>
                </div>
            </form>
        </div>

        <!-- Tab 2: Ubah Password -->
        <div id="tab-password" class="tab-content" style="display: none;">
            <?php if (empty($user['email']) || empty($user['email_verified_at'])): ?>
                <div class="form-info" style="border-left: 4px solid var(--gold-400);">
                    ⚠️ <strong>Verifikasi Email Diperlukan</strong><br>
                    Untuk menjaga keamanan akun Anda, Anda harus menambahkan email di tab <strong>Edit Profil</strong> dan melakukan <strong>Verifikasi Email</strong> sebelum dapat mengubah password.
                </div>
            <?php else: ?>
                <form action="<?= BASE_URL ?>/proses/profil.php" method="POST" id="form-password">
                    <input type="hidden" name="aksi" value="ubah_password_verified">
                    
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" id="password_baru" name="password_baru" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
                    </div>

                    <div class="form-group">
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control" required minlength="6" placeholder="Ketik ulang password baru">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Perbarui Password</button>
                </form>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function switchTab(tabId) {
    // Hide all contents
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    
    // Reset all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.style.borderBottom = '2px solid transparent';
        btn.style.color = 'var(--text-muted)';
    });

    // Show selected content
    document.getElementById(tabId).style.display = 'block';

    // Highlight selected button
    const activeBtn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick').includes(tabId));
    if (activeBtn) {
        activeBtn.style.borderBottom = '2px solid var(--gold-400)';
        activeBtn.style.color = 'var(--gold-400)';
    }
}

const formPassword = document.getElementById('form-password');
if (formPassword) {
    formPassword.addEventListener('submit', function(e) {
        const pwd1 = document.getElementById('password_baru').value;
        const pwd2 = document.getElementById('konfirmasi_password').value;
        if (pwd1 !== pwd2) {
            e.preventDefault();
            alert('Konfirmasi password tidak cocok!');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

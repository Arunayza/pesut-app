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

<div style="max-width: 800px; margin: 0 auto; width: 100%;">
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

    <div class="card" style="width: 100%;">
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
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <input type="email" name="email" id="input-email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="Belum ada email" style="margin-bottom:0; max-width: 250px;">
                            <?php if (!empty($user['email']) && empty($user['email_verified_at'])): ?>
                                <span class="badge badge-warning" id="badge-verif-status" style="white-space: nowrap;">⚠️ Belum Verifikasi</span>
                            <?php elseif (!empty($user['email']) && !empty($user['email_verified_at'])): ?>
                                <span class="badge badge-success" id="badge-verif-status" style="white-space: nowrap;">✅ Terverifikasi</span>
                            <?php endif; ?>
                            <?php if (!empty($user['email'])): ?>
                                <button type="button" class="btn btn-danger btn-sm" id="btn-lepas-email" style="padding: 6px 12px; font-size: 12px; border-radius: 6px;">🗑️ Lepas Tautan</button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- OTP Section -->
                        <div id="otp-section" style="margin-top: 12px; <?= (!empty($user['email']) && empty($user['email_verified_at'])) ? 'display:block;' : 'display:none;' ?>">
                            <div style="display:flex; gap:8px;">
                                <button type="button" class="btn btn-secondary btn-sm" id="btn-kirim-otp">Kirim Kode OTP</button>
                                <div id="otp-input-group" style="display:none; gap:8px;">
                                    <input type="text" id="input-otp" class="form-control" placeholder="123456" maxlength="6" style="margin-bottom:0; width: 120px; text-align:center; letter-spacing: 4px; font-weight: bold;">
                                    <button type="button" class="btn btn-primary btn-sm" id="btn-verif-otp">Verifikasi</button>
                                </div>
                            </div>
                            <small id="otp-msg" style="color: var(--text-muted); display:block; margin-top:4px;"></small>
                        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnKirimOTP = document.getElementById('btn-kirim-otp');
    const inputEmail = document.getElementById('input-email');
    const otpInputGroup = document.getElementById('otp-input-group');
    const inputOtp = document.getElementById('input-otp');
    const btnVerifOtp = document.getElementById('btn-verif-otp');
    const otpMsg = document.getElementById('otp-msg');
    const otpSection = document.getElementById('otp-section');
    const badgeStatus = document.getElementById('badge-verif-status');

    let originalEmail = inputEmail.value;

    inputEmail.addEventListener('input', function() {
        if (inputEmail.value !== '' && inputEmail.value !== originalEmail) {
            otpSection.style.display = 'block';
            otpInputGroup.style.display = 'none';
            btnKirimOTP.style.display = 'block';
            if (badgeStatus) badgeStatus.style.display = 'none';
        } else if (inputEmail.value === '') {
            otpSection.style.display = 'none';
            if (badgeStatus) badgeStatus.style.display = 'none';
        }
    });

    btnKirimOTP.addEventListener('click', async function() {
        const emailVal = inputEmail.value.trim();
        if(!emailVal) return alert('Email tidak boleh kosong!');
        
        btnKirimOTP.textContent = 'Mengirim...';
        btnKirimOTP.disabled = true;

        try {
            const formData = new FormData();
            formData.append('aksi', 'kirim_otp_ajax');
            formData.append('email', emailVal);
            
            const r = await fetch('<?= BASE_URL ?>/proses/profil.php', { method: 'POST', body: formData });
            const res = await r.json();
            
            if (res.status === 'success') {
                otpMsg.textContent = res.message;
                otpMsg.style.color = 'var(--green-500)';
                btnKirimOTP.style.display = 'none';
                otpInputGroup.style.display = 'flex';
                // Update original email to current so we know it's saved
                originalEmail = emailVal;
            } else {
                otpMsg.textContent = res.message;
                otpMsg.style.color = 'var(--red-500)';
                btnKirimOTP.textContent = 'Kirim Ulang Kode OTP';
                btnKirimOTP.disabled = false;
            }
        } catch(e) {
            otpMsg.textContent = 'Terjadi kesalahan sistem.';
            otpMsg.style.color = 'var(--red-500)';
            btnKirimOTP.textContent = 'Kirim Ulang Kode OTP';
            btnKirimOTP.disabled = false;
        }
    });

    btnVerifOtp.addEventListener('click', async function() {
        const otpVal = inputOtp.value.trim();
        if(!otpVal) return alert('Masukkan kode OTP!');
        
        btnVerifOtp.textContent = 'Memverifikasi...';
        btnVerifOtp.disabled = true;

        try {
            const formData = new FormData();
            formData.append('aksi', 'verif_otp_ajax');
            formData.append('otp', otpVal);
            
            const r = await fetch('<?= BASE_URL ?>/proses/profil.php', { method: 'POST', body: formData });
            const res = await r.json();
            
            if (res.status === 'success') {
                otpSection.innerHTML = '<span class="badge badge-success">✅ Email Berhasil Diverifikasi!</span>';
                setTimeout(() => window.location.reload(), 1500);
            } else {
                otpMsg.textContent = res.message;
                otpMsg.style.color = 'var(--red-500)';
                btnVerifOtp.textContent = 'Verifikasi';
                btnVerifOtp.disabled = false;
            }
        } catch(e) {
            otpMsg.textContent = 'Terjadi kesalahan sistem.';
            otpMsg.style.color = 'var(--red-500)';
            btnVerifOtp.textContent = 'Verifikasi';
            btnVerifOtp.disabled = false;
        }
    });

    const btnLepasEmail = document.getElementById('btn-lepas-email');
    if (btnLepasEmail) {
        btnLepasEmail.addEventListener('click', async function() {
            if (confirm('Yakin ingin melepas tautan email ini? Jika dilepas, Anda tidak akan bisa mengubah password sampai menautkan email baru.')) {
                btnLepasEmail.disabled = true;
                btnLepasEmail.textContent = 'Memproses...';
                try {
                    const formData = new FormData();
                    formData.append('aksi', 'lepas_email_ajax');
                    const r = await fetch('<?= BASE_URL ?>/proses/profil.php', { method: 'POST', body: formData });
                    const res = await r.json();
                    if (res.status === 'success') {
                        window.location.reload();
                    } else {
                        alert(res.message);
                        btnLepasEmail.textContent = '🗑️ Lepas Tautan';
                        btnLepasEmail.disabled = false;
                    }
                } catch(e) {
                    alert('Terjadi kesalahan sistem.');
                    btnLepasEmail.textContent = '🗑️ Lepas Tautan';
                    btnLepasEmail.disabled = false;
                }
            }
        });
    }
});
</script>

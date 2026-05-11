<?php
/**
 * Tambah Pegawai Baru (Admin)
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

$pageTitle = 'Tambah Pegawai Baru';

// Ambil daftar atasan (untuk pilihan atasan_id)
$stmt = $pdo->query("SELECT id, nama, jabatan FROM users WHERE aktif = 1 ORDER BY nama ASC");
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="card" style="margin: 0 auto; max-width: 1200px;">
    <div class="card-header">
        <h3>➕ Form Tambah Pegawai Baru (Multi-Add)</h3>
    </div>
    <div class="card-body">
        <div class="form-info warning" style="margin-bottom: 20px;">
            ℹ️ Password default untuk pegawai baru adalah: <strong>password123</strong>. Pegawai bisa mengubahnya nanti. Saldo cuti akan digenerate otomatis saat pegawai pertama kali login atau diajukan cuti.
        </div>

        <form action="<?= BASE_URL ?>/proses/tambah_user.php" method="POST" id="form-tambah-pegawai">
            <div id="pegawai-list" style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
                <!-- Initial Card -->
                <div class="pegawai-card" style="background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 12px; padding: 20px; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px; margin-bottom: 16px;">
                        <h4 style="margin: 0; font-size: 14px; font-weight: 700; color: var(--text-primary);">Pegawai #<span class="pgw-number">1</span></h4>
                        <button type="button" class="btn btn-danger btn-sm btn-hapus-baris" style="padding: 4px 10px; font-size: 11px;">🗑️ Hapus</button>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>NIP <span style="color:red">*</span></label>
                            <input type="text" name="nip[]" class="form-control nip-input" required placeholder="Masukkan NIP 18 digit">
                        </div>
                        <div class="form-group">
                            <label>Nama <span style="color:red">*</span></label>
                            <input type="text" name="nama[]" class="form-control" required placeholder="Nama Lengkap & Gelar">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 100px 150px 1fr; gap: 16px; margin-bottom: 16px;">
                        <div class="form-group" style="margin:0;">
                            <label>L/P <span style="color:red">*</span></label>
                            <select name="jenis_kelamin[]" class="form-control no-tomselect" required>
                                <option value="L">L</option>
                                <option value="P">P</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Status <span style="color:red">*</span></label>
                            <select name="status_pegawai[]" class="form-control no-tomselect status-input" required>
                                <option value="PNS">PNS</option>
                                <option value="PPPK">PPPK</option>
                                <option value="Hakim">Hakim</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Jabatan <span style="color:red">*</span></label>
                            <input type="text" name="jabatan[]" class="form-control" required placeholder="Contoh: Analis Perkara Peradilan">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1.5fr; gap: 16px;">
                        <div class="form-group" style="margin:0;">
                            <label>Pangkat/Gol</label>
                            <input type="text" name="pangkat[]" class="form-control" placeholder="Contoh: Penata Muda (III/a)">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Mulai Kerja <span style="color:red">*</span></label>
                            <input type="date" name="tgl_mulai_kerja[]" class="form-control tgl-input" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Role Sistem <span style="color:red">*</span></label>
                            <select name="role[]" class="form-control tomselect-init" required>
                                <?php foreach (ROLE_LABELS as $roleVal => $roleLabel): ?>
                                <option value="<?= $roleVal ?>" <?= $roleVal === 'staf_umk' ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <button type="button" class="btn btn-secondary" id="btn-tambah-baris">➕ Tambah Pegawai Lagi</button>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--glass-border); margin: 24px 0;">

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="<?= BASE_URL ?>/pages/kelola_user.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Semua Pegawai</button>
            </div>
        </form>
    </div>
</div>

<!-- Template for Cloning -->
<template id="pegawai-template">
    <div class="pegawai-card" style="background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 12px; padding: 20px; position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px; margin-bottom: 16px;">
            <h4 style="margin: 0; font-size: 14px; font-weight: 700; color: var(--text-primary);">Pegawai #<span class="pgw-number">X</span></h4>
            <button type="button" class="btn btn-danger btn-sm btn-hapus-baris" style="padding: 4px 10px; font-size: 11px;">🗑️ Hapus</button>
        </div>
        
        <div class="form-grid-2">
            <div class="form-group">
                <label>NIP <span style="color:red">*</span></label>
                <input type="text" name="nip[]" class="form-control nip-input" required placeholder="Masukkan NIP 18 digit">
            </div>
            <div class="form-group">
                <label>Nama <span style="color:red">*</span></label>
                <input type="text" name="nama[]" class="form-control" required placeholder="Nama Lengkap & Gelar">
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 100px 150px 1fr; gap: 16px; margin-bottom: 16px;">
            <div class="form-group" style="margin:0;">
                <label>L/P <span style="color:red">*</span></label>
                <select name="jenis_kelamin[]" class="form-control no-tomselect" required>
                    <option value="L">L</option>
                    <option value="P">P</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Status <span style="color:red">*</span></label>
                <select name="status_pegawai[]" class="form-control no-tomselect status-input" required>
                    <option value="PNS">PNS</option>
                    <option value="PPPK">PPPK</option>
                    <option value="Hakim">Hakim</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Jabatan <span style="color:red">*</span></label>
                <input type="text" name="jabatan[]" class="form-control" required placeholder="Contoh: Analis Perkara Peradilan">
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1.5fr; gap: 16px;">
            <div class="form-group" style="margin:0;">
                <label>Pangkat/Gol</label>
                <input type="text" name="pangkat[]" class="form-control" placeholder="Contoh: Penata Muda (III/a)">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Mulai Kerja <span style="color:red">*</span></label>
                <input type="date" name="tgl_mulai_kerja[]" class="form-control tgl-input" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Role Sistem <span style="color:red">*</span></label>
                <select name="role[]" class="form-control tomselect-init" required>
                    <?php foreach (ROLE_LABELS as $roleVal => $roleLabel): ?>
                    <option value="<?= $roleVal ?>" <?= $roleVal === 'staf_umk' ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const listWrap = document.getElementById('pegawai-list');
    const btnTambah = document.getElementById('btn-tambah-baris');
    const template = document.getElementById('pegawai-template');

    // Init TomSelect for the first card
    if (typeof TomSelect !== 'undefined') {
        const firstTom = listWrap.querySelector('.tomselect-init');
        if (firstTom) {
            new TomSelect(firstTom, { create: false, sortField: { field: "text", direction: "asc" } });
        }
    }

    function updateNumbers() {
        const cards = listWrap.querySelectorAll('.pegawai-card');
        cards.forEach((card, idx) => {
            const numEl = card.querySelector('.pgw-number');
            if (numEl) numEl.textContent = idx + 1;
        });
    }

    // Tambah card baru
    btnTambah.addEventListener('click', function() {
        const node = template.content.cloneNode(true);
        listWrap.appendChild(node);
        updateNumbers();

        // Init TomSelect on the newly added element
        const newCard = listWrap.lastElementChild;
        const newSel = newCard.querySelector('.tomselect-init');
        if (newSel && typeof TomSelect !== 'undefined') {
            new TomSelect(newSel, { create: false, sortField: { field: "text", direction: "asc" } });
        }
    });

    // Hapus card
    listWrap.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-hapus-baris')) {
            const cards = listWrap.querySelectorAll('.pegawai-card');
            if (cards.length > 1) {
                e.target.closest('.pegawai-card').remove();
                updateNumbers();
            } else {
                alert('Minimal harus ada 1 pegawai untuk ditambahkan!');
            }
        }
    });

    // === Auto-fill tgl_mulai_kerja dari NIP (PNS & Hakim) ===
    function parseTglMulaiFromNip(nip) {
        const clean = nip.replace(/\D/g, '');
        if (clean.length < 14) return null;
        const year  = clean.substring(8, 12);
        const month = clean.substring(12, 14);
        const y = parseInt(year, 10);
        const m = parseInt(month, 10);
        if (y < 1950 || y > 2100 || m < 1 || m > 12) return null;
        return `${year}-${month.padStart(2, '0')}-01`;
    }

    function tryAutoFillRow(card) {
        if (!card) return;
        const nipEl    = card.querySelector('.nip-input');
        const statusEl = card.querySelector('.status-input');
        const tglEl    = card.querySelector('.tgl-input');
        if (!nipEl || !statusEl || !tglEl) return;
        
        const status = statusEl.value;
        if (status !== 'PNS' && status !== 'Hakim') return;
        
        const tgl = parseTglMulaiFromNip(nipEl.value);
        if (tgl) {
            tglEl.value = tgl;
            tglEl.style.transition = 'border-color 0.3s';
            tglEl.style.borderColor = 'var(--orange-400)';
            setTimeout(() => tglEl.style.borderColor = '', 1500);
        }
    }

    // Event delegation: tangkap input NIP dan perubahan status di semua card
    listWrap.addEventListener('input', function(e) {
        if (e.target.classList.contains('nip-input')) {
            tryAutoFillRow(e.target.closest('.pegawai-card'));
        }
    });
    listWrap.addEventListener('blur', function(e) {
        if (e.target.classList.contains('nip-input')) {
            tryAutoFillRow(e.target.closest('.pegawai-card'));
        }
    }, true);
    listWrap.addEventListener('change', function(e) {
        if (e.target.classList.contains('status-input')) {
            tryAutoFillRow(e.target.closest('.pegawai-card'));
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

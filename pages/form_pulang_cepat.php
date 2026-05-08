<?php
/**
 * Form Pengajuan Pulang Cepat / Datang Terlambat
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireLogin();

$pageTitle = 'Izin Waktu';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
.form-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    backdrop-filter: blur(12px);
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
}
.form-card-header {
    padding: 24px 28px 18px;
    border-bottom: 1px solid var(--glass-border);
    background: linear-gradient(135deg, rgba(245,158,11,0.12) 0%, rgba(239,68,68,0.08) 100%);
    display: flex; align-items: center; gap: 14px;
}
.form-card-header-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    box-shadow: 0 4px 12px rgba(245,158,11,0.35); flex-shrink: 0;
}
.form-card-header h2 { font-size: 18px; font-weight: 700; color: var(--text-primary); margin: 0; }
.form-card-header p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
.form-card-body { padding: 24px 28px; }

.pegawai-strip {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--glass-border);
    border-radius: 10px; margin-bottom: 20px;
}
.pegawai-strip-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg,#f59e0b,#ef4444);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.pegawai-strip-name { font-weight: 600; font-size: 13px; color: var(--text-primary); }
.pegawai-strip-meta { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

.form-section { margin-bottom: 20px; }
.form-section-title {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1.5px; color: var(--text-muted);
    margin-bottom: 12px;
    display: flex; align-items: center; gap: 8px;
}
.form-section-title::after { content: ''; flex: 1; height: 1px; background: var(--glass-border); }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 600px) { .form-grid-2 { grid-template-columns: 1fr; } }

/* Jam kerja info */
.jam-info-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;
}
.jam-info-item {
    padding: 12px 14px;
    background: rgba(245,158,11,0.08);
    border: 1px solid rgba(245,158,11,0.2);
    border-radius: 10px;
}
.jam-info-item .label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #f59e0b; margin-bottom: 3px; }
.jam-info-item .value { font-size: 14px; font-weight: 700; color: var(--text-primary); }
@media (max-width: 500px) { .jam-info-grid { grid-template-columns: 1fr; } }

/* Visual tipe selector */
.tipe-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
.tipe-option { position: relative; cursor: pointer; }
.tipe-option input[type=radio] { position: absolute; opacity: 0; width: 0; height: 0; }
.tipe-option-label {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 6px; padding: 16px 10px;
    border: 2px solid var(--glass-border);
    border-radius: 10px;
    background: rgba(255,255,255,0.03);
    cursor: pointer; transition: all 0.2s; text-align: center;
}
.tipe-option input:checked + .tipe-option-label {
    border-color: #f59e0b;
    background: rgba(245,158,11,0.1);
    box-shadow: 0 0 0 3px rgba(245,158,11,0.15);
}
.tipe-option-label .tipe-icon { font-size: 24px; }
.tipe-option-label .tipe-text { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.tipe-option-label .tipe-sub { font-size: 11px; color: var(--text-muted); }
@media (max-width: 480px) { .tipe-selector { grid-template-columns: 1fr; } }

/* Preview card */
.preview-card {
    background: rgba(245,158,11,0.06);
    border: 1px solid rgba(245,158,11,0.2);
    border-radius: 10px; padding: 14px 18px; margin: 12px 0; display: none;
}
.preview-card.show { display: block; }
.preview-card h4 {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1px; color: #f59e0b; margin: 0 0 10px;
}
.preview-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 5px 0; border-bottom: 1px solid rgba(245,158,11,0.1); font-size: 12px;
}
.preview-row:last-child { border-bottom: none; }
.preview-row .label { color: var(--text-muted); }
.preview-row .value { font-weight: 600; color: var(--text-primary); }
.preview-row .value.error { color: #ef4444; }

/* Upload opsional */
.upload-optional {
    border: 1px dashed rgba(245,158,11,0.25);
    border-radius: 10px; background: rgba(245,158,11,0.03);
    padding: 14px 16px;
}
.upload-optional-toggle {
    display: flex; align-items: center; gap: 8px; cursor: pointer;
    font-size: 13px; color: var(--text-muted); user-select: none;
}
.upload-optional-toggle input[type=checkbox] { accent-color: #f59e0b; width: 16px; height: 16px; }
.upload-content { margin-top: 12px; display: none; }
.upload-content.show { display: block; }

.form-submit-area {
    display: flex; gap: 10px; justify-content: flex-end;
    padding-top: 20px; border-top: 1px solid var(--glass-border);
}
</style>

<div class="form-card">
    <div class="form-card-header">
        <div class="form-card-header-icon">⏰</div>
        <div>
            <h2>Form Izin Waktu</h2>
            <p>Pengajuan datang terlambat atau pulang lebih awal</p>
        </div>
    </div>
    <div class="form-card-body">

        <!-- Pegawai Info -->
        <div class="pegawai-strip">
            <div class="pegawai-strip-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
            <div>
                <div class="pegawai-strip-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                <div class="pegawai-strip-meta">NIP: <?= htmlspecialchars($_SESSION['nip']) ?> &nbsp;•&nbsp; <?= htmlspecialchars($_SESSION['jabatan']) ?></div>
            </div>
        </div>

        <!-- Jam Kerja Info -->
        <div class="jam-info-grid">
            <div class="jam-info-item">
                <div class="label">🕗 Jam Masuk</div>
                <div class="value"><?= JAM_MASUK ?></div>
            </div>
            <div class="jam-info-item">
                <div class="label">🕔 Jam Pulang Resmi</div>
                <div class="value" style="font-size:12px;">
                    Sen–Kam&nbsp;<?= JAM_PULANG_SENIN_KAMIS ?>&nbsp;|&nbsp;Jum&nbsp;<?= JAM_PULANG_JUMAT ?>
                </div>
            </div>
        </div>

        <form action="<?= BASE_URL ?>/proses/ajukan_pulang_cepat.php" method="POST" id="form-pc" enctype="multipart/form-data">

            <!-- Jenis Izin -->
            <div class="form-section">
                <div class="form-section-title">🎯 Jenis Izin Waktu</div>
                <div class="tipe-selector">
                    <label class="tipe-option">
                        <input type="radio" name="tipe_izin_waktu" value="datang_terlambat" checked>
                        <div class="tipe-option-label">
                            <span class="tipe-icon">🚶</span>
                            <span class="tipe-text">Datang Terlambat</span>
                            <span class="tipe-sub">Masuk setelah <?= JAM_MASUK ?></span>
                        </div>
                    </label>
                    <label class="tipe-option">
                        <input type="radio" name="tipe_izin_waktu" value="pulang_cepat">
                        <div class="tipe-option-label">
                            <span class="tipe-icon">🏃</span>
                            <span class="tipe-text">Pulang Lebih Awal</span>
                            <span class="tipe-sub">Pulang sebelum jam resmi</span>
                        </div>
                    </label>
                </div>
                <!-- Hidden select kompatibel JS -->
                <select id="tipe_izin_waktu" name="tipe_izin_waktu_hidden" style="display:none;"></select>
            </div>

            <!-- Waktu -->
            <div class="form-section">
                <div class="form-section-title">📅 Waktu</div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="tanggal_pulang">Tanggal</label>
                        <input type="date" id="tanggal_pulang" name="tanggal_pulang" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="jam_pulang_diajukan" id="label_jam_diajukan">⏰ Jam Kedatangan</label>
                        <input type="time" id="jam_pulang_diajukan" name="jam_pulang_diajukan" class="form-control" required>
                    </div>
                </div>

                <div class="preview-card" id="pc-preview">
                    <h4>📊 Hasil Perhitungan</h4>
                    <div class="preview-row">
                        <span class="label">Hari</span>
                        <span class="value" id="preview-hari">—</span>
                    </div>
                    <div class="preview-row">
                        <span class="label" id="label_jam_resmi">Jam Masuk Resmi</span>
                        <span class="value" id="preview-jam-resmi">—</span>
                    </div>
                    <div class="preview-row">
                        <span class="label">Selisih Waktu</span>
                        <span class="value" id="preview-selisih">—</span>
                    </div>
                    <div class="preview-row">
                        <span class="label">Status</span>
                        <span class="value" id="preview-pc-status">—</span>
                    </div>
                </div>
            </div>

            <!-- Alasan -->
            <div class="form-section">
                <div class="form-section-title">📝 Keterangan</div>
                <div class="form-group">
                    <label for="alasan">Alasan</label>
                    <textarea id="alasan" name="alasan" class="form-control" rows="3"
                        placeholder="Jelaskan alasan datang terlambat / pulang lebih awal..." required></textarea>
                </div>
            </div>

            <!-- Lampiran Opsional (Hanya untuk Pulang Cepat) -->
            <div class="form-section" id="section-lampiran-pc" style="display:none;">
                <div class="form-section-title">📎 Lampiran</div>
                <div class="upload-optional">
                    <label class="upload-optional-toggle">
                        <input type="checkbox" id="toggle-lampiran-pc" onchange="toggleLampiran('lampiran-pc-content', this)">
                        Sertakan lampiran / bukti pendukung (opsional)
                    </label>
                    <div class="upload-content" id="lampiran-pc-content">
                        <input type="file" name="lampiran" id="file-lampiran-pc" class="form-control" accept=".jpg,.jpeg,.png,.pdf"
                            style="margin-top:4px;">
                        <small style="color:var(--text-muted); display:block; margin-top:6px;">Format: JPG, PNG, PDF · Maks 2MB</small>
                    </div>
                </div>
            </div>

            <div class="form-submit-area">
                <a href="<?= BASE_URL ?>/index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" id="btn-submit-pc" class="btn btn-primary" disabled>🚀 Ajukan Izin Waktu</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleLampiran(id, cb) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('show', cb.checked);
}

// Sync radio → hidden select (untuk kompatibilitas JS app.js)
document.querySelectorAll('input[name="tipe_izin_waktu"]').forEach(r => {
    r.addEventListener('change', () => {
        const sel = document.getElementById('tipe_izin_waktu');
        if (sel) sel.value = r.value;
        const isTerlambat = r.value === 'datang_terlambat';
        const lj = document.getElementById('label_jam_diajukan');
        const lr = document.getElementById('label_jam_resmi');
        if (lj) lj.textContent = isTerlambat ? '⏰ Jam Kedatangan' : '⏰ Jam Kepulangan';
        if (lr) lr.textContent = isTerlambat ? 'Jam Masuk Resmi' : 'Jam Pulang Resmi';

        // Toggle lampiran visibility: only show for pulang_cepat
        const lampiranSection = document.getElementById('section-lampiran-pc');
        if (lampiranSection) {
            lampiranSection.style.display = isTerlambat ? 'none' : 'block';
            // Reset lampiran when switching to terlambat
            if (isTerlambat) {
                const fileLampiran = document.getElementById('file-lampiran-pc');
                if (fileLampiran) fileLampiran.value = '';
                const toggleCb = document.getElementById('toggle-lampiran-pc');
                if (toggleCb) { toggleCb.checked = false; }
                const lampiranContent = document.getElementById('lampiran-pc-content');
                if (lampiranContent) lampiranContent.classList.remove('show');
            }
        }

        // Trigger recalc
        const jamDiajukan = document.getElementById('jam_pulang_diajukan');
        if (jamDiajukan) jamDiajukan.dispatchEvent(new Event('change'));
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

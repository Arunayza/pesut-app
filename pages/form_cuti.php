<?php
/**
 * Form Pengajuan Cuti
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireLogin();

$saldo = cekSisaCuti($_SESSION['user_id'], (int) TAHUN_AKTIF, $pdo);

// Ambil data profil
$stmtProfil = $pdo->prepare("SELECT no_telp, alamat, jenis_kelamin, status_pegawai, tgl_mulai_kerja, pangkat FROM users WHERE id = ?");
$stmtProfil->execute([$_SESSION['user_id']]);
$profilUser = $stmtProfil->fetch();

$isPPPK  = ($profilUser['status_pegawai'] ?? '') === 'PPPK';
$isPerempuan = ($profilUser['jenis_kelamin'] ?? '') === 'P';

// Hitung sisa per kategori
$sisaTahunanIni  = max(0, $saldo['jatah_tahunan'] - $saldo['terpakai_tahunan']);
$sisaTahunanLalu = max(0, $saldo['jatah_tahunan_lalu'] - $saldo['terpakai_tahunan_lalu']);
$sisaSakit       = max(0, $saldo['jatah_sakit'] - $saldo['terpakai_sakit']);
$sisaMelahirkan  = max(0, $saldo['jatah_melahirkan'] - $saldo['terpakai_melahirkan']);
$sisaAlasanPenting = max(0, $saldo['jatah_alasan_penting'] - $saldo['terpakai_alasan_penting']);

$pageTitle = 'Pengajuan Cuti';
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
    background: linear-gradient(135deg, rgba(99,102,241,0.12) 0%, rgba(139,92,246,0.08) 100%);
    display: flex; align-items: center; gap: 14px;
}
.form-card-header-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    box-shadow: 0 4px 12px rgba(99,102,241,0.35);
    flex-shrink: 0;
}
.form-card-header h2 { font-size: 18px; font-weight: 700; color: var(--text-primary); margin: 0; }
.form-card-header p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
.form-card-body { padding: 24px 28px; }

/* Pegawai strip */
.pegawai-strip {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--glass-border);
    border-radius: 10px; margin-bottom: 20px;
}
.pegawai-strip-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg,#6366f1,#8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.pegawai-strip-name { font-weight: 600; font-size: 13px; color: var(--text-primary); }
.pegawai-strip-meta { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

/* Saldo pills */
.saldo-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
.saldo-pill {
    display: flex; align-items: center; gap: 6px;
    padding: 8px 14px;
    background: rgba(99,102,241,0.08);
    border: 1px solid rgba(99,102,241,0.2);
    border-radius: 50px; font-size: 12px; color: var(--text-primary);
}
.saldo-pill .num { font-size: 16px; font-weight: 800; color: #6366f1; line-height: 1; }
.saldo-pill.warn .num { color: #f59e0b; }
.saldo-pill.danger .num { color: #ef4444; }
.saldo-pill.muted { background: rgba(255,255,255,0.04); border-color: var(--glass-border); }
.saldo-pill.muted .num { color: var(--text-muted); }

/* Section */
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

/* Preview card */
.preview-card {
    background: rgba(99,102,241,0.06);
    border: 1px solid rgba(99,102,241,0.2);
    border-radius: 10px; padding: 14px 18px; margin: 12px 0;
    display: none;
}
.preview-card.show { display: block; }
.preview-card h4 {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1px; color: #6366f1; margin: 0 0 10px;
}
.preview-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 5px 0; border-bottom: 1px solid rgba(99,102,241,0.1);
    font-size: 12px;
}
.preview-row:last-child { border-bottom: none; }
.preview-row .label { color: var(--text-muted); }
.preview-row .value { font-weight: 600; color: var(--text-primary); }
.preview-row .value.error { color: #ef4444; }

/* TTD area — compact */
.ttd-compact {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 12px; padding: 16px;
    display: flex; flex-direction: column; gap: 10px;
}
.ttd-compact-label {
    font-size: 12px; font-weight: 600; color: var(--text-primary);
    display: flex; align-items: center; gap: 6px;
}
.ttd-compact-hint { font-size: 11px; color: var(--text-muted); }
.ttd-canvas-wrap {
    border: 2px dashed rgba(99,102,241,0.35);
    border-radius: 8px; background: #fff; overflow: hidden;
    max-width: 380px; /* limit width so it doesn't stretch full page */
    margin: 0 auto; width: 100%;
}
.ttd-canvas-wrap canvas {
    display: block; width: 100%; height: 120px;
    cursor: crosshair; touch-action: none;
    background-color: #fff;
    background-image:
        linear-gradient(to right, transparent 49.5%, rgba(0,0,0,0.1) 49.5%, rgba(0,0,0,0.1) 50.5%, transparent 50.5%),
        linear-gradient(to bottom, transparent 60%, rgba(0,0,0,0.1) 60%, rgba(0,0,0,0.1) 62%, transparent 62%);
}
.ttd-toolbar { display: flex; gap: 6px; justify-content: center; }

/* Upload opsional */
.upload-optional {
    border: 1px dashed rgba(99,102,241,0.25);
    border-radius: 10px; background: rgba(99,102,241,0.03);
    padding: 14px 16px;
}
.upload-optional-toggle {
    display: flex; align-items: center; gap: 8px; cursor: pointer;
    font-size: 13px; color: var(--text-muted); user-select: none;
}
.upload-optional-toggle input[type=checkbox] { accent-color: #6366f1; width: 16px; height: 16px; }
.upload-content { margin-top: 12px; display: none; }
.upload-content.show { display: block; }

/* Submit */
.form-submit-area {
    display: flex; gap: 10px; justify-content: flex-end;
    padding-top: 20px; border-top: 1px solid var(--glass-border); margin-top: 4px;
}
</style>

<a href="<?= BASE_URL ?>/dashboard_v2.php" class="btn-back-dashboard" style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); text-decoration: none; font-size: 13px; font-weight: 600; margin-bottom: 16px; padding: 6px 14px; border-radius: 8px; transition: all 0.2s; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.03);">
    ← Kembali ke Dashboard
</a>
<div class="form-card">
    <div class="form-card-header">
        <div class="form-card-header-icon">🏖️</div>
        <div>
            <h2>Form Pengajuan Cuti</h2>
            <p>Isi formulir dengan lengkap dan benar sebelum mengajukan</p>
        </div>
    </div>
    <div class="form-card-body">

        <!-- Pegawai Info Card -->
        <div class="pegawai-strip" style="flex-direction: column; align-items: stretch; gap: 0; padding: 0; overflow: hidden;">
            <div style="display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: linear-gradient(135deg, rgba(99,102,241,0.08) 0%, rgba(139,92,246,0.05) 100%); border-bottom: 1px solid var(--glass-border);">
                <div class="pegawai-strip-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
                <div>
                    <div class="pegawai-strip-name" style="font-size: 14px;"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                    <div class="pegawai-strip-meta"><?= htmlspecialchars($profilUser['status_pegawai'] ?? 'PNS') ?> — <?= htmlspecialchars($_SESSION['jabatan']) ?></div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0;">
                <div style="padding: 10px 18px; border-bottom: 1px solid var(--glass-border); border-right: 1px solid var(--glass-border);">
                    <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 2px;">NIP</div>
                    <div style="font-size: 12px; font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($_SESSION['nip']) ?></div>
                </div>
                <div style="padding: 10px 18px; border-bottom: 1px solid var(--glass-border);">
                    <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 2px;">Pangkat/Gol.</div>
                    <div style="font-size: 12px; font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($profilUser['pangkat'] ?? '-') ?></div>
                </div>
                <div style="padding: 10px 18px; border-right: 1px solid var(--glass-border);">
                    <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 2px;">Masa Kerja</div>
                    <div style="font-size: 12px; font-weight: 600; color: var(--text-primary);"><?= hitungMasaKerja($profilUser['tgl_mulai_kerja'] ?? '2020-01-01') ?></div>
                </div>
                <div style="padding: 10px 18px;">
                    <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 2px;">Unit Kerja</div>
                    <div style="font-size: 12px; font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($_SESSION['unit_kerja'] ?? APP_INSTANSI) ?></div>
                </div>
            </div>
        </div>

        <!-- Saldo Kuota Cuti -->
        <?php
        $tahunIni = (int)TAHUN_AKTIF;
        $tahunLalu = $tahunIni - 1;
        ?>
        <div class="form-section" style="margin-top: 4px;">
            <div class="form-section-title">📊 Sisa Kuota Cuti</div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px;">
                <?php if (!$isPPPK && $sisaTahunanLalu > 0): ?>
                <div style="padding: 12px 14px; background: rgba(99,102,241,0.06); border: 1px solid rgba(99,102,241,0.18); border-radius: 12px;">
                    <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 4px;">📅 Tahunan <?= $tahunLalu ?></div>
                    <div style="display: flex; align-items: baseline; gap: 4px;">
                        <span style="font-size: 22px; font-weight: 800; color: #6366f1; line-height: 1;"><?= $sisaTahunanLalu ?></span>
                        <span style="font-size: 11px; color: var(--text-muted);">hari</span>
                    </div>
                </div>
                <?php endif; ?>
                <div style="padding: 12px 14px; background: rgba(59,130,246,0.06); border: 1px solid rgba(59,130,246,0.18); border-radius: 12px;">
                    <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 4px;">📅 Tahunan <?= $tahunIni ?></div>
                    <div style="display: flex; align-items: baseline; gap: 4px;">
                        <span id="badge-sisa-cuti" style="font-size: 22px; font-weight: 800; color: <?= $sisaTahunanIni <= 3 ? ($sisaTahunanIni == 0 ? '#ef4444' : '#f59e0b') : '#3b82f6' ?>; line-height: 1;"><?= $sisaTahunanIni ?></span>
                        <span style="font-size: 11px; color: var(--text-muted);">hari</span>
                    </div>
                </div>
                <?php if ($sisaSakit > 0): ?>
                <div style="padding: 12px 14px; background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.18); border-radius: 12px;">
                    <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 4px;">🤒 Sakit</div>
                    <div style="display: flex; align-items: baseline; gap: 4px;">
                        <span style="font-size: 22px; font-weight: 800; color: #10b981; line-height: 1;"><?= $sisaSakit ?></span>
                        <span style="font-size: 11px; color: var(--text-muted);">hari</span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($isPerempuan && $sisaMelahirkan > 0): ?>
                <div style="padding: 12px 14px; background: rgba(255,105,180,0.06); border: 1px solid rgba(255,105,180,0.18); border-radius: 12px;">
                    <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 4px;">🤱 Melahirkan</div>
                    <div style="display: flex; align-items: baseline; gap: 4px;">
                        <span style="font-size: 22px; font-weight: 800; color: #ff69b4; line-height: 1;"><?= $sisaMelahirkan ?></span>
                        <span style="font-size: 11px; color: var(--text-muted);">hari</span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!$isPPPK && $sisaAlasanPenting > 0): ?>
                <div style="padding: 12px 14px; background: rgba(245,158,11,0.06); border: 1px solid rgba(245,158,11,0.18); border-radius: 12px;">
                    <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 4px;">📋 Alasan Penting</div>
                    <div style="display: flex; align-items: baseline; gap: 4px;">
                        <span style="font-size: 22px; font-weight: 800; color: #f59e0b; line-height: 1;"><?= $sisaAlasanPenting ?></span>
                        <span style="font-size: 11px; color: var(--text-muted);">hari</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <form action="<?= BASE_URL ?>/proses/simpan_preview.php" method="POST" id="form-cuti" enctype="multipart/form-data">
            <input type="hidden" name="jenis_form" value="cuti">

            <!-- Jenis & Periode -->
            <div class="form-section">
                <div class="form-section-title">📋 Jenis & Periode Cuti</div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label for="tipe_cuti">Jenis Cuti</label>
                    <select name="tipe_cuti" id="tipe_cuti" class="form-control">
                        <option value="tahunan">Cuti Tahunan <?= $tahunIni ?> (sisa <?= $sisaTahunanIni ?> hari)</option>
                        <?php if (!$isPPPK && $sisaTahunanLalu > 0): ?>
                        <option value="tahunan_lalu">Cuti Tahunan <?= $tahunLalu ?> (sisa <?= $sisaTahunanLalu ?> hari)</option>
                        <?php endif; ?>
                        <option value="sakit">Cuti Sakit (sisa <?= $sisaSakit ?> hari)</option>
                        <?php if ($isPerempuan): ?>
                        <option value="melahirkan">Cuti Melahirkan (sisa <?= $sisaMelahirkan ?> hari)</option>
                        <?php endif; ?>
                        <?php if (!$isPPPK): ?>
                        <option value="alasan_penting">Cuti Alasan Penting (sisa <?= $sisaAlasanPenting ?> hari)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="tanggal_mulai">Tanggal Mulai</label>
                        <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="tanggal_selesai">Tanggal Selesai</label>
                        <input type="date" id="tanggal_selesai" name="tanggal_selesai" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="preview-card" id="cuti-preview">
                    <h4>📊 Hasil Perhitungan</h4>
                    <div class="preview-row">
                        <span class="label">Jumlah Hari Kerja</span>
                        <span class="value" id="preview-hari-kerja">—</span>
                    </div>
                    <div class="preview-row" id="preview-tanggal-row" style="display:none; flex-direction:column; align-items:flex-start; gap:6px;">
                        <span class="label">📅 Rincian Tanggal</span>
                        <div id="preview-tanggal-list" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:2px;"></div>
                    </div>
                    <div class="preview-row">
                        <span class="label">Sisa Kuota</span>
                        <span class="value" id="preview-sisa-cuti">—</span>
                    </div>
                    <div class="preview-row">
                        <span class="label">Status</span>
                        <span class="value" id="preview-status">—</span>
                    </div>
                </div>
            </div>

            <!-- Keterangan -->
            <div class="form-section">
                <div class="form-section-title">📝 Keterangan</div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label for="alasan">Alasan Cuti</label>
                    <textarea id="alasan" name="alasan" class="form-control" rows="3"
                        placeholder="Jelaskan alasan pengajuan cuti Anda..." required></textarea>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="telepon">No. Telepon Selama Cuti</label>
                        <input type="text" id="telepon" name="telepon" class="form-control"
                            placeholder="08123456789" value="<?= htmlspecialchars($profilUser['no_telp'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="alamat_cuti">Alamat Selama Cuti</label>
                        <textarea id="alamat_cuti" name="alamat_cuti" class="form-control" rows="2"
                            placeholder="Alamat lengkap selama cuti"><?= htmlspecialchars($profilUser['alamat'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Lampiran Opsional -->
            <div class="form-section">
                <div class="form-section-title">📎 Lampiran</div>
                <div class="upload-optional">
                    <label class="upload-optional-toggle">
                        <input type="checkbox" id="toggle-lampiran-cuti" onchange="toggleLampiran('lampiran-cuti-content', this)">
                        Sertakan lampiran / bukti pendukung (opsional)
                    </label>
                    <div class="upload-content" id="lampiran-cuti-content">
                        <input type="file" name="lampiran" class="form-control" accept=".jpg,.jpeg,.png,.pdf"
                            style="margin-top:4px;">
                        <small style="color:var(--text-muted); display:block; margin-top:6px;">Format: JPG, PNG, PDF · Maks 2MB</small>
                    </div>
                </div>
            </div>



            <div class="form-submit-area">
                <a href="<?= BASE_URL ?>/dashboard_v2.php" class="btn btn-secondary">Batal</a>
                <button type="submit" id="btn-submit-cuti" class="btn btn-primary" disabled>📄 Preview & Ajukan</button>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle lampiran
function toggleLampiran(id, cb) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('show', cb.checked);
}


</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

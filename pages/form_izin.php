<?php
/**
 * Form Pengajuan Izin Keluar Kantor
 * Selalu 1 hari, dalam hitungan jam
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireLogin();

$pageTitle = 'Pengajuan Izin';
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
    background: linear-gradient(135deg, rgba(139,92,246,0.12) 0%, rgba(99,102,241,0.08) 100%);
    display: flex; align-items: center; gap: 14px;
}
.form-card-header-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    box-shadow: 0 4px 12px rgba(139,92,246,0.35); flex-shrink: 0;
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
    background: linear-gradient(135deg,#8b5cf6,#6366f1);
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

.info-banner {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 12px 16px;
    background: rgba(139,92,246,0.08);
    border: 1px solid rgba(139,92,246,0.2);
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 12px; color: var(--text-primary);
    line-height: 1.5;
}
.info-banner .icon { font-size: 16px; flex-shrink: 0; }

.upload-optional {
    border: 1px dashed rgba(139,92,246,0.25);
    border-radius: 10px; background: rgba(139,92,246,0.03);
    padding: 14px 16px;
}
.upload-optional-toggle {
    display: flex; align-items: center; gap: 8px; cursor: pointer;
    font-size: 13px; color: var(--text-muted); user-select: none;
}
.upload-optional-toggle input[type=checkbox] { accent-color: #8b5cf6; width: 16px; height: 16px; }
.upload-content { margin-top: 12px; display: none; }
.upload-content.show { display: block; }

.form-submit-area {
    display: flex; gap: 10px; justify-content: flex-end;
    padding-top: 20px; border-top: 1px solid var(--glass-border);
}
</style>

<div class="form-card">
    <div class="form-card-header">
        <div class="form-card-header-icon">📝</div>
        <div>
            <h2>Form Izin Keluar Kantor</h2>
            <p>Pengajuan izin keluar dalam hitungan jam — satu hari kerja</p>
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

        <!-- Info Banner -->
        <div class="info-banner">
            <span class="icon">ℹ️</span>
            <span>
                Izin keluar kantor berlaku untuk <strong>satu hari</strong> dalam hitungan jam.
                Jika membutuhkan izin lebih dari satu hari, gunakan <strong>Pengajuan Cuti</strong>.
            </span>
        </div>

        <form action="<?= BASE_URL ?>/proses/ajukan_izin.php" method="POST" id="form-izin" enctype="multipart/form-data">

            <!-- Waktu -->
            <div class="form-section">
                <div class="form-section-title">📅 Waktu Izin</div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label for="tanggal_mulai">Tanggal Izin</label>
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-control" required min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="jam_mulai">⏰ Jam Keluar</label>
                        <input type="time" id="jam_mulai" name="jam_mulai" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="jam_selesai">⏰ Jam Kembali</label>
                        <input type="time" id="jam_selesai" name="jam_selesai" class="form-control" required>
                    </div>
                </div>
            </div>

            <!-- Keperluan -->
            <div class="form-section">
                <div class="form-section-title">📝 Keterangan</div>
                <div class="form-group">
                    <label for="alasan">Alasan / Keperluan</label>
                    <textarea id="alasan" name="alasan" class="form-control" rows="3"
                        placeholder="Jelaskan keperluan Anda keluar kantor..." required></textarea>
                </div>
            </div>

            <!-- Lampiran Opsional -->
            <div class="form-section">
                <div class="form-section-title">📎 Lampiran</div>
                <div class="upload-optional">
                    <label class="upload-optional-toggle">
                        <input type="checkbox" id="toggle-lampiran" onchange="toggleLampiran('lampiran-content', this)">
                        Sertakan lampiran / bukti pendukung (opsional)
                    </label>
                    <div class="upload-content" id="lampiran-content">
                        <input type="file" name="lampiran" class="form-control" accept=".jpg,.jpeg,.png,.pdf"
                            style="margin-top:4px;">
                        <small style="color:var(--text-muted); display:block; margin-top:6px;">Format: JPG, PNG, PDF · Maks 2MB</small>
                    </div>
                </div>
            </div>

            <div class="form-submit-area">
                <a href="<?= BASE_URL ?>/index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">🚀 Ajukan Izin</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleLampiran(id, cb) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('show', cb.checked);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

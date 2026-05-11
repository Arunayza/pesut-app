<?php
/**
 * Preview Surat — Render PDF dari Word template (sama persis kayak output final)
 * TTD atasan/pejabat masih kosong. User bisa Edit / Cancel / Ajukan.
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireLogin();

if (empty($_SESSION['preview_data'])) {
    setFlash('error', 'Tidak ada data untuk di-preview. Silakan isi form terlebih dahulu.');
    redirect(BASE_URL . '/dashboard_v2.php');
}

$data = $_SESSION['preview_data'];
$jenis = $data['jenis'];

// Determine back URL
$backUrl = BASE_URL . '/pages/form_cuti.php';
if ($jenis === 'izin') $backUrl = BASE_URL . '/pages/form_izin.php';
if ($jenis === 'pulang_cepat') $backUrl = BASE_URL . '/pages/form_pulang_cepat.php';

// Jenis label
$jenisLabel = 'Cuti';
if ($jenis === 'izin') $jenisLabel = 'Izin Keluar Kantor';
if ($jenis === 'pulang_cepat') {
    $jenisLabel = ($data['tipe_izin_waktu'] ?? '') === 'datang_terlambat' ? 'Datang Terlambat' : 'Pulang Cepat';
}

$pageTitle = 'Preview Surat ' . $jenisLabel;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
    .preview-wrapper {
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Status Banner */
    .preview-banner {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px 20px;
        background: rgba(245, 158, 11, 0.08);
        border: 1px solid rgba(245, 158, 11, 0.25);
        border-radius: 14px;
        margin-bottom: 20px;
    }
    .preview-banner-icon {
        width: 48px; height: 48px;
        background: rgba(245, 158, 11, 0.15);
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px; flex-shrink: 0;
    }
    .preview-banner h3 {
        font-size: 15px; font-weight: 700; 
        color: var(--text-primary); margin: 0 0 2px;
    }
    .preview-banner p {
        font-size: 12px; color: var(--text-muted); margin: 0;
    }

    /* Action Buttons */
    .preview-actions {
        display: flex; gap: 10px; flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .preview-actions .btn {
        padding: 10px 20px; font-weight: 600; border-radius: 10px;
        font-size: 13px; display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-edit-back {
        background: rgba(255,255,255,0.06); border: 1px solid var(--glass-border);
        color: var(--text-primary); cursor: pointer; text-decoration: none;
    }
    .btn-edit-back:hover { background: rgba(255,255,255,0.1); }
    .btn-cancel {
        background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);
        color: #ef4444; cursor: pointer; text-decoration: none;
    }
    .btn-cancel:hover { background: rgba(239, 68, 68, 0.15); }
    .btn-ajukan {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none; color: #fff; cursor: pointer;
        box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
        transition: all 0.2s;
    }
    .btn-ajukan:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 24px rgba(16, 185, 129, 0.4);
    }
    .btn-ajukan:disabled {
        opacity: 0.6; cursor: not-allowed; transform: none;
        box-shadow: none;
    }

    /* PDF Container */
    .pdf-container {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    }
    .pdf-container-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 20px;
        background: rgba(255,255,255,0.03);
        border-bottom: 1px solid var(--glass-border);
    }
    .pdf-container-header h4 {
        font-size: 13px; font-weight: 600;
        color: var(--text-primary); margin: 0;
        display: flex; align-items: center; gap: 8px;
    }
    .pdf-loading {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 80px 20px; gap: 16px;
        color: var(--text-muted);
    }
    .pdf-loading .spinner {
        width: 40px; height: 40px;
        border: 3px solid var(--glass-border);
        border-top-color: var(--gold-400);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .pdf-frame {
        width: 100%;
        min-height: 80vh;
        border: none;
        display: block;
    }

    .pdf-error {
        padding: 40px 20px;
        text-align: center;
        color: var(--text-muted);
        display: none;
    }
    .pdf-error .error-icon { font-size: 48px; margin-bottom: 12px; }
    .pdf-error h4 { font-size: 15px; font-weight: 700; margin: 0 0 6px; color: var(--text-primary); }
    .pdf-error p { font-size: 13px; margin: 0; }

    /* Light mode fixes */
    [data-theme="light"] .btn-edit-back { background: rgba(0,0,0,0.04); }
    [data-theme="light"] .btn-edit-back:hover { background: rgba(0,0,0,0.07); }
</style>

<div class="preview-wrapper">
    <a href="<?= BASE_URL ?>/dashboard_v2.php" class="btn-back-dashboard" style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); text-decoration: none; font-size: 13px; font-weight: 600; margin-bottom: 16px; padding: 6px 14px; border-radius: 8px; transition: all 0.2s; border: 1px solid transparent;">
        ← Kembali ke Dashboard
    </a>

    <!-- Banner -->
    <div class="preview-banner">
        <div class="preview-banner-icon">📄</div>
        <div>
            <h3>Preview Surat <?= htmlspecialchars($jenisLabel) ?></h3>
            <p>Dokumen ini belum diajukan. Periksa data Anda, lalu klik "Ajukan Sekarang" untuk mengirim.</p>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="preview-actions">
        <a href="<?= $backUrl ?>" class="btn btn-edit-back">✏️ Kembali & Edit</a>
        <a href="<?= BASE_URL ?>/proses/batalkan_preview.php" class="btn btn-cancel">✕ Batalkan</a>
        <div style="flex: 1;"></div>
        <button onclick="window.open('<?= BASE_URL ?>/proses/generate_preview_pdf.php', '_blank')" class="btn btn-edit-back">🔗 Buka di Tab Baru</button>
        <form action="<?= BASE_URL ?>/proses/ajukan_dari_preview.php" method="POST" style="display:inline;" id="form-ajukan-top"
              onsubmit="return confirmAjukan(this)">
            <input type="hidden" name="confirm" value="1">
            <input type="hidden" name="ttd_pengaju" class="ttd-data-hidden">
            <button type="submit" class="btn btn-ajukan" id="btn-ajukan">
                ✅ Ajukan Sekarang
            </button>
        </form>
    </div>

    <!-- PDF Viewer -->
    <div class="pdf-container">
        <div class="pdf-container-header">
            <h4>📄 Dokumen Preview — <?= htmlspecialchars($jenisLabel) ?></h4>
            <span style="font-size: 11px; color: var(--text-muted);">Dihasilkan dari template resmi</span>
        </div>

        <!-- Loading state -->
        <div class="pdf-loading" id="pdf-loading">
            <div class="spinner"></div>
            <div style="font-size: 13px; font-weight: 600;">Menghasilkan dokumen preview...</div>
            <div style="font-size: 11px;">Membuka template Word & mengonversi ke PDF. Mohon tunggu beberapa detik.</div>
        </div>

        <!-- PDF iframe -->
        <iframe 
            id="pdf-frame"
            class="pdf-frame" 
            style="display: none;"
            src="<?= BASE_URL ?>/proses/generate_preview_pdf.php?t=<?= time() ?>"
        ></iframe>

        <!-- Error state -->
        <div class="pdf-error" id="pdf-error">
            <div class="error-icon">⚠️</div>
            <h4>Gagal memuat preview</h4>
            <p>Dokumen PDF tidak bisa dihasilkan. Coba refresh halaman atau klik "Buka di Tab Baru".</p>
            <a href="<?= BASE_URL ?>/proses/generate_preview_pdf.php" target="_blank" class="btn btn-secondary" style="margin-top: 16px;">🔗 Coba Buka Langsung</a>
        </div>
    </div>

    <!-- Tanda Tangan Pemohon (Hanya Cuti) -->
    <?php if ($jenis === 'cuti'): ?>
    <div style="background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 16px; padding: 24px; margin-top: 20px; box-shadow: 0 8px 32px rgba(0,0,0,0.18);">
        <h4 style="font-size: 15px; font-weight: 700; color: var(--text-primary); margin: 0 0 16px; display: flex; align-items: center; gap: 8px;">✍️ Tanda Tangan Pemohon</h4>
        <p style="font-size: 13px; color: var(--text-muted); margin: 0 0 16px;">Silakan gambar tanda tangan Anda di kotak berikut sebelum mengajukan cuti.</p>
        <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
            <div style="background: #fff; border: 2px dashed var(--glass-border); border-radius: 12px; width: 100%; max-width: 400px; height: 200px; overflow: hidden; position: relative;">
                <canvas id="ttd-canvas" style="display:block; width:100%; height:100%; touch-action:none;"></canvas>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="undoCanvas()">↩️ Undo</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="clearCanvas()">🗑️ Hapus</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bottom Actions (duplicate for convenience) -->
    <div class="preview-actions" style="margin-top: 20px; justify-content: center;">
        <a href="<?= $backUrl ?>" class="btn btn-edit-back">✏️ Kembali & Edit</a>
        <form action="<?= BASE_URL ?>/proses/ajukan_dari_preview.php" method="POST" style="display:inline;" id="form-ajukan-bottom"
              onsubmit="return confirmAjukan(this)">
            <input type="hidden" name="confirm" value="1">
            <input type="hidden" name="ttd_pengaju" class="ttd-data-hidden">
            <button type="submit" class="btn btn-ajukan" id="btn-ajukan-bottom">
                ✅ Ajukan Sekarang
            </button>
        </form>
    </div>
</div>

<script>
// Handle iframe loading
const iframe = document.getElementById('pdf-frame');
const loading = document.getElementById('pdf-loading');
const errorDiv = document.getElementById('pdf-error');

// Timeout: if PDF doesn't load in 30 seconds, show error
let loadTimeout = setTimeout(function() {
    loading.style.display = 'none';
    errorDiv.style.display = 'block';
}, 30000);

iframe.addEventListener('load', function() {
    clearTimeout(loadTimeout);
    loading.style.display = 'none';
    iframe.style.display = 'block';
});

iframe.addEventListener('error', function() {
    clearTimeout(loadTimeout);
    loading.style.display = 'none';
    errorDiv.style.display = 'block';
});

function confirmAjukan(formElement) {
    <?php if ($jenis === 'cuti'): ?>
    const ttdDataHidden = document.querySelectorAll('.ttd-data-hidden');
    let hasTtd = false;
    ttdDataHidden.forEach(input => {
        if (input.value && input.value.trim() !== '') hasTtd = true;
    });
    if (!hasTtd) {
        alert('Silakan gambar tanda tangan Anda terlebih dahulu!');
        return false;
    }
    <?php endif; ?>

    if (!confirm('Apakah Anda yakin ingin mengajukan surat ini? Pastikan semua data sudah benar.')) {
        return false;
    }
    // Disable all submit buttons
    document.querySelectorAll('.btn-ajukan').forEach(function(btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Mengajukan...';
    });
    return true;
}

<?php if ($jenis === 'cuti'): ?>
// ---- Signature Canvas ----
const canvas = document.getElementById('ttd-canvas');
if (canvas) {
    const ctx = canvas.getContext('2d');
    let isDrawing = false, lastX = 0, lastY = 0, paths = [], currentPath = [];

    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';
        ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#1a1a2e';
        redrawPaths();
    }
    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const t = e.touches ? e.touches[0] : e;
        return { x: t.clientX - rect.left, y: t.clientY - rect.top };
    }
    function startDraw(e) { e.preventDefault(); isDrawing = true; const p = getPos(e); lastX=p.x; lastY=p.y; currentPath=[{x:p.x,y:p.y}]; }
    function draw(e) {
        if (!isDrawing) return; e.preventDefault();
        const p = getPos(e);
        ctx.beginPath(); ctx.moveTo(lastX,lastY); ctx.lineTo(p.x,p.y); ctx.stroke();
        lastX=p.x; lastY=p.y; currentPath.push({x:p.x,y:p.y});
    }
    function stopDraw() { if (isDrawing && currentPath.length>1) paths.push([...currentPath]); isDrawing=false; currentPath=[]; syncHidden(); }
    function syncHidden() {
        const ttdVal = paths.length>0 ? canvas.toDataURL('image/png') : '';
        document.querySelectorAll('.ttd-data-hidden').forEach(input => input.value = ttdVal);
    }
    function redrawPaths() {
        ctx.clearRect(0,0,canvas.width,canvas.height);
        ctx.lineWidth=2.5; ctx.lineCap='round'; ctx.lineJoin='round'; ctx.strokeStyle='#1a1a2e';
        paths.forEach(path => {
            if(path.length<2) return;
            ctx.beginPath(); ctx.moveTo(path[0].x,path[0].y);
            for(let i=1;i<path.length;i++) ctx.lineTo(path[i].x,path[i].y);
            ctx.stroke();
        });
        syncHidden();
    }
    canvas.addEventListener('mousedown',startDraw); canvas.addEventListener('mousemove',draw);
    canvas.addEventListener('mouseup',stopDraw); canvas.addEventListener('mouseleave',stopDraw);
    canvas.addEventListener('touchstart',startDraw); canvas.addEventListener('touchmove',draw);
    canvas.addEventListener('touchend',stopDraw);
    window.clearCanvas = () => { paths=[]; ctx.clearRect(0,0,canvas.width,canvas.height); syncHidden(); };
    window.undoCanvas  = () => { paths.pop(); redrawPaths(); };
    resizeCanvas();
}
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

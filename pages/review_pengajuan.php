<?php
/**
 * Review Pengajuan + Tanda Tangan Digital
 * Admin bisa melihat detail pengajuan dan menandatangani
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';
require_once __DIR__ . '/../helpers/role_helper.php';

requireLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Cek akses
if (!canAccessKelolaPengajuan($userRole, $pdo, $userId)) {
    setFlash('error', 'Anda tidak memiliki akses!');
    redirect(BASE_URL . '/index.php');
}

$pengajuanId = (int) ($_GET['id'] ?? 0);
if ($pengajuanId <= 0) {
    setFlash('error', 'Pengajuan tidak ditemukan!');
    redirect(BASE_URL . '/pages/kelola_pengajuan.php');
}

// Ambil data pengajuan + user
$stmt = $pdo->prepare("
    SELECT p.*, u.nama, u.nip, u.jabatan, u.pangkat, u.unit_kerja, u.status_pegawai, u.atasan_id as requester_atasan_id
    FROM pengajuan p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$pengajuanId]);
$data = $stmt->fetch();

if (!$data) {
    setFlash('error', 'Pengajuan tidak ditemukan!');
    redirect(BASE_URL . '/pages/kelola_pengajuan.php');
}

// Cek apakah user berhak TTD
$berhakTtd = false;
$alasanTidakBerhak = '';
$statusPegawaiPemohon = $data['status_pegawai'] ?? 'PNS';

if ($data['status'] !== 'pending') {
    // Sudah diproses
} else {
    $sudahAdaBerapaTtd = hitungTTD($pdo, $pengajuanId);
    
    if (in_array($data['jenis_pengajuan'], ['izin', 'pulang_cepat'])) {
        // IZIN / PULANG CEPAT: 1 TTD saja
        if ($sudahAdaBerapaTtd === 0) {
            if (canSignIzin($userId, $userRole, $data['requester_atasan_id'])) {
                $berhakTtd = true;
            } else {
                $alasanTidakBerhak = 'Pengajuan izin hanya dapat ditandatangani oleh Atasan Langsung atau Pejabat Struktural.';
            }
        }
    } else {
        // CUTI: 2 TTD (Atasan Langsung -> Pejabat Berwenang)
        if ($sudahAdaBerapaTtd === 0) {
            if (!empty($data['requester_atasan_id'])) {
                if ($userId == $data['requester_atasan_id']) {
                    $berhakTtd = true;
                } else {
                    $alasanTidakBerhak = 'Menunggu tanda tangan dari Atasan Langsung pemohon terlebih dahulu.';
                }
            } else {
                if (isPejabatBerwenang($userRole, $statusPegawaiPemohon)) {
                    $berhakTtd = true;
                } else {
                    $alasanTidakBerhak = 'Pengajuan ini memerlukan tanda tangan Pejabat Berwenang.';
                }
            }
        } elseif ($sudahAdaBerapaTtd === 1) {
            if (isPejabatBerwenang($userRole, $statusPegawaiPemohon)) {
                $berhakTtd = true;
            } else {
                $pejabatLabel = ($statusPegawaiPemohon === 'PPPK') ? 'Sekretaris/Panitera' : 'Ketua/Wakil Ketua';
                $alasanTidakBerhak = 'Pengajuan ini sedang menunggu tanda tangan Pejabat Berwenang (' . $pejabatLabel . ').';
            }
        }
    }
}

// Cek apakah sudah TTD
$userId = $_SESSION['user_id'];
$sudahTtd = sudahTTD($pdo, $pengajuanId, $userId);
$ttdList = ambilTTDPengajuan($pdo, $pengajuanId);
$ttdCount = count($ttdList);

// Ketua/Wakil yang juga atasan langsung boleh TTD kedua kali (sebagai Pejabat Berwenang)
$bolehTtdKedua = false;
if ($sudahTtd && $data['jenis_pengajuan'] === 'cuti' && $ttdCount === 1) {
    // Hanya boleh TTD kedua jika: TTD pertama adalah dia sendiri (sebagai atasan)
    // DAN dia adalah Pejabat Berwenang untuk pemohon ini
    // DAN belum ada TTD ke-2 (urutan_ttd = 2)
    $sudahTtdKedua = sudahTTDUrutan($pdo, $pengajuanId, $userId, 2);
    if (!$sudahTtdKedua &&
        $userId == $data['requester_atasan_id'] &&
        isPejabatBerwenang($userRole, $statusPegawaiPemohon)) {
        $bolehTtdKedua = true;
    }
}

$pageTitle = 'Review Pengajuan';
$flash = getFlash();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<a href="<?= BASE_URL ?>/pages/kelola_pengajuan.php" class="btn btn-secondary btn-sm" style="margin-bottom: 20px;">
    ← Kembali ke Daftar
</a>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Detail Pengajuan -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Detail Pengajuan</h3>
            <?= statusBadge($data['status']) ?>
        </div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Nama Pegawai</span>
                    <span class="detail-value"><?= htmlspecialchars($data['nama']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">NIP</span>
                    <span class="detail-value"><?= htmlspecialchars($data['nip']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Jabatan</span>
                    <span class="detail-value"><?= htmlspecialchars($data['jabatan']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Pangkat</span>
                    <span class="detail-value"><?= htmlspecialchars($data['pangkat'] ?? '-') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Jenis Pengajuan</span>
                    <span class="detail-value"><?= jenisBadge($data['jenis_pengajuan']) ?></span>
                </div>

                <?php if ($data['jenis_pengajuan'] === 'cuti'): ?>
                <div class="detail-item">
                    <span class="detail-label">Periode Cuti</span>
                    <span class="detail-value"><?= formatTanggal($data['tanggal_mulai']) ?> — <?= formatTanggal($data['tanggal_selesai']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Jumlah Hari Kerja</span>
                    <span class="detail-value"><strong><?= $data['jumlah_hari'] ?> hari</strong></span>
                </div>
                <?php elseif ($data['jenis_pengajuan'] === 'izin'): ?>
                <div class="detail-item">
                    <span class="detail-label">Tanggal Izin</span>
                    <span class="detail-value">
                        <?= formatTanggal($data['tanggal_mulai']) ?> 
                        <?php if (!empty($data['tanggal_selesai'])): ?>
                            — <?= formatTanggal($data['tanggal_selesai']) ?> (<?= $data['jumlah_hari'] ?> hari kerja)
                        <?php else: ?>
                            <small class="badge badge-purple" style="margin-left: 8px;">Jam: <?= substr($data['jam_mulai'], 0, 5) ?> - <?= substr($data['jam_selesai'], 0, 5) ?></small>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($data['lampiran'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Lampiran</span>
                    <span class="detail-value">
                        <a href="<?= BASE_URL ?>/<?= $data['lampiran'] ?>" target="_blank" class="badge badge-purple" style="text-decoration:none;">📎 Lihat Surat Dokter</a>
                    </span>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="detail-item">
                    <span class="detail-label">Tanggal</span>
                    <span class="detail-value"><?= formatTanggal($data['tanggal_pulang']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Jam Pulang</span>
                    <span class="detail-value">
                        <?= substr($data['jam_pulang_diajukan'], 0, 5) ?> 
                        <small style="color: var(--text-muted);">(Resmi: <?= substr($data['jam_pulang_resmi'], 0, 5) ?>)</small>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Selisih</span>
                    <span class="detail-value"><?= formatSelisihWaktu($data['selisih_menit']) ?></span>
                </div>
                <?php endif; ?>

                <div class="detail-item full-width">
                    <span class="detail-label">Alasan</span>
                    <span class="detail-value"><?= nl2br(htmlspecialchars($data['alasan'])) ?></span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Diajukan Pada</span>
                    <span class="detail-value"><?= formatTanggal($data['created_at']) ?></span>
                </div>
            </div>

            <!-- Status TTD -->
            <?php $butuhTtdDisplay = in_array($data['jenis_pengajuan'], ['izin', 'pulang_cepat']) ? 1 : 2; ?>
            <div style="margin-top: 24px; padding: 16px; background: rgba(255,255,255,0.03); border-radius: 8px;">
                <h4 style="font-size: 14px; margin-bottom: 12px; color: var(--gold-400);">✍️ Status Tanda Tangan (<?= $ttdCount ?>/<?= $butuhTtdDisplay ?>)</h4>
                <?php if (empty($ttdList)): ?>
                    <p style="color: var(--text-muted); font-size: 13px;">Belum ada tanda tangan.</p>
                <?php else: ?>
                    <?php foreach ($ttdList as $ttd): ?>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--glass-border);">
                        <span class="badge badge-success">✅</span>
                        <div>
                            <strong style="font-size: 13px;"><?= htmlspecialchars($ttd['nama']) ?></strong><br>
                            <small style="color: var(--text-muted);"><?= htmlspecialchars($ttd['jabatan_ttd']) ?> — <?= waktuRelatif($ttd['created_at']) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel TTD -->
    <div class="card">
        <div class="card-header">
            <h3>✍️ Tanda Tangan</h3>
        </div>
        <div class="card-body">
            <?php if ($data['status'] !== 'pending'): ?>
                <div class="form-info success">
                    ✅ Pengajuan ini sudah diproses (<?= $data['status'] ?>).
                </div>
            <?php elseif ($sudahTtd && !$bolehTtdKedua): ?>
                <div class="form-info success">
                    ✅ Anda sudah menandatangani pengajuan ini.
                    <?php 
                    $butuhTtd = in_array($data['jenis_pengajuan'], ['izin', 'pulang_cepat']) ? 1 : 2;
                    if ($ttdCount < $butuhTtd): 
                    ?>
                        <br><small>Menunggu tanda tangan pejabat lainnya.</small>
                    <?php endif; ?>
                </div>
            <?php elseif ($bolehTtdKedua): ?>
                <div class="form-info" style="background: rgba(37, 99, 235, 0.08); border-color: rgba(37, 99, 235, 0.2); color: #2563eb;">
                    ✍️ Anda sudah menandatangani sebagai <strong>Atasan Langsung</strong>.<br>
                    Silakan tanda tangan sekali lagi sebagai <strong><?= getJabatanResmi($_SESSION['role']) ?></strong> untuk menyetujui pengajuan ini.
                </div>

                <div class="form-info">
                    Gambar tanda tangan Anda di bawah menggunakan mouse atau jari (di layar sentuh).
                    <br><strong>Penandatangan: <?= htmlspecialchars($_SESSION['nama']) ?> — <?= getJabatanResmi($_SESSION['role']) ?></strong>
                </div>

                <div class="ttd-canvas-wrapper">
                    <canvas id="ttd-canvas"></canvas>
                    <div class="ttd-actions">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearCanvas()">🗑️ Hapus</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="undoCanvas()">↩️ Undo</button>
                    </div>
                </div>

                <form id="ttd-form" style="margin-top: 16px;">
                    <input type="hidden" name="pengajuan_id" value="<?= $pengajuanId ?>">
                    <input type="hidden" name="ttd_data" id="ttd-data">
                    <button type="submit" class="btn btn-primary" style="width: 100%;" id="btn-simpan-ttd">
                        ✍️ Simpan TTD Pejabat & Setujui
                    </button>
                </form>
            <?php elseif (!$berhakTtd): ?>
                <div class="form-info" style="border-left: 4px solid var(--orange-500);">
                    ⚠️ <strong>Anda tidak dapat menandatangani:</strong><br>
                    <?= htmlspecialchars($alasanTidakBerhak) ?>
                </div>
            <?php else: ?>
                <div class="form-info">
                    Gambar tanda tangan Anda di bawah menggunakan mouse atau jari (di layar sentuh).
                    <br><strong>Penandatangan: <?= htmlspecialchars($_SESSION['nama']) ?> — <?= getJabatanResmi($_SESSION['role']) ?></strong>
                </div>

                <div class="ttd-canvas-wrapper">
                    <canvas id="ttd-canvas"></canvas>
                    <div class="ttd-actions">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearCanvas()">🗑️ Hapus</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="undoCanvas()">↩️ Undo</button>
                    </div>
                </div>

                <form id="ttd-form" style="margin-top: 16px;">
                    <input type="hidden" name="pengajuan_id" value="<?= $pengajuanId ?>">
                    <input type="hidden" name="ttd_data" id="ttd-data">
                    <button type="submit" class="btn btn-primary" style="width: 100%;" id="btn-simpan-ttd">
                        ✍️ Simpan Tanda Tangan & Setujui
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.detail-item {
    padding: 8px 0;
}
.detail-item.full-width {
    grid-column: 1 / -1;
}
.detail-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-muted);
    margin-bottom: 4px;
}
.detail-value {
    font-size: 14px;
    color: var(--text-primary);
}
.ttd-canvas-wrapper {
    border: 2px dashed var(--glass-border);
    border-radius: 12px;
    padding: 16px;
    background: rgba(255,255,255,0.02);
    text-align: center;
}
#ttd-canvas {
    width: 100%;
    height: 200px;
    background-color: #f8f9fa;
    background-image: linear-gradient(to right, transparent 49.5%, rgba(0,0,0,0.15) 49.5%, rgba(0,0,0,0.15) 50.5%, transparent 50.5%), linear-gradient(to bottom, transparent 65%, rgba(0,0,0,0.15) 65%, rgba(0,0,0,0.15) 67%, transparent 67%);
    border: 2px solid #ced4da;
    border-radius: 8px;
    cursor: crosshair;
    touch-action: none;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
}
.ttd-actions {
    margin-top: 10px;
    display: flex;
    gap: 8px;
    justify-content: center;
}
@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// ---- Signature Canvas ----
const canvas = document.getElementById('ttd-canvas');
if (canvas) {
    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let lastX = 0, lastY = 0;
    let paths = []; // For undo
    let currentPath = [];

    // Set canvas resolution properly
    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';
        ctx.lineWidth = 3.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#1a1a2e';
        // Redraw
        redrawPaths();
    }

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const touch = e.touches ? e.touches[0] : e;
        return {
            x: touch.clientX - rect.left,
            y: touch.clientY - rect.top
        };
    }

    function startDraw(e) {
        e.preventDefault();
        isDrawing = true;
        const pos = getPos(e);
        lastX = pos.x;
        lastY = pos.y;
        currentPath = [{x: pos.x, y: pos.y}];
    }

    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault();
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        lastX = pos.x;
        lastY = pos.y;
        currentPath.push({x: pos.x, y: pos.y});
    }

    function stopDraw(e) {
        if (isDrawing && currentPath.length > 1) {
            paths.push([...currentPath]);
        }
        isDrawing = false;
        currentPath = [];
    }

    function redrawPaths() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.lineWidth = 3.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#1a1a2e';
        paths.forEach(path => {
            if (path.length < 2) return;
            ctx.beginPath();
            ctx.moveTo(path[0].x, path[0].y);
            for (let i = 1; i < path.length; i++) {
                ctx.lineTo(path[i].x, path[i].y);
            }
            ctx.stroke();
        });
    }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDraw);
    canvas.addEventListener('mouseleave', stopDraw);
    canvas.addEventListener('touchstart', startDraw);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('touchend', stopDraw);

    window.clearCanvas = function() {
        paths = [];
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    };

    window.undoCanvas = function() {
        paths.pop();
        redrawPaths();
    };

    // Initialize
    resizeCanvas();

    // Form submit
    document.getElementById('ttd-form').addEventListener('submit', function(e) {
        e.preventDefault();

        if (paths.length === 0) {
            alert('Silakan gambar tanda tangan terlebih dahulu!');
            return;
        }

        // Export canvas as PNG base64
        const dataURL = canvas.toDataURL('image/png');
        document.getElementById('ttd-data').value = dataURL;

        const btn = document.getElementById('btn-simpan-ttd');
        btn.disabled = true;
        btn.textContent = '⏳ Menyimpan...';

        const formData = new FormData(this);

        fetch('<?= BASE_URL ?>/proses/simpan_ttd.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = data.redirect || '<?= BASE_URL ?>/pages/kelola_pengajuan.php';
            } else {
                alert(data.error || 'Gagal menyimpan TTD!');
                btn.disabled = false;
                btn.textContent = '✍️ Simpan Tanda Tangan & Setujui';
            }
        })
        .catch(err => {
            alert('Terjadi kesalahan!');
            btn.disabled = false;
            btn.textContent = '✍️ Simpan Tanda Tangan & Setujui';
        });
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

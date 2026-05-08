<?php
/**
 * Proses Simpan Tanda Tangan Digital
 * Menyimpan gambar TTD dari canvas ke file dan database
 * Jika sudah 2 TTD → auto update status disetujui
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';
require_once __DIR__ . '/../helpers/role_helper.php';
require_once __DIR__ . '/../helpers/file_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pengajuanId = (int) ($_POST['pengajuan_id'] ?? 0);
$ttdData     = $_POST['ttd_data'] ?? '';
$userId      = $_SESSION['user_id'];

if ($pengajuanId <= 0 || empty($ttdData)) {
    echo json_encode(['error' => 'Data tidak lengkap!']);
    exit;
}

// Cek pengajuan valid & masih pending
$stmt = $pdo->prepare("SELECT * FROM pengajuan WHERE id = ? AND status = 'pending'");
$stmt->execute([$pengajuanId]);
$pengajuan = $stmt->fetch();

if (!$pengajuan) {
    echo json_encode(['error' => 'Pengajuan tidak ditemukan atau sudah diproses!']);
    exit;
}

// Cek belum TTD (dengan pengecualian: Ketua bisa 2x TTD untuk Hakim)
$sudahTtdUser = sudahTTD($pdo, $pengajuanId, $userId);
$sudahAdaBerapaTtdAwal = hitungTTD($pdo, $pengajuanId);

// Untuk cuti: Ketua yang juga atasan langsung boleh TTD 2x
$bolehTtdKedua = false;
if ($sudahTtdUser && $pengajuan['jenis_pengajuan'] === 'cuti' && $sudahAdaBerapaTtdAwal === 1) {
    // Cek apakah user ini adalah atasan langsung pemohon DAN punya role ketua/wakil
    $stmtAtasan = $pdo->prepare("SELECT atasan_id, status_pegawai FROM users WHERE id = ?");
    $stmtAtasan->execute([$pengajuan['user_id']]);
    $rowCheck = $stmtAtasan->fetch();
    $atasanIdCheck = $rowCheck['atasan_id'] ?? null;
    $statusPegawaiCheck = $rowCheck['status_pegawai'] ?? 'PNS';
    $role = $_SESSION['role'];
    
    if ($userId == $atasanIdCheck && isPejabatBerwenang($role, $statusPegawaiCheck)) {
        $bolehTtdKedua = true;
    }
}

if ($sudahTtdUser && !$bolehTtdKedua) {
    echo json_encode(['error' => 'Anda sudah menandatangani pengajuan ini!']);
    exit;
}

// Cek Atasan ID pemohon
$stmt = $pdo->prepare("SELECT atasan_id FROM users WHERE id = ?");
$stmt->execute([$pengajuan['user_id']]);
$atasanId = $stmt->fetchColumn();

// Siapa saja yang berhak TTD saat ini?
$berhakTtd = false;
$urutanTtd = 1; // default urutan TTD
$role = $_SESSION['role'];
$sudahAdaBerapaTtd = hitungTTD($pdo, $pengajuanId);

// Ambil status_pegawai pemohon
$stmtSP = $pdo->prepare("SELECT status_pegawai FROM users WHERE id = ?");
$stmtSP->execute([$pengajuan['user_id']]);
$statusPegawaiPemohon = $stmtSP->fetchColumn() ?: 'PNS';

if (in_array($pengajuan['jenis_pengajuan'], ['izin', 'pulang_cepat'])) {
    // Izin & Pulang Cepat: 1 TTD saja
    if ($sudahAdaBerapaTtd === 0) {
        $berhakTtd = canSignIzin($userId, $role, $atasanId);
        $urutanTtd = 1;
    }
} else {
    // CUTI (butuh 2 TTD)
    if ($sudahAdaBerapaTtd === 0) {
        // TTD1: Atasan langsung
        if (!empty($atasanId) && $userId == $atasanId) {
            $berhakTtd = true;
            $urutanTtd = 1;
        } elseif (empty($atasanId)) {
            // Jika tidak ada atasan, pejabat berwenang boleh TTD1
            if (isPejabatBerwenang($role, $statusPegawaiPemohon)) {
                $berhakTtd = true;
                $urutanTtd = 1;
            }
        }
    } elseif ($sudahAdaBerapaTtd === 1) {
        // TTD2: Pejabat Berwenang (berdasarkan status_pegawai pemohon)
        // Ketua yang juga atasan langsung boleh TTD ke-2 ini
        if (isPejabatBerwenang($role, $statusPegawaiPemohon)) {
            $berhakTtd = true;
            $urutanTtd = 2;
        }
    }
}

if (!$berhakTtd) {
    echo json_encode(['error' => 'Anda tidak memiliki hak untuk menandatangani pengajuan ini pada tahap ini.']);
    exit;
}

// Decode base64 image
$ttdData = str_replace('data:image/png;base64,', '', $ttdData);
$ttdData = str_replace(' ', '+', $ttdData);
$imageData = base64_decode($ttdData);

if (!$imageData) {
    echo json_encode(['error' => 'Format tanda tangan tidak valid!']);
    exit;
}

// Simpan file TTD dengan path terstruktur per user
$namaTtd = $_SESSION['nama'] ?? 'user';
$jenisTtd = $pengajuan['jenis_pengajuan'] ?? 'pengajuan';
$tglTtd   = date('Y-m-d');
$pathInfo = generateTtdPath($namaTtd, $jenisTtd, $tglTtd);

if (!file_put_contents($pathInfo['abs'], $imageData)) {
    echo json_encode(['error' => 'Gagal menyimpan file tanda tangan!']);
    exit;
}

$ttdPath = $pathInfo['relative'];

try {
    $pdo->beginTransaction();

    // Simpan TTD ke database
    // jabatan_ttd menggunakan jabatan resmi berdasarkan role
    $jabatanTtd = getJabatanResmi($_SESSION['role']);
    $stmt = $pdo->prepare("INSERT INTO ttd_pengajuan (pengajuan_id, user_id, urutan_ttd, jabatan_ttd, ttd_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$pengajuanId, $userId, $urutanTtd, $jabatanTtd, $ttdPath]);

    // Hitung total TTD sekarang
    $totalTTD = hitungTTD($pdo, $pengajuanId);

    $statusBaru = 'pending';
    $message = 'Tanda tangan berhasil disimpan!';

    // Jika sudah memenuhi TTD → auto approve
    $butuhTtd = in_array($pengajuan['jenis_pengajuan'], ['izin', 'pulang_cepat']) ? 1 : 2;
    if ($totalTTD >= $butuhTtd) {
        $stmt = $pdo->prepare("
            UPDATE pengajuan SET status = 'disetujui', disetujui_oleh = ?, tanggal_approval = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId, $pengajuanId]);

        $statusBaru = 'disetujui';
        $message = 'Pengajuan disetujui! Kedua pejabat telah menandatangani.';

        // Update saldo cuti jika cuti disetujui
        if ($pengajuan['jenis_pengajuan'] === 'cuti') {
            $tahun = (int) date('Y', strtotime($pengajuan['tanggal_mulai']));
            $tipeCuti = $pengajuan['tipe_cuti'] ?? 'tahunan';
            
            // Tentukan kolom yang perlu diupdate
            // Catatan: tipe 'tahunan_lalu' disimpan sebagai 'tahunan' di DB pengajuan
            // tapi pemotongannya dari kolom terpakai_tahunan_lalu
            // Cara deteksi: cek apakah alasan berisi 'tahunan_lalu' di catatan log, 
            // atau cek dari nama kolom — karena DB sudah save sebagai 'tahunan',
            // kita cek via catatan log aktivitas
            $columnTerpakai = 'terpakai_tahunan';
            if ($tipeCuti === 'sakit') $columnTerpakai = 'terpakai_sakit';
            elseif ($tipeCuti === 'melahirkan') $columnTerpakai = 'terpakai_melahirkan';
            elseif ($tipeCuti === 'alasan_penting') $columnTerpakai = 'terpakai_alasan_penting';

            // Cek apakah pengajuan ini dari paket tahunan lalu
            // (tersimpan di log aktivitas dengan catatan 'tahunan_lalu')
            $stmtLog = $pdo->prepare("SELECT catatan FROM log_aktivitas WHERE pengajuan_id = ? AND aksi = 'pengajuan_baru' LIMIT 1");
            $stmtLog->execute([$pengajuanId]);
            $logCatatan = $stmtLog->fetchColumn() ?: '';
            if ($tipeCuti === 'tahunan' && strpos($logCatatan, 'tahunan_lalu') !== false) {
                $columnTerpakai = 'terpakai_tahunan_lalu';
                // Deduct dari tahun sebelumnya
                $tahunSaldo = $tahun - 1;
            } else {
                $tahunSaldo = $tahun;
            }

            $stmt = $pdo->prepare("
                UPDATE saldo_cuti SET {$columnTerpakai} = {$columnTerpakai} + ?
                WHERE user_id = ? AND tahun = ?
            ");
            $stmt->execute([$pengajuan['jumlah_hari'], $pengajuan['user_id'], $tahunSaldo]);
        }

        // Notifikasi ke pegawai
        $jenisLabel = ['cuti' => 'Cuti', 'izin' => 'Izin', 'pulang_cepat' => 'Izin Waktu'];
        $jenis = $jenisLabel[$pengajuan['jenis_pengajuan']] ?? $pengajuan['jenis_pengajuan'];
        $pesanNotif = ($pengajuan['jenis_pengajuan'] === 'pulang_cepat') ? 
            "Pengajuan {$jenis} Anda telah disetujui oleh Ketua PTUN. Silakan download surat." : 
            "Pengajuan {$jenis} Anda telah disetujui oleh kedua pejabat. Silakan download surat.";
            
        buatNotifikasi($pdo, $pengajuan['user_id'],
            "Pengajuan {$jenis} Disetujui ✅",
            $pesanNotif,
            BASE_URL . '/pages/riwayat.php'
        );

        // Log aktivitas
        $stmt = $pdo->prepare("
            INSERT INTO log_aktivitas (pengajuan_id, user_id, aksi, status_lama, status_baru, catatan)
            VALUES (?, ?, 'setujui', 'pending', 'disetujui', 'Disetujui setelah 2 TTD lengkap')
        ");
        $stmt->execute([$pengajuanId, $userId]);
    } else {
        // Notifikasi ke pejabat lain bahwa ada TTD baru
        // Notify pejabat berwenang yang relevan
        $stmtPemohon = $pdo->prepare("SELECT status_pegawai FROM users WHERE id = ?");
        $stmtPemohon->execute([$pengajuan['user_id']]);
        $spPemohon = $stmtPemohon->fetchColumn() ?: 'PNS';
        
        // Cari pejabat berwenang yang bisa TTD2
        $allPejabat = $pdo->query("SELECT id, role FROM users WHERE aktif = 1 AND id != " . $userId)->fetchAll();
        foreach ($allPejabat as $pej) {
            if (isPejabatBerwenang($pej['role'], $spPemohon)) {
                buatNotifikasi($pdo, $pej['id'],
                "TTD Baru pada Pengajuan #" . $pengajuanId,
                htmlspecialchars($_SESSION['nama']) . " telah menandatangani pengajuan. Menunggu TTD Anda.",
                BASE_URL . '/pages/review_pengajuan.php?id=' . $pengajuanId
            );
            }
        }
    }

    $pdo->commit();

    setFlash('success', $message);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'status'  => $statusBaru,
        'redirect' => BASE_URL . '/pages/kelola_pengajuan.php'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    // Hapus file jika gagal
    if (file_exists($filepath)) unlink($filepath);
    echo json_encode(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}

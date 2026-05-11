<?php
/**
 * Ajukan dari Preview — Submit data dari SESSION ke database
 * Ini adalah langkah terakhir setelah user melihat preview
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';
require_once __DIR__ . '/../helpers/file_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['preview_data'])) {
    setFlash('error', 'Tidak ada data untuk diajukan. Silakan isi form terlebih dahulu.');
    redirect(BASE_URL . '/dashboard_v2.php');
}

$data = $_SESSION['preview_data'];
$jenis = $data['jenis'];
$userId = $_SESSION['user_id'];
$namaPengaju = $_SESSION['nama'] ?? 'user';

try {
    if ($jenis === 'cuti') {
        // ==================== CUTI ====================
        $tipeCuti    = $data['tipe_cuti'];
        $mulai       = $data['tanggal_mulai'];
        $selesai     = $data['tanggal_selesai'];
        $jumlahHari  = $data['jumlah_hari'];
        $alasan      = $data['alasan'];
        $telepon     = $data['telepon'];
        $alamatCuti  = $data['alamat_cuti'];
        $ttdPengaju  = trim($_POST['ttd_pengaju'] ?? '');

        if (empty($ttdPengaju)) {
            setFlash('error', 'Tanda tangan tidak boleh kosong!');
            redirect(BASE_URL . '/pages/preview_surat.php');
        }

        // Simpan TTD pengaju
        $ttdDataRaw = str_replace('data:image/png;base64,', '', $ttdPengaju);
        $ttdDataRaw = str_replace(' ', '+', $ttdDataRaw);
        $imageData  = base64_decode($ttdDataRaw);

        if (!$imageData) {
            setFlash('error', 'Format tanda tangan tidak valid!');
            redirect(BASE_URL . '/pages/preview_surat.php');
        }

        $ttdInfo = generateTtdPath($namaPengaju, 'cuti', $mulai);
        if (!file_put_contents($ttdInfo['abs'], $imageData)) {
            setFlash('error', 'Gagal menyimpan file tanda tangan!');
            redirect(BASE_URL . '/pages/preview_surat.php');
        }
        $ttdPath = $ttdInfo['relative'];

        // Handle lampiran dari temp
        $lampiranPath = null;
        if (!empty($data['lampiran_temp']) && file_exists($data['lampiran_temp'])) {
            $lampiranErr = null;
            // Move from temp to permanent
            $ext = pathinfo($data['lampiran_orig'], PATHINFO_EXTENSION);
            $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($namaPengaju));
            $permDir = __DIR__ . '/../assets/lampiran/' . $slug . '/';
            if (!is_dir($permDir)) mkdir($permDir, 0755, true);
            $permFile = $permDir . 'cuti_' . $mulai . '.' . $ext;
            copy($data['lampiran_temp'], $permFile);
            @unlink($data['lampiran_temp']);
            $lampiranPath = 'assets/lampiran/' . $slug . '/cuti_' . $mulai . '.' . $ext;
        }

        $tipeCutiDb = ($tipeCuti === 'tahunan_lalu') ? 'tahunan' : $tipeCuti;

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO pengajuan (user_id, jenis_pengajuan, tipe_cuti, tanggal_mulai, tanggal_selesai, jumlah_hari, alasan, telepon, alamat_cuti, ttd_pengaju, lampiran, status)
            VALUES (?, 'cuti', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$userId, $tipeCutiDb, $mulai, $selesai, $jumlahHari, $alasan, $telepon, $alamatCuti, $ttdPath, $lampiranPath]);
        $pengajuanId = $pdo->lastInsertId();

        $catatanLog = 'Pengajuan cuti baru - ' . ($tipeCuti === 'tahunan_lalu' ? 'tahunan_lalu' : $tipeCutiDb);
        $stmt = $pdo->prepare("
            INSERT INTO log_aktivitas (pengajuan_id, user_id, aksi, status_baru, catatan)
            VALUES (?, ?, 'pengajuan_baru', 'pending', ?)
        ");
        $stmt->execute([$pengajuanId, $userId, $catatanLog]);

        notifKePejabat($pdo, $userId,
            'Pengajuan Cuti Baru 🏖️',
            htmlspecialchars($_SESSION['nama']) . ' mengajukan cuti ' . $jumlahHari . ' hari kerja (' . $mulai . ' s/d ' . $selesai . ')',
            BASE_URL . '/pages/review_pengajuan.php?id=' . $pengajuanId
        );

        $pdo->commit();
        setFlash('success', "Pengajuan cuti berhasil diajukan! ({$jumlahHari} hari kerja)");

    } elseif ($jenis === 'izin') {
        // ==================== IZIN ====================
        $mulai     = $data['tanggal_mulai'];
        $jamMulai  = $data['jam_mulai'];
        $jamSelesai = $data['jam_selesai'];
        $alasan    = $data['alasan'];

        // Handle lampiran dari temp
        $lampiranPath = null;
        if (!empty($data['lampiran_temp']) && file_exists($data['lampiran_temp'])) {
            $ext = pathinfo($data['lampiran_orig'], PATHINFO_EXTENSION);
            $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($namaPengaju));
            $permDir = __DIR__ . '/../assets/lampiran/' . $slug . '/';
            if (!is_dir($permDir)) mkdir($permDir, 0755, true);
            $permFile = $permDir . 'izin_' . $mulai . '.' . $ext;
            copy($data['lampiran_temp'], $permFile);
            @unlink($data['lampiran_temp']);
            $lampiranPath = 'assets/lampiran/' . $slug . '/izin_' . $mulai . '.' . $ext;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO pengajuan (user_id, jenis_pengajuan, tanggal_mulai, jam_mulai, jam_selesai, alasan, lampiran, status)
            VALUES (?, 'izin', ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$userId, $mulai, $jamMulai, $jamSelesai, $alasan, $lampiranPath]);
        $pengajuanId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO log_aktivitas (pengajuan_id, user_id, aksi, status_baru, catatan)
            VALUES (?, ?, 'pengajuan_baru', 'pending', 'Pengajuan izin keluar kantor baru')
        ");
        $stmt->execute([$pengajuanId, $userId]);

        notifKePejabat($pdo, $userId,
            'Pengajuan Izin Keluar Kantor Baru 📝',
            htmlspecialchars($_SESSION['nama']) . " mengajukan izin keluar kantor pada {$mulai} jam {$jamMulai}-{$jamSelesai}",
            BASE_URL . '/pages/review_pengajuan.php?id=' . $pengajuanId
        );

        $pdo->commit();
        setFlash('success', "Pengajuan izin keluar kantor berhasil diajukan! ({$jamMulai} - {$jamSelesai})");

    } elseif ($jenis === 'pulang_cepat') {
        // ==================== PULANG CEPAT ====================
        $tanggal      = $data['tanggal_pulang'];
        $jamResmi     = $data['jam_pulang_resmi'];
        $jamDiajukan  = $data['jam_pulang_diajukan'];
        $tipe         = $data['tipe_izin_waktu'];
        $selisihMenit = $data['selisih_menit'];
        $alasan       = $data['alasan'];

        // Handle lampiran dari temp
        $lampiranPath = null;
        if (!empty($data['lampiran_temp']) && file_exists($data['lampiran_temp'])) {
            $ext = pathinfo($data['lampiran_orig'], PATHINFO_EXTENSION);
            $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($namaPengaju));
            $jenisTipe = ($tipe === 'datang_terlambat') ? 'terlambat' : 'pulang_cepat';
            $permDir = __DIR__ . '/../assets/lampiran/' . $slug . '/';
            if (!is_dir($permDir)) mkdir($permDir, 0755, true);
            $permFile = $permDir . $jenisTipe . '_' . $tanggal . '.' . $ext;
            copy($data['lampiran_temp'], $permFile);
            @unlink($data['lampiran_temp']);
            $lampiranPath = 'assets/lampiran/' . $slug . '/' . $jenisTipe . '_' . $tanggal . '.' . $ext;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO pengajuan (user_id, jenis_pengajuan, tipe_izin_waktu, tanggal_pulang, jam_pulang_resmi, jam_pulang_diajukan, selisih_menit, alasan, lampiran, status)
            VALUES (?, 'pulang_cepat', ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$userId, $tipe, $tanggal, $jamResmi, $jamDiajukan, $selisihMenit, $alasan, $lampiranPath]);
        $pengajuanId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO log_aktivitas (pengajuan_id, user_id, aksi, status_baru, catatan)
            VALUES (?, ?, 'pengajuan_baru', 'pending', 'Pengajuan pulang cepat baru')
        ");
        $stmt->execute([$pengajuanId, $userId]);

        notifKePejabat($pdo, $userId,
            'Pengajuan Pulang Cepat Baru 🕐',
            htmlspecialchars($_SESSION['nama']) . ' mengajukan pulang cepat pada ' . $tanggal . ' (selisih ' . formatSelisihWaktu($selisihMenit) . ')',
            BASE_URL . '/pages/review_pengajuan.php?id=' . $pengajuanId
        );

        $pdo->commit();
        setFlash('success', 'Pengajuan pulang cepat berhasil! (Selisih: ' . formatSelisihWaktu($selisihMenit) . ')');

    } else {
        setFlash('error', 'Jenis pengajuan tidak dikenali!');
        redirect(BASE_URL . '/dashboard_v2.php');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    setFlash('error', 'Terjadi kesalahan: ' . $e->getMessage());
    redirect(BASE_URL . '/pages/preview_surat.php');
}

// Bersihkan data preview dari session
unset($_SESSION['preview_data']);

redirect(BASE_URL . '/pages/riwayat.php');

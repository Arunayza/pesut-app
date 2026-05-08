<?php
/**
 * PESUT - Helper Notifikasi
 * Fungsi untuk membuat dan mengelola notifikasi in-app
 */

/**
 * Buat notifikasi baru untuk user tertentu
 */
function buatNotifikasi(PDO $pdo, int $userId, string $judul, string $pesan, ?string $link = null): void
{
    $stmt = $pdo->prepare("INSERT INTO notifikasi (user_id, judul, pesan, link) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $judul, $pesan, $link]);
}

/**
 * Kirim notifikasi ke Pejabat berwenang dan Atasan Langsung (jika ada)
 */
function notifKePejabat(PDO $pdo, int $pembuatId, string $judul, string $pesan, ?string $link = null): void
{
    // Pastikan role_helper sudah di-require
    if (!defined('ROLE_PEJABAT')) {
        require_once __DIR__ . '/role_helper.php';
    }

    // 1. Notif ke semua pejabat struktural
    $placeholders = implode(',', array_fill(0, count(ROLE_PEJABAT), '?'));
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role IN ($placeholders) AND aktif = 1");
    $stmt->execute(ROLE_PEJABAT);
    $pejabat = $stmt->fetchAll();
    foreach ($pejabat as $p) {
        buatNotifikasi($pdo, $p['id'], $judul, $pesan, $link);
    }

    // 2. Notif ke Atasan Langsung (jika punya dan belum termasuk pejabat)
    $stmt = $pdo->prepare("SELECT atasan_id FROM users WHERE id = ?");
    $stmt->execute([$pembuatId]);
    $atasanId = $stmt->fetchColumn();

    if ($atasanId) {
        // Cek apakah atasan sudah termasuk pejabat (agar tidak double notif)
        $alreadyNotified = false;
        foreach ($pejabat as $p) {
            if ($p['id'] == $atasanId) { $alreadyNotified = true; break; }
        }
        if (!$alreadyNotified) {
            buatNotifikasi($pdo, $atasanId, "Info Pegawai: " . $judul, $pesan, $link);
        }
    }
}

/**
 * Ambil jumlah notifikasi belum dibaca
 */
function hitungNotifBelumDibaca(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND dibaca = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Ambil notifikasi terbaru (untuk dropdown)
 */
function ambilNotifikasiTerbaru(PDO $pdo, int $userId, int $limit = 10): array
{
    $stmt = $pdo->prepare("SELECT * FROM notifikasi WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Tandai notifikasi sebagai dibaca
 */
function tandaiDibaca(PDO $pdo, int $notifId, int $userId): bool
{
    $stmt = $pdo->prepare("UPDATE notifikasi SET dibaca = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$notifId, $userId]);
}

/**
 * Tandai semua notifikasi sebagai dibaca
 */
function tandaiSemuaDibaca(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("UPDATE notifikasi SET dibaca = 1 WHERE user_id = ? AND dibaca = 0");
    return $stmt->execute([$userId]);
}

/**
 * Hitung jumlah TTD yang sudah ada untuk pengajuan
 */
function hitungTTD(PDO $pdo, int $pengajuanId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ttd_pengajuan WHERE pengajuan_id = ?");
    $stmt->execute([$pengajuanId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Cek apakah user sudah TTD untuk pengajuan ini (any urutan)
 */
function sudahTTD(PDO $pdo, int $pengajuanId, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ttd_pengajuan WHERE pengajuan_id = ? AND user_id = ?");
    $stmt->execute([$pengajuanId, $userId]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Cek apakah user sudah TTD pada urutan tertentu (1=Atasan, 2=Pejabat Berwenang)
 * Digunakan untuk Ketua yang bisa TTD dua kali di pengajuan cuti Hakim
 */
function sudahTTDUrutan(PDO $pdo, int $pengajuanId, int $userId, int $urutan): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ttd_pengajuan WHERE pengajuan_id = ? AND user_id = ? AND urutan_ttd = ?");
    $stmt->execute([$pengajuanId, $userId, $urutan]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Ambil daftar TTD untuk pengajuan
 */
function ambilTTDPengajuan(PDO $pdo, int $pengajuanId): array
{
    $stmt = $pdo->prepare("
        SELECT t.*, u.nama, u.nip, u.jabatan, u.pangkat
        FROM ttd_pengajuan t
        JOIN users u ON t.user_id = u.id
        WHERE t.pengajuan_id = ?
        ORDER BY t.created_at ASC
    ");
    $stmt->execute([$pengajuanId]);
    return $stmt->fetchAll();
}

/**
 * Format waktu relatif (berapa lama yang lalu)
 */
function waktuRelatif(string $datetime): string
{
    $now = new DateTime();
    $dt = new DateTime($datetime);
    $diff = $now->getTimestamp() - $dt->getTimestamp();

    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    return formatTanggal($datetime);
}

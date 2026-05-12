<?php
/**
 * PESUT - Helper Functions
 * Fungsi utility untuk kalkulasi cuti, jam kerja, dll.
 */

/**
 * Hitung jumlah hari kerja antara dua tanggal
 * Mengecualikan: Sabtu, Minggu, dan hari libur nasional dari DB
 */
function hitungHariKerja(string $mulai, string $selesai, PDO $pdo, bool $returnDates = false)
{
    $start = new DateTime($mulai);
    $end   = new DateTime($selesai);
    $end->modify('+1 day'); // inclusive end date

    // Ambil daftar hari libur dari DB dalam range
    $stmt = $pdo->prepare("SELECT tanggal FROM hari_libur WHERE tanggal BETWEEN ? AND ?");
    $stmt->execute([$mulai, $selesai]);
    $libur = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'tanggal');

    $hariKerja = 0;
    $tanggalList = [];
    $interval  = new DateInterval('P1D');
    $period    = new DatePeriod($start, $interval, $end);

    foreach ($period as $hari) {
        $dayOfWeek = (int) $hari->format('N'); // 1=Senin ... 7=Minggu
        if ($dayOfWeek >= 6) continue;          // Skip Sabtu & Minggu
        if (in_array($hari->format('Y-m-d'), $libur)) continue;
        $hariKerja++;
        $tanggalList[] = $hari->format('Y-m-d');
    }

    if ($returnDates) {
        return ['jumlah' => $hariKerja, 'tanggal_list' => $tanggalList];
    }

    return $hariKerja;
}

/**
 * Dapatkan jam pulang resmi berdasarkan tanggal
 * Senin-Kamis: 16:30, Jumat: 17:00
 */
function getJamPulangResmi(?string $tanggal): string
{
    if (empty($tanggal)) return JAM_PULANG_SENIN_KAMIS;
    $dayOfWeek = (int) (new DateTime($tanggal))->format('N');

    if ($dayOfWeek === 5) {
        return JAM_PULANG_JUMAT;    // Jumat
    }
    return JAM_PULANG_SENIN_KAMIS;  // Senin-Kamis
}

/**
 * Hitung selisih menit antara jam pulang resmi dan jam diajukan
 */
function hitungSelisihMenit(string $jamResmi, string $jamDiajukan): int
{
    // Parse jam dalam format H:i menggunakan tanggal referensi yang sama
    // agar getTimestamp() tidak bermasalah karena perbedaan tanggal
    $base     = '2000-01-01 ';
    $resmi    = new DateTime($base . $jamResmi);
    $diajukan = new DateTime($base . $jamDiajukan);

    $diff = $resmi->getTimestamp() - $diajukan->getTimestamp();
    return (int) floor($diff / 60); // Bisa negatif — caller yang validasi
}

/**
 * Format selisih menit ke format "X jam Y menit"
 */
function formatSelisihWaktu(int $menit): string
{
    $jam  = floor($menit / 60);
    $sisa = $menit % 60;

    if ($jam > 0 && $sisa > 0) {
        return "{$jam} jam {$sisa} menit";
    } elseif ($jam > 0) {
        return "{$jam} jam";
    }
    return "{$sisa} menit";
}

/**
 * Hitung Masa Kerja dalam format "X Tahun Y Bulan"
 */
function hitungMasaKerja(string $tglMulai): string
{
    $start = new DateTime($tglMulai);
    $now   = new DateTime();
    $diff  = $now->diff($start);
    
    $tahun = $diff->y;
    $bulan = $diff->m;
    
    return "{$tahun} Tahun {$bulan} Bulan";
}

/**
 * Cek sisa kuota cuti user untuk tahun tertentu (semua jenis)
 */
function cekSisaCuti(int $userId, int $tahun, PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT * FROM saldo_cuti WHERE user_id = ? AND tahun = ?");
    $stmt->execute([$userId, $tahun]);
    $saldo = $stmt->fetch();

    if (!$saldo) {
        // Cek gender untuk cuti melahirkan
        $stmtUser = $pdo->prepare("SELECT jenis_kelamin FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $jk = $stmtUser->fetchColumn();
        $jatahMelahirkan = ($jk === 'P') ? 90 : 0;

        // Auto-create saldo jika belum ada, semua default 0 (karena diisi manual oleh HR)
        $stmt = $pdo->prepare("
            INSERT INTO saldo_cuti (
                user_id, tahun,
                jatah_tahunan_lalu, terpakai_tahunan_lalu,
                jatah_tahunan, terpakai_tahunan,
                jatah_sakit, terpakai_sakit,
                jatah_melahirkan, terpakai_melahirkan,
                jatah_alasan_penting, terpakai_alasan_penting
            ) VALUES (?, ?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
        ");
        $stmt->execute([$userId, $tahun]);
        
        return [
            'jatah_tahunan_lalu' => 0,
            'terpakai_tahunan_lalu' => 0,
            'jatah_tahunan' => 0,
            'terpakai_tahunan' => 0,
            'jatah_sakit' => 0,
            'terpakai_sakit' => 0,
            'jatah_melahirkan' => 0,
            'terpakai_melahirkan' => 0,
            'jatah_alasan_penting' => 0,
            'terpakai_alasan_penting' => 0,
        ];
    }

    return $saldo;
}

/**
 * Cek apakah tanggal adalah hari kerja (bukan weekend & bukan libur)
 */
function isHariKerja(?string $tanggal, PDO $pdo): bool
{
    if (empty($tanggal)) return false;
    $dayOfWeek = (int) (new DateTime($tanggal))->format('N');
    if ($dayOfWeek >= 6) return false; // Weekend

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hari_libur WHERE tanggal = ?");
    $stmt->execute([$tanggal]);
    return $stmt->fetchColumn() == 0;
}

/**
 * Nama hari dalam Bahasa Indonesia
 */
function namaHari(?string $tanggal): string
{
    if (empty($tanggal)) return '';
    $hari = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $dayOfWeek = (int) (new DateTime($tanggal))->format('N');
    return $hari[$dayOfWeek];
}

/**
 * Format tanggal ke format Indonesia
 */
function formatTanggal(?string $tanggal): string
{
    if (empty($tanggal)) return '-';
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $dt = new DateTime($tanggal);
    $d  = $dt->format('j');
    $m  = (int) $dt->format('n');
    $y  = $dt->format('Y');
    return "$d {$bulan[$m]} $y";
}

/**
 * Format tanggal ke format Indonesia dengan nama hari
 * Contoh: "Selasa, 28 April 2026"
 */
function formatTanggalHari(?string $tanggal): string
{
    if (empty($tanggal)) return '-';
    return namaHari($tanggal) . ', ' . formatTanggal($tanggal);
}

/**
 * Set flash message ke session
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Ambil dan hapus flash message
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Redirect ke URL
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Cek apakah user sudah login
 */
function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        redirect(BASE_URL . '/pages/login.php');
    }
}

/**
 * Label status dengan warna badge
 */
function statusBadge(string $status): string
{
    $map = [
        'pending'     => '<span class="badge badge-warning">Pending</span>',
        'disetujui'   => '<span class="badge badge-success">Disetujui</span>',
        'ditolak'     => '<span class="badge badge-danger">Ditolak</span>',
        'dibatalkan'  => '<span class="badge badge-secondary">Dibatalkan</span>',
    ];
    return $map[$status] ?? '<span class="badge">' . ucfirst($status) . '</span>';
}

/**
 * Label jenis pengajuan
 */
function jenisBadge(string $jenis, string $tipeWaktu = null): string
{
    if ($jenis === 'pulang_cepat') {
        if ($tipeWaktu === 'datang_terlambat') {
            return '<span class="badge badge-orange">Datang Terlambat</span>';
        }
        return '<span class="badge badge-orange">Pulang Cepat</span>';
    }

    $map = [
        'cuti'          => '<span class="badge badge-info">Cuti</span>',
        'izin'          => '<span class="badge badge-purple">Izin</span>',
    ];
    return $map[$jenis] ?? '<span class="badge">' . ucfirst($jenis) . '</span>';
}

/**
 * Fungsi Terbilang (Angka ke Huruf)
 */
function terbilang(int $angka): string
{
    $angka = abs($angka);
    $baca = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
    $terbilang = "";

    if ($angka < 12) {
        $terbilang = " " . $baca[$angka];
    } elseif ($angka < 20) {
        $terbilang = terbilang($angka - 10) . " belas";
    } elseif ($angka < 100) {
        $terbilang = terbilang((int)($angka / 10)) . " puluh" . terbilang($angka % 10);
    } elseif ($angka < 200) {
        $terbilang = " seratus" . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        $terbilang = terbilang((int)($angka / 100)) . " ratus" . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        $terbilang = " seribu" . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        $terbilang = terbilang((int)($angka / 1000)) . " ribu" . terbilang($angka % 1000);
    }

    return trim($terbilang);
}

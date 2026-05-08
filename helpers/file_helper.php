<?php
/**
 * Helper: File Path Generator untuk TTD dan Lampiran
 * 
 * Struktur folder:
 *   assets/ttd/{slug_nama}/{jenis}_{slug_nama}_{tanggal}.png
 *   assets/lampiran/{slug_nama}/{jenis}_{slug_nama}_{tanggal}.{ext}
 */

/**
 * Buat slug dari nama (huruf kecil, spasi → underscore, hapus karakter khusus)
 */
function slugNama(string $nama): string
{
    $nama = strtolower(trim($nama));
    // Transliterasi umum karakter Indonesia
    $nama = str_replace(['á','à','ä','â'], 'a', $nama);
    $nama = str_replace(['é','è','ë','ê'], 'e', $nama);
    $nama = str_replace(['í','ì','ï','î'], 'i', $nama);
    $nama = str_replace(['ó','ò','ö','ô'], 'o', $nama);
    $nama = str_replace(['ú','ù','ü','û'], 'u', $nama);
    // Gelar akademik: hapus titik dan koma
    $nama = preg_replace('/[,.]/', '', $nama);
    // Hapus karakter non-alphanumeric kecuali spasi
    $nama = preg_replace('/[^a-z0-9 ]/', '', $nama);
    // Spasi → underscore, trim & collapse
    $nama = preg_replace('/\s+/', '_', trim($nama));
    return $nama ?: 'user';
}

/**
 * Generate path TTD terstruktur:
 *   assets/ttd/{slug_nama}/ttd_{jenis}_{slug_nama}_{YYYY-MM-DD}_{uniq}.png
 *
 * @param string $namaUser  - Nama lengkap user yang TTD
 * @param string $jenis     - 'cuti', 'izin', 'pulang_cepat', 'pengaju'
 * @param string $tanggal   - Format YYYY-MM-DD (default: today)
 * @return array ['dir' => string, 'relative' => string, 'filename' => string]
 */
function generateTtdPath(string $namaUser, string $jenis = 'ttd', string $tanggal = ''): array
{
    $slug    = slugNama($namaUser);
    $tgl     = $tanggal ?: date('Y-m-d');
    $uniq    = substr(uniqid(), -6);
    $filename = "ttd_{$jenis}_{$slug}_{$tgl}_{$uniq}.png";
    $relDir  = "assets/ttd/{$slug}";
    $absDir  = __DIR__ . '/../' . $relDir;

    if (!is_dir($absDir)) {
        mkdir($absDir, 0755, true);
    }

    return [
        'dir'      => $absDir,
        'relative' => $relDir . '/' . $filename,
        'filename' => $filename,
        'abs'      => $absDir . '/' . $filename,
    ];
}

/**
 * Generate path Lampiran terstruktur:
 *   assets/lampiran/{slug_nama}/{jenis}_{slug_nama}_{YYYY-MM-DD}_{uniq}.{ext}
 *
 * @param string $namaUser  - Nama lengkap user pemilik pengajuan
 * @param string $jenis     - 'cuti', 'izin', 'pulang_cepat', 'terlambat'
 * @param string $ext       - Ekstensi file (jpg, png, pdf)
 * @param string $tanggal   - Format YYYY-MM-DD (default: today)
 * @return array ['dir' => string, 'relative' => string, 'abs' => string]
 */
function generateLampiranPath(string $namaUser, string $jenis, string $ext, string $tanggal = ''): array
{
    $slug    = slugNama($namaUser);
    $tgl     = $tanggal ?: date('Y-m-d');
    $uniq    = substr(uniqid(), -6);
    $ext     = strtolower(ltrim($ext, '.'));
    $filename = "{$jenis}_{$slug}_{$tgl}_{$uniq}.{$ext}";
    $relDir  = "assets/lampiran/{$slug}";
    $absDir  = __DIR__ . '/../' . $relDir;

    if (!is_dir($absDir)) {
        mkdir($absDir, 0755, true);
    }

    return [
        'dir'      => $absDir,
        'relative' => $relDir . '/' . $filename,
        'filename' => $filename,
        'abs'      => $absDir . '/' . $filename,
    ];
}

/**
 * Handle upload lampiran opsional dari $_FILES
 * Return relative path atau null jika tidak ada upload
 *
 * @param string $fieldName    - Nama input file di form
 * @param string $namaUser     - Nama user pemilik
 * @param string $jenis        - Jenis pengajuan
 * @param string $tanggal      - Tanggal pengajuan
 * @param string|null $errMsg  - Diisi jika ada error (pass by reference)
 */
function handleUploadLampiran(
    string $fieldName,
    string $namaUser,
    string $jenis,
    string $tanggal,
    ?string &$errMsg = null
): ?string {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null; // Tidak ada file atau tidak dipilih
    }

    $fileTmp  = $_FILES[$fieldName]['tmp_name'];
    $origName = $_FILES[$fieldName]['name'];
    $fileSize = $_FILES[$fieldName]['size'];
    $fileExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($fileExt, $allowedExt)) {
        $errMsg = 'Format lampiran tidak didukung! Gunakan JPG, PNG, atau PDF.';
        return null;
    }

    if ($fileSize > 2 * 1024 * 1024) {
        $errMsg = 'Ukuran lampiran maksimal 2MB!';
        return null;
    }

    $pathInfo = generateLampiranPath($namaUser, $jenis, $fileExt, $tanggal);

    if (!move_uploaded_file($fileTmp, $pathInfo['abs'])) {
        $errMsg = 'Gagal mengunggah lampiran!';
        return null;
    }

    return $pathInfo['relative'];
}

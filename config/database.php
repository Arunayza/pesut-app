<?php
/**
 * PESUT - Koneksi Database
 * Menggunakan PDO dengan prepared statements
 */

$is_localhost = php_sapi_name() === 'cli' || (isset($_SERVER['HTTP_HOST']) && (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false));

if ($is_localhost) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'pesut_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // Pengaturan database untuk InfinityFree
    define('DB_HOST', 'sql302.infinityfree.com'); // Ganti dengan MySQL Hostname dari panel InfinityFree (contoh: sql302.infinityfree.com)
    define('DB_NAME', 'if0_41882425_pesut'); // Pastikan nama DB di panel InfinityFree sama dengan ini
    define('DB_USER', 'if0_41882425'); // Sesuai dengan username vPanel / FTP
    define('DB_PASS', '84P8g8wsfrzq82'); // Sesuai dengan password vPanel / FTP
}

define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

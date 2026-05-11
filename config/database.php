<?php
/**
 * PESUT - Koneksi Database
 * Menggunakan PDO dengan prepared statements
 */

$is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;

if ($is_localhost) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'pesut_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // Pengaturan database untuk InfinityFree (Nanti ganti di sini atau biarkan dan edit di server)
    define('DB_HOST', 'sqlxxx.epizy.com'); // Ganti dengan DB Host dari InfinityFree
    define('DB_NAME', 'epiz_xxxx_pesut_db'); // Ganti dengan DB Name dari InfinityFree
    define('DB_USER', 'epiz_xxxx'); // Ganti dengan DB User dari InfinityFree
    define('DB_PASS', 'password_anda'); // Ganti dengan DB Password dari InfinityFree
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

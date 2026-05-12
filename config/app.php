<?php
/**
 * PESUT - Konfigurasi Aplikasi
 */

define('APP_NAME', 'PESUT');
define('APP_FULL_NAME', 'Pengajuan Elektronik Surat Izin & Cuti Terpadu');
define('APP_INSTANSI', 'PTUN Samarinda');
define('APP_VERSION', '2.0.0');

define('JAM_MASUK', '08:00');
define('JAM_PULANG_SENIN_KAMIS', '16:30');
define('JAM_PULANG_JUMAT', '17:00');

define('KUOTA_CUTI_DEFAULT', 12);
define('TAHUN_AKTIF', date('Y'));
define('BASE_URL', 'https://pesut-ptun-samarinda.fwh.is');

date_default_timezone_set('Asia/Makassar');

<?php
require 'config/database.php';
try {
    $pdo->exec('ALTER TABLE pengajuan ADD COLUMN nomor_sk VARCHAR(100) DEFAULT NULL AFTER catatan_approval;');
    echo 'OK';
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo 'OK (Already exists)';
    } else {
        echo $e->getMessage();
    }
}

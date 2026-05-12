<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo->exec("ALTER TABLE ttd_pengajuan DROP INDEX uk_pengajuan_user;");
    echo "Index uk_pengajuan_user dropped.<br>";
} catch (Exception $e) {
    echo "Notice dropping index: " . $e->getMessage() . "<br>";
}

try {
    $pdo->exec("ALTER TABLE ttd_pengajuan ADD COLUMN urutan_ttd INT DEFAULT 1;");
    echo "Column urutan_ttd added.<br>";
} catch (Exception $e) {
    echo "Notice adding column: " . $e->getMessage() . "<br>";
}

try {
    $pdo->exec("ALTER TABLE ttd_pengajuan ADD UNIQUE KEY uk_pengajuan_user_urutan (pengajuan_id, user_id, urutan_ttd);");
    echo "New unique key uk_pengajuan_user_urutan added.<br>";
} catch (Exception $e) {
    echo "Notice adding unique key: " . $e->getMessage() . "<br>";
}

echo "Migration completed.";

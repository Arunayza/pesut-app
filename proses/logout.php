<?php
/**
 * Proses Logout
 */
session_start();
session_unset();
session_destroy();

require_once __DIR__ . '/../config/app.php';
header("Location: " . BASE_URL . "/pages/login.php");
exit;

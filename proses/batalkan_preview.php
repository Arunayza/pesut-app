<?php
/**
 * Batalkan Preview — Hapus data session dan temp files, redirect ke dashboard
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireLogin();

// Clean up temp lampiran jika ada
if (!empty($_SESSION['preview_data']['lampiran_temp']) && file_exists($_SESSION['preview_data']['lampiran_temp'])) {
    @unlink($_SESSION['preview_data']['lampiran_temp']);
}

unset($_SESSION['preview_data']);

setFlash('success', 'Pengajuan dibatalkan.');
redirect(BASE_URL . '/dashboard_v2.php');

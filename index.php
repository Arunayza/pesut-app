<?php
/**
 * Index → Redirect ke Dashboard V2
 * V1 dashboard disimpan di cleanup/v1/index.php
 */
session_start();
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/helpers/functions.php';

requireLogin();

$_SESSION['design_version'] = 'v2';
header('Location: ' . BASE_URL . '/dashboard_v2.php');
exit;

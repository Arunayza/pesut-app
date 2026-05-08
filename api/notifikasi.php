<?php
/**
 * API Notifikasi - GET/POST
 * GET: Ambil notifikasi terbaru + count belum dibaca
 * POST: Tandai dibaca (single/all)
 */
ob_start();

// Paksa session menggunakan cookie yang sama dengan aplikasi utama
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/notifikasi.php';

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Debug: log session state (hapus di production)
// error_log('NOTIF API session: ' . print_r($_SESSION, true));

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'count' => 0, 'notifikasi' => [], 'debug' => 'no_session']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $count      = hitungNotifBelumDibaca($pdo, $userId);
        $notifikasi = ambilNotifikasiTerbaru($pdo, $userId, 15);

        $formatted = array_map(function ($n) {
            return [
                'id'     => (int)$n['id'],
                'judul'  => $n['judul'],
                'pesan'  => $n['pesan'] ?? '',
                'link'   => $n['link'] ?? '#',
                'dibaca' => (bool)$n['dibaca'],
                'waktu'  => waktuRelatif($n['created_at']),
            ];
        }, $notifikasi);

        echo json_encode([
            'count'      => $count,
            'notifikasi' => $formatted,
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB error', 'count' => 0, 'notifikasi' => []]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $aksi  = $input['aksi'] ?? '';

    try {
        if ($aksi === 'baca_semua') {
            tandaiSemuaDibaca($pdo, $userId);
            echo json_encode(['success' => true]);
        } elseif ($aksi === 'baca' && isset($input['id'])) {
            tandaiDibaca($pdo, (int)$input['id'], $userId);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

<?php
require_once '../../includes/session_config.php';
require_once '../../includes/forex_service.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$rangeDays = isset($_GET['range']) ? (int)$_GET['range'] : 7;
try {
    $snapshot = get_latest_forex_snapshot($rangeDays);
    echo json_encode([
        'status' => 'success',
        'data' => $snapshot,
    ]);
} catch (Throwable $e) {
    $cached = function_exists('load_cached_forex_snapshot') ? load_cached_forex_snapshot() : null;
    if ($cached) {
        echo json_encode([
            'status' => 'success',
            'data' => $cached,
            'message' => 'Showing cached forex snapshot. Live NRB feed is currently unavailable.',
        ]);
        exit;
    }
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}

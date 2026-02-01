<?php
require_once '../../includes/session_config.php';
require_once '../../includes/calendar_service.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}


$mode = strtolower($_GET['mode'] ?? 'ad');
$requestedYear = (int)($_GET['year'] ?? date('Y'));
$requestedMonth = (int)($_GET['month'] ?? date('n'));

try {
    // Allow forcing remote usage for testing in non-production environments via ?force_remote=1
    $forceRemote = null;
    if (isset($_GET['force_remote']) && defined('ENVIRONMENT') && ENVIRONMENT !== 'production') {
        $forceRemote = filter_var($_GET['force_remote'], FILTER_VALIDATE_BOOLEAN);
        error_log('fetch_dates.php: force_remote=' . ($forceRemote ? 'true' : 'false'));
    }

    $payload = get_calendar_payload($mode, $requestedYear, $requestedMonth, $forceRemote);
    echo json_encode($payload);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

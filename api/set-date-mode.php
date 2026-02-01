<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/date_preferences.php';

define('JSON_RESPONSE_HEADERS', true);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!$csrfHeader || !$sessionToken || !hash_equals($sessionToken, $csrfHeader)) {
    http_response_code(419);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$rawInput = file_get_contents('php://input');
$decoded = json_decode($rawInput, true);
if (!is_array($decoded)) {
    $decoded = $_POST;
}

$requestedMode = isset($decoded['mode']) ? strtolower(trim($decoded['mode'])) : '';
if (!in_array($requestedMode, ['ad', 'bs'], true)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid mode']);
    exit;
}

$finalMode = hrms_set_date_display_mode($requestedMode);

echo json_encode([
    'status' => 'success',
    'mode' => $finalMode,
]);

<?php
require_once __DIR__ . '/../../includes/mobile_api.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$auth = mobile_require_auth($pdo);
$empId = $auth['employee_id'];

$data = array_merge($_POST, mobile_get_json_body());
$lat = $data['lat'] ?? null;
$lon = $data['lon'] ?? null;
$accuracy = $data['accuracy'] ?? null;
$provider = $data['provider'] ?? 'mobile';
$capturedAt = $data['captured_at'] ?? null;

if (!is_numeric($lat) || !is_numeric($lon)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
    exit;
}

mobile_store_location($pdo, $empId, $lat, $lon, $accuracy, $provider, (string)$auth['token_id'], $capturedAt);

echo json_encode(['success' => true]);

<?php
require_once __DIR__ . '/../../../includes/mobile_api.php';

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
$wifiSsid = $data['wifi_ssid'] ?? null;
$wifiBssid = $data['wifi_bssid'] ?? null;
$capturedAt = $data['captured_at'] ?? null;

if (!is_numeric($lat) || !is_numeric($lon)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Location required']);
    exit;
}

$result = mobile_record_attendance($pdo, $empId, $lat, $lon, $accuracy, $wifiSsid, $wifiBssid, $capturedAt, 'mobile');
if (!$result['success']) {
    http_response_code(403);
}

echo json_encode($result);

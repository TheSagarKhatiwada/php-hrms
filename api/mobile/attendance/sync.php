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
$entries = $data['entries'] ?? [];

if (!is_array($entries) || empty($entries)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No entries to sync']);
    exit;
}

$results = [];
foreach ($entries as $idx => $entry) {
    if (!is_array($entry)) {
        $results[] = ['index' => $idx, 'success' => false, 'message' => 'Invalid entry'];
        continue;
    }
    $lat = $entry['lat'] ?? null;
    $lon = $entry['lon'] ?? null;
    $accuracy = $entry['accuracy'] ?? null;
    $wifiSsid = $entry['wifi_ssid'] ?? null;
    $wifiBssid = $entry['wifi_bssid'] ?? null;
    $capturedAt = $entry['captured_at'] ?? null;
    $reason = !empty($entry['offline']) ? 'mobile_offline' : 'mobile';

    if (!is_numeric($lat) || !is_numeric($lon)) {
        $results[] = ['index' => $idx, 'success' => false, 'message' => 'Location required'];
        continue;
    }

    $result = mobile_record_attendance($pdo, $empId, $lat, $lon, $accuracy, $wifiSsid, $wifiBssid, $capturedAt, $reason);
    $result['index'] = $idx;
    $results[] = $result;
}

echo json_encode([
    'success' => true,
    'results' => $results
]);

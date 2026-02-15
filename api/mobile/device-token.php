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
$token = trim($data['token'] ?? '');
$platform = $data['platform'] ?? 'android';
$deviceId = $data['device_id'] ?? null;

if ($token === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Device token required']);
    exit;
}

$platform = in_array($platform, ['android', 'ios'], true) ? $platform : 'android';

$stmt = $pdo->prepare("INSERT INTO mobile_device_tokens (employee_id, device_id, token, platform, is_active)
    VALUES (:emp, :device_id, :token, :platform, 1)
    ON DUPLICATE KEY UPDATE employee_id = VALUES(employee_id), device_id = VALUES(device_id), platform = VALUES(platform), is_active = 1");
$stmt->execute([
    ':emp' => $empId,
    ':device_id' => $deviceId,
    ':token' => $token,
    ':platform' => $platform
]);

echo json_encode(['success' => true]);

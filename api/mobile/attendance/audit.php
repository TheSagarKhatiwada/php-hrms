<?php
require_once __DIR__ . '/../../../includes/mobile_api.php';

header('Content-Type: application/json');

$auth = mobile_require_auth($pdo);
$empId = $auth['employee_id'];

$geofence = hrms_get_branch_geofence_for_employee($pdo, $empId);
$wifiRequired = false;
$defaultSsid = null;
if (!empty($geofence['branch_id'])) {
    $wifiRequired = mobile_branch_requires_wifi($pdo, $geofence['branch_id']);
    $defaultSsid = mobile_branch_default_ssid($pdo, $geofence['branch_id']);
}

$logs = [];
try {
    $stmt = $pdo->prepare("SELECT latitude, longitude, accuracy_meters, created_at FROM location_logs WHERE employee_id = :emp ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([':emp' => $empId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $logs = [];
}

echo json_encode([
    'success' => true,
    'geofence' => $geofence,
    'wifi_required' => $wifiRequired,
    'default_ssid' => $defaultSsid,
    'location_logs' => $logs,
]);

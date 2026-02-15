<?php
require_once __DIR__ . '/../../includes/mobile_api.php';

header('Content-Type: application/json');

$auth = mobile_require_auth($pdo);
$empId = $auth['employee_id'];

$geofence = hrms_get_branch_geofence_for_employee($pdo, $empId);
if (empty($geofence)) {
    echo json_encode(['success' => true, 'geofence' => null, 'wifi_required' => false, 'wifi_access_points' => []]);
    exit;
}

$wifiRequired = false;
$wifiList = [];
if (!empty($geofence['branch_id'])) {
    $wifiRequired = mobile_branch_requires_wifi($pdo, $geofence['branch_id']);
    $stmt = $pdo->prepare("SELECT ssid, bssid FROM branch_wifi_access_points WHERE branch_id = :branch AND is_active = 1 ORDER BY id ASC");
    $stmt->execute([':branch' => $geofence['branch_id']]);
    $wifiList = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

echo json_encode([
    'success' => true,
    'geofence' => $geofence,
    'wifi_required' => $wifiRequired,
    'wifi_access_points' => $wifiList
]);

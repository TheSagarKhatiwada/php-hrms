<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/utilities.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['allowed' => false, 'message' => 'Unauthorized']);
    exit;
}

$empId = $_SESSION['user_id'];
$geofence = hrms_get_branch_geofence_for_employee($pdo, $empId);

if (empty($geofence) || (int)($geofence['geofence_enabled'] ?? 0) !== 1) {
    echo json_encode(['allowed' => true, 'message' => 'Geofence not enabled']);
    exit;
}

$lat = null;
$lon = null;
try {
    $locStmt = $pdo->prepare("SELECT latitude, longitude FROM location_logs
                              WHERE employee_id = :emp
                              ORDER BY created_at DESC LIMIT 1");
    $locStmt->execute([':emp' => $empId]);
    if ($row = $locStmt->fetch(PDO::FETCH_ASSOC)) {
        $lat = $row['latitude'];
        $lon = $row['longitude'];
    }
} catch (PDOException $e) {
    // ignore
}

if ($lat === null || $lon === null) {
    $metaLoc = $_SESSION['meta']['last_location'] ?? null;
    $lat = $metaLoc['lat'] ?? null;
    $lon = $metaLoc['lon'] ?? null;
}

if ($lat === null || $lon === null) {
    echo json_encode(['allowed' => false, 'message' => 'No recent location found']);
    exit;
}

$allowed = hrms_is_within_geofence($lat, $lon, $geofence);

if ($allowed) {
    echo json_encode(['allowed' => true]);
} else {
    echo json_encode(['allowed' => false, 'message' => 'Outside branch geofence']);
}

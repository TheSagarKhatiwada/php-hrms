<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/csrf_protection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$lat = isset($_POST['lat']) ? trim($_POST['lat']) : null;
$lon = isset($_POST['lon']) ? trim($_POST['lon']) : null;
$acc = isset($_POST['accuracy']) ? trim($_POST['accuracy']) : null;

if (!is_numeric($lat) || !is_numeric($lon)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO location_logs
        (employee_id, session_id, latitude, longitude, accuracy_meters, provider, ip_address, user_agent)
        VALUES (:employee_id, :session_id, :lat, :lon, :accuracy, 'browser', :ip, :ua)");

    $stmt->execute([
        ':employee_id' => $_SESSION['user_id'],
        ':session_id' => session_id(),
        ':lat' => $lat,
        ':lon' => $lon,
        ':accuracy' => is_numeric($acc) ? $acc : null,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
    ]);

    // Update session meta so profile shows last location without extra requests
    if (!isset($_SESSION['meta'])) $_SESSION['meta'] = [];
    $_SESSION['meta']['last_location'] = [
        'lat' => (string)$lat,
        'lon' => (string)$lon,
        'ts' => time()
    ];

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to store location']);
}

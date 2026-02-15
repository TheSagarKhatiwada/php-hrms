<?php
require_once __DIR__ . '/../../includes/mobile_api.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = array_merge($_POST, mobile_get_json_body());
$loginId = trim($data['login_id'] ?? '');
$password = $data['password'] ?? '';
$lat = $data['lat'] ?? null;
$lon = $data['lon'] ?? null;
$accuracy = $data['accuracy'] ?? null;
$deviceId = $data['device_id'] ?? null;
$deviceName = $data['device_name'] ?? null;

if ($loginId === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Login ID and password are required']);
    exit;
}

$stmt = $pdo->prepare("SELECT emp_id, first_name, middle_name, last_name, email, password, login_access, status, role_id, branch, designation_id, user_image FROM employees WHERE email = ? OR emp_id = ? LIMIT 1");
$stmt->execute([$loginId, $loginId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

if ((int)($user['login_access'] ?? 0) !== 1 || ($user['status'] ?? '') !== 'active') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (is_numeric($lat) && is_numeric($lon)) {
    mobile_store_location($pdo, $user['emp_id'], $lat, $lon, $accuracy, 'mobile_login');
}

$token = mobile_issue_token($pdo, $user['emp_id'], $deviceId, $deviceName, 30);

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

echo json_encode([
    'success' => true,
    'token' => $token,
    'employee' => [
        'emp_id' => $user['emp_id'],
        'name' => $fullName,
        'email' => $user['email'],
        'role_id' => $user['role_id'],
        'branch_id' => $user['branch'],
        'designation_id' => $user['designation_id'],
        'user_image' => $user['user_image']
    ]
]);

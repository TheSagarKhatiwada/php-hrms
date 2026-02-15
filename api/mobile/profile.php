<?php
require_once __DIR__ . '/../../includes/mobile_api.php';

header('Content-Type: application/json');

$auth = mobile_require_auth($pdo);
$empId = $auth['employee_id'];

$stmt = $pdo->prepare("SELECT emp_id, first_name, middle_name, last_name, email, office_email, phone, office_phone, address, date_of_birth, join_date, exit_date, branch, designation_id, role_id, user_image, status FROM employees WHERE emp_id = :emp LIMIT 1");
$stmt->execute([':emp' => $empId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Employee not found']);
    exit;
}

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

$user['name'] = $fullName;

unset($user['first_name'], $user['middle_name'], $user['last_name']);

echo json_encode(['success' => true, 'employee' => $user]);

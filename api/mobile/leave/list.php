<?php
require_once __DIR__ . '/../../../includes/mobile_api.php';

header('Content-Type: application/json');

$auth = mobile_require_auth($pdo);
$empId = $auth['employee_id'];

$status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '';
$allowed = ['pending', 'approved', 'rejected', 'cancelled'];
if ($status !== '' && !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$sql = "SELECT lr.id, lr.start_date, lr.end_date, lr.days_requested, lr.status, lr.reason, lr.applied_date,
        lt.name AS leave_type_name
    FROM leave_requests lr
    INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.employee_id = :emp";
$params = [':emp' => $empId];
if ($status !== '') {
    $sql .= ' AND lr.status = :status';
    $params[':status'] = $status;
}
$sql .= ' ORDER BY lr.applied_date DESC LIMIT 100';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success' => true, 'items' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load leave requests']);
}

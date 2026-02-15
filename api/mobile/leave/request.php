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
$leaveTypeId = isset($data['leave_type_id']) ? (int)$data['leave_type_id'] : 0;
$startDate = isset($data['start_date']) ? trim((string)$data['start_date']) : '';
$endDate = isset($data['end_date']) ? trim((string)$data['end_date']) : '';
$reason = isset($data['reason']) ? trim((string)$data['reason']) : '';

if ($leaveTypeId <= 0 || $startDate === '' || $endDate === '' || mb_strlen($reason) < 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid leave input']);
    exit;
}

if (strtotime($startDate) === false || strtotime($endDate) === false || strtotime($startDate) > strtotime($endDate)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid date range']);
    exit;
}

$days = (new DateTime($startDate))->diff(new DateTime($endDate))->days + 1;

try {
    $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, days_requested, status, applied_date)
        VALUES (:emp, :type, :start, :end, :reason, :days, 'pending', NOW())");
    $stmt->execute([
        ':emp' => $empId,
        ':type' => $leaveTypeId,
        ':start' => $startDate,
        ':end' => $endDate,
        ':reason' => $reason,
        ':days' => $days,
    ]);

    echo json_encode(['success' => true, 'message' => 'Leave request submitted']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to submit leave request']);
}

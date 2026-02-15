<?php
require_once __DIR__ . '/../../../includes/mobile_api.php';

header('Content-Type: application/json');

$auth = mobile_require_auth($pdo);
$empId = $auth['employee_id'];

$isAdmin = mobile_is_admin($pdo, $empId);
$canProcess = $isAdmin || mobile_has_permission($pdo, $empId, 'process_attendance_requests');
$canViewRequests = $isAdmin || mobile_has_permission($pdo, $empId, 'request_attendance') || $canProcess;
$canViewAll = $isAdmin || $canProcess;

if (!$canViewRequests) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit < 10) $limit = 10;
if ($limit > 200) $limit = 200;

$allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled'];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$sql = "SELECT ar.*, 
    emp.first_name AS emp_first_name, emp.middle_name AS emp_middle_name, emp.last_name AS emp_last_name, emp.emp_id AS emp_code,
    branch.name AS emp_branch_name,
    requester.first_name AS requester_first_name, requester.last_name AS requester_last_name,
    reviewer.first_name AS reviewer_first_name, reviewer.last_name AS reviewer_last_name
  FROM attendance_requests ar
  LEFT JOIN employees emp ON ar.emp_id = emp.emp_id
  LEFT JOIN branches branch ON emp.branch = branch.id
  LEFT JOIN employees requester ON ar.requested_by = requester.emp_id
    LEFT JOIN employees reviewer ON ar.reviewed_by = reviewer.emp_id
  WHERE 1=1";

$params = [];
if ($status !== '') {
    $sql .= ' AND ar.status = :status';
    $params[':status'] = $status;
}
if (!$canViewAll) {
    $sql .= ' AND ar.requested_by = :requested_by';
    $params[':requested_by'] = $empId;
}

$sql .= ' ORDER BY ar.created_at DESC LIMIT :limitRows';

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limitRows', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = [];
    foreach ($rows as $row) {
        $name = trim(($row['emp_first_name'] ?? '') . ' ' . ($row['emp_middle_name'] ?? '') . ' ' . ($row['emp_last_name'] ?? ''));
        $requester = trim(($row['requester_first_name'] ?? '') . ' ' . ($row['requester_last_name'] ?? ''));
        $reviewer = trim(($row['reviewer_first_name'] ?? '') . ' ' . ($row['reviewer_last_name'] ?? ''));
        $items[] = [
            'id' => (int)($row['id'] ?? 0),
            'employee_name' => $name,
            'employee_id' => $row['emp_code'] ?? $row['emp_id'] ?? '',
            'branch_name' => $row['emp_branch_name'] ?? '',
            'request_date' => $row['request_date'] ?? '',
            'request_time' => $row['request_time'] ?? '',
            'reason_label' => $row['reason_label'] ?? '',
            'status' => $row['status'] ?? 'pending',
            'requested_by_name' => $requester,
            'remarks' => $row['remarks'] ?? '',
            'created_at' => $row['created_at'] ?? '',
            'reviewed_at' => $row['reviewed_at'] ?? '',
            'review_notes' => $row['review_notes'] ?? '',
            'reviewed_by_name' => $reviewer,
        ];
    }

    echo json_encode([
        'success' => true,
        'can_process' => $canProcess,
        'items' => $items,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load requests']);
}

<?php
require_once __DIR__ . '/../../../includes/mobile_api.php';
require_once __DIR__ . '/../../../includes/notification_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$auth = mobile_require_auth($pdo);
$empId = $auth['employee_id'];

$isAdmin = mobile_is_admin($pdo, $empId);
$canProcess = $isAdmin || mobile_has_permission($pdo, $empId, 'process_attendance_requests');
if (!$canProcess) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$data = array_merge($_POST, mobile_get_json_body());
$requestId = isset($data['request_id']) ? (int)$data['request_id'] : 0;
$action = isset($data['action']) ? strtolower(trim((string)$data['action'])) : '';
$reviewNotes = isset($data['review_notes']) ? trim((string)$data['review_notes']) : '';

if ($requestId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM attendance_requests WHERE id = ? LIMIT 1');
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Attendance request not found']);
        exit;
    }

    if (($request['status'] ?? '') !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Request already processed']);
        exit;
    }

    $pdo->beginTransaction();

    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $reviewerName = '';
    try {
        $nameStmt = $pdo->prepare('SELECT first_name, middle_name, last_name FROM employees WHERE emp_id = ? LIMIT 1');
        $nameStmt->execute([$empId]);
        $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);
        if ($nameRow) {
            $reviewerName = trim(implode(' ', array_filter([
                $nameRow['first_name'] ?? '',
                $nameRow['middle_name'] ?? '',
                $nameRow['last_name'] ?? ''
            ])));
        }
    } catch (Throwable $e) {
        $reviewerName = '';
    }

    $reviewPrefix = $newStatus === 'approved' ? 'Approved by' : 'Rejected by';
    if ($reviewerName !== '') {
        $reviewPrefix .= ' ' . $reviewerName;
    } else {
        $reviewPrefix .= ' ' . $empId;
    }

    $notes = $reviewNotes;
    if ($reviewPrefix !== '') {
        $notes = $notes !== '' ? ($reviewPrefix . ' — ' . $notes) : $reviewPrefix;
    }

    $updateStmt = $pdo->prepare('UPDATE attendance_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ? WHERE id = ?');
    $updateStmt->execute([$newStatus, $empId, $notes, $requestId]);

    if ($action === 'approve') {
        $checkLog = $pdo->prepare('SELECT id FROM attendance_logs WHERE emp_id = ? AND date = ? AND time = ? LIMIT 1');
        $checkLog->execute([$request['emp_id'], $request['request_date'], $request['request_time']]);

        if (!$checkLog->fetchColumn()) {
            $manualReason = ($request['reason_code'] ?? '') . ' || Appd. by ' . ($reviewerName !== '' ? $reviewerName : $empId);
            if (!empty($request['remarks'])) {
                $manualReason .= ' — ' . $request['remarks'];
            }
            $insertLog = $pdo->prepare('INSERT INTO attendance_logs (emp_id, date, time, method, manual_reason) VALUES (?, ?, ?, ?, ?)');
            $insertLog->execute([
                $request['emp_id'],
                $request['request_date'],
                $request['request_time'],
                1,
                $manualReason,
            ]);

            $logId = $pdo->lastInsertId();
            if ($logId) {
                $linkStmt = $pdo->prepare('UPDATE attendance_requests SET attendance_log_id = ? WHERE id = ?');
                $linkStmt->execute([$logId, $requestId]);
            }
        }
    }

    $pdo->commit();

    $title = 'Attendance Request ' . ucfirst($newStatus);
    $message = 'Your attendance request for ' . ($request['request_date'] ?? '') . ' has been ' . $newStatus . '.';
    $type = $newStatus === 'approved' ? 'success' : 'danger';
    if (!empty($request['requested_by'])) {
        notify_user($request['requested_by'], $title, $message, $type, 'modules/attendance/attendance.php');
    }

    echo json_encode(['success' => true, 'message' => 'Request ' . $newStatus . ' successfully']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process request']);
}

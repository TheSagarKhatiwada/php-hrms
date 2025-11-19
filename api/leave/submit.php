<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../modules/leave/accrual.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    // Resolve employee id (emp_id)
    $stmt = $pdo->prepare('SELECT emp_id FROM employees WHERE emp_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Employee not found');
    }
    $employeeId = $row['emp_id'];

    $leave_type_id = isset($_POST['leave_type_id']) ? (int)$_POST['leave_type_id'] : 0;
    $start_date    = $_POST['start_date'] ?? '';
    $end_date      = $_POST['end_date'] ?? '';
    $reason        = trim($_POST['reason'] ?? '');
    $is_half_day   = isset($_POST['is_half_day']) ? 1 : 0;
    $half_day_period = $is_half_day ? ($_POST['half_day_period'] ?? null) : null;

    if (!$leave_type_id || !$start_date || !$end_date || strlen($reason) < 10) {
        throw new Exception('Invalid input');
    }

    if (strtotime($start_date) > strtotime($end_date)) {
        throw new Exception('End date cannot be earlier than start date.');
    }

    $today = strtotime(date('Y-m-d'));
    if (strtotime($start_date) < $today) {
        throw new Exception('Cannot apply for leave in the past.');
    }

    // Calculate days
    $days = $is_half_day ? 0.5 : ((new DateTime($start_date))->diff(new DateTime($end_date))->days + 1);

    // Check balance via accrual
    $balance = checkLeaveBalance($employeeId, $leave_type_id, $days);
    if (empty($balance['can_apply'])) {
        throw new Exception($balance['message'] ?? 'Insufficient balance');
    }

    // Check overlapping
    $chk = $pdo->prepare("SELECT id FROM leave_requests WHERE employee_id = ? AND status != 'rejected' AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?) OR (start_date >= ? AND end_date <= ?))");
    $chk->execute([$employeeId, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date]);
    if ($chk->rowCount() > 0) {
        throw new Exception('You already have a leave request for the selected dates.');
    }

    // Insert (handle schemas that require total_days column)
    $hasTotalDays = false;
    try {
        $chkCol = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'total_days'");
        $hasTotalDays = (bool)$chkCol->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ie) { /* ignore */ }

    if ($hasTotalDays) {
        $sql = 'INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, days_requested, total_days, status, is_half_day, half_day_period, applied_date) VALUES (?, ?, ?, ?, ?, ?, ?, "pending", ?, ?, NOW())';
        $params = [$employeeId, $leave_type_id, $start_date, $end_date, $reason, $days, $days, $is_half_day, $half_day_period];
    } else {
        $sql = 'INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, days_requested, status, is_half_day, half_day_period, applied_date) VALUES (?, ?, ?, ?, ?, ?, "pending", ?, ?, NOW())';
        $params = [$employeeId, $leave_type_id, $start_date, $end_date, $reason, $days, $is_half_day, $half_day_period];
    }

    $ins = $pdo->prepare($sql);
    $ok = $ins->execute($params);
    if (!$ok) {
        throw new Exception('Error submitting leave request.');
    }

    $requestId = $pdo->lastInsertId();
    include_once '../../modules/leave/notifications.php';
    sendLeaveNotification('submitted', $requestId);

    echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully!', 'request_id' => $requestId]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

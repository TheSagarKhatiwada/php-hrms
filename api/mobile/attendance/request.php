<?php
require_once __DIR__ . '/../../../includes/mobile_api.php';
require_once __DIR__ . '/../../../includes/settings.php';
require_once __DIR__ . '/../../../includes/reason_helpers.php';
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
$canRequest = $isAdmin || mobile_has_permission($pdo, $empId, 'request_attendance');
$canRequestForOthers = $isAdmin || mobile_has_permission($pdo, $empId, 'request_attendance_for_others');

if (!$canRequest) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$data = array_merge($_POST, mobile_get_json_body());
$targetEmpId = isset($data['emp_id']) ? trim((string)$data['emp_id']) : '';
$requestDate = isset($data['request_date']) ? trim((string)$data['request_date']) : '';
$requestTime = isset($data['request_time']) ? trim((string)$data['request_time']) : '';
$reasonCode = isset($data['reason_code']) ? trim((string)$data['reason_code']) : '';
$remarks = isset($data['remarks']) ? trim((string)$data['remarks']) : '';

if ($targetEmpId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Employee is required']);
    exit;
}

if (!$isAdmin && !$canRequestForOthers && $targetEmpId !== $empId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not allowed to request for others']);
    exit;
}

if ($requestDate === '' || $requestTime === '' || $reasonCode === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Date, time, and reason are required']);
    exit;
}

$reasonMap = function_exists('hrms_reason_label_map') ? hrms_reason_label_map() : [];
if (!array_key_exists($reasonCode, $reasonMap)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid reason']);
    exit;
}

if (strlen($requestTime) === 5) {
    $requestTime .= ':00';
}

$timezone = function_exists('get_setting') ? get_setting('timezone', 'UTC') : 'UTC';
try {
    $tz = new DateTimeZone($timezone);
} catch (Exception $e) {
    $tz = new DateTimeZone('UTC');
}

$now = new DateTimeImmutable('now', $tz);
$minAllowedDate = $now->modify('-30 days');

$requestDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $requestDate . ' ' . $requestTime, $tz);
$dateTimeErrors = DateTimeImmutable::getLastErrors();
if (!$requestDateTime || ($dateTimeErrors && ($dateTimeErrors['warning_count'] > 0 || $dateTimeErrors['error_count'] > 0))) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid date or time']);
    exit;
}

if ($requestDateTime > $now) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Future requests are not allowed']);
    exit;
}

if ($requestDateTime < $minAllowedDate) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Requests are limited to last 30 days']);
    exit;
}

try {
    $empStmt = $pdo->prepare('SELECT emp_id FROM employees WHERE emp_id = ? LIMIT 1');
    $empStmt->execute([$targetEmpId]);
    if (!$empStmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to validate employee']);
    exit;
}

try {
    $duplicateStmt = $pdo->prepare('SELECT id FROM attendance_requests WHERE emp_id = ? AND request_date = ? AND status = ? LIMIT 1');
    $duplicateStmt->execute([$targetEmpId, $requestDateTime->format('Y-m-d'), 'pending']);
    if ($duplicateStmt->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'A pending request already exists']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to check existing requests']);
    exit;
}

$reasonLabel = $reasonMap[$reasonCode] ?? $reasonCode;
$trimmedRemarks = $remarks !== '' ? mb_substr($remarks, 0, 500) : null;

try {
    $insertStmt = $pdo->prepare('INSERT INTO attendance_requests (emp_id, requested_by, request_date, request_time, requested_method, reason_code, reason_label, remarks, status)
        VALUES (:emp_id, :requested_by, :request_date, :request_time, :requested_method, :reason_code, :reason_label, :remarks, :status)');
    $insertStmt->execute([
        ':emp_id' => $targetEmpId,
        ':requested_by' => $empId,
        ':request_date' => $requestDateTime->format('Y-m-d'),
        ':request_time' => $requestDateTime->format('H:i:s'),
        ':requested_method' => 'M',
        ':reason_code' => $reasonCode,
        ':reason_label' => $reasonLabel,
        ':remarks' => $trimmedRemarks,
        ':status' => 'pending'
    ]);

    $requesterName = 'Employee';
    try {
        $nameStmt = $pdo->prepare('SELECT first_name, last_name FROM employees WHERE emp_id = ?');
        $nameStmt->execute([$empId]);
        $u = $nameStmt->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $requesterName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        }
    } catch (Throwable $e) {}

    $notifTitle = 'New Attendance Request';
    $notifMessage = $requesterName . ' has requested attendance adjustment for ' . $requestDateTime->format('Y-m-d') . '.';
    notify_system($notifTitle, $notifMessage, 'info', true);

    echo json_encode(['success' => true, 'message' => 'Attendance request submitted']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to submit request']);
}

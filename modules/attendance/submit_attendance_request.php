<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/settings.php';
require_once '../../includes/reason_helpers.php';
require_once '../../includes/csrf_protection.php';
require_once '../../includes/notification_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('error', 'Invalid request method.');
    header('Location: attendance.php');
    exit();
}

// Validate CSRF Token
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('error', 'Invalid security token. Please try again.');
    header('Location: attendance.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    set_flash_message('error', 'Please sign in to request attendance changes.');
    header('Location: ../../index.php');
    exit();
}

$currentUserId = $_SESSION['user_id'];
$isAdmin = function_exists('is_admin') && is_admin();

$empId = isset($_POST['emp_id']) ? trim((string)$_POST['emp_id']) : '';
$requestDate = isset($_POST['request_date']) ? trim((string)$_POST['request_date']) : '';
$requestTime = isset($_POST['request_time']) ? trim((string)$_POST['request_time']) : '';
$reasonCode = isset($_POST['reason_code']) ? trim((string)$_POST['reason_code']) : '';
$remarks = isset($_POST['remarks']) ? trim((string)$_POST['remarks']) : '';

if ($empId === '') {
    set_flash_message('error', 'Employee information is missing.');
    header('Location: attendance.php');
    exit();
}

// Non-admin users can only file requests for themselves
if (!$isAdmin && $empId !== $currentUserId) {
    set_flash_message('error', 'You are not allowed to request attendance for another employee.');
    header('Location: attendance.php');
    exit();
}

if ($requestDate === '' || $requestTime === '' || $reasonCode === '') {
    set_flash_message('error', 'Date, time, and reason are required.');
    header('Location: attendance.php');
    exit();
}

$reasonMap = function_exists('hrms_reason_label_map') ? hrms_reason_label_map() : [];
if (!array_key_exists($reasonCode, $reasonMap)) {
    set_flash_message('error', 'Please choose a valid manual attendance reason.');
    header('Location: attendance.php');
    exit();
}

// Normalize time to HH:MM:SS
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
    set_flash_message('error', 'Please provide a valid date and time.');
    header('Location: attendance.php');
    exit();
}

if ($requestDateTime > $now) {
    set_flash_message('error', 'You cannot request attendance for a future date or time.');
    header('Location: attendance.php');
    exit();
}

if ($requestDateTime < $minAllowedDate) {
    set_flash_message('error', 'Requests are limited to the last 30 days.');
    header('Location: attendance.php');
    exit();
}

// Validate employee exists
try {
    $empStmt = $pdo->prepare('SELECT emp_id FROM employees WHERE emp_id = ? LIMIT 1');
    $empStmt->execute([$empId]);
    if (!$empStmt->fetchColumn()) {
        set_flash_message('error', 'Employee record not found.');
        header('Location: attendance.php');
        exit();
    }
} catch (Exception $e) {
    set_flash_message('error', 'Unable to validate employee record.');
    header('Location: attendance.php');
    exit();
}

// Prevent duplicate pending requests for the same day and entry
try {
    $duplicateStmt = $pdo->prepare('SELECT id FROM attendance_requests WHERE emp_id = ? AND request_date = ? AND status = ? LIMIT 1');
    $duplicateStmt->execute([$empId, $requestDateTime->format('Y-m-d'), 'pending']);
    if ($duplicateStmt->fetchColumn()) {
        set_flash_message('warning', 'You already have a pending request for this entry. Please wait for review.');
        header('Location: attendance.php');
        exit();
    }
} catch (Exception $e) {
    set_flash_message('error', 'Unable to check existing requests. Please try again.');
    header('Location: attendance.php');
    exit();
}

$reasonLabel = $reasonMap[$reasonCode] ?? $reasonCode;
$trimmedRemarks = $remarks !== '' ? mb_substr($remarks, 0, 500) : null;

try {
    $insertStmt = $pdo->prepare('INSERT INTO attendance_requests (emp_id, requested_by, request_date, request_time, requested_method, reason_code, reason_label, remarks, status)
        VALUES (:emp_id, :requested_by, :request_date, :request_time, :requested_method, :reason_code, :reason_label, :remarks, :status)');
    $insertStmt->execute([
        ':emp_id' => $empId,
        ':requested_by' => $currentUserId,
        ':request_date' => $requestDateTime->format('Y-m-d'),
        ':request_time' => $requestDateTime->format('H:i:s'),
        ':requested_method' => 'M',
        ':reason_code' => $reasonCode,
        ':reason_label' => $reasonLabel,
        ':remarks' => $trimmedRemarks,
        ':status' => 'pending'
    ]);

    set_flash_message('success', 'Attendance request submitted for review.');

    // Notify Admins
    $requesterName = 'Employee';
    try {
        $nameStmt = $pdo->prepare("SELECT first_name, last_name FROM employees WHERE emp_id = ?");
        $nameStmt->execute([$currentUserId]);
        $u = $nameStmt->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $requesterName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        }
    } catch (Exception $e) {}

    $notifTitle = "New Attendance Request";
    $notifMessage = "$requesterName has requested attendance adjustment for " . $requestDateTime->format('Y-m-d') . ".";
    // Notify system logs and admins
    notify_system($notifTitle, $notifMessage, 'info', true);

} catch (Exception $e) {
    error_log('Attendance request insert failed: ' . $e->getMessage());
    set_flash_message('error', 'Unable to submit your request right now. Please try again later.');
}

header('Location: attendance.php');
exit();

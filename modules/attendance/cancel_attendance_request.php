<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/csrf_protection.php';

// 1. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('error', 'Invalid request method.');
    header('Location: attendance.php?action=request');
    exit();
}

// 2. Check CSRF Token
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('error', 'Invalid security token. Please try again.');
    header('Location: attendance.php?action=request');
    exit();
}

// 3. Get Inputs
$requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$currentUserEmpId = $_SESSION['emp_id'] ?? null;

if ($requestId <= 0 || !$currentUserEmpId) {
    set_flash_message('error', 'Invalid request parameters.');
    header('Location: attendance.php?action=request');
    exit();
}

try {
    // 4. Fetch Request Details
    $stmt = $pdo->prepare("SELECT * FROM attendance_requests WHERE id = ? LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        set_flash_message('error', 'Attendance request not found.');
        header('Location: attendance.php?action=request');
        exit();
    }

    // 5. Verify Ownership
    if ($request['emp_id'] !== $currentUserEmpId) {
        set_flash_message('error', 'You are not authorized to cancel this request.');
        header('Location: attendance.php?action=request');
        exit();
    }

    // 6. Verify Status
    if ($request['status'] !== 'pending') {
        set_flash_message('warning', 'Only pending requests can be cancelled.');
        header('Location: attendance.php?action=request');
        exit();
    }

    // 7. Cancel Request
    $updateStmt = $pdo->prepare("UPDATE attendance_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$requestId]);

    set_flash_message('success', 'Attendance request cancelled successfully.');
    header('Location: attendance.php?action=request');
    exit();

} catch (Exception $e) {
    error_log("Error cancelling attendance request: " . $e->getMessage());
    set_flash_message('error', 'An error occurred while processing your request.');
    header('Location: attendance.php?action=request');
    exit();
}

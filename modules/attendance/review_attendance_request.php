<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/csrf_protection.php';
require_once '../../includes/notification_helpers.php';

// 1. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('error', 'Invalid request method.');
    header('Location: attendance.php');
    exit();
}

// 2. Check CSRF Token
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('error', 'Invalid security token. Please try again.');
    header('Location: attendance.php');
    exit();
}

// 3. Check Permissions
$canManageAttendance = (function_exists('is_admin') && is_admin()) || (function_exists('has_permission') && has_permission('manage_attendance'));
if (!$canManageAttendance) {
    set_flash_message('error', 'You do not have permission to review attendance requests.');
    header('Location: attendance.php');
    exit();
}

// 4. Get Inputs
$requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$action = isset($_POST['review_action']) ? strtolower(trim($_POST['review_action'])) : '';
$reviewerId = $_SESSION['user_id'] ?? null;

if ($requestId <= 0 || !in_array($action, ['approve', 'reject'])) {
    set_flash_message('error', 'Invalid request parameters.');
    header('Location: attendance.php');
    exit();
}

try {
    // 5. Fetch Request Details
    $stmt = $pdo->prepare("SELECT * FROM attendance_requests WHERE id = ? LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        set_flash_message('error', 'Attendance request not found.');
        header('Location: attendance.php');
        exit();
    }

    if ($request['status'] !== 'pending') {
        set_flash_message('warning', 'This request has already been processed.');
        header('Location: attendance.php');
        exit();
    }

    $pdo->beginTransaction();

    // 6. Update Request Status
    $reviewNotes = isset($_POST['review_notes']) ? trim($_POST['review_notes']) : '';
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    $reviewerName = '';
    if (!empty($reviewerId)) {
        try {
            $nameStmt = $pdo->prepare('SELECT first_name, middle_name, last_name FROM employees WHERE emp_id = ? LIMIT 1');
            $nameStmt->execute([$reviewerId]);
            $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);
            if ($nameRow) {
                $reviewerName = trim(implode(' ', array_filter([
                    $nameRow['first_name'] ?? '',
                    $nameRow['middle_name'] ?? '',
                    $nameRow['last_name'] ?? ''
                ])));
            }
        } catch (Exception $e) {
            $reviewerName = '';
        }
    }

    $reviewPrefix = $newStatus === 'approved' ? 'Approved by' : 'Rejected by';
    if (!empty($reviewerName)) {
        $reviewPrefix .= ' ' . $reviewerName;
    } elseif (!empty($reviewerId)) {
        $reviewPrefix .= ' ' . $reviewerId;
    }

    if ($reviewPrefix !== '') {
        if ($reviewNotes !== '') {
            $reviewNotes = $reviewPrefix . ' — ' . $reviewNotes;
        } else {
            $reviewNotes = $reviewPrefix;
        }
    }

    $updateStmt = $pdo->prepare("UPDATE attendance_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $reviewerId, $reviewNotes, $requestId]);

    // 7. If Approved, Insert into Attendance Logs
    if ($action === 'approve') {
        // Check for existing log to avoid duplicates (though request logic should have caught this, race conditions exist)
        $checkLog = $pdo->prepare("SELECT id FROM attendance_logs WHERE emp_id = ? AND date = ? AND time = ? LIMIT 1");
        $checkLog->execute([$request['emp_id'], $request['request_date'], $request['request_time']]);
        
        if (!$checkLog->fetchColumn()) {
            $insertLog = $pdo->prepare("INSERT INTO attendance_logs (emp_id, date, time, method, manual_reason) VALUES (?, ?, ?, ?, ?)");
            // method 1 = Manual
            // manual_reason format: "reason_code || Appd. by Reviewer [— remarks]"
            $manualReason = $request['reason_code'];
            $reviewerLabel = '';
            if (!empty($reviewerName)) {
                $reviewerLabel = $reviewerName;
            } elseif (!empty($reviewerId)) {
                $reviewerLabel = $reviewerId;
            }
            if ($reviewerLabel !== '') {
                $manualReason .= ' || Appd. by ' . $reviewerLabel;
            }
            if (!empty($request['remarks'])) {
                $manualReason .= ' — ' . $request['remarks'];
            }
            
            $insertLog->execute([
                $request['emp_id'],
                $request['request_date'],
                $request['request_time'],
                1, // Manual
                $manualReason
            ]);
            
            // Link the created log to the request
            $logId = $pdo->lastInsertId();
            if ($logId) {
                $linkStmt = $pdo->prepare("UPDATE attendance_requests SET attendance_log_id = ? WHERE id = ?");
                $linkStmt->execute([$logId, $requestId]);
            }
        }
    }

    $pdo->commit();

    // 8. Send Notification to Requester
    $requesterId = $request['requested_by']; // Or emp_id? Usually requested_by is the user who made the request (could be self)
    // If requested_by is different from emp_id (e.g. manager requested for employee), maybe notify both? 
    // For now, notify the person who made the request.
    
    $title = "Attendance Request " . ucfirst($newStatus);
    $message = "Your attendance request for " . $request['request_date'] . " has been " . $newStatus . ".";
    $type = ($newStatus === 'approved') ? 'success' : 'danger';
    
    if ($requesterId) {
        notify_user($requesterId, $title, $message, $type, 'modules/attendance/attendance.php');
    }

    set_flash_message('success', 'Request ' . $newStatus . ' successfully.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Review attendance request failed: ' . $e->getMessage());
    set_flash_message('error', 'An error occurred while processing the request.');
}

header('Location: attendance.php?action=request'); // Return to request tab
exit();

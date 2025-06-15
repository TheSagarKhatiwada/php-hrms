<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get employee ID for current user (primary key ID, not emp_id)
// Note: $user_id from session is already the primary key ID from employees table
$currentEmployeeId = $user_id;

if (!$currentEmployeeId) {
    echo json_encode(['success' => false, 'message' => 'Employee not found']);
    exit();
}

if ($_POST && isset($_POST['request_id'])) {
    try {
        $request_id = intval($_POST['request_id']);
        // Verify the request belongs to the current user and is pending
        $check_sql = "SELECT id, status FROM leave_requests WHERE id = ? AND employee_id = ? AND status = 'pending'";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$request_id, $currentEmployeeId]);
        $request = $check_stmt->fetch();
        
        if (!$request) {
            throw new Exception("Leave request not found or cannot be cancelled.");
        }
        
        // Update the request status to cancelled
        $update_sql = "UPDATE leave_requests SET status = 'cancelled', reviewed_date = NOW() WHERE id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        
        if ($update_stmt->execute([$request_id])) {
            // Send notification
            include_once 'notifications.php';
            sendLeaveNotification('cancelled', $request_id);
            
            echo json_encode(['success' => true, 'message' => 'Leave request cancelled successfully']);
        } else {
            throw new Exception("Failed to cancel leave request");
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>

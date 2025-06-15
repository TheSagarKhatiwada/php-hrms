<?php
// Include session configuration first to ensure session is available
require_once '../../includes/session_config.php';

// Enable error reporting but don't display errors on screen
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include required files
try {
    require_once '../../includes/db_connection.php';
    require_once '../../includes/settings.php';
    require_once '../../includes/notification_helpers.php';
} catch (Exception $e) {
    // If any required file is missing, set error and redirect
    $_SESSION['error'] = 'System error: Failed to load required dependencies';
    header('Location: attendance.php');
    exit();
}

// Helper function to notify user of attendance actions
function notify_employee_attendance($emp_id, $action, $details) {
    if (function_exists('notify_attendance')) {
        return notify_attendance($emp_id, $action, $details);
    }
    return false;
}

// Test database connection
try {
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    // If database connection fails, set error and redirect
    $_SESSION['error'] = 'Database connection error. Please try again or contact support.';
    header('Location: attendance.php');
    exit();
}

// Process the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle edit attendance form submission
    if (isset($_POST['attendanceId']) && !empty($_POST['attendanceId'])) {
        // Get form data
        $attendanceId = $_POST['attendanceId'];
        $attendanceDate = $_POST['attendanceDate'];
        $attendanceTime = $_POST['attendanceTime'];
        $reason = $_POST['reason'];
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
        
        // Validate required fields
        if (empty($attendanceDate) || empty($attendanceTime) || empty($reason)) {
            $_SESSION['error'] = 'All required fields must be filled.';
            header('Location: attendance.php');
            exit();
        }
        
        // Store reason and remarks with spaced separator
        $manualReason = $reason;
        if (!empty($remarks)) {
            $manualReason .= ' || ' . $remarks;
        }
        
        try {
            // Update the attendance record
            $stmt = $pdo->prepare("UPDATE attendance_logs SET date = ?, time = ?, manual_reason = ? WHERE id = ?");
            $result = $stmt->execute([$attendanceDate, $attendanceTime, $manualReason, $attendanceId]);
            
            if ($result && $stmt->rowCount() > 0) {
                // Try to send notification but don't let it break the update process
                try {
                    if (function_exists('notify_employee_attendance')) {
                        // Get employee ID for notification
                        $empStmt = $pdo->prepare("SELECT emp_Id FROM attendance_logs WHERE id = ?");
                        $empStmt->execute([$attendanceId]);
                        $empData = $empStmt->fetch(PDO::FETCH_ASSOC);
                        if ($empData) {
                            notify_employee_attendance($empData['emp_Id'], 'attendance_updated', $attendanceDate . ' ' . $attendanceTime);
                        }
                    }
                } catch (Exception $e) {
                    // Just log the error, don't prevent successful update
                    error_log("Error sending update notification: " . $e->getMessage());
                }
                
                $_SESSION['success'] = 'Attendance record updated successfully.';
                header('Location: attendance.php');
                exit();
            } else {
                $_SESSION['error'] = 'No changes were made or attendance record not found.';
                header('Location: attendance.php');
                exit();
            }
        } catch (PDOException $e) {
            $errorMessage = $e->getMessage();
            error_log("Database error in attendance update: " . $errorMessage);
            $_SESSION['error'] = 'Database error. Please try again or contact support.';
            header('Location: attendance.php');
            exit();
        }
    } else {
        $_SESSION['error'] = 'Invalid attendance record specified.';
        header('Location: attendance.php');
        exit();
    }
} else {
    // If not a POST request, redirect back to attendance page
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: attendance.php');
    exit();
}
?>
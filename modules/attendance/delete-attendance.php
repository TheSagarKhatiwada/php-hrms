<?php
// Use shared session configuration and utilities
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/utilities.php';
require_once __DIR__ . '/../../includes/db_connection.php';

// Authorization: only admins or users with `manage_attendance` permission may delete
$canManageAttendance = (function_exists('is_admin') && is_admin()) || (function_exists('has_permission') && has_permission('manage_attendance'));
if (!$canManageAttendance) {
    $_SESSION['error'] = 'Unauthorized action.';
    header('Location: attendance.php');
    exit();
}

// Check if the 'id' parameter is provided in the URL
if (isset($_GET['id'])) {
    // Get the ID from the URL
    $attendanceId = $_GET['id'];

    // Before deleting ensure the record exists and is a manual entry (method == 1)
    $checkStmt = $pdo->prepare("SELECT method FROM attendance_logs WHERE id = :id LIMIT 1");
    $checkStmt->bindParam(':id', $attendanceId, PDO::PARAM_INT);
    $checkStmt->execute();
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        $_SESSION['error'] = "Attendance record not found.";
        header('Location: attendance.php');
        exit();
    }

    // Only allow deletion of manual logs (method == 1)
    if ((string)$existing['method'] !== '1') {
        $_SESSION['error'] = 'Only manual attendance records may be deleted.';
        header('Location: attendance.php');
        exit();
    }

    // Prepare the DELETE SQL query
    $sql = "DELETE FROM attendance_logs WHERE id = :id";

    try {
        // Prepare the statement
        $stmt = $pdo->prepare($sql);

        // Bind the parameter
        $stmt->bindParam(':id', $attendanceId, PDO::PARAM_INT);

        // Execute the query
        if ($stmt->execute()) {
            $_SESSION['success'] = "Attendance record deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting attendance record";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "Invalid attendance ID";
}

// Redirect back to the attendance list
header("Location: attendance.php");
exit();

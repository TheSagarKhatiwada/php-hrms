<?php
// Include session configuration before starting any session
require_once 'includes/session_config.php';

// Add session_start if not already started in an included file
session_start();
include 'includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Prepare SQL statement
        $sql = "UPDATE attendance_logs SET date = ?, time = ?, manual_reason = ? WHERE id = ?";
        
        // Get values from POST
        $id = $_POST['id'];
        $date = $_POST['attendanceDate'];
        $time = $_POST['attendanceTime'];
        $reason = $_POST['reason'];
        $remarks = $_POST['remarks'];
        
        // Combine reason and remarks
        $manual_reason = $remarks ? $reason . " || " . $remarks : $reason;
        
        // Execute query
        $stmt = $pdo->prepare($sql);
        if($stmt->execute([$date, $time, $manual_reason, $id])) {
            $_SESSION['success'] = 'Attendance record updated successfully';
        } else {
            $_SESSION['error'] = 'Failed to update attendance record';
        }
        header("Location: attendance.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header("Location: attendance.php");
        exit();
    }
} else {
    $_SESSION['error'] = 'Invalid request method';
    header("Location: attendance.php");
    exit();
}
?>
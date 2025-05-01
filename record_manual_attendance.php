<?php
// This file has error reporting enabled
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include session configuration first to avoid conflicts
require_once 'includes/session_config.php';

// Don't start a new session if one already exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Access denied.";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Include the database connection file
    if (!defined('DB_CONNECTION_INCLUDED')) {
        require_once 'includes/db_connection.php';
        define('DB_CONNECTION_INCLUDED', true);
    }

    // Get form data
    $empId = $_POST['empId'];
    $attendanceDate = $_POST['attendanceDate'];
    $attendanceTime = $_POST['attendanceTime'];
    $reason = $_POST['reason'];
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

    // Store reason and remarks with spaced separator
    $manualReason = $reason;
    if (!empty($remarks)) {
        $manualReason .= ' || ' . $remarks;
    }

    try {
        // SQL query to insert data into the table
        $sql = "INSERT INTO attendance_logs (emp_Id, date, time, method, manual_reason) 
                VALUES (:empId, :attendanceDate, :attendanceTime, 1, :manualReason)";
        
        // Prepare statement
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':empId', $empId);
        $stmt->bindParam(':attendanceDate', $attendanceDate);
        $stmt->bindParam(':attendanceTime', $attendanceTime);
        $stmt->bindParam(':manualReason', $manualReason);
        
        // Execute statement
        $stmt->execute();

        // Set notification message in session
        $_SESSION['success'] = "Manual attendance recorded successfully";
        header("Location: attendance.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error recording manual attendance: " . $e->getMessage();
        header("Location: attendance.php");
        exit();
    }
}
?>

<?php
// Start session
session_start();

// Include the database connection file
require_once 'includes/db_connection.php';

// Check if the 'id' parameter is provided in the URL
if (isset($_GET['id'])) {
    // Get the ID from the URL
    $attendanceId = $_GET['id'];

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

<?php
include 'includes/db_connection.php'; // Ensure this file contains the PDO connection ($pdo)
require_once __DIR__ . '/includes/header.php';

try {
    // SQL query to update attendance_log with emp_Id from employees based on machine_id
    $sql = "UPDATE attendance_logs a JOIN employees e ON a.mach_id = e.mach_id SET a.emp_Id = e.emp_id;";

    // Prepare and execute the statement
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $_SESSION['success'] = 'Records updated successfully.';
    header('Location: attendance.php');
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = 'Error updating records: ' . $e->getMessage();
    header('Location: attendance.php');
    exit();
}
?>

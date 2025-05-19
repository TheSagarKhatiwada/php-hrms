<?php
// Include session configuration before starting any session
require_once 'includes/session_config.php';

session_start();
require 'includes/db_connection.php';

if (isset($_GET['id'])) {
    $emp_id = $_GET['id'];
    
    try {
        // Delete the employee
        $stmt = $pdo->prepare("DELETE FROM employees WHERE emp_id = :emp_id");
        $stmt->execute([':emp_id' => $emp_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Employee deleted successfully";
        } else {
            $_SESSION['error'] = "Employee not found or could not be deleted";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting employee: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "No employee ID provided";
}

// Redirect back to the employees page with cache-busting parameter
header("Location: employees.php?_nocache=" . time());
exit();
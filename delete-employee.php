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
        
        $_SESSION['success_message'] = "Employee deleted successfully";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting employee: " . $e->getMessage();
    }
}

header("Location: employees.php");
exit();
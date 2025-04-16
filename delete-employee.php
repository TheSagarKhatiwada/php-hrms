<?php
// Start the session
session_start();

// Include the database connection file
require_once 'includes/db_connection.php';

// Check if the emp_id parameter is set in the URL
if (isset($_GET['id'])) {
    // Get the emp_id from the URL
    $empId = $_GET['id'];

    // Prepare the SQL statement to delete the employee
    $sql = "DELETE FROM employees WHERE emp_id = :empId";
    $stmt = $pdo->prepare($sql);

    // Bind the emp_id parameter and execute the statement
    if ($stmt->execute([':empId' => $empId])) {
        // Set success message
        $_SESSION['success'] = "Employee deleted successfully";
    } else {
        // Set error message
        $_SESSION['error'] = "Error deleting employee";
    }
} else {
    // Set error message if no ID provided
    $_SESSION['error'] = "Invalid employee ID";
}

// Redirect to the employees list page
header('Location: employees.php');
exit;
?>
<?php
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
        // Redirect to the employees list page with a success message
        header('Location: employees.php?status=success');
        exit;
    } else {
        // Redirect to the employees list page with an error message
        header('Location: employees.php?status=error');
        exit;
    }
} else {
    // Redirect to the employees list page if emp_id is not set
    header('Location: employees.php');
    exit;
}
?>
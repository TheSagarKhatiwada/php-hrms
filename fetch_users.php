<?php
include 'includes/db_connection.php';  // Include your DB connection file

// Check if the branch parameter is provided
if (isset($_POST['branch'])) {
    $branchId = trim($_POST['branch']);  // Trim any extra spaces around the branch input

    // Sanitize input (optional step for added security)
    if (!empty($branchId)) {
        try {
            // Prepare the query to fetch employees and their branch name
            $stmt = $pdo->prepare("
                SELECT e.emp_id, CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) AS full_name, e.designation, b.name
                FROM employees e
                JOIN branches b ON e.branch = b.id
                WHERE b.id = :id
            ");
            $stmt->execute(['id' => $branchId]);

            // Fetch all employees that match the branch
            $employees = $stmt->fetchAll();
            if ($employees) {
                // Loop through and create an option for each employee with branch name
                foreach ($employees as $employee) {
                    echo "<option value='{$employee['emp_id']}'>
                            {$employee['full_name']} - {$employee['designation']}
                          </option>";
                }
            } else {
                echo "<option value=''>No employees found</option>";
            }
        } catch (PDOException $e) {
            // You can log the error or echo a more detailed message if necessary
            echo "<option value=''>Error fetching employees: {$e->getMessage()}</option>";
        }
    } else {
        echo "<option value=''>Invalid branch</option>";  // Handle empty branch input
    }
} else {
    echo "<option value=''>Branch not selected</option>";  // Handle if branch parameter is not provided
}
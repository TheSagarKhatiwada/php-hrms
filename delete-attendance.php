<?php
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
        $stmt->execute();

        // Redirect back to the attendance list (or to a success page)
        header("Location: attendance.php?status=deleted");
        exit();  // Make sure to stop the script after redirection
    } catch (PDOException $e) {
        // Handle any errors
        echo "Error: " . $e->getMessage();
    }
} else {
    // If no ID is provided, display an error
    echo "Error: Invalid ID.";
}

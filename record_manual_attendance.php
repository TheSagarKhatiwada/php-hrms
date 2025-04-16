<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Include the database connection file
    require_once 'includes/db_connection.php';

    // Get form data
    $empId = $_POST['empId'];
    $attendanceDate = $_POST['attendanceDate'];
    $attendanceTime = $_POST['attendanceTime'];
    $reason = $_POST['reason'];

    try {
        // SQL query to insert data into the table
        $sql = "INSERT INTO attendance_logs (emp_Id, date, time, method, manual_reason) 
                VALUES (:empId, :attendanceDate, :attendanceTime, 1, :reason)";
        
        // Prepare statement
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':empId', $empId);
        $stmt->bindParam(':attendanceDate', $attendanceDate);
        $stmt->bindParam(':attendanceTime', $attendanceTime);
        $stmt->bindParam(':reason', $reason);
        
        // Execute statement
        $stmt->execute();
        
        // Redirect back to the attendance list (or to a success page)
        header("Location: attendance.php?status=manual-sucess");
        exit();  // Make sure to stop the script after redirection
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    // Close the database connection (optional, since PHP will close it when the script ends)
    $pdo = null;
}
?>

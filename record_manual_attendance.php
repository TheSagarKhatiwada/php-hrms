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
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

    // Convert reason code to text
    $reasonText = '';
    switch($reason) {
        case '1':
            $reasonText = 'Card Forgot';
            break;
        case '2':
            $reasonText = 'Card Lost';
            break;
        case '3':
            $reasonText = 'Forgot to Punch';
            break;
        case '4':
            $reasonText = 'Office Work Delay';
            break;
        case '5':
            $reasonText = 'Field Visit';
            break;
        default:
            $reasonText = 'Unknown';
    }

    // Combine reason and remarks
    $manualReason = $reasonText;
    if (!empty($remarks)) {
        $manualReason .= ' - ' . $remarks;
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

<?php
// This file has error reporting enabled
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include session configuration first to avoid conflicts
require_once '../../includes/session_config.php';

// Don't start a new session if one already exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Access denied.";
    header("Location: index.php");
    exit();
}

// Include the database connection file
require_once '../../includes/db_connection.php';

// Include settings file to get timezone configuration
require_once '../../includes/settings.php';

// Include utilities which might contain the notification function
if (file_exists('../../includes/utilities.php')) {
    require_once '../../includes/utilities.php';
}

// Handle AJAX clock in request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'clock_in') {
    // Use a flag to track if data was saved successfully
    $dataSaved = false;
    
    try {
        // Get the employee ID from POST and convert to internal ID
        $emp_id_string = $_POST['emp_id'];
        
        // Get the internal employee ID (integer) from the emp_id string
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE emp_id = ?");
        $stmt->execute([$emp_id_string]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            $_SESSION['error'] = 'Employee not found';
            header("Location: attendance.php");
            exit();
        }
        
        $emp_internal_id = $employee['id']; // This is the integer ID for attendance_logs
        
        // Get timezone from settings
        $timezone = get_setting('timezone', 'UTC');
        date_default_timezone_set($timezone);
        
        // Get current date and time using the configured timezone
        $today = date('Y-m-d');
        $current_time = date('H:i:s');
        
        // For debugging
        error_log("Using timezone: $timezone");
        error_log("Current time in this timezone: $current_time");
        
        // Check if employee already clocked in today
        $stmt = $pdo->prepare("SELECT id FROM attendance_logs WHERE emp_Id = ? AND date = ? LIMIT 1");
        $stmt->execute([$emp_internal_id, $today]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $_SESSION['error'] = 'You have already clocked in today';
            header("Location: attendance.php");
            exit();
        }
        
        // Insert clock in record - improved error handling
        $sql = "INSERT INTO attendance_logs (emp_Id, date, time, method, mach_sn, mach_id, manual_reason) VALUES (?, ?, ?, '2', 0, 0, 'web')";
        $stmt = $pdo->prepare($sql);
        
        try {
            if ($stmt->execute([$emp_internal_id, $today, $current_time])) {
                // Mark that data was successfully saved
                $dataSaved = true;
                
                // Log successful clock in
                error_log("Employee $emp_id_string successfully clocked in at $current_time on $today");
                
                // Try to send notification but don't let it break the clock-in process
                try {
                    if (function_exists('notify_attendance')) {
                        notify_attendance($emp_id_string, 'clocked in', $today . ' ' . $current_time);
                    }
                } catch (Exception $e) {
                    // Just log the error but don't let it affect the success response
                    error_log("Error sending notification: " . $e->getMessage());
                }
                
                // Set success message in session
                $formattedTime = date('h:i A', strtotime($current_time));
                $_SESSION['success'] = 'Clock in recorded successfully at ' . $formattedTime;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error in clock in: " . json_encode($errorInfo));
                $_SESSION['error'] = 'Failed to record clock in. SQL error: ' . $errorInfo[2];
            }
            header("Location: attendance.php");
            exit();
        } catch (PDOException $e) {
            $errorMessage = $e->getMessage();
            error_log("Database error in clock in process: " . $errorMessage);
            // If data was saved, still return partial success
            if ($dataSaved) {
                $_SESSION['success'] = 'Your attendance was recorded, but there was an issue with notifications.';
                $_SESSION['warning'] = 'Database warning after saving: ' . $errorMessage;
            } else {
                $_SESSION['error'] = 'Database error occurred: ' . $errorMessage;
            }
            header("Location: attendance.php");
            exit();
        }
    } catch (PDOException $e) {
        $errorMessage = $e->getMessage();
        error_log("Database error in clock in process: " . $errorMessage);
        // If data was saved, still return partial success
        if ($dataSaved) {
            $_SESSION['success'] = 'Your attendance was recorded, but there was an issue with notifications.';
            $_SESSION['warning'] = 'Database error after saving: ' . $errorMessage;
        } else {
            $_SESSION['error'] = 'Database error occurred: ' . $errorMessage;
        }
        header("Location: attendance.php");
        exit();
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        error_log("General error in clock in process: " . $errorMessage);
        // If data was saved, still return partial success
        if ($dataSaved) {
            $_SESSION['success'] = 'Your attendance was recorded, but there was an issue with the process.';
            $_SESSION['warning'] = 'System error after saving: ' . $errorMessage;
        } else {
            $_SESSION['error'] = 'An error occurred: ' . $errorMessage;
        }
        header("Location: attendance.php");
        exit();
    }
}

// Handle regular form submission for manual attendance
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {
    // Get timezone from settings
    $timezone = get_setting('timezone', 'UTC');
    date_default_timezone_set($timezone);
    
    // Get form data
    $empId_string = $_POST['empId'];
    
    // Convert string emp_id to integer employee ID for database operations
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE emp_id = ?");
    $stmt->execute([$empId_string]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        $_SESSION['error'] = 'Employee not found';
        header("Location: attendance.php");
        exit();
    }
    
    $empId = $employee['id']; // Integer ID for database operations
    $attendanceDate = $_POST['attendanceDate'];
    $attendanceTime = $_POST['attendanceTime'];
    $reason = $_POST['reason'];
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

    // Store reason and remarks with spaced separator
    $manualReason = $reason;
    if (!empty($remarks)) {
        $manualReason .= ' || ' . $remarks;
    }

    try {
        // SQL query to insert data into the table
        $sql = "INSERT INTO attendance_logs (emp_Id, date, time, method, manual_reason, mach_sn, mach_id) 
                VALUES (:empId, :attendanceDate, :attendanceTime, '1', :manualReason, 0, 0)";
        
        // Prepare statement
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':empId', $empId);
        $stmt->bindParam(':attendanceDate', $attendanceDate);
        $stmt->bindParam(':attendanceTime', $attendanceTime);
        $stmt->bindParam(':manualReason', $manualReason);
        
        // Execute statement
        $stmt->execute();

        // Set notification message in session
        $_SESSION['success'] = "Manual attendance recorded successfully";
        header("Location: attendance.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error recording manual attendance: " . $e->getMessage();
        error_log("Manual attendance error: " . $e->getMessage());
        header("Location: attendance.php");
        exit();
    }
}
?>

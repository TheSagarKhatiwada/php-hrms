<?php
// This file handles both check-in and check-out requests
// Instead of having separate logic, we just record the timestamp and let the system determine what it is

// Include session configuration first to avoid conflicts
require_once 'includes/session_config.php';

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
require_once 'includes/db_connection.php';

// Include settings file to get timezone configuration
require_once 'includes/settings.php';

// Include notification helpers
if (file_exists('includes/notification_helpers.php')) {
    require_once 'includes/notification_helpers.php';
}

// Handle AJAX attendance request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'record_attendance') {
    header('Content-Type: application/json');
    // Disable output buffering to prevent issues with JSON responses
    if (ob_get_level()) ob_end_clean();
    
    // Use a flag to track if data was saved successfully
    $dataSaved = false;
    
    try {        // Get the employee ID from POST (now using emp_id directly)
        $emp_id = $_POST['emp_id'];
        
        // Get timezone from settings
        $timezone = get_setting('timezone', 'UTC');
        date_default_timezone_set($timezone);
        
        // Get current date and time using the configured timezone
        $today = date('Y-m-d');
        $current_time = date('H:i:s');
        
        // For debugging
        error_log("Using timezone: $timezone");
        error_log("Current time in this timezone: $current_time");        // Check attendance records for today (to determine if this is check-in or check-out)
        $stmt = $pdo->prepare("SELECT time FROM attendance_logs WHERE emp_Id = ? AND date = ? ORDER BY time DESC LIMIT 1");
        $stmt->execute([$emp_id, $today]);
        $lastRecord = $stmt->fetch(PDO::FETCH_ASSOC);
          // Determine if this is check-in or check-out
        // First record of the day is check-in, all subsequent records are check-out
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE emp_Id = ? AND date = ?");
        $stmt->execute([$emp_id, $today]);
        $count = $stmt->fetchColumn();
        
        // If count is zero, it's the first record (check-in); otherwise it's a check-out
        $isCheckIn = ($count == 0);
        $actionType = $isCheckIn ? 'CI' : 'CO';
        
        // Insert attendance record
        $sql = "INSERT INTO attendance_logs (emp_Id, date, time, method, mach_sn, mach_id, manual_reason) VALUES (?, ?, ?, '2', 0, 0, ?)";
        $stmt = $pdo->prepare($sql);        try {
            if ($stmt->execute([$emp_id, $today, $current_time, $actionType])) {
                // Mark that data was successfully saved
                $dataSaved = true;
                
                // Log successful attendance recording
                error_log("Employee $emp_id successfully recorded $actionType at $current_time on $today");
                
                // Try to send notification but don't let it break the attendance process
                try {
                    if (function_exists('notify_attendance')) {
                        $notifyAction = $isCheckIn ? 'checked_in' : 'checked_out';
                        notify_attendance($emp_id, $notifyAction, $today . ' ' . $current_time);
                    }
                } catch (Exception $e) {
                    // Just log the error but don't let it affect the success response
                    error_log("Error sending notification: " . $e->getMessage());
                }
                
                // Safely encode the JSON response
                $formattedTime = date('h:i A', strtotime($current_time));
                $message = $isCheckIn 
                    ? "Clock in recorded successfully at $formattedTime" 
                    : "Clock out recorded successfully at $formattedTime. Have a nice day!";
                    
                echo json_encode([
                    'success' => true, 
                    'message' => $message, 
                    'data_saved' => true,
                    'action' => $actionType
                ]);
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error in attendance recording: " . json_encode($errorInfo));
                echo json_encode([
                    'success' => false, 
                    'message' => "Failed to record $actionType. SQL error: " . $errorInfo[2]
                ]);
            }
            exit();
        } catch (PDOException $e) {
            $errorMessage = $e->getMessage();
            error_log("Database error in attendance process: " . $errorMessage);
            // If data was saved, still return partial success
            if ($dataSaved) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Your $actionType was recorded, but there was an issue with notifications.",
                    'data_saved' => true,
                    'warning' => 'Database warning after saving: ' . $errorMessage
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Database error occurred: ' . $errorMessage,
                    'debug_info' => 'Check server logs for more details'
                ]);
            }
            exit();
        }
    } catch (PDOException $e) {
        $errorMessage = $e->getMessage();
        error_log("Database error in attendance process: " . $errorMessage);
        echo json_encode([
            'success' => false, 
            'message' => 'Database error occurred: ' . $errorMessage,
            'debug_info' => 'Check server logs for more details'
        ]);
        exit();
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        error_log("General error in attendance process: " . $errorMessage);
        echo json_encode([
            'success' => false, 
            'message' => 'An error occurred: ' . $errorMessage,
            'debug_info' => 'Check server logs for more details'
        ]);
        exit();
    }
}

// Handle regular form submission for manual attendance by admin
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {
    // Get timezone from settings
    $timezone = get_setting('timezone', 'UTC');
    date_default_timezone_set($timezone);
    
    // Get form data
    $empId = $_POST['empId'];
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

// Save last attendance timestamp for users
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_timestamp') {
    header('Content-Type: application/json');
    // Disable output buffering
    if (ob_get_level()) ob_end_clean();
    
    // Just return success - we don't actually need to store the timestamp
    // The main attendance record is already saved in the attendance_logs table
    echo json_encode([
        'success' => true,
        'message' => 'Attendance recorded successfully'
    ]);
    exit();
}

// Get saved attendance timestamp for users
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_timestamp') {
    header('Content-Type: application/json');
    // Disable output buffering
    if (ob_get_level()) ob_end_clean();
    
    // Return current time as we don't store timestamps in a separate table
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Current time returned'
    ]);
    exit();
}
<?php
// Start the session
session_start();

// Enable error reporting but don't display errors on screen
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include required files
try {
    require_once 'includes/db_connection.php';
    require_once 'includes/settings.php';
    require_once 'includes/notification_helpers.php';
} catch (Exception $e) {
    // If any required file is missing, return error
    echo json_encode([
        'success' => false,
        'message' => 'System error: Failed to load required dependencies'
    ]);
    exit();
}

// Helper function to notify user of attendance actions
function notify_employee_attendance($emp_id, $action, $details) {
    if (function_exists('notify_attendance')) {
        return notify_attendance($emp_id, $action, $details);
    }
    return false;
}

// Test database connection
try {
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    // If database connection fails, return error
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error. Please try again or contact support.'
    ]);
    exit();
}

// Process the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle clock-out action
    if (isset($_POST['action']) && $_POST['action'] === 'clock_out') {
        // Check for employee ID
        if (!isset($_POST['emp_id']) || empty($_POST['emp_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing employee ID'
            ]);
            exit();
        }
        
        $emp_id = $_POST['emp_id'];
        
        // Get timezone from settings
        $timezone = get_setting('timezone', 'UTC');
        date_default_timezone_set($timezone);
        
        // Get current date and time
        $current_time = date('H:i:s');
        $today = date('Y-m-d');
        
        try {
            // Check if employee has a clock-in record for today
            $stmt = $pdo->prepare("SELECT id, time FROM attendance_logs WHERE emp_Id = ? AND date = ? ORDER BY time ASC LIMIT 1");
            $stmt->execute([$emp_id, $today]);
            $clockIn = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$clockIn) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No clock-in record found for today. Please contact your administrator.'
                ]);
                exit();
            }
            
            // Insert a new record for clock-out instead of updating the existing one
            $stmt = $pdo->prepare("INSERT INTO attendance_logs (emp_Id, date, time, method, mach_sn, mach_id, manual_reason) VALUES (?, ?, ?, '2', 0, 0, '')");
            
            try {
                $result = $stmt->execute([$emp_id, $today, $current_time]);
                
                if ($result) {
                    // Notify the employee, but don't let notification errors affect the clock-out
                    try {
                        if (function_exists('notify_employee_attendance')) {
                            notify_employee_attendance($emp_id, 'checked_out', $current_time);
                        }
                    } catch (Exception $e) {
                        // Just log the error, don't prevent successful clock out
                        error_log("Error sending checkout notification: " . $e->getMessage());
                    }
                    
                    // Check if the message includes "Clock out recorded successfully"
                    echo json_encode([
                        'success' => true,
                        'message' => 'Clock out recorded successfully. Have a nice day!'
                    ]);
                    exit();
                } else {
                    $errorInfo = $stmt->errorInfo();
                    error_log("SQL Error in clock out: " . json_encode($errorInfo));
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error updating attendance record: ' . ($errorInfo[2] ?? 'Unknown error')
                    ]);
                    exit();
                }
            } catch (PDOException $e) {
                $errorMessage = $e->getMessage();
                error_log("Database error in clock out: " . $errorMessage);
                echo json_encode([
                    'success' => false,
                    'message' => 'Database error. Please try again or contact support.',
                    'debug_info' => $errorMessage
                ]);
                exit();
            }
        } catch (PDOException $e) {
            $errorMessage = $e->getMessage();
            echo json_encode([
                'success' => false,
                'message' => 'Database error. Please try again or contact support.'
            ]);
            exit();
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action specified'
        ]);
        exit();
    }
} else {
    // If not a POST request, return error
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}
?>
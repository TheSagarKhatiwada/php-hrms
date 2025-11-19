<?php
// This file has error reporting enabled
error_reporting(E_ALL);
ini_set('display_errors', 1);

// (Removed verbose debug logging)

// Include session configuration first to avoid conflicts
require_once '../../includes/session_config.php';

// Don't start a new session if one already exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// (Removed verbose debug logging)

include '../../includes/db_connection.php';

// (Removed verbose debug logging)

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {    // (Removed verbose debug logging)
    $_SESSION['error'] = "Access denied.";
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {    // (Removed verbose debug logging)
    
    try {
        // Validate required fields
        $empId = trim($_POST['empId'] ?? '');
        $startDate = trim($_POST['startDate'] ?? '');
        $endDate = trim($_POST['endDate'] ?? '');
        $inTime = trim($_POST['inTime'] ?? '');
        $outTime = trim($_POST['outTime'] ?? '');
        $workingDays = $_POST['workingDays'] ?? [];
        $reason = trim($_POST['reason'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        // Validation
        if (empty($empId) || empty($startDate) || empty($endDate) || empty($inTime) || empty($outTime) || empty($workingDays) || empty($reason)) {
            throw new Exception('All required fields must be filled.');
        }

        // Validate date range
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        if ($start > $end) {
            throw new Exception('Start date cannot be after end date.');
        }

        // Check if date range is not more than 30 days
        $interval = $start->diff($end);
        if ($interval->days > 30) {
            throw new Exception('Date range cannot exceed 30 days.');
        }

        // Validate times
        if ($inTime >= $outTime) {
            throw new Exception('In time must be before out time.');
        }

        // Convert working days to array of day numbers (0 = Sunday, 1 = Monday, etc.)
        $workingDayNumbers = array_map('intval', $workingDays);

        // Get employee details for validation
        $empStmt = $pdo->prepare("SELECT emp_id, first_name, last_name FROM employees WHERE emp_id = ?");
        $empStmt->execute([$empId]);
        $employee = $empStmt->fetch();

        if (!$employee) {
            throw new Exception('Invalid employee selected.');
        }

        $recordsCreated = 0;
        $recordsSkipped = 0;
        $errorMessages = [];

        // Begin transaction
        $pdo->beginTransaction();

        // Iterate through date range
        $current = clone $start;
        while ($current <= $end) {
            $dayOfWeek = (int)$current->format('w'); // 0 = Sunday, 1 = Monday, etc.
            
            // Check if current day is a working day
            if (in_array($dayOfWeek, $workingDayNumbers)) {                $currentDateStr = $current->format('Y-m-d');
                
                // Check if attendance already exists for this employee on this date
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE emp_id = ? AND date = ?");
                $checkStmt->execute([$empId, $currentDateStr]);
                $existingCount = $checkStmt->fetchColumn();
                  if ($existingCount > 0) {
                    $recordsSkipped++;
                    $errorMessages[] = "Attendance already exists for {$employee['first_name']} {$employee['last_name']} on {$currentDateStr}";
                } else {
                    // Insert IN record
                    $inStmt = $pdo->prepare("INSERT INTO attendance_logs (emp_id, date, time, method, manual_reason) VALUES (?, ?, ?, 1, ?)");
                    $inStmt->execute([$empId, $currentDateStr, $inTime, trim($reason) . ($remarks !== '' ? ' || ' . trim($remarks) : '')]);
                    
                    // Insert OUT record  
                    $outStmt = $pdo->prepare("INSERT INTO attendance_logs (emp_id, date, time, method, manual_reason) VALUES (?, ?, ?, 1, ?)");
                    $outStmt->execute([$empId, $currentDateStr, $outTime, trim($reason) . ($remarks !== '' ? ' || ' . trim($remarks) : '')]);
                    
                    $recordsCreated += 2; // IN and OUT records
                }
            }
            
            // Move to next day
            $current->add(new DateInterval('P1D'));
        }

        // Commit transaction
        $pdo->commit();

        // Set success message
        $successMessage = "Periodic attendance processed successfully!";
        $successMessage .= " Created {$recordsCreated} attendance records";
        if ($recordsSkipped > 0) {
            $successMessage .= ", skipped {$recordsSkipped} existing records";
        }
        $successMessage .= " for {$employee['first_name']} {$employee['last_name']}.";

        $_SESSION['success'] = $successMessage;

        if (!empty($errorMessages)) {
            $_SESSION['warning'] = implode('<br>', $errorMessages);
        }

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        error_log("Error in record_manual_attendance_periodic.php: " . $e->getMessage());
        $_SESSION['error'] = "Error creating periodic attendance: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
}

// Redirect back to attendance page
header("Location: attendance.php");
exit();
?>

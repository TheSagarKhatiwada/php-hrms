<?php
// Include session configuration before starting any session
require_once 'includes/session_config.php';

// Remove duplicate session_start() - it's already handled in session_config.php
// Increase execution time and memory limit for large file processing
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['attendanceFile'])) {
    require 'includes/db_connection.php'; // Include the PDO connection

    $file = $_FILES['attendanceFile'];

    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['message'] = ['type' => 'error', 'content' => 'File upload error.'];
        header('Location: attendance.php');
        exit();
    }

    // Ensure it's a .txt file
    if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'txt') {
        $_SESSION['message'] = ['type' => 'error', 'content' => 'Invalid file type. Only .txt files are allowed.'];
        header('Location: attendance.php');
        exit();
    }

    // Check file size (limit to 10MB for performance)
    $maxFileSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxFileSize) {
        $_SESSION['message'] = ['type' => 'error', 'content' => 'File size too large. Maximum allowed size is 10MB.'];
        header('Location: attendance.php');
        exit();
    }

    // Read the file
    $fileContent = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $error_lines = [];

    // Validate file has at least one data row
    if (count($fileContent) < 2) { // Should have header + at least one data row
        $_SESSION['message'] = ['type' => 'error', 'content' => 'The uploaded file does not contain any data rows. Please check the file format.'];
        header('Location: attendance.php');
        exit();
    }

    try {
        // Begin transaction for data integrity
        $pdo->beginTransaction();
        
        // Prepare statements outside the loop for better performance
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE mach_sn = ?");
        $updateStmt = $pdo->prepare("UPDATE attendance_logs SET mach_id = ?, date = ?, time = ?, updated_at = CURRENT_TIMESTAMP WHERE mach_sn = ?");
        $insertStmt = $pdo->prepare("INSERT INTO attendance_logs (mach_sn, mach_id, emp_Id, date, time, method, manual_reason) VALUES (?, ?, 1, ?, ?, ?, ?)");
        
        $batchCount = 0;
        $maxBatchSize = 100; // Process in batches to avoid timeout

        foreach ($fileContent as $index => $line) {
            if ($index === 0) continue; // Skip header row

            // Split the line into columns (tab or multiple spaces as delimiter)
            $data = preg_split('/\t+|\s{2,}/', trim($line));

            if (count($data) < 8) { // Changed from 7 to 8 since we need index 7 for time
                $skipped++; // Skip invalid rows
                // Track error lines (up to 5)
                if (count($error_lines) < 5) {
                    $error_lines[] = "Line " . ($index + 1) . ": Not enough columns (expected at least 8)";
                }
                continue;
            }

            // Extract necessary fields with proper validation
            $mach_sn = isset($data[0]) ? intval($data[0]) : 0; // Unique No.
            $mach_id = isset($data[2]) ? intval($data[2]) : 0; // Employee machine ID
            $date = isset($data[6]) ? trim($data[6]) : ''; // Extract date
            $time = isset($data[7]) ? trim($data[7]) : ''; // Extract time

            // Check if any required field is missing or invalid
            if (empty($mach_sn) || empty($mach_id) || empty($date) || empty($time)) {
                $skipped++; // Skip this row if there's invalid data
                // Track error lines (up to 5)
                if (count($error_lines) < 5) {
                    $error_lines[] = "Line " . ($index + 1) . ": Missing required data (machine SN, ID, date or time)";
                }
                continue;
            }

            // Validate and format the date (expected format: YYYY-MM-DD)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                // Try to convert from other common formats
                $parsedDate = date('Y-m-d', strtotime($date));
                if ($parsedDate === false || $parsedDate === '1970-01-01') {
                    $skipped++; // Skip if date is invalid and can't be parsed
                    continue;
                }
                $date = $parsedDate;
            }

            // Ensure the time format is valid (HH:MM:SS)
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
                // Try to convert from other common time formats
                $parsedTime = date('H:i:s', strtotime($time));
                if ($parsedTime === false || ($parsedTime === '00:00:00' && $time !== '00:00:00')) {
                    $time = '00:00:00'; // Default time if invalid format
                } else {
                    $time = $parsedTime;
                }
            }

            // Check if the record already exists
            $checkStmt->execute([$mach_sn]);
            $count = $checkStmt->fetchColumn();

            if ($count > 0) {
                // Update existing record
                if ($updateStmt->execute([$mach_id, $date, $time, $mach_sn])) {
                    $updated++;
                }
            } else {
                // Insert new record - use 1 as temporary emp_Id (will be updated later)
                if ($insertStmt->execute([$mach_sn, $mach_id, $date, $time, 0, ''])) {
                    $inserted++;
                }
            }
            
            // Commit in batches to prevent timeout
            $batchCount++;
            if ($batchCount >= $maxBatchSize) {
                $pdo->commit();
                $pdo->beginTransaction();
                $batchCount = 0;
            }
        }

        // Set success message with inserted and updated counts
        $messageContent = "$inserted records inserted, $updated updated, $skipped skipped (invalid data).";
        
        // If there were skipped lines, include the first 5 error details
        if (!empty($error_lines)) {
            $messageContent .= " Issues found: " . implode("; ", $error_lines);
            if (count($error_lines) >= 5 && $skipped > 5) {
                $messageContent .= " and " . ($skipped - 5) . " more...";
            }
        }
        
        $_SESSION['message'] = [
            'type' => ($skipped > 0) ? 'warning' : 'success',
            'content' => $messageContent
        ];

        // Update attendance_logs with emp_Id from employees based on machine_id
        // Use a more efficient query that limits the number of rows updated per operation
        $updateEmployeeIdSql = "UPDATE attendance_logs a 
                               JOIN employees e ON a.mach_id = e.mach_id 
                               SET a.emp_Id = e.id 
                               WHERE a.method = 0 AND (a.emp_Id = 0 OR a.emp_Id IS NULL OR a.emp_Id = 1)
                               LIMIT 1000";
    
        // Execute the update in smaller batches to prevent timeout
        $totalUpdated = 0;
        do {
            $stmt = $pdo->prepare($updateEmployeeIdSql);
            $stmt->execute();
            $rowsUpdated = $stmt->rowCount();
            $totalUpdated += $rowsUpdated;
            
            // Small delay to prevent overwhelming the database
            if ($rowsUpdated > 0) {
                usleep(10000); // 10ms delay
            }
        } while ($rowsUpdated > 0);
    
        // Append to existing success message rather than overwriting it
        $_SESSION['message']['content'] .= " $totalUpdated employee IDs updated successfully.";
        
        // Final commit for any remaining transactions
        $pdo->commit();
    } catch (PDOException $e) {
        // Roll back the transaction if something failed
        $pdo->rollBack();
        
        // Provide more specific error messages based on the error code
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if (strpos($errorMessage, 'Duplicate entry') !== false) {
            $_SESSION['message'] = ['type' => 'error', 'content' => 'Duplicate records found in the upload file. Please check and try again.'];
        } else if (strpos($errorMessage, 'foreign key constraint fails') !== false) {
            $_SESSION['message'] = ['type' => 'error', 'content' => 'Some records reference invalid employee IDs. Please check and try again.'];
        } else if (strpos($errorMessage, 'Data too long') !== false) {
            $_SESSION['message'] = ['type' => 'error', 'content' => 'Some data exceeded the allowed length. Please check your file format.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'content' => 'Error processing attendance data: ' . $errorMessage];
        }
        
        // Log the error to a file with more detailed information
        error_log('Upload attendance error: ' . $errorMessage . ' (Code: ' . $errorCode . ')', 3, 'error_log.txt');
    }

    header('Location: attendance.php');
    exit();
} else {
    $_SESSION['message'] = ['type' => 'error', 'content' => 'No file uploaded.'];
    header('Location: attendance.php');
    exit();
}
?>

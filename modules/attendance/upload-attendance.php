<?php
// Include session configuration (session is already started in session_config.php)
require_once '../../includes/session_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['attendanceFile'])) {
    require '../../includes/db_connection.php'; // Include the PDO connection

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

    // Read the file
    $fileContent = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $inserted = 0;
    $updated = 0;
    $skipped = 0;

    try {
        // Begin transaction for data integrity
        $pdo->beginTransaction();

        foreach ($fileContent as $index => $line) {
            if ($index === 0) continue; // Skip header row

            // Split the line into columns (tab or multiple spaces as delimiter)
            $data = preg_split('/\t+|\s{2,}/', trim($line));

            if (count($data) < 8) { // Changed from 7 to 8 since we need index 7 for time
                $skipped++; // Skip invalid rows
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
                continue;
            }

            // Ensure the time format is valid
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
                $time = '00:00:00'; // Default time if invalid format
            }

            // Check if the record already exists
            $checkQuery = "SELECT COUNT(*) FROM attendance_logs WHERE mach_sn = ?";
            $stmt = $pdo->prepare($checkQuery);
            $stmt->execute([$mach_sn]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                // Update existing record
                $updateQuery = "UPDATE attendance_logs SET mach_id = ?, date = ?, time = ?, updated_at = CURRENT_TIMESTAMP WHERE mach_sn = ?";
                $stmt = $pdo->prepare($updateQuery);
                if ($stmt->execute([$mach_id, $date, $time, $mach_sn])) {
                    $updated++;
                }
            } else {
                // Insert new record with temporary emp_id of 1 (will be updated later)
                $insertQuery = "INSERT INTO attendance_logs (mach_sn, mach_id, emp_id, date, time, method) VALUES (?, ?, 1, ?, ?, 0)";
                $stmt = $pdo->prepare($insertQuery);
                if ($stmt->execute([$mach_sn, $mach_id, $date, $time])) {
                    $inserted++;
                }
            }
        }

        // Set success message with inserted and updated counts
        $_SESSION['message'] = [
            'type' => 'success',
            'content' => "$inserted records inserted, $updated updated, $skipped skipped (invalid data)."
        ];

        // Update attendance_logs with emp_Id from employees based on machine_id
        // SQL query to update attendance_log with emp_Id from employees based on machine_id, excluding manual method entries
        $sql = "UPDATE attendance_logs a 
                JOIN employees e ON a.mach_id = e.mach_id 
                SET a.emp_Id = e.emp_id 
                WHERE a.method = 0;";
    
        // Prepare and execute the statement
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    
        // Append to existing success message rather than overwriting it
        $_SESSION['message']['content'] .= ' Employee IDs updated successfully.';
        
        // Commit the transaction if everything went well
        $pdo->commit();
    } catch (PDOException $e) {
        // Roll back the transaction if something failed
        $pdo->rollBack();
        $_SESSION['message'] = ['type' => 'error', 'content' => 'Error processing attendance data: ' . $e->getMessage()];
        
        // Log the error to a file
        error_log('Upload attendance error: ' . $e->getMessage(), 3, 'error_log.txt');
    }

    header('Location: attendance.php');
    exit();
} else {
    $_SESSION['message'] = ['type' => 'error', 'content' => 'No file uploaded.'];
    header('Location: attendance.php');
    exit();
}
?>
<?php
/**
 * Scheduled Task for Automatic Notifications
 * 
 * This script checks for events like birthdays and work anniversaries
 * and sends notifications to the appropriate users.
 * 
 * Recommended to run this script once daily via cron job or scheduler:
 * Example cron entry: 0 1 * * * php /path/to/scheduled_notifications.php
 */

// Include necessary files
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/utilities.php'; // This will include notification_helpers.php

// Set timezone if not already set in php.ini
date_default_timezone_set('Asia/Kolkata'); // Adjust to your timezone

// Current date information
$today = date('Y-m-d');
$currentDay = date('d');
$currentMonth = date('m');
$currentYear = date('Y');

// Log execution
error_log("Running scheduled notifications on {$today}");

/**
 * Check for birthdays and send notifications
 */
function checkBirthdays($pdo) {
    global $currentDay, $currentMonth;
    
    try {
        // Query employees whose birthday is today
        // Assuming dob is stored in a field named 'date_of_birth' or similar
        $stmt = $pdo->prepare("
            SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
            FROM employees 
            WHERE DAY(date_of_birth) = :day AND MONTH(date_of_birth) = :month
            AND status = 'active' AND login_access = 1
        ");
        
        $stmt->execute([
            ':day' => $currentDay,
            ':month' => $currentMonth
        ]);
        
        $birthdayEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($birthdayEmployees)) {
            // Get HR/Management team IDs to notify them about birthdays
            $adminStmt = $pdo->prepare("SELECT id FROM employees WHERE role = 1 OR role = 2");
            $adminStmt->execute();
            $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Process each employee with a birthday today
            foreach ($birthdayEmployees as $employee) {
                // Send birthday notification to the employee
                notify_employee($employee['id'], 'birthday');
                
                // Notify HR/Management about the employee's birthday
                if (!empty($adminIds)) {
                    notify_users(
                        $adminIds,
                        'Employee Birthday',
                        "Today is {$employee['full_name']}'s birthday! Don't forget to wish them.",
                        'success'
                    );
                }
                
                error_log("Birthday notification sent for employee: {$employee['full_name']}");
            }
        }
    } catch (PDOException $e) {
        error_log("Error checking birthdays: " . $e->getMessage());
    }
}

/**
 * Check for work anniversaries and send notifications
 */
function checkWorkAnniversaries($pdo) {
    global $currentDay, $currentMonth, $currentYear;
    
    try {
        // Query employees whose work anniversary is today
        // Assuming join_date stores the employee's start date
        $stmt = $pdo->prepare("
            SELECT id, CONCAT(first_name, ' ', last_name) as full_name, join_date
            FROM employees 
            WHERE DAY(join_date) = :day AND MONTH(join_date) = :month
            AND YEAR(join_date) < :year
            AND status = 'active' AND login_access = 1
        ");
        
        $stmt->execute([
            ':day' => $currentDay,
            ':month' => $currentMonth,
            ':year' => $currentYear
        ]);
        
        $anniversaryEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($anniversaryEmployees)) {
            // Get HR/Management team IDs to notify them about work anniversaries
            $adminStmt = $pdo->prepare("SELECT id FROM employees WHERE role = 1 OR role = 2");
            $adminStmt->execute();
            $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Process each employee with a work anniversary today
            foreach ($anniversaryEmployees as $employee) {
                // Calculate years of service
                $joinDate = new DateTime($employee['join_date']);
                $today = new DateTime();
                $years = $today->diff($joinDate)->y;
                
                // Skip if it's less than 1 year
                if ($years < 1) continue;
                
                // Send work anniversary notification to the employee
                notify_employee(
                    $employee['id'], 
                    'work_anniversary', 
                    ['years' => $years]
                );
                
                // Notify HR/Management about the employee's work anniversary
                if (!empty($adminIds)) {
                    notify_users(
                        $adminIds,
                        "Employee Work Anniversary",
                        "{$employee['full_name']} is celebrating {$years} year" . ($years > 1 ? 's' : '') . " with the company today!",
                        'success'
                    );
                }
                
                error_log("Work anniversary notification sent for {$employee['full_name']} ({$years} years)");
            }
        }
    } catch (PDOException $e) {
        error_log("Error checking work anniversaries: " . $e->getMessage());
    }
}

/**
 * Check for assets due for return
 */
function checkAssetsDueForReturn($pdo) {
    global $today;
    
    try {
        // Find assets that are due for return today or tomorrow (24-48 hour notice)
        $stmt = $pdo->prepare("
            SELECT aa.AssignmentID, aa.EmployeeID, aa.ExpectedReturnDate, 
                   fa.AssetName, fa.AssetID
            FROM AssetAssignments aa
            JOIN fixedassets fa ON aa.AssetID = fa.AssetID
            WHERE aa.ExpectedReturnDate BETWEEN :today AND DATE_ADD(:today, INTERVAL 2 DAY)
            AND aa.ReturnDate IS NULL
        ");
        
        $stmt->execute([':today' => $today]);
        $dueAssets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($dueAssets as $asset) {
            // Send reminder to employee
            notify_asset(
                $asset['EmployeeID'], 
                'return_due', 
                $asset['AssetName']
            );
            
            error_log("Asset return notification sent for {$asset['AssetName']} to employee #{$asset['EmployeeID']}");
        }
    } catch (PDOException $e) {
        error_log("Error checking assets due for return: " . $e->getMessage());
    }
}

/**
 * Check for overdue assets
 */
function checkOverdueAssets($pdo) {
    global $today;
    
    try {
        // Find assets that are overdue (past expected return date)
        $stmt = $pdo->prepare("
            SELECT aa.AssignmentID, aa.EmployeeID, aa.ExpectedReturnDate, 
                   fa.AssetName, fa.AssetID
            FROM AssetAssignments aa
            JOIN fixedassets fa ON aa.AssetID = fa.AssetID
            WHERE aa.ExpectedReturnDate < :today
            AND aa.ReturnDate IS NULL
        ");
        
        $stmt->execute([':today' => $today]);
        $overdueAssets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get admin IDs for notification
        $adminStmt = $pdo->prepare("SELECT id FROM employees WHERE role = 1");
        $adminStmt->execute();
        $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($overdueAssets as $asset) {
            // Send overdue notification to employee
            notify_asset(
                $asset['EmployeeID'], 
                'overdue', 
                $asset['AssetName']
            );
            
            // Notify admins about overdue assets
            if (!empty($adminIds)) {
                $employeeStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM employees WHERE id = :id");
                $employeeStmt->execute([':id' => $asset['EmployeeID']]);
                $employeeName = $employeeStmt->fetchColumn() ?: "Employee #{$asset['EmployeeID']}";
                
                notify_users(
                    $adminIds,
                    "Overdue Asset Alert",
                    "Asset {$asset['AssetName']} assigned to {$employeeName} is overdue for return. Expected return date was {$asset['ExpectedReturnDate']}.",
                    'warning',
                    'manage_assignments.php'
                );
            }
            
            error_log("Overdue asset notification sent for {$asset['AssetName']} assigned to employee #{$asset['EmployeeID']}");
        }
    } catch (PDOException $e) {
        error_log("Error checking overdue assets: " . $e->getMessage());
    }
}

// Run all notification checks
checkBirthdays($pdo);
checkWorkAnniversaries($pdo);
checkAssetsDueForReturn($pdo);
checkOverdueAssets($pdo);

error_log("Scheduled notifications completed successfully");
echo "Scheduled notifications completed successfully\n";
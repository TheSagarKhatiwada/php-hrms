<?php
// Define include check to allow config.php inclusion
if (!defined('INCLUDE_CHECK')) {
    define('INCLUDE_CHECK', true);
}

require_once 'includes/db_connection.php';

echo "=== Attendance Tables and Columns Verification ===\n";

try {
    // Check attendance_logs table structure
    echo "1. attendance_logs table structure:\n";
    $stmt = $pdo->prepare("DESCRIBE attendance_logs");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fields as $field) {
        echo "   {$field['Field']} - {$field['Type']} - {$field['Null']} - {$field['Key']} - Default: " . ($field['Default'] ?? 'NULL') . "\n";
    }
    
    // Check employees table relevant fields
    echo "\n2. employees table relevant fields:\n";
    $stmt = $pdo->prepare("DESCRIBE employees");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fields as $field) {
        if (in_array($field['Field'], ['id', 'emp_id', 'user_id', 'first_name', 'last_name'])) {
            echo "   {$field['Field']} - {$field['Type']} - {$field['Null']} - {$field['Key']}\n";
        }
    }
    
    // Check sample data to understand the relationship
    echo "\n3. Sample data analysis:\n";
    $stmt = $pdo->prepare("SELECT id, emp_id, user_id, CONCAT(first_name, ' ', last_name) as name FROM employees LIMIT 3");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($employees as $emp) {
        echo "   Employee: ID={$emp['id']}, emp_id='{$emp['emp_id']}', user_id=" . ($emp['user_id'] ?? 'NULL') . ", name='{$emp['name']}'\n";
    }
    
    // Check existing attendance_logs data
    echo "\n4. Sample attendance_logs data:\n";
    $stmt = $pdo->prepare("SELECT emp_Id, date, time FROM attendance_logs LIMIT 3");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($logs) > 0) {
        foreach ($logs as $log) {
            echo "   emp_Id: {$log['emp_Id']}, date: {$log['date']}, time: {$log['time']}\n";
        }
    } else {
        echo "   No attendance logs found\n";
    }
    
    // Check foreign key constraints
    echo "\n5. Foreign key constraints on attendance_logs:\n";
    $stmt = $pdo->prepare("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'attendance_logs'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmt->execute();
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($constraints) > 0) {
        foreach ($constraints as $constraint) {
            echo "   {$constraint['CONSTRAINT_NAME']}: {$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
        }
    } else {
        echo "   No foreign key constraints found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Verification Complete ===\n";
?>

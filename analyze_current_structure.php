<?php
// Define include check to allow config.php inclusion
if (!defined('INCLUDE_CHECK')) {
    define('INCLUDE_CHECK', true);
}

require_once 'includes/db_connection.php';

echo "=== Current Database Structure Analysis ===\n";

try {
    // Check employees table structure
    echo "1. employees table structure:\n";
    $stmt = $pdo->prepare("DESCRIBE employees");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fields as $field) {
        if (in_array($field['Field'], ['id', 'employee_id', 'emp_id', 'user_id'])) {
            echo "   {$field['Field']} - {$field['Type']} - {$field['Null']} - {$field['Key']} - Default: " . ($field['Default'] ?? 'NULL') . "\n";
        }
    }
    
    // Check attendance_logs structure
    echo "\n2. attendance_logs table structure:\n";
    $stmt = $pdo->prepare("DESCRIBE attendance_logs");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fields as $field) {
        if (in_array($field['Field'], ['id', 'emp_Id', 'emp_id', 'employee_id'])) {
            echo "   {$field['Field']} - {$field['Type']} - {$field['Null']} - {$field['Key']}\n";
        }
    }
    
    // Check sample employee data
    echo "\n3. Sample employee data:\n";
    $stmt = $pdo->prepare("SELECT id, employee_id, emp_id FROM employees LIMIT 3");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($employees as $emp) {
        echo "   ID: {$emp['id']}, employee_id: " . ($emp['employee_id'] ?? 'NULL') . ", emp_id: {$emp['emp_id']}\n";
    }
    
    // Check attendance_logs data types
    echo "\n4. Sample attendance_logs data:\n";
    $stmt = $pdo->prepare("SELECT id, emp_Id FROM attendance_logs LIMIT 3");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($logs as $log) {
        echo "   Log ID: {$log['id']}, emp_Id: {$log['emp_Id']} (type: " . gettype($log['emp_Id']) . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Analysis Complete ===\n";
?>

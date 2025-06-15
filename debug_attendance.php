<?php
// Define include check to allow config.php inclusion
if (!defined('INCLUDE_CHECK')) {
    define('INCLUDE_CHECK', true);
}

require_once 'includes/db_connection.php';

echo "=== Attendance Logs Table Investigation ===\n";

try {
    // Check attendance_logs table structure
    echo "1. Attendance_logs table structure:\n";
    $stmt = $pdo->prepare("DESCRIBE attendance_logs");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fields as $field) {
        echo "   {$field['Field']} - {$field['Type']} - {$field['Null']} - {$field['Key']}\n";
    }
    
    // Check what emp_Id values exist
    echo "\n2. Sample emp_Id values in attendance_logs:\n";
    $stmt = $pdo->prepare("SELECT DISTINCT emp_Id FROM attendance_logs LIMIT 5");
    $stmt->execute();
    $empIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($empIds as $empId) {
        echo "   $empId\n";
    }
    
    // Check employees table emp_id values
    echo "\n3. Sample emp_id values in employees table:\n";
    $stmt = $pdo->prepare("SELECT emp_id, id FROM employees LIMIT 5");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($employees as $emp) {
        echo "   emp_id: {$emp['emp_id']}, internal id: {$emp['id']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Investigation Complete ===\n";
?>

<?php
// Define include check to allow config.php inclusion
if (!defined('INCLUDE_CHECK')) {
    define('INCLUDE_CHECK', true);
}

require_once 'includes/db_connection.php';

echo "=== Completing Database Migration Cleanup ===\n";

try {
    // Clean up orphaned attendance records
    echo "1. Cleaning up orphaned attendance records...\n";
    $stmt = $pdo->prepare("DELETE FROM attendance_logs WHERE emp_Id IS NULL OR emp_Id = ''");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    echo "   Deleted $deleted orphaned attendance records\n";
    
    // Clean up employees table
    echo "2. Cleaning up employees table...\n";
    
    // Drop employee_id column if it exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM employees LIKE 'employee_id'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "   Dropping employee_id column...\n";
        $pdo->exec("ALTER TABLE employees DROP COLUMN employee_id");
    }
    
    // Drop user_id column if it exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM employees LIKE 'user_id'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "   Dropping user_id column...\n";
        $pdo->exec("ALTER TABLE employees DROP COLUMN user_id");
    }
    
    echo "3. Verifying final structure...\n";
    
    // Check attendance_logs structure
    $stmt = $pdo->prepare("DESCRIBE attendance_logs");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fields as $field) {
        if ($field['Field'] == 'emp_Id') {
            echo "   ✅ attendance_logs.emp_Id: {$field['Type']}\n";
        }
    }
    
    // Check employees table structure
    echo "   employees table relevant fields:\n";
    $stmt = $pdo->prepare("DESCRIBE employees");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fields as $field) {
        if (in_array($field['Field'], ['id', 'emp_id', 'employee_id', 'user_id'])) {
            echo "   {$field['Field']}: {$field['Type']}\n";
        }
    }
    
    // Check attendance data
    echo "\n4. Checking attendance data integrity...\n";
    $stmt = $pdo->prepare("
        SELECT a.emp_Id, COUNT(*) as count, e.emp_id as employee_exists
        FROM attendance_logs a 
        LEFT JOIN employees e ON a.emp_Id = e.emp_id
        GROUP BY a.emp_Id
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $result) {
        $status = $result['employee_exists'] ? '✅ Valid' : '❌ Invalid';
        echo "   emp_Id '{$result['emp_Id']}': {$result['count']} records - $status\n";
    }
    
    echo "\n=== Cleanup Completed Successfully ===\n";
    
} catch (Exception $e) {
    echo "❌ Error during cleanup: " . $e->getMessage() . "\n";
}
?>

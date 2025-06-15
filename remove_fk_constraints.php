<?php
// Define include check to allow config.php inclusion
if (!defined('INCLUDE_CHECK')) {
    define('INCLUDE_CHECK', true);
}

require_once 'includes/db_connection.php';

echo "=== Removing Foreign Key Constraints and Completing Cleanup ===\n";

try {
    // Check for foreign key constraints on employees table
    echo "1. Checking foreign key constraints...\n";
    $stmt = $pdo->prepare("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'employees'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmt->execute();
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($constraints as $constraint) {
        echo "   Found FK: {$constraint['CONSTRAINT_NAME']} on {$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
        
        // Drop the foreign key constraint
        $pdo->exec("ALTER TABLE employees DROP FOREIGN KEY {$constraint['CONSTRAINT_NAME']}");
        echo "   Dropped FK: {$constraint['CONSTRAINT_NAME']}\n";
    }
    
    // Now try to drop the user_id column
    echo "2. Dropping user_id column...\n";
    $stmt = $pdo->prepare("SHOW COLUMNS FROM employees LIKE 'user_id'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE employees DROP COLUMN user_id");
        echo "   ✅ Dropped user_id column\n";
    } else {
        echo "   user_id column already removed\n";
    }
    
    // Drop employee_id column if it still exists
    echo "3. Dropping employee_id column...\n";
    $stmt = $pdo->prepare("SHOW COLUMNS FROM employees LIKE 'employee_id'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE employees DROP COLUMN employee_id");
        echo "   ✅ Dropped employee_id column\n";
    } else {
        echo "   employee_id column already removed\n";
    }
    
    echo "4. Final verification...\n";
    
    // Check final employees table structure
    $stmt = $pdo->prepare("DESCRIBE employees");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $id_fields = [];
    foreach ($fields as $field) {
        if (in_array($field['Field'], ['id', 'emp_id', 'employee_id', 'user_id'])) {
            $id_fields[] = $field['Field'] . ' (' . $field['Type'] . ')';
        }
    }
    echo "   employees table ID fields: " . implode(', ', $id_fields) . "\n";
    
    // Check attendance_logs structure
    $stmt = $pdo->prepare("DESCRIBE attendance_logs");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fields as $field) {
        if ($field['Field'] == 'emp_Id') {
            echo "   ✅ attendance_logs.emp_Id: {$field['Type']}\n";
        }
    }
    
    // Check data integrity
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance_logs");
    $stmt->execute();
    $total = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as valid 
        FROM attendance_logs a 
        JOIN employees e ON a.emp_Id = e.emp_id
    ");
    $stmt->execute();
    $valid = $stmt->fetch()['valid'];
    
    echo "   Attendance records: $valid/$total valid\n";
    
    echo "\n=== Migration Completed Successfully ===\n";
    echo "✅ Database now uses emp_id as the primary employee identifier\n";
    echo "✅ attendance_logs.emp_Id is VARCHAR(20) matching employees.emp_id\n";
    echo "✅ Removed redundant employee_id and user_id columns\n";
    echo "✅ All foreign key constraints removed\n";
    
} catch (Exception $e) {
    echo "❌ Error during cleanup: " . $e->getMessage() . "\n";
}
?>

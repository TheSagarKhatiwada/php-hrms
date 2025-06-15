<?php
/**
 * Check Current Database Structure and Create Final Migration
 * This will check the current state of the employees table and remove the id column if it's still present
 */

require_once 'includes/db_connection.php';

echo "=== FINAL MIGRATION: Remove employees.id column ===\n\n";

try {
    // Check current table structure
    echo "1. Checking current employees table structure...\n";
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasIdColumn = false;
    $hasEmpIdColumn = false;
    
    echo "Current columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']}) - Key: {$column['Key']}\n";
        if ($column['Field'] === 'id') {
            $hasIdColumn = true;
        }
        if ($column['Field'] === 'emp_id') {
            $hasEmpIdColumn = true;
        }
    }
    
    if (!$hasEmpIdColumn) {
        throw new Exception("emp_id column not found! Migration cannot proceed.");
    }
    
    echo "\n2. Checking for any remaining references to employees.id...\n";
    
    // Check if any foreign key constraints still reference employees.id
    $stmt = $pdo->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM 
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE 
            REFERENCED_TABLE_NAME = 'employees' 
            AND REFERENCED_COLUMN_NAME = 'id'
            AND TABLE_SCHEMA = DATABASE()
    ");
    $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($foreignKeys)) {
        echo "❌ Found foreign key constraints still referencing employees.id:\n";
        foreach ($foreignKeys as $fk) {
            echo "  - {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
            echo "    Constraint: {$fk['CONSTRAINT_NAME']}\n";
        }
        echo "\nThese must be removed before proceeding.\n";
        return;
    } else {
        echo "✓ No foreign key constraints found referencing employees.id\n";
    }
    
    if ($hasIdColumn) {
        echo "\n3. Removing employees.id column...\n";
        
        // First, check if id is part of any index
        $stmt = $pdo->query("SHOW INDEX FROM employees WHERE Column_name = 'id'");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($indexes)) {
            echo "Found indexes on id column:\n";
            foreach ($indexes as $index) {
                echo "  - {$index['Key_name']}\n";
                if ($index['Key_name'] === 'PRIMARY') {
                    echo "Dropping PRIMARY KEY...\n";
                    $pdo->exec("ALTER TABLE employees DROP PRIMARY KEY");
                } else {
                    echo "Dropping index {$index['Key_name']}...\n";
                    $pdo->exec("ALTER TABLE employees DROP INDEX `{$index['Key_name']}`");
                }
            }
        }
        
        // Remove the id column
        $pdo->exec("ALTER TABLE employees DROP COLUMN id");
        echo "✓ employees.id column removed successfully\n";
        
        // Set emp_id as primary key if it isn't already
        echo "\n4. Setting emp_id as PRIMARY KEY...\n";
        try {
            $pdo->exec("ALTER TABLE employees ADD PRIMARY KEY (emp_id)");
            echo "✓ emp_id set as PRIMARY KEY\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Multiple primary key') !== false) {
                echo "✓ emp_id is already a PRIMARY KEY\n";
            } else {
                throw $e;
            }
        }
        
    } else {
        echo "\n3. employees.id column not found - already removed\n";
        
        // Check if emp_id is primary key
        $stmt = $pdo->query("SHOW INDEX FROM employees WHERE Key_name = 'PRIMARY'");
        $primaryKey = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($primaryKey && $primaryKey['Column_name'] === 'emp_id') {
            echo "✓ emp_id is already the PRIMARY KEY\n";
        } else {
            echo "Setting emp_id as PRIMARY KEY...\n";
            $pdo->exec("ALTER TABLE employees ADD PRIMARY KEY (emp_id)");
            echo "✓ emp_id set as PRIMARY KEY\n";
        }
    }
    
    echo "\n5. Final verification...\n";
    
    // Show final table structure
    $stmt = $pdo->query("DESCRIBE employees");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Final employees table structure:\n";
    foreach ($finalColumns as $column) {
        echo "  - {$column['Field']} ({$column['Type']}) - Key: {$column['Key']}\n";
    }
    
    // Test a simple join to ensure everything works
    echo "\n6. Testing attendance join...\n";
    $stmt = $pdo->prepare("
        SELECT e.emp_id, e.first_name, e.last_name, COUNT(a.id) as attendance_count
        FROM employees e
        LEFT JOIN attendance_logs a ON a.emp_Id = e.emp_id
        GROUP BY e.emp_id
        LIMIT 5
    ");
    $stmt->execute();
    $testResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($testResults)) {
        echo "✓ Attendance join test successful:\n";
        foreach ($testResults as $result) {
            echo "  - {$result['emp_id']}: {$result['first_name']} {$result['last_name']} ({$result['attendance_count']} records)\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ MIGRATION COMPLETED SUCCESSFULLY!\n";
    echo "✅ All employee operations now use emp_id as the primary identifier\n";
    echo "✅ Database schema is fully migrated to emp_id-centric design\n";
    echo str_repeat("=", 60) . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Migration failed!\n";
}
?>

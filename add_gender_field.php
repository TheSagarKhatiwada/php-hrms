<?php
/**
 * Migration script to add missing gender column to employees table
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connection.php';

echo "=== Employees Table Gender Field Migration ===\n";
echo "Adding missing gender column...\n\n";

try {
    echo "1. Checking current table structure...\n";
    
    // Check if gender column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'gender'");
    $gender_exists = $stmt->fetch() !== false;
    
    echo "   - gender column exists: " . ($gender_exists ? 'YES' : 'NO') . "\n";
    
    if (!$gender_exists) {
        echo "\n2. Adding gender column...\n";
        $pdo->exec("ALTER TABLE employees ADD COLUMN gender ENUM('M','F','Other') DEFAULT NULL AFTER last_name");
        echo "   ✓ Added gender column successfully\n";
        
        echo "\n=== Migration Completed Successfully ===\n";
        echo "The employees table now includes:\n";
        echo "  • gender - Employee gender (M=Male, F=Female, Other)\n";
        
    } else {
        echo "\n=== No Changes Required ===\n";
        echo "Gender column already exists in the employees table.\n";
    }
    
    echo "\nCurrent relevant table structure:\n";
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        if (in_array($column['Field'], ['first_name', 'middle_name', 'last_name', 'gender', 'email', 'date_of_birth'])) {
            echo "  • {$column['Field']} - {$column['Type']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: Migration failed - " . $e->getMessage() . "\n";
    exit(1);
}
?>

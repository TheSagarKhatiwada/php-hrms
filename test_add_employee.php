<?php
// Define include check to allow config.php inclusion
if (!defined('INCLUDE_CHECK')) {
    define('INCLUDE_CHECK', true);
}

require_once 'includes/db_connection.php';

echo "=== Add Employee Functionality Test ===\n";

try {
    // Test 1: Check if required tables exist
    echo "1. Checking required tables:\n";
      $tables = ['employees', 'branches', 'designations', 'roles'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE '$table'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo "   ✓ $table table exists\n";
        } else {
            echo "   ✗ $table table missing\n";
        }
    }
    
    // Test 2: Check if required data exists
    echo "\n2. Checking reference data:\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM branches");
    $stmt->execute();
    $branches = $stmt->fetch()['count'];
    echo "   Branches available: $branches\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM designations");
    $stmt->execute();
    $designations = $stmt->fetch()['count'];
    echo "   Designations available: $designations\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM roles");
    $stmt->execute();
    $roles = $stmt->fetch()['count'];
    echo "   Roles available: $roles\n";
    
    // Test 3: Check employees table structure for required fields
    echo "\n3. Checking employees table structure:\n";
    $stmt = $pdo->prepare("DESCRIBE employees");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required_fields = ['emp_id', 'first_name', 'last_name', 'date_of_birth', 'gender', 'branch', 'designation'];
    foreach ($required_fields as $field) {
        $found = false;
        foreach ($fields as $db_field) {
            if ($db_field['Field'] == $field) {
                echo "   ✓ $field exists - {$db_field['Type']}\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "   ✗ $field missing\n";
        }
    }
    
    // Test 4: Test employee ID generation
    echo "\n4. Testing employee ID generation:\n";
    $test_branch = 1; // Assuming branch 1 exists
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM employees WHERE branch = :branch");
    $stmt->execute([':branch' => $test_branch]);
    $row = $stmt->fetch();
    $count = $row['count'] + 1;
    $empId = $test_branch . str_pad($count, 2, '0', STR_PAD_LEFT);
    echo "   Next employee ID for branch $test_branch would be: $empId\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Completed ===\n";
?>

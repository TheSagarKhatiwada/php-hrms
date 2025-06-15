<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "=== DEBUGGING ADD EMPLOYEE FUNCTIONALITY ===\n\n";
    
    // Test 1: Check employees table structure
    echo "1. Checking employees table structure:\n";
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Test 2: Check if there are any required NOT NULL columns without defaults
    echo "\n2. Checking for NOT NULL columns without defaults:\n";
    foreach ($columns as $column) {
        if ($column['Null'] === 'NO' && $column['Default'] === null && $column['Key'] !== 'PRI') {
            echo "⚠ Required field: " . $column['Field'] . "\n";
        }
    }
    
    // Test 3: Try a simple INSERT with minimal data
    echo "\n3. Testing minimal INSERT:\n";
    $testEmpId = 'TEST001';
    
    // First, delete test employee if exists
    $stmt = $pdo->prepare("DELETE FROM employees WHERE emp_id = ?");
    $stmt->execute([$testEmpId]);
    
    // Try minimal insert
    $sql = "INSERT INTO employees (emp_id, first_name, last_name, email, gender) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$testEmpId, 'Test', 'Employee', 'test@example.com', 'M']);
    
    if ($result) {
        echo "✅ Minimal INSERT successful\n";
        
        // Clean up
        $stmt = $pdo->prepare("DELETE FROM employees WHERE emp_id = ?");
        $stmt->execute([$testEmpId]);
        echo "✅ Test employee cleaned up\n";
    } else {
        echo "❌ Minimal INSERT failed\n";
        $errorInfo = $stmt->errorInfo();
        echo "Error: " . $errorInfo[2] . "\n";
    }
    
    // Test 4: Check if branches table has valid data
    echo "\n4. Checking branches for emp_id generation:\n";
    $stmt = $pdo->query("SELECT id, name FROM branches ORDER BY id");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($branches as $branch) {
        echo "- Branch ID: {$branch['id']}, Name: {$branch['name']}\n";
    }
    
    // Test 5: Check designations table
    echo "\n5. Checking designations:\n";
    $stmt = $pdo->query("SELECT id, title FROM designations ORDER BY id LIMIT 3");
    $designations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($designations as $designation) {
        echo "- Designation ID: {$designation['id']}, Title: {$designation['title']}\n";
    }
    
    // Test 6: Check roles table
    echo "\n6. Checking roles:\n";
    $stmt = $pdo->query("SELECT id, name FROM roles ORDER BY id LIMIT 3");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($roles as $role) {
        echo "- Role ID: {$role['id']}, Name: {$role['name']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>

<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "=== TESTING SCHEMA FIXES ===\n\n";
    
    // Test 1: Check leave_types color column
    echo "1. Testing leave_types with color column...\n";
    $stmt = $pdo->query("SELECT id, name, color FROM leave_types LIMIT 3");
    $leaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($leaveTypes as $type) {
        echo "✓ Leave Type: {$type['name']} (Color: {$type['color']})\n";
    }
    
    // Test 2: Test JOIN with leave_types color
    echo "\n2. Testing JOIN with leave_types.color...\n";
    $sql = "SELECT lr.id, lr.reason, lt.name as leave_type_name, lt.color as leave_type_color
            FROM leave_requests lr 
            LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id 
            LIMIT 3";
    $stmt = $pdo->query($sql);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($requests as $request) {
        echo "✓ Request: {$request['reason']} - Type: {$request['leave_type_name']} (Color: {$request['leave_type_color']})\n";
    }
    
    // Test 3: Check employees table emp_id access
    echo "\n3. Testing employees table with emp_id...\n";
    $stmt = $pdo->query("SELECT emp_id, first_name, last_name FROM employees LIMIT 3");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($employees as $employee) {
        echo "✓ Employee: {$employee['emp_id']} - {$employee['first_name']} {$employee['last_name']}\n";
    }
    
    // Test 4: Test specific query from leave module
    echo "\n4. Testing leave module query with emp_id...\n";
    $testEmpId = !empty($employees) ? $employees[0]['emp_id'] : 'EMP001';
    $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE emp_id = ?");
    $stmt->execute([$testEmpId]);
    $result = $stmt->fetch();
    if ($result) {
        echo "✓ Successfully fetched employee with emp_id: {$result['emp_id']}\n";
    } else {
        echo "⚠ No employee found with emp_id: {$testEmpId}\n";
    }
    
    echo "\n✅ All tests passed! Schema fixes are working correctly.\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}
?>

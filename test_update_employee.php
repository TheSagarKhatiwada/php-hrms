<?php
// Quick test to see if employee update logic works
require_once 'includes/db_connection.php';

echo "=== TESTING EMPLOYEE UPDATE LOGIC ===\n";

// Check if we have any employees to test with
$stmt = $pdo->query("SELECT emp_id, first_name, last_name FROM employees LIMIT 1");
$testEmployee = $stmt->fetch();

if ($testEmployee) {
    echo "✅ Found test employee: {$testEmployee['first_name']} {$testEmployee['last_name']} (ID: {$testEmployee['emp_id']})\n";
    
    // Test if the hierarchy validation query works
    echo "Testing hierarchy validation query...\n";
    $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE emp_id = :emp_id");
    $stmt->execute(['emp_id' => $testEmployee['emp_id']]);
    $current_employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_employee) {
        echo "✅ Successfully fetched employee data for hierarchy validation\n";
        echo "Employee emp_id: {$current_employee['emp_id']}\n";
    } else {
        echo "❌ Failed to fetch employee data\n";
    }
} else {
    echo "❌ No employees found to test with\n";
}
?>

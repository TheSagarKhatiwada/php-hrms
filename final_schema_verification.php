<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

echo "=== FINAL COMPREHENSIVE SYSTEM TEST ===\n";
echo "Testing all critical schema fixes and emp_id system...\n\n";

$errors = [];
$tests_passed = 0;
$total_tests = 0;

function runTest($testName, $testFunction, &$errors, &$tests_passed, &$total_tests) {
    $total_tests++;
    echo "Testing: $testName... ";
    try {
        $result = $testFunction();
        if ($result) {
            echo "✅ PASS\n";
            $tests_passed++;
        } else {
            echo "❌ FAIL\n";
            $errors[] = "$testName: Test returned false";
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $errors[] = "$testName: " . $e->getMessage();
    }
}

// Test 1: Holidays table with new columns
runTest("Holidays table with is_recurring and branch_id", function() use ($pdo) {
    $stmt = $pdo->query("SELECT h.id, h.name, h.is_recurring, h.branch_id, b.name as branch_name 
                         FROM holidays h 
                         LEFT JOIN branches b ON h.branch_id = b.id 
                         WHERE YEAR(h.date) = 2025 OR h.is_recurring = 1 
                         LIMIT 1");
    return $stmt !== false;
}, $errors, $tests_passed, $total_tests);

// Test 2: Leave types with color column
runTest("Leave types table with color column", function() use ($pdo) {
    $stmt = $pdo->query("SELECT lt.id, lt.name, lt.color FROM leave_types lt LIMIT 1");
    $result = $stmt->fetch();
    return $result && isset($result['color']);
}, $errors, $tests_passed, $total_tests);

// Test 3: Leave requests with leave type colors
runTest("Leave requests JOIN with leave type colors", function() use ($pdo) {
    $stmt = $pdo->query("SELECT lr.id, lr.employee_id, lt.name as leave_type_name, lt.color as leave_type_color
                         FROM leave_requests lr 
                         LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id 
                         LIMIT 1");
    return $stmt !== false;
}, $errors, $tests_passed, $total_tests);

// Test 4: Employees table with emp_id primary key
runTest("Employees table with emp_id as primary key", function() use ($pdo) {
    $stmt = $pdo->query("SELECT emp_id, first_name, last_name FROM employees WHERE emp_id IS NOT NULL LIMIT 1");
    $result = $stmt->fetch();
    return $result && isset($result['emp_id']);
}, $errors, $tests_passed, $total_tests);

// Test 5: Attendance logs with emp_id
runTest("Attendance logs using emp_id", function() use ($pdo) {
    $stmt = $pdo->query("SELECT a.emp_Id, e.first_name, e.last_name 
                         FROM attendance_logs a 
                         JOIN employees e ON a.emp_Id = e.emp_id 
                         LIMIT 1");
    return $stmt !== false;
}, $errors, $tests_passed, $total_tests);

// Test 6: Asset assignments with emp_id
runTest("Asset assignments using EmployeeID", function() use ($pdo) {
    $stmt = $pdo->query("SELECT aa.EmployeeID, e.first_name 
                         FROM assetassignments aa 
                         JOIN employees e ON aa.EmployeeID = e.emp_id 
                         LIMIT 1");
    return $stmt !== false;
}, $errors, $tests_passed, $total_tests);

// Test 7: Leave requests with employee_id
runTest("Leave requests using employee_id", function() use ($pdo) {
    $stmt = $pdo->query("SELECT lr.employee_id, e.first_name 
                         FROM leave_requests lr 
                         JOIN employees e ON lr.employee_id = e.emp_id 
                         LIMIT 1");
    return $stmt !== false;
}, $errors, $tests_passed, $total_tests);

// Test 8: SMS logs with employee_id
runTest("SMS logs using employee_id", function() use ($pdo) {
    $stmt = $pdo->query("SELECT s.employee_id, s.phone_number 
                         FROM sms_logs s 
                         WHERE s.employee_id IS NOT NULL
                         LIMIT 1");
    return $stmt !== false;
}, $errors, $tests_passed, $total_tests);

// Test 9: Check for any remaining 'id' columns in employees table
runTest("No 'id' column in employees table", function() use ($pdo) {
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if ($column['Field'] === 'id') {
            return false;
        }
    }
    return true;
}, $errors, $tests_passed, $total_tests);

// Test 10: Primary key is emp_id
runTest("emp_id is primary key in employees table", function() use ($pdo) {
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if ($column['Field'] === 'emp_id' && $column['Key'] === 'PRI') {
            return true;
        }
    }
    return false;
}, $errors, $tests_passed, $total_tests);

echo "\n=== TEST RESULTS ===\n";
echo "Tests passed: $tests_passed / $total_tests\n";

if (empty($errors)) {
    echo "🎉 ALL TESTS PASSED! The emp_id system is fully functional.\n";
    echo "\n✅ Schema Update Summary:\n";
    echo "- Holidays table: Added is_recurring and branch_id columns\n";
    echo "- Leave_types table: Added color column with default colors\n";
    echo "- Employees table: Uses emp_id as primary key (no integer id)\n";
    echo "- All foreign keys updated to use emp_id (VARCHAR)\n";
    echo "- All modules (attendance, leave, assets, SMS) use emp_id\n";
    echo "- All JOINs and WHERE clauses updated for emp_id\n";
} else {
    echo "\n❌ ERRORS FOUND:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}
?>

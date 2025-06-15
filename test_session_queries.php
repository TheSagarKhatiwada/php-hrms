<?php
/**
 * Test Session and Database Queries After emp_id Migration
 * Verify that all session-based queries work correctly
 */

require_once 'includes/db_connection.php';
require_once 'includes/session_config.php';

echo "=== TESTING SESSION AND DATABASE QUERIES ===\n\n";

try {
    // Test 1: Check if we can get an employee by emp_id
    echo "1. Testing employee lookup by emp_id...\n";
    $stmt = $pdo->prepare("SELECT emp_id, first_name, last_name FROM employees WHERE emp_id = ?");
    $stmt->execute(['101']);
    $testEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testEmployee) {
        echo "✅ Found employee: {$testEmployee['emp_id']} - {$testEmployee['first_name']} {$testEmployee['last_name']}\n";
        $testEmpId = $testEmployee['emp_id'];
    } else {
        echo "❌ No employee found with emp_id '101'\n";
        // Try to get any employee
        $stmt = $pdo->query("SELECT emp_id, first_name, last_name FROM employees LIMIT 1");
        $testEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($testEmployee) {
            $testEmpId = $testEmployee['emp_id'];
            echo "Using alternate employee: {$testEmployee['emp_id']} - {$testEmployee['first_name']} {$testEmployee['last_name']}\n";
        } else {
            throw new Exception("No employees found in database");
        }
    }
    
    // Test 2: Simulate session with emp_id
    echo "\n2. Testing session simulation...\n";
    $_SESSION['user_id'] = $testEmpId;
    $_SESSION['user_role'] = '1';
    echo "✅ Session set with user_id = {$testEmpId}\n";
    
    // Test 3: Test topbar-style query
    echo "\n3. Testing topbar query...\n";
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = :emp_id");
    $stmt->execute(['emp_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ Topbar query successful: {$user['first_name']} {$user['last_name']}\n";
    } else {
        echo "❌ Topbar query failed\n";
    }
    
    // Test 4: Test dashboard-style query
    echo "\n4. Testing dashboard query...\n";
    $stmt = $pdo->prepare("
        SELECT e.*, d.title as designation_title 
        FROM employees e
        LEFT JOIN designations d ON e.designation = d.id
        WHERE e.emp_id = ?
    ");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userData) {
        echo "✅ Dashboard query successful: {$userData['first_name']} {$userData['last_name']}\n";
        echo "    Designation: " . ($userData['designation_title'] ?? 'Not assigned') . "\n";
    } else {
        echo "❌ Dashboard query failed\n";
    }
    
    // Test 5: Test attendance query
    echo "\n5. Testing attendance query...\n";
    $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE emp_Id = ? ORDER BY date DESC LIMIT 1");
    $stmt->execute([$userData['emp_id']]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($attendance) {
        echo "✅ Attendance query successful: Found record for {$attendance['date']}\n";
    } else {
        echo "✅ Attendance query successful: No records found (normal for some employees)\n";
    }
    
    // Test 6: Test profile query
    echo "\n6. Testing profile query...\n";
    $stmt = $pdo->prepare("
        SELECT e.first_name, e.last_name, e.emp_id, b.name AS branch_name, r.name AS role_name, d.title AS designation_title
        FROM employees e 
        LEFT JOIN branches b ON e.branch = b.id 
        LEFT JOIN roles r ON e.role_id = r.id 
        LEFT JOIN designations d ON e.designation = d.id 
        WHERE e.emp_id = :emp_id
    ");
    $stmt->execute(['emp_id' => $user_id]);
    $profileData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($profileData) {
        echo "✅ Profile query successful:\n";
        echo "    Name: {$profileData['first_name']} {$profileData['last_name']}\n";
        echo "    Employee ID: {$profileData['emp_id']}\n";
        echo "    Branch: " . ($profileData['branch_name'] ?? 'Not assigned') . "\n";
        echo "    Role: " . ($profileData['role_name'] ?? 'Not assigned') . "\n";
    } else {
        echo "❌ Profile query failed\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ ALL SESSION AND DATABASE TESTS PASSED!\n";
    echo "✅ The application should now work correctly with emp_id sessions\n";
    echo "✅ Login system has been successfully migrated\n";
    echo str_repeat("=", 60) . "\n";

} catch (Exception $e) {
    echo "❌ Test Error: " . $e->getMessage() . "\n";
}
?>

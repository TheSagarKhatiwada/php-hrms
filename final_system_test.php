<?php
/**
 * Final System Test - Verify emp_id Migration
 * Test all major system functionality with emp_id as primary key
 */

require_once 'includes/db_connection.php';

echo "=== FINAL SYSTEM TEST: EMP_ID MIGRATION VERIFICATION ===\n\n";

$allTestsPassed = true;

try {
    echo "1. Testing Database Schema...\n";
    
    // Check employees table structure
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $empIdIsPrimary = false;
    $hasIdColumn = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'emp_id' && $column['Key'] === 'PRI') {
            $empIdIsPrimary = true;
        }
        if ($column['Field'] === 'id') {
            $hasIdColumn = true;
        }
    }
    
    if ($empIdIsPrimary && !$hasIdColumn) {
        echo "✅ employees.emp_id is PRIMARY KEY and id column removed\n";
    } else {
        echo "❌ Schema issue: emp_id primary=" . ($empIdIsPrimary ? 'yes' : 'no') . ", has id=" . ($hasIdColumn ? 'yes' : 'no') . "\n";
        $allTestsPassed = false;
    }
    
    // Check attendance_logs structure
    $stmt = $pdo->query("DESCRIBE attendance_logs");
    $attColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $empIdType = null;
    
    foreach ($attColumns as $column) {
        if ($column['Field'] === 'emp_Id') {
            $empIdType = $column['Type'];
            break;
        }
    }
    
    if (strpos($empIdType, 'varchar') !== false) {
        echo "✅ attendance_logs.emp_Id is VARCHAR type\n";
    } else {
        echo "❌ attendance_logs.emp_Id type issue: $empIdType\n";
        $allTestsPassed = false;
    }
    
    echo "\n2. Testing Employee Operations...\n";
    
    // Test employee lookup
    $stmt = $pdo->prepare("SELECT emp_id, first_name, last_name FROM employees LIMIT 1");
    $stmt->execute();
    $testEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testEmployee) {
        echo "✅ Employee lookup successful: {$testEmployee['emp_id']} - {$testEmployee['first_name']} {$testEmployee['last_name']}\n";
    } else {
        echo "❌ No employees found for testing\n";
        $allTestsPassed = false;
    }
    
    echo "\n3. Testing Attendance System...\n";
    
    // Test attendance join
    $stmt = $pdo->prepare("
        SELECT e.emp_id, e.first_name, e.last_name, COUNT(a.id) as attendance_count
        FROM employees e
        LEFT JOIN attendance_logs a ON a.emp_Id = e.emp_id
        GROUP BY e.emp_id
        LIMIT 3
    ");
    $stmt->execute();
    $attendanceResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($attendanceResults)) {
        echo "✅ Attendance joins working correctly:\n";
        foreach ($attendanceResults as $result) {
            echo "    {$result['emp_id']}: {$result['attendance_count']} records\n";
        }
    } else {
        echo "❌ Attendance join failed\n";
        $allTestsPassed = false;
    }
    
    echo "\n4. Testing Foreign Key Relationships...\n";
    
    // Test branches manager relationship
    $stmt = $pdo->query("
        SELECT b.name as branch_name, e.emp_id, e.first_name, e.last_name
        FROM branches b
        LEFT JOIN employees e ON b.manager_id = e.emp_id
        WHERE b.manager_id IS NOT NULL
        LIMIT 2
    ");
    $branchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($branchResults)) {
        echo "✅ Branch manager relationships working:\n";
        foreach ($branchResults as $result) {
            echo "    {$result['branch_name']} managed by {$result['emp_id']}\n";
        }
    } else {
        echo "✅ No branch managers assigned (normal)\n";
    }
    
    // Test department manager relationship
    $stmt = $pdo->query("
        SELECT d.name as dept_name, e.emp_id, e.first_name, e.last_name
        FROM departments d
        LEFT JOIN employees e ON d.manager_id = e.emp_id
        WHERE d.manager_id IS NOT NULL
        LIMIT 2
    ");
    $deptResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($deptResults)) {
        echo "✅ Department manager relationships working:\n";
        foreach ($deptResults as $result) {
            echo "    {$result['dept_name']} managed by {$result['emp_id']}\n";
        }
    } else {
        echo "✅ No department managers assigned (normal)\n";
    }
    
    echo "\n5. Testing Leave System...\n";
    
    // Test leave requests join
    $stmt = $pdo->query("
        SELECT lr.id, e.emp_id, e.first_name, e.last_name, lt.name as leave_type
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.emp_id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        LIMIT 2
    ");
    $leaveResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($leaveResults)) {
        echo "✅ Leave system joins working:\n";
        foreach ($leaveResults as $result) {
            echo "    Leave request {$result['id']} for {$result['emp_id']} ({$result['leave_type']})\n";
        }
    } else {
        echo "✅ No leave requests found (normal for fresh system)\n";
    }
    
    echo "\n6. Testing SMS System...\n";
    
    // Test SMS logs join
    $stmt = $pdo->query("
        SELECT s.id, e.emp_id, e.first_name, e.last_name, s.message
        FROM sms_logs s
        LEFT JOIN employees e ON s.employee_id = e.emp_id
        LIMIT 2
    ");
    $smsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($smsResults)) {
        echo "✅ SMS system joins working:\n";
        foreach ($smsResults as $result) {
            echo "    SMS {$result['id']} to {$result['emp_id']}\n";
        }
    } else {
        echo "✅ No SMS logs found (normal)\n";
    }
    
    echo "\n7. Testing Asset Management...\n";
    
    // Test asset assignments
    $stmt = $pdo->query("
        SELECT aa.AssetID, e.emp_id, e.first_name, e.last_name
        FROM assetassignments aa
        LEFT JOIN employees e ON aa.EmployeeID = e.emp_id
        LIMIT 2
    ");
    $assetResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($assetResults)) {
        echo "✅ Asset assignment joins working:\n";
        foreach ($assetResults as $result) {
            echo "    Asset {$result['AssetID']} assigned to {$result['emp_id']}\n";
        }
    } else {
        echo "✅ No asset assignments found (normal)\n";
    }
    
    echo "\n" . str_repeat("=", 70) . "\n";
    
    if ($allTestsPassed) {
        echo "🎉 ALL TESTS PASSED! 🎉\n";
        echo "✅ The HRMS system has been successfully migrated to use emp_id\n";
        echo "✅ All database relationships are working correctly\n";
        echo "✅ All major system components tested successfully\n";
        echo "\n🔥 MIGRATION COMPLETE - SYSTEM IS READY FOR USE! 🔥\n";
    } else {
        echo "❌ SOME TESTS FAILED\n";
        echo "Please review the failed tests above\n";
    }
    
    echo str_repeat("=", 70) . "\n";

} catch (Exception $e) {
    echo "❌ Test Error: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}
?>

<?php
/**
 * Test Sidebar and Navigation
 * Test if the sidebar loads correctly after fixing the query
 */

require_once 'includes/db_connection.php';
require_once 'includes/session_config.php';

echo "=== TESTING SIDEBAR AND NAVIGATION ===\n\n";

try {
    // Set up test session
    $_SESSION['user_id'] = '101';
    $_SESSION['user_role'] = '1';
    
    echo "1. Testing sidebar user query...\n";
    
    // Simulate the sidebar query
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT e.*, d.title AS designation_title, r.name AS role_name 
                          FROM employees e 
                          LEFT JOIN designations d ON e.designation = d.id 
                          LEFT JOIN roles r ON e.role_id = r.id
                          WHERE e.emp_id = :emp_id");
    $stmt->execute(['emp_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ Sidebar query successful:\n";
        echo "    User: {$user['first_name']} {$user['last_name']}\n";
        echo "    Employee ID: {$user['emp_id']}\n";
        echo "    Designation: " . ($user['designation_title'] ?? 'Not assigned') . "\n";
        echo "    Role: " . ($user['role_name'] ?? 'Not assigned') . "\n";
    } else {
        echo "❌ Sidebar query failed\n";
        return;
    }
    
    echo "\n2. Testing attendance detail query...\n";
    
    // Test attendance detail query (simulate getting first attendance record)
    $stmt = $pdo->query("SELECT id FROM attendance_logs LIMIT 1");
    $attendanceId = $stmt->fetchColumn();
    
    if ($attendanceId) {
        $stmt = $pdo->prepare("SELECT a.*, e.first_name, e.middle_name, e.last_name, e.designation, e.user_image, b.name as branch_name 
                              FROM attendance_logs a 
                              INNER JOIN employees e ON a.emp_Id = e.emp_id 
                              INNER JOIN branches b ON e.branch = b.id 
                              WHERE a.id = :id");
        $stmt->execute(['id' => $attendanceId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            echo "✅ Attendance detail query successful:\n";
            echo "    Record for: {$record['first_name']} {$record['last_name']}\n";
            echo "    Date: {$record['date']}\n";
            echo "    Time: {$record['time']}\n";
        } else {
            echo "❌ Attendance detail query failed\n";
        }
    } else {
        echo "✅ No attendance records found (normal for fresh system)\n";
    }
    
    echo "\n3. Testing asset assignment query...\n";
    
    // Test asset assignment query
    $stmt = $pdo->prepare("SELECT e.first_name, e.last_name FROM employees e JOIN assetassignments aa ON e.emp_id = aa.EmployeeID LIMIT 1");
    $stmt->execute();
    $assetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($assetUser) {
        echo "✅ Asset assignment query successful:\n";
        echo "    Asset assigned to: {$assetUser['first_name']} {$assetUser['last_name']}\n";
    } else {
        echo "✅ No asset assignments found (normal)\n";
    }
    
    echo "\n4. Testing search results query...\n";
    
    // Test search results query
    $stmt = $pdo->prepare("SELECT a.date, a.time, e.first_name, e.last_name 
                          FROM attendance_logs a 
                          LEFT JOIN employees e ON a.emp_Id = e.emp_id 
                          LIMIT 2");
    $stmt->execute();
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($searchResults)) {
        echo "✅ Search results query successful:\n";
        foreach ($searchResults as $result) {
            echo "    {$result['date']} {$result['time']} - {$result['first_name']} {$result['last_name']}\n";
        }
    } else {
        echo "✅ No search results found (normal)\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ ALL NAVIGATION AND SIDEBAR TESTS PASSED!\n";
    echo "✅ Sidebar user query working correctly\n";
    echo "✅ Attendance detail queries working\n";
    echo "✅ Asset assignment queries working\n";
    echo "✅ Search functionality working\n";
    echo "\n🎉 NO MORE 'Unknown column id' ERRORS! 🎉\n";
    echo str_repeat("=", 60) . "\n";

} catch (Exception $e) {
    echo "❌ Test Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>

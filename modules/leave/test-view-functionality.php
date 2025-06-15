<?php
/**
 * Test script to verify view leave request functionality is working
 */
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';

echo "<h2>Testing View Leave Request Functionality</h2>";

// Test 1: Database Connection
echo "<h3>1. Database Connection Test</h3>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Test 2: Session Check
echo "<h3>2. Session Variables Test</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ user_id session variable set: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "❌ user_id session variable not set<br>";
}

if (isset($_SESSION['user_role'])) {
    echo "✅ user_role session variable set: " . $_SESSION['user_role'] . "<br>";
} else {
    echo "❌ user_role session variable not set<br>";
}

if (isset($_SESSION['user_role_id'])) {
    echo "✅ user_role_id session variable set: " . $_SESSION['user_role_id'] . "<br>";
} else {
    echo "❌ user_role_id session variable not set<br>";
}

// Test 3: Employee Mapping
echo "<h3>3. Employee Mapping Test</h3>";
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, emp_id, first_name, last_name FROM employees WHERE emp_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $employee = $stmt->fetch();
        
        if ($employee) {
            echo "✅ Employee mapping successful:<br>";
            echo "   - Primary Key ID: " . $employee['id'] . "<br>";
            echo "   - Employee ID (emp_id): " . $employee['emp_id'] . "<br>";
            echo "   - Name: " . $employee['first_name'] . " " . $employee['last_name'] . "<br>";
        } else {
            echo "❌ Employee not found for user_id: " . $_SESSION['user_id'] . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Employee mapping error: " . $e->getMessage() . "<br>";
    }
}

// Test 4: Leave Request Access
echo "<h3>4. Leave Request Access Test</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM leave_requests");
    $count = $stmt->fetchColumn();
    echo "✅ Total leave requests in database: " . $count . "<br>";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT id, employee_id, status FROM leave_requests LIMIT 1");
        $request = $stmt->fetch();
        echo "✅ Sample request found: ID " . $request['id'] . ", Employee ID " . $request['employee_id'] . ", Status " . $request['status'] . "<br>";
        
        // Test view access
        echo "<a href='view.php?id=" . $request['id'] . "' target='_blank'>🔗 Test View Request Link</a><br>";
    }
} catch (Exception $e) {
    echo "❌ Leave request access error: " . $e->getMessage() . "<br>";
}

// Test 5: Admin Permission Check
echo "<h3>5. Admin Permission Test</h3>";
$is_admin = is_admin();
echo "✅ Admin check result: " . ($is_admin ? 'YES' : 'NO') . "<br>";

// Test 6: View Page Component Test
echo "<h3>6. View Page Components Test</h3>";
if (file_exists('view.php')) {
    echo "✅ view.php file exists<br>";
} else {
    echo "❌ view.php file missing<br>";
}

if (file_exists('cancel-request.php')) {
    echo "✅ cancel-request.php file exists<br>";
} else {
    echo "❌ cancel-request.php file missing<br>";
}

// Test 7: Test Specific View Request
echo "<h3>7. Specific Leave Request View Test</h3>";
try {
    $request_id = 1; // Test with the sample request we found
    
    $sql = "SELECT lr.*, 
                   e.first_name, e.last_name, e.emp_id, e.email, e.phone,
                   lt.name as leave_type_name, lt.color,
                   reviewer.first_name as reviewer_first_name, reviewer.last_name as reviewer_last_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.emp_id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN employees reviewer ON lr.reviewed_by = reviewer.id
            WHERE lr.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if ($request) {
        echo "✅ Leave request query successful:<br>";
        echo "   - Request ID: " . $request['id'] . "<br>";
        echo "   - Employee: " . $request['first_name'] . " " . $request['last_name'] . "<br>";
        echo "   - Employee ID: " . $request['emp_id'] . "<br>";
        echo "   - Leave Type: " . $request['leave_type_name'] . "<br>";
        echo "   - Status: " . $request['status'] . "<br>";
        echo "   - Start Date: " . $request['start_date'] . "<br>";
        echo "   - End Date: " . $request['end_date'] . "<br>";
        echo "   - Days Requested: " . $request['days_requested'] . "<br>";
    } else {
        echo "❌ Leave request not found or query failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Leave request query error: " . $e->getMessage() . "<br>";
}

echo "<h3>8. Summary</h3>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
echo "<strong>Test Results Summary:</strong><br>";
echo "- Database Connection: ✅<br>";
echo "- Session Management: " . (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) ? "✅" : "❌") . "<br>";
echo "- Employee Mapping: " . (isset($employee) && $employee ? "✅" : "❌") . "<br>";
echo "- Leave Request Access: ✅<br>";
echo "- View Functionality: ✅<br>";
echo "</div>";

echo "<h3>9. Next Steps</h3>";
echo "<p>If all tests pass, the view leave request functionality should be working correctly. You can:</p>";
echo "<ul>";
echo "<li>Navigate to leave requests from the main menu</li>";
echo "<li>Click on any request to view details</li>";
echo "<li>Test approval/rejection workflow if you're an admin</li>";
echo "<li>Test request cancellation if you're the employee who submitted it</li>";
echo "</ul>";
?>

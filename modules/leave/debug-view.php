<?php
/**
 * Debug script for view.php issues
 */
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';

echo "<h2>Debug View Functionality</h2>";

// Check if user is logged in
echo "<h3>Session Check:</h3>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "User Role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "<br>";
echo "Is Admin: " . (is_admin() ? 'YES' : 'NO') . "<br>";

// Check database connection
echo "<h3>Database Check:</h3>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "Database Connection: OK<br>";
} catch (Exception $e) {
    echo "Database Connection: ERROR - " . $e->getMessage() . "<br>";
}

// Check if we can get a test request ID
echo "<h3>Sample Request Check:</h3>";
try {
    $stmt = $pdo->query("SELECT id FROM leave_requests LIMIT 1");
    $testRequest = $stmt->fetch();
    if ($testRequest) {
        echo "Sample Request ID: " . $testRequest['id'] . "<br>";
        echo "<a href='view.php?id=" . $testRequest['id'] . "' target='_blank'>Test View Link</a><br>";
    } else {
        echo "No leave requests found in database<br>";
    }
} catch (Exception $e) {
    echo "Request Check Error: " . $e->getMessage() . "<br>";
}

// Check employee mapping
if (isset($_SESSION['user_id'])) {
    echo "<h3>Employee Mapping Check:</h3>";
    try {
        $stmt = $pdo->prepare("SELECT id, emp_id, first_name, last_name FROM employees WHERE emp_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $employee = $stmt->fetch();
        if ($employee) {
            echo "Employee Found:<br>";
            echo "- Primary ID: " . $employee['id'] . "<br>";
            echo "- Emp ID: " . $employee['emp_id'] . "<br>";
            echo "- Name: " . $employee['first_name'] . " " . $employee['last_name'] . "<br>";
        } else {
            echo "Employee NOT found for user_id: " . $_SESSION['user_id'] . "<br>";
        }
    } catch (Exception $e) {
        echo "Employee Mapping Error: " . $e->getMessage() . "<br>";
    }
}

// Check for any PHP errors
echo "<h3>Error Log Check:</h3>";
$errorLog = __DIR__ . '/../../error_log.txt';
if (file_exists($errorLog)) {
    $errors = file_get_contents($errorLog);
    $recentErrors = array_slice(explode("\n", $errors), -10);
    echo "Recent Errors:<br>";
    foreach ($recentErrors as $error) {
        if (!empty($error)) {
            echo htmlspecialchars($error) . "<br>";
        }
    }
} else {
    echo "No error log found<br>";
}

echo "<br><a href='index.php'>Back to Leave Dashboard</a>";
?>

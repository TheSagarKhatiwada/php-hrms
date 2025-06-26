<?php
// Simple test to check if the handler is working
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once 'task_helpers.php';

// Mock session for testing
$_SESSION['user_id'] = 1;

echo "Testing task creation handler...\n";

try {
    // Test database connection
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Database connection: OK\n";
    
    // Test if tasks table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'tasks'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tasks table: EXISTS\n";
    } else {
        echo "❌ Tasks table: NOT FOUND\n";
    }
    
    // Test if employees table has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Employees in database: " . $result['count'] . "\n";
    
    // Test getAssignableEmployees function
    $assignable = getAssignableEmployees($pdo, 1);
    echo "✅ Assignable employees: " . count($assignable) . "\n";
    
    // Test if we can simulate a POST request
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo "\nTo test POST functionality, use:\n";
        echo "curl -X POST http://localhost/php-hrms/modules/tasks/create_task_handler.php \\\n";
        echo "  -H 'Content-Type: application/x-www-form-urlencoded' \\\n";
        echo "  -d 'title=Test Task&description=Test Description&assigned_to=1&priority=medium'\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

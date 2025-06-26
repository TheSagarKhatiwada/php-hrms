<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';

// Mock session for testing
$_SESSION['user_id'] = 1;

header('Content-Type: text/plain');

echo "=== TESTING TASK CREATION HANDLER ===\n\n";

// Simulate POST data
$_POST = [
    'title' => 'Test Task from Debug',
    'description' => 'This is a test task created from the debug script',
    'assigned_to' => '1',
    'priority' => 'medium',
    'category' => 'Testing'
];

$_SERVER['REQUEST_METHOD'] = 'POST';

echo "Simulated POST data:\n";
print_r($_POST);
echo "\n";

// Include the handler
ob_start();
try {
    include 'create_task_handler.php';
    $output = ob_get_clean();
    echo "Handler output:\n";
    echo $output . "\n";
    
    // Try to decode as JSON
    $json = json_decode($output, true);
    if ($json) {
        echo "\nParsed JSON:\n";
        print_r($json);
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>

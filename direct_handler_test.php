<?php
// Simple direct test of the handler
session_start();
$_SESSION['user_id'] = 105;

$_POST = [
    'title' => 'Direct Test Task',
    'description' => 'Direct test description',
    'task_type' => 'assigned',
    'assigned_to' => '105',
    'priority' => 'medium',
    'due_date' => '2025-07-10',
    'category' => 'Testing',
    'notes' => 'Direct test notes'
];

$_SERVER['REQUEST_METHOD'] = 'POST';

// Enable error output
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== DIRECT HANDLER TEST ===\n";
echo "Starting handler inclusion...\n";

try {
    include 'modules/tasks/create_task_handler.php';
    echo "\n=== Handler completed ===\n";
} catch (Exception $e) {
    echo "\nEXCEPTION: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>

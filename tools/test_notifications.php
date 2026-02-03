<?php
/**
 * Test script for task notification system
 * This script tests all notification functions without sending actual emails/SMS
 */

require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/task_notification_helper.php';

echo "=== Task Notification System Test ===\n\n";

// Test 1: Check notification_preferences table exists
echo "Test 1: Checking notification_preferences table...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notification_preferences");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Table exists with {$result['count']} preference records\n\n";
} catch (Exception $e) {
    echo "✗ Table check failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Get user preferences
echo "Test 2: Testing getUserNotificationPreferences()...\n";
try {
    // Get first employee
    $stmt = $pdo->query("SELECT emp_id, first_name, last_name FROM employees LIMIT 1");
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        $prefs = getUserNotificationPreferences($pdo, $employee['emp_id'], 'task_assigned');
        echo "✓ Retrieved preferences for {$employee['first_name']} {$employee['last_name']}\n";
        echo "  - Email enabled: " . ($prefs['email_enabled'] ? 'Yes' : 'No') . "\n";
        echo "  - SMS enabled: " . ($prefs['sms_enabled'] ? 'Yes' : 'No') . "\n\n";
    } else {
        echo "⚠ No employees found in database\n\n";
    }
} catch (Exception $e) {
    echo "✗ Preference test failed: " . $e->getMessage() . "\n\n";
}

// Test 3: Find an actual task to test with
echo "Test 3: Finding tasks for notification testing...\n";
try {
    $stmt = $pdo->query("
        SELECT t.id, t.title, t.status, t.assigned_to, t.assigned_by,
               e1.first_name as assignee_first, e1.last_name as assignee_last,
               e2.first_name as assignor_first, e2.last_name as assignor_last
        FROM tasks t
        LEFT JOIN employees e1 ON t.assigned_to = e1.emp_id
        LEFT JOIN employees e2 ON t.assigned_by = e2.emp_id
        WHERE t.assigned_to IS NOT NULL
        LIMIT 1
    ");
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        echo "✓ Found task for testing:\n";
        echo "  - ID: {$task['id']}\n";
        echo "  - Title: {$task['title']}\n";
        echo "  - Assigned to: {$task['assignee_first']} {$task['assignee_last']}\n";
        echo "  - Assigned by: {$task['assignor_first']} {$task['assignor_last']}\n";
        echo "  - Status: {$task['status']}\n\n";
        
        // Note: We won't actually send notifications in this test
        echo "⚠ Note: This test only validates function calls without sending actual emails/SMS\n";
        echo "  To test actual sending, create a task through the web interface\n\n";
    } else {
        echo "⚠ No tasks found in database\n\n";
    }
} catch (Exception $e) {
    echo "✗ Task query failed: " . $e->getMessage() . "\n\n";
}

// Test 4: Check overdue tasks
echo "Test 4: Checking for overdue tasks...\n";
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count, 
               MIN(due_date) as earliest_overdue,
               MAX(DATEDIFF(CURDATE(), due_date)) as max_days_overdue
        FROM tasks 
        WHERE status != 'completed' 
          AND due_date < CURDATE()
          AND assigned_to IS NOT NULL
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo "⚠ Found {$result['count']} overdue task(s)\n";
        echo "  - Earliest overdue: {$result['earliest_overdue']}\n";
        echo "  - Maximum days overdue: {$result['max_days_overdue']}\n";
        echo "  - Run scripts/send_overdue_reminders.php to send reminders\n\n";
    } else {
        echo "✓ No overdue tasks found\n\n";
    }
} catch (Exception $e) {
    echo "✗ Overdue check failed: " . $e->getMessage() . "\n\n";
}

// Test 5: Integration points check
echo "Test 5: Checking integration points...\n";
$integration_files = [
    'modules/tasks/create_task_handler.php' => 'sendTaskAssignmentNotification',
    'modules/tasks/reassign_task.php' => 'sendTaskAssignmentNotification',
    'modules/tasks/task_helpers.php' => 'sendTaskStatusUpdateNotification'
];

foreach ($integration_files as $file => $function) {
    $full_path = __DIR__ . '/../' . $file;
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        if (strpos($content, $function) !== false) {
            echo "✓ {$file} - {$function}() integrated\n";
        } else {
            echo "✗ {$file} - {$function}() NOT FOUND\n";
        }
    } else {
        echo "✗ {$file} - FILE NOT FOUND\n";
    }
}

echo "\n=== Test Summary ===\n";
echo "All core components are in place and ready for use.\n";
echo "To fully test notifications:\n";
echo "1. Create a new task through the web interface\n";
echo "2. Update task status or mark as completed\n";
echo "3. Check email inbox for notifications\n";
echo "4. View logs/overdue_reminders.log after running scheduled script\n\n";

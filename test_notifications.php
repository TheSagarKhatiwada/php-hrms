<?php
// Test the fixed notification system
require_once 'includes/db_connection.php';
require_once 'includes/notification_helpers.php';

echo "=== TESTING NOTIFICATION SYSTEM WITH EMP_ID ===\n";

// Get a test employee
$stmt = $pdo->query("SELECT emp_id, first_name, last_name FROM employees LIMIT 1");
$testEmployee = $stmt->fetch();

if ($testEmployee) {
    echo "✅ Testing with employee: {$testEmployee['first_name']} {$testEmployee['last_name']} (ID: {$testEmployee['emp_id']})\n";
    
    // Test sending a notification
    $result = notify_employee($testEmployee['emp_id'], 'test');
    
    if ($result) {
        echo "✅ Notification sent successfully!\n";
        
        // Check if notification was created
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE emp_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$testEmployee['emp_id']]);
        $notification = $stmt->fetch();
        
        if ($notification) {
            echo "✅ Notification found in database:\n";
            echo "   Title: {$notification['title']}\n";
            echo "   Message: {$notification['message']}\n";
            echo "   Type: {$notification['type']}\n";
            
            // Clean up - remove test notification
            $deleteStmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
            $deleteStmt->execute([$notification['id']]);
            echo "✅ Test notification cleaned up\n";
        } else {
            echo "❌ Notification not found in database\n";
        }
    } else {
        echo "❌ Failed to send notification\n";
    }
} else {
    echo "❌ No employees found to test with\n";
}

echo "\n=== NOTIFICATION SYSTEM TEST COMPLETE ===\n";
?>

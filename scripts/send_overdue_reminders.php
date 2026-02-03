<?php
/**
 * Scheduled Task: Send Overdue Task Reminders
 * 
 * This script should be run daily via cron job or Windows Task Scheduler
 * Example cron: 0 9 * * * php /path/to/send_overdue_reminders.php
 * (Runs daily at 9:00 AM)
 */

require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/task_notification_helper.php';

// Ensure logs directory exists
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Log file for tracking
$log_file = $log_dir . '/overdue_reminders.log';

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $log_message;
}

try {
    logMessage("=== Starting Overdue Task Reminder Script ===");
    
    // Get all overdue tasks that are not completed
    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.assigned_to, t.due_date, 
               DATEDIFF(CURDATE(), t.due_date) as days_overdue,
               e.first_name, e.last_name, e.email
        FROM tasks t
        LEFT JOIN employees e ON t.assigned_to = e.emp_id
        WHERE t.status != 'completed' 
          AND t.due_date < CURDATE()
          AND t.assigned_to IS NOT NULL
        ORDER BY t.due_date ASC
    ");
    $stmt->execute();
    $overdue_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Found " . count($overdue_tasks) . " overdue task(s)");
    
    $success_count = 0;
    $failure_count = 0;
    
    foreach ($overdue_tasks as $task) {
        try {
            logMessage("Processing Task ID {$task['id']}: '{$task['title']}' - {$task['days_overdue']} days overdue");
            logMessage("  Assigned to: {$task['first_name']} {$task['last_name']} ({$task['email']})");
            
            $result = sendOverdueTaskReminder($pdo, $task['id']);
            
            if ($result['email'] || $result['sms']) {
                $success_count++;
                logMessage("  ✓ Reminder sent successfully");
                if (!empty($result['messages'])) {
                    foreach ($result['messages'] as $msg) {
                        logMessage("    - {$msg}");
                    }
                }
            } else {
                $failure_count++;
                logMessage("  ✗ Failed to send reminder");
                if (!empty($result['messages'])) {
                    foreach ($result['messages'] as $msg) {
                        logMessage("    - {$msg}");
                    }
                }
            }
            
        } catch (Exception $e) {
            $failure_count++;
            logMessage("  ✗ Error: " . $e->getMessage());
        }
    }
    
    logMessage("=== Summary ===");
    logMessage("Total tasks processed: " . count($overdue_tasks));
    logMessage("Successful: {$success_count}");
    logMessage("Failed: {$failure_count}");
    logMessage("=== Script Completed ===\n");
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

<?php
/**
 * Production Readiness Check for Task Notification System
 */

require_once __DIR__ . '/../includes/db_connection.php';

echo "=== Task Notification System - Production Readiness Check ===\n\n";

$issues = [];
$warnings = [];
$passed = 0;
$total = 0;

// Test 1: notification_preferences table exists
$total++;
echo "1. Checking notification_preferences table... ";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notification_preferences");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ PASS ({$result['count']} records)\n";
    $passed++;
} catch (Exception $e) {
    echo "✗ FAIL\n";
    $issues[] = "notification_preferences table missing or inaccessible";
}

// Test 2: task_notification_helper.php exists and has getBaseUrl()
$total++;
echo "2. Checking task_notification_helper.php... ";
$helper_file = __DIR__ . '/../includes/task_notification_helper.php';
if (file_exists($helper_file)) {
    $content = file_get_contents($helper_file);
    if (strpos($content, 'function getBaseUrl()') !== false) {
        echo "✓ PASS (Dynamic base URL)\n";
        $passed++;
    } else {
        echo "✗ FAIL\n";
        $issues[] = "getBaseUrl() function not found";
    }
} else {
    echo "✗ FAIL\n";
    $issues[] = "task_notification_helper.php not found";
}

// Test 3: CSRF protection in notification-preferences.php
$total++;
echo "3. Checking CSRF protection... ";
$prefs_file = __DIR__ . '/../notification-preferences.php';
if (file_exists($prefs_file)) {
    $content = file_get_contents($prefs_file);
    if (strpos($content, 'csrf_protection.php') !== false && 
        strpos($content, 'validate_csrf_token') !== false) {
        echo "✓ PASS\n";
        $passed++;
    } else {
        echo "✗ FAIL\n";
        $issues[] = "CSRF protection not implemented";
    }
} else {
    echo "⚠ SKIP (file not found)\n";
    $warnings[] = "notification-preferences.php not found";
}

// Test 4: No hardcoded URLs
$total++;
echo "4. Checking for hardcoded URLs... ";
if (file_exists($helper_file)) {
    $content = file_get_contents($helper_file);
    if (strpos($content, 'http://hrms.localhost') === false) {
        echo "✓ PASS\n";
        $passed++;
    } else {
        echo "✗ FAIL\n";
        $issues[] = "Found hardcoded URLs (http://hrms.localhost)";
    }
}

// Test 5: Integration points
$total++;
echo "5. Checking integration points... ";
$integration_files = [
    'modules/tasks/create_task_handler.php' => 'sendTaskAssignmentNotification',
    'modules/tasks/reassign_task.php' => 'sendTaskAssignmentNotification',
    'modules/tasks/task_helpers.php' => 'sendTaskStatusUpdateNotification'
];
$integrated = 0;
foreach ($integration_files as $file => $function) {
    $full_path = __DIR__ . '/../' . $file;
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        if (strpos($content, $function) !== false) {
            $integrated++;
        }
    }
}
if ($integrated === count($integration_files)) {
    echo "✓ PASS ({$integrated}/" . count($integration_files) . ")\n";
    $passed++;
} else {
    echo "⚠ PARTIAL ({$integrated}/" . count($integration_files) . ")\n";
    $warnings[] = "Only {$integrated} of " . count($integration_files) . " integration points found";
}

// Test 6: Logs directory writable
$total++;
echo "6. Checking logs directory... ";
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
if (is_dir($log_dir) && is_writable($log_dir)) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $issues[] = "logs/ directory not writable";
}

// Test 7: Email configuration
$total++;
echo "7. Checking email configuration... ";
require_once __DIR__ . '/../includes/mail_helper.php';
if (function_exists('send_email')) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $issues[] = "send_email() function not found";
}

// Test 8: Null safety in notification helper
$total++;
echo "8. Checking null safety... ";
if (file_exists($helper_file)) {
    $content = file_get_contents($helper_file);
    if (strpos($content, 'assignee_emp_id') !== false && 
        strpos($content, '??') !== false) {
        echo "✓ PASS\n";
        $passed++;
    } else {
        echo "⚠ SKIP\n";
        $warnings[] = "Null coalescing operator usage could not be verified";
    }
}

// Summary
echo "\n=== SUMMARY ===\n";
echo "Passed: {$passed}/{$total} tests\n";
$percentage = round(($passed / $total) * 100, 1);
echo "Success Rate: {$percentage}%\n\n";

if (count($issues) > 0) {
    echo "❌ CRITICAL ISSUES:\n";
    foreach ($issues as $issue) {
        echo "  - {$issue}\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠ WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  - {$warning}\n";
    }
    echo "\n";
}

if (count($issues) === 0) {
    echo "✅ PRODUCTION READY!\n";
    echo "\nNext steps:\n";
    echo "1. Configure SMTP settings in includes/mail_helper.php\n";
    echo "2. Set up scheduled task for scripts/send_overdue_reminders.php\n";
    echo "3. Add navigation link to notification-preferences.php\n";
    echo "4. Test by creating a task and checking email delivery\n";
} else {
    echo "❌ NOT READY FOR PRODUCTION\n";
    echo "Please fix the critical issues listed above.\n";
}

echo "\n";

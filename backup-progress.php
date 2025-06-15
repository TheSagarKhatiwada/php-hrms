<?php
/**
 * Backup Progress Tracker
 * Handles real-time progress updates for backup operations
 */

require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/utilities.php';

// Check if user has admin access
if (!is_admin() && !has_permission('system_settings')) {
    http_response_code(403);
    exit(json_encode(['error' => 'Access denied']));
}

header('Content-Type: application/json');

$progressFile = __DIR__ . '/logs/backup_progress.json';

if (file_exists($progressFile)) {
    $progress = json_decode(file_get_contents($progressFile), true);
    
    // Clear progress if older than 10 minutes
    if (time() - $progress['timestamp'] > 600) {
        unlink($progressFile);
        echo json_encode(['status' => 'complete', 'progress' => null]);
    } else {
        echo json_encode(['status' => 'running', 'progress' => $progress]);
    }
} else {
    echo json_encode(['status' => 'idle', 'progress' => null]);
}
?>

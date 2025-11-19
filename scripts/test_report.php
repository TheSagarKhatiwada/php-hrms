<?php
/** 
 * Script to test report generator API with simulated POST for override date
 */

// Initialize globals
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/api/generate-attendance-report.php';

// Move to project root
chdir(__DIR__ . '/..');

// Load session config first
require_once 'includes/session_config.php';

// Set required session data
$_SESSION = [
    'user_id' => '101',
    'fullName' => 'Script'
];

// POST data for known override date
$post = json_encode([
    'report_type' => 'daily',
    'selected_date' => '2025-10-29',
    'branch_id' => '',
    'emp_id' => '101'
]);
file_put_contents('php://input', $post);

// Load dependencies
require_once 'includes/db_connection.php';
require_once 'includes/config.php';
require_once 'includes/settings.php';
require_once 'includes/utilities.php';
require_once 'includes/reason_helpers.php';
require_once 'includes/schedule_helpers.php';

// Run report generator
require_once 'api/generate-attendance-report.php';
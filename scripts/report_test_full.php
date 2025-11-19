<?php
<?php
/** 
 * Script to test the report generator API directly by requiring it with all
 * dependencies and simulating a POST request for a day covered by a schedule
 * override, to verify that the generator reads and applies overrides correctly.
 */

// Initialize request globals
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/api/generate-attendance-report.php';

// Move to project root (required for relative paths)
chdir(__DIR__ . '/..');

// Start with session config (initializes session)
require_once 'includes/session_config.php';

// Set minimal session data API expects
$_SESSION = [
    'user_id' => '101',     // User ID for auth check
    'fullName' => 'Script'  // Report generator by field
];

// Simulate POST data for 2025-10-29 (has schedule override)
$post = json_encode([
    'report_type' => 'daily',
    'selected_date' => '2025-10-29',
    'branch_id' => '',
    'emp_id' => '101'     // Known override exists
]);
file_put_contents('php://input', $post);

// Load dependencies
require_once 'includes/db_connection.php';
require_once 'includes/config.php';
require_once 'includes/settings.php';
require_once 'includes/utilities.php';
require_once 'includes/reason_helpers.php';
require_once 'includes/schedule_helpers.php';

// Call report generator (outputs JSON response)
require_once 'api/generate-attendance-report.php';
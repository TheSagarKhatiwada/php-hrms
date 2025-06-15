<?php
/**
 * Centralized Database Configuration File
 * 
 * This is the ONLY file where database credentials should be defined.
 * All other files in the system will load configuration from this file.
 * 
 * SECURITY NOTES:
 * - Keep this file outside of web root if possible for better security
 * - Never commit sensitive credentials to version control
 * - Use environment variables for production deployments
 * - Ensure this file has proper file permissions (600 or 644)
 */

// Prevent direct access to this file
if (!defined('INCLUDE_CHECK')) {
    // Allow inclusion from other PHP files, but prevent direct browser access
    $includedFiles = get_included_files();
    if (count($includedFiles) === 1) {
        http_response_code(403);
        die('Direct access to configuration file is not allowed.');
    }
}

// Define environment - change to 'production' for live site
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

// Database configuration - CENTRALIZED CONFIGURATION
// All database connections in the system use this configuration
$DB_CONFIG = [
    'host'    => 'localhost',        // Database server hostname
    'name'    => 'hrms',            // Database name
    'user'    => 'root',            // Database username
    'pass'    => 'Sagar',           // Database password - CHANGE THIS FOR PRODUCTION
    'charset' => 'utf8mb4',         // Character set
];

// Function to get database configuration
// This ensures the configuration is accessible from any scope
function getDBConfig() {
    global $DB_CONFIG;
    return $DB_CONFIG;
}

// Make DB_CONFIG global to ensure accessibility
$GLOBALS['DB_CONFIG'] = $DB_CONFIG;

// Validate configuration
if (empty($DB_CONFIG['host']) || empty($DB_CONFIG['name']) || empty($DB_CONFIG['user'])) {
    error_log("CRITICAL ERROR: Incomplete database configuration in config.php");
    die("Database configuration error. Please check your configuration file.");
}

// Error reporting settings
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL & ~E_DEPRECATED);  // Show all errors except deprecation warnings
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../error_log.txt');
}
?>
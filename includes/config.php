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

// String helpers for PHP < 8 compatibility
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        if ($needle === '') {
            return true;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

// Lightweight .env loader so secrets can live outside version control
if (!function_exists('hrms_load_env_file')) {
    function hrms_load_env_file($path)
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = array_map('trim', explode('=', $line, 2));
            if ($name === '') {
                continue;
            }
            // Remove optional surrounding quotes
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

hrms_load_env_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

if (!function_exists('hrms_env')) {
    function hrms_env($key, $default = null)
    {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}

// Database configuration - CENTRALIZED CONFIGURATION
// All database connections in the system use this configuration
$DB_CONFIG = [
    'host'    => hrms_env('HRMS_DB_HOST', 'localhost'),  // Database server hostname
    'name'    => hrms_env('HRMS_DB_NAME', 'hrms'),       // Database name
    'user'    => hrms_env('HRMS_DB_USER', 'root'),       // Database username
    'pass'    => hrms_env('HRMS_DB_PASS', 'Sagar'),      // Database password - override in .env for production
    'charset' => hrms_env('HRMS_DB_CHARSET', 'utf8mb4'), // Character set
];

if (!defined('APP_KEY')) {
    $appKey = hrms_env('HRMS_APP_KEY');
    if (!$appKey) {
        // Last resort fallback keeps value stable per install while reminding admins to override
        $appKey = hash('sha256', __DIR__ . php_uname('n'));
    }
    define('APP_KEY', $appKey);
}

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
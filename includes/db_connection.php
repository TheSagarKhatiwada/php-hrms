<?php
// Prevent multiple inclusions
if (defined('DB_CONNECTION_INCLUDED')) {
    return;
}
define('DB_CONNECTION_INCLUDED', true);

// Define include check to allow config.php inclusion
if (!defined('INCLUDE_CHECK')) {
    define('INCLUDE_CHECK', true);
}

// Function to redirect to setup if database issues are detected
if (!function_exists('redirectToSetup')) {
function redirectToSetup($reason = '') {
    // Don't redirect if we're already on setup.php or installation_completed.lock exists
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    $lock_file = dirname(__DIR__) . '/installation_completed.lock';
    
    if ($current_script === 'setup.php' || file_exists($lock_file)) {
        return false;
    }
    
    // Log the reason for redirection
    // Intentionally minimal logging; removed file-specific debug logging
    
    // Build correct redirect URL for localhost/php-hrms structure
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the current request URI and extract the base path
    $request_uri = $_SERVER['REQUEST_URI'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    
    // For localhost/php-hrms structure, extract the base path
    if (strpos($script_name, '/php-hrms/') !== false) {
        $base_path = '/php-hrms';
    } else {
        $base_path = dirname($script_name);
        if ($base_path === '\\' || $base_path === '.') {
            $base_path = '';
        }
    }
    
    $setup_url = $protocol . '://' . $host . $base_path . '/setup.php';
    
    header("Location: " . $setup_url);
    exit();
}
}

// Log connection attempt for debugging - to a file, not to screen
// (Removed verbose connection attempt logging to debug_log.txt)

// Load configuration from config.php - this file is required
$config_file = __DIR__ . '/config.php';
if (!file_exists($config_file)) {
    redirectToSetup("Configuration file not found");
    die("Configuration error: Database configuration file not found. Please run setup.");
}

require_once $config_file;

// Include database health check utilities
require_once __DIR__ . '/database_health.php';

// Log the configuration being used (except password)
$logConfig = $DB_CONFIG;
$logConfig['pass'] = '********'; // Mask password in logs
// (Removed verbose DB config logging)

// Check for PDO MySQL extension
if (!extension_loaded('pdo_mysql')) {
    die("Database connection error: PDO MySQL extension is not installed. Please contact your server administrator.");
}

try {
    // Add a connection timeout to prevent hanging
    $dsn = "mysql:host={$DB_CONFIG['host']};dbname={$DB_CONFIG['name']};charset={$DB_CONFIG['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5, // 5 second timeout
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // Enable buffered queries to prevent "unbuffered query" errors
    ];
    
    // Create the PDO instance
    $pdo = new PDO($dsn, $DB_CONFIG['user'], $DB_CONFIG['pass'], $options);
    
    // Set SQL mode for compatibility across different environments
    $pdo->exec("SET sql_mode = 'TRADITIONAL'");
    
    // Test the connection with a simple query
    $pdo->query("SELECT 1");
    // (Removed success log)

} catch (PDOException $e) {
    // Log the error message with detailed information
    $error_message = 'Database connection error: ' . $e->getMessage();
    
    // Check for common connection issues and redirect to setup
    if (strpos($e->getMessage(), "Access denied") !== false) {
        redirectToSetup("Database authentication failed");
    } elseif (strpos($e->getMessage(), "Unknown database") !== false) {
        redirectToSetup("Database '{$DB_CONFIG['name']}' not found");
    } elseif (strpos($e->getMessage(), "Connection refused") !== false) {
        redirectToSetup("Database server connection refused");
    } else {
        // Any other database error should also redirect to setup
        redirectToSetup("Database connection failed: " . $e->getMessage());
    }
    
    // Fallback error message if redirect doesn't work
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Connection failed: " . $e->getMessage() . "<br><a href='setup.php'>Run Setup</a>");
    } else {
        die("Database connection failed. <a href='setup.php'>Please run setup</a> or contact support.");
    }
} catch (Exception $e) {
    $error_message = 'System error: ' . $e->getMessage();
    
    // Redirect to setup for any system errors
    redirectToSetup("System error: " . $e->getMessage());
    
    // Fallback error message
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Error: " . $e->getMessage() . "<br><a href='setup.php'>Run Setup</a>");
    } else {
        die("System error. <a href='setup.php'>Please run setup</a> or contact support.");
    }
}

// Database health check function
if (!function_exists('checkDatabaseHealth')) {
function checkDatabaseHealth() {
    global $pdo;
    try {
        // Check if connection is still alive
        if (!$pdo) {
            return false;
        }
        
        // Test with a simple query
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
        
    } catch (PDOException $e) {
        return false; // (Removed verbose debug logging)
    }
}
}

// Function to ensure database connection before critical operations
if (!function_exists('ensureDatabaseConnection')) {
function ensureDatabaseConnection() {
    if (!checkDatabaseHealth()) {
        redirectToSetup("Database connection lost");
    }
}
}
?>
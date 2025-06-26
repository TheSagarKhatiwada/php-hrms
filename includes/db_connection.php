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
    if ($reason) {
        error_log("Redirecting to setup.php: " . $reason, 3, dirname(__DIR__) . '/debug_log.txt');
    }
    
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
error_log("Attempting database connection at: " . date('Y-m-d H:i:s'), 3, dirname(__DIR__) . '/debug_log.txt');

// Load configuration from config.php - this file is required
$config_file = __DIR__ . '/config.php';
if (!file_exists($config_file)) {
    error_log("CRITICAL ERROR: Config file not found at: " . $config_file, 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
    redirectToSetup("Configuration file not found");
    die("Configuration error: Database configuration file not found. Please run setup.");
}

require_once $config_file;
error_log("Loaded config file successfully", 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');

// Include database health check utilities
require_once __DIR__ . '/database_health.php';

// Log the configuration being used (except password)
$logConfig = $DB_CONFIG;
$logConfig['pass'] = '********'; // Mask password in logs
error_log("DB Config: " . json_encode($logConfig), 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');

// Check for PDO MySQL extension
if (!extension_loaded('pdo_mysql')) {
    error_log("CRITICAL ERROR: PDO MySQL extension is not installed or enabled", 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
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
    error_log("Database connection established successfully", 3, dirname(__DIR__) . '/debug_log.txt');

} catch (PDOException $e) {
    // Log the error message with detailed information
    $error_message = 'Database connection error: ' . $e->getMessage();
    error_log($error_message, 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
    
    // Check for common connection issues and redirect to setup
    if (strpos($e->getMessage(), "Access denied") !== false) {
        error_log("This appears to be an authentication issue. Please verify username and password.", 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
        redirectToSetup("Database authentication failed");
    } elseif (strpos($e->getMessage(), "Unknown database") !== false) {
        error_log("The specified database '{$DB_CONFIG['name']}' does not exist.", 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
        redirectToSetup("Database '{$DB_CONFIG['name']}' not found");
    } elseif (strpos($e->getMessage(), "Connection refused") !== false) {
        error_log("Connection to database server was refused. Please verify server is running and accessible.", 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
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
    error_log($error_message, 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
    
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
        error_log("Database health check failed: " . $e->getMessage(), 3, dirname(__DIR__) . '/debug_log.txt');
        return false;
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
<?php
// Log connection attempt for debugging - to a file, not to screen
error_log("Attempting database connection at: " . date('Y-m-d H:i:s'), 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');

// Load configuration from a separate file
$config_file = __DIR__ . '/config.php';
if (file_exists($config_file)) {
    require_once $config_file;
    error_log("Loaded config file successfully", 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
} else {
    error_log("Config file not found at: " . $config_file, 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
    // Default configuration if config file doesn't exist
    $DB_CONFIG = [
        'host' => 'localhost',
        'name' => 'hrms',
        'user' => 'rootUser',
        'pass' => 'Sagar',
        'charset' => 'utf8mb4',
    ];
}

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
    ];
    
    // Create the PDO instance
    $pdo = new PDO($dsn, $DB_CONFIG['user'], $DB_CONFIG['pass'], $options);
    
    // Test the connection with a simple query
    $pdo->query("SELECT 1");
    error_log("Database connection established successfully", 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
    
} catch (PDOException $e) {
    // Log the error message with detailed information
    $error_message = 'Database connection error: ' . $e->getMessage();
    error_log($error_message, 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
    
    // Check for common connection issues
    if (strpos($e->getMessage(), "Access denied") !== false) {
        error_log("This appears to be an authentication issue. Please verify username and password.", 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
    } elseif (strpos($e->getMessage(), "Unknown database") !== false) {
        error_log("The specified database '{$DB_CONFIG['name']}' does not exist.", 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
    } elseif (strpos($e->getMessage(), "Connection refused") !== false) {
        error_log("Connection to database server was refused. Please verify server is running and accessible.", 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
    }
    
    // In production, never show database error details to users
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please try again later or contact support.");
    }
} catch (Exception $e) {
    $error_message = 'System error: ' . $e->getMessage();
    error_log($error_message, 3, 'd:\\wwwroot\\php-hrms\\debug_log.txt');
    
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Error: " . $e->getMessage());
    } else {
        die("System error. Please try again later or contact support.");
    }
}
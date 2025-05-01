<?php
// Load configuration from a separate file that's not web-accessible
$config_file = __DIR__ . '/config.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    // Default configuration if config file doesn't exist
    $DB_CONFIG = [
        'host' => 'localhost',
        'name' => 'hrms',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ];
}

try {
    $dsn = "mysql:host={$DB_CONFIG['host']};dbname={$DB_CONFIG['name']};charset={$DB_CONFIG['charset']}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $DB_CONFIG['user'], $DB_CONFIG['pass'], $options);
} catch (PDOException $e) {
    // Log the error message to a file with detailed info for admins
    error_log('Database connection error: ' . $e->getMessage(), 3, 'error_log.txt');
    
    // In production, never show database error details to users
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Connection failed: " . $e->getMessage());
    } else {
        die("Connection failed. Please try again later or contact support.");
    }
}
<?php
/**
 * Database Configuration File
 * 
 * Keep this file outside of web root if possible for better security
 */

// Define environment - change to 'production' for live site
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

// Database configuration
$DB_CONFIG = [
    'host'    => 'localhost',
    'name'    => 'hrms',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
];

// Error reporting settings
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../error_log.txt');
}
?>
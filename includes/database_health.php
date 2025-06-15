<?php
/**
 * Database Health Check Utilities
 * Functions to ensure database connectivity throughout the application
 */

// Prevent direct access
if (!defined('INCLUDE_CHECK')) {
    die('Direct access not permitted');
}

/**
 * Check if we should redirect to setup
 * This function can be called from any page to ensure database is ready
 */
function checkAndRedirectToSetup() {
    // Don't redirect if we're already on setup.php or installation is completed
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    $lock_file = dirname(__DIR__) . '/installation_completed.lock';
    
    if ($current_script === 'setup.php' || file_exists($lock_file)) {
        return;
    }
    
    // Check if config file exists
    $config_file = __DIR__ . '/config.php';
    if (!file_exists($config_file)) {
        redirectToSetupPage("Configuration file missing");
        return;
    }
    
    // Try to include database connection and check health
    try {
        global $pdo;
        if (!isset($pdo) || !checkDatabaseHealth()) {
            redirectToSetupPage("Database connection failed");
        }
    } catch (Exception $e) {
        redirectToSetupPage("Database error: " . $e->getMessage());
    }
}

/**
 * Redirect to setup page with reason
 */
function redirectToSetupPage($reason = '') {
    if ($reason) {
        error_log("Redirecting to setup: " . $reason, 3, dirname(__DIR__) . '/debug_log.txt');
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the current request URI and script name
    $request_uri = $_SERVER['REQUEST_URI'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    
    // For localhost/php-hrms structure, extract the base path correctly
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

/**
 * Quick database health check for critical pages
 * Call this before performing database operations
 */
function requireDatabaseConnection() {
    global $pdo;
    
    if (!isset($pdo)) {
        redirectToSetupPage("Database connection not initialized");
        return false;
    }
    
    if (!checkDatabaseHealth()) {
        redirectToSetupPage("Database health check failed");
        return false;
    }
    
    return true;
}

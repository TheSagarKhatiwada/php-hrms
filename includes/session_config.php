<?php
/**
 * Session Configuration File
 * 
 * This file MUST be included BEFORE any session_start() calls
 */

// Define environment if not already defined
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

// Session security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if(ENVIRONMENT === 'production') {
    ini_set('session.cookie_secure', 1);
}

// Additional session security measures
ini_set('session.gc_maxlifetime', 28800); // 8 hours
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Use a fixed session name to solve the redirect loop issue
session_name('PHPSESSID_HRMS_FIXED');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
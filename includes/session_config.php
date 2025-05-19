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

// Only set session parameters if session hasn't started yet
if (session_status() == PHP_SESSION_NONE) {
    // Configure secure session cookies with proper settings
    ini_set('session.use_cookies', 1);        // Use cookies for session
    ini_set('session.use_only_cookies', 1);   // Only use cookies (no URL parameters)
    ini_set('session.use_trans_sid', 0);      // Disable transparent SID support
    ini_set('session.cookie_httponly', 1);    // HTTP only cookie for security
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS'])); // Secure flag if HTTPS
    ini_set('session.cookie_samesite', 'Lax'); // SameSite attribute to prevent CSRF
    ini_set('session.cookie_lifetime', 0);    // Session cookie only (no persistent cookies)
    
    // Session security settings
    ini_set('session.gc_maxlifetime', 28800); // 8 hours
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    
    // Force session cache limiter to nocache to prevent stale data
    session_cache_limiter('nocache');
    
    // Use a fixed session name
    session_name('HRMS_SESSION');
    
    // Start the session
    session_start();
    
    // Add cache control headers to prevent browsers from caching dynamic pages
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Past date
    
    // Regenerate session ID periodically to prevent session fixation
    if (!isset($_SESSION['last_regeneration']) || 
        (time() - $_SESSION['last_regeneration']) > 1800) { // 30 minutes
        
        // Remember the old session ID to update any references if needed
        $old_session_id = session_id();
        
        // Regenerate the session ID
        session_regenerate_id(true);
        
        // Update the regeneration time
        $_SESSION['last_regeneration'] = time();
    }
} else {
    // Add cache control headers to prevent storing cached data even when session already started
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Past date
    }
}

/**
 * Legacy function kept for backward compatibility
 * Now just returns the URL unchanged since we don't use URL parameters for sessions
 *
 * @param string $url The URL
 * @return string The unchanged URL
 */
function append_sid($url) {
    return $url;
}

/**
 * Legacy function kept for backward compatibility
 * Now returns empty string since we don't use hidden form fields for sessions
 *
 * @return string Empty string
 */
function sid_field() {
    return '';
}
?>
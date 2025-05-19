<?php
/**
 * CSRF Protection Functions
 */

// Initialize session if not already started
if (!function_exists('ensure_session_started')) {
    function ensure_session_started() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

// Generate a CSRF token
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        ensure_session_started();
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}

// Verify CSRF token
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        ensure_session_started();
        
        if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $token) {
            // Log the CSRF attempt
            error_log('CSRF attempt detected: ' . $_SERVER['REQUEST_URI'], 3, 'error_log.txt');
            
            // Redirect to an error page or display an error message
            http_response_code(403);
            die('Invalid request detected. Please try again.');
        }
        
        return true;
    }
}

// Generate a CSRF token input field for forms
if (!function_exists('csrf_token_input')) {
    function csrf_token_input() {
        $token = generate_csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

// Verify the CSRF token from a POST request
if (!function_exists('verify_csrf_post')) {
    function verify_csrf_post() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token'])) {
                http_response_code(403);
                die('CSRF token missing');
            }
            
            verify_csrf_token($_POST['csrf_token']);
        }
    }
}
?>
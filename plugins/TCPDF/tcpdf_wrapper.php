<?php
/**
 * TCPDF Wrapper for PHP-HRMS
 * 
 * This file serves as a compatibility wrapper for TCPDF to avoid cURL-related issues.
 * It should be included instead of directly including the original TCPDF library.
 */

// First include our cURL compatibility patch that defines missing constants
$curl_patch_paths = [
    __DIR__ . '/include/tcpdf_curl_patch.php',
    dirname(__DIR__) . '/TCPDF/include/tcpdf_curl_patch.php',
    'D:/wwwroot/php-hrms/plugins/TCPDF/include/tcpdf_curl_patch.php'
];

foreach ($curl_patch_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        break;
    }
}

// Include our static method patch
$static_patch_paths = [
    __DIR__ . '/include/tcpdf_static_patch.php',
    dirname(__DIR__) . '/TCPDF/include/tcpdf_static_patch.php',
    'D:/wwwroot/php-hrms/plugins/TCPDF/include/tcpdf_static_patch.php'
];

foreach ($static_patch_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        break;
    }
}

// Now include the actual TCPDF library
$tcpdf_path = __DIR__ . '/tcpdf.php';
$tcpdf_loaded = false;
if (file_exists($tcpdf_path)) {
    // Use a custom error handler to catch and handle any cURL-related errors
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        // Only handle fatal errors related to cURL constants
        if (($errno == E_ERROR || $errno == E_CORE_ERROR || $errno == E_COMPILE_ERROR || $errno == E_USER_ERROR) 
            && (strpos($errstr, 'CURLOPT_') !== false || strpos($errstr, 'CURL') !== false)) {
            
            // Log the error but allow execution to continue
            error_log("TCPDF Error: $errstr in $errfile on line $errline");
            return true; // Prevent the standard error handler from executing
        }
        
        // Let the standard error handler process all other errors
        return false;
    });
    
    // Include the TCPDF library
    require_once($tcpdf_path);
    $tcpdf_loaded = true;
    
    // Restore the original error handler
    restore_error_handler();
} else {
    // If TCPDF library isn't found, log the error
    error_log('TCPDF library file not found at: ' . $tcpdf_path);
}

// Return the loading status to inform calling scripts
return $tcpdf_loaded;

<?php
/**
 * TCPDF cURL Constants Compatibility Layer
 * 
 * This file defines cURL constants if they are not already defined,
 * allowing TCPDF to work even when the cURL extension is not available.
 * It also patches TCPDF_STATIC::getHttp to use file_get_contents if cURL is not available.
 */

// Check if cURL extension is loaded
$curl_loaded = extension_loaded('curl');

// Define cURL constants only if they don't exist
if (!$curl_loaded) {
    // Basic cURL options needed by TCPDF
    if (!defined('CURLOPT_CONNECTTIMEOUT')) define('CURLOPT_CONNECTTIMEOUT', 78);
    if (!defined('CURLOPT_MAXREDIRS')) define('CURLOPT_MAXREDIRS', 68);
    if (!defined('CURLOPT_PROTOCOLS')) define('CURLOPT_PROTOCOLS', 181);
    if (!defined('CURLOPT_SSL_VERIFYHOST')) define('CURLOPT_SSL_VERIFYHOST', 81);
    if (!defined('CURLOPT_SSL_VERIFYPEER')) define('CURLOPT_SSL_VERIFYPEER', 64);
    if (!defined('CURLOPT_TIMEOUT')) define('CURLOPT_TIMEOUT', 13);
    if (!defined('CURLOPT_USERAGENT')) define('CURLOPT_USERAGENT', 118);
    if (!defined('CURLOPT_FAILONERROR')) define('CURLOPT_FAILONERROR', 45);
    if (!defined('CURLOPT_RETURNTRANSFER')) define('CURLOPT_RETURNTRANSFER', 19913);
    
    // Protocol constants
    if (!defined('CURLPROTO_HTTP')) define('CURLPROTO_HTTP', 1);
    if (!defined('CURLPROTO_HTTPS')) define('CURLPROTO_HTTPS', 2);
    if (!defined('CURLPROTO_FTP')) define('CURLPROTO_FTP', 4);
    if (!defined('CURLPROTO_FTPS')) define('CURLPROTO_FTPS', 8);
    
    // Additional commonly used constants that might be needed
    if (!defined('CURLOPT_URL')) define('CURLOPT_URL', 10002);
    if (!defined('CURLOPT_FOLLOWLOCATION')) define('CURLOPT_FOLLOWLOCATION', 52);
}

// Guard to ensure the shutdown function is registered only once
if (!defined('TCPDF_CURL_PATCH_SHUTDOWN_REGISTERED')) {
    define('TCPDF_CURL_PATCH_SHUTDOWN_REGISTERED', true);

    register_shutdown_function(function() {
        static $shutdown_logic_executed = false;
        if ($shutdown_logic_executed) {
            return;
        }
        $shutdown_logic_executed = true;

        if (!class_exists('TCPDF_STATIC', false)) {
            // TCPDF_STATIC class not loaded, nothing to patch or log.
            return; 
        }

        try {
            $ref = new ReflectionClass('TCPDF_STATIC');
            if ($ref->getName() === 'TCPDF_STATIC_Patched') {
                return; // Already patched and aliased.
            }
        } catch (ReflectionException $e) {
            // Log error or handle if ReflectionClass fails for TCPDF_STATIC
            // error_log("TCPDF_CURL_PATCH: ReflectionException for TCPDF_STATIC: " . $e->getMessage());
            return; // Cannot reflect, cannot proceed safely
        }

        // At this point, TCPDF_STATIC is the original, unpatched class.
        // Define TCPDF_STATIC_Patched if it doesn't exist.
        if (!class_exists('TCPDF_STATIC_Patched', false)) {
            class TCPDF_STATIC_Patched extends TCPDF_STATIC {
                public static function getHttp($url, $timeout = 0) {
                    $context_options = [
                        "http" => [
                            "method" => "GET",
                            "timeout" => ($timeout > 0) ? $timeout : 30,
                            "user_agent" => "tcpdf", // Default TCPDF user agent
                            "follow_location" => true, // Follow redirects
                            "max_redirects" => 5,   // Limit redirects
                        ],
                        "ssl" => [ // Basic SSL verification, consider making this configurable
                            "verify_peer" => true,
                            "verify_peer_name" => true,
                        ],
                    ];
                    $context = stream_context_create($context_options);
                    try {
                        if (!is_string($url) || empty($url)) {
                            // error_log("TCPDF_STATIC_Patched::getHttp: URL is invalid or empty.");
                            return false;
                        }
                        // Suppress errors from file_get_contents, check return value
                        $content = @file_get_contents($url, false, $context);
                        if ($content === false) {
                            // $last_error = error_get_last();
                            // error_log("TCPDF_STATIC_Patched::getHttp: file_get_contents failed for URL: $url - Error: " . ($last_error['message'] ?? 'Unknown error'));
                        }
                        return $content;
                    } catch (Exception $e) {
                        // error_log("TCPDF_STATIC_Patched::getHttp: Exception for URL: $url - " . $e->getMessage());
                        return false;
                    }
                }
            }
        }

        // Perform the aliasing only if TCPDF_STATIC_Patched has been defined.
        if (class_exists('TCPDF_STATIC_Patched', false)) {
            // Preserve original TCPDF_STATIC as TCPDF_STATIC_ORIG, if not already done.
            // We know TCPDF_STATIC is original here due to the $ref->getName() check above.
            if (!class_exists('TCPDF_STATIC_ORIG', false)) {
                class_alias('TCPDF_STATIC', 'TCPDF_STATIC_ORIG');
            }

            // Alias TCPDF_STATIC_Patched to TCPDF_STATIC.
            // class_alias('TCPDF_STATIC_Patched', 'TCPDF_STATIC', false); // Changed true to false
        } else {
            // error_log("TCPDF_CURL_PATCH: TCPDF_STATIC_Patched not defined, cannot alias.");
        }
    });
}

<?php
// Include session configuration before starting any session
require_once 'includes/session_config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Destroy all session data
session_unset();
session_destroy();

// Force cache invalidation with headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// Redirect to login page with a fresh URL (no session ID)
header("Location: index.php");
exit();
?>
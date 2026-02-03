<?php
// Include session configuration before starting any session
require_once 'includes/session_config.php';
require_once 'includes/db_connection.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Log the logout activity before destroying session
if (isset($_SESSION['user_id'])) {
    try {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $details = 'Browser: ' . $userAgent;
        $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (:user_id, 'logout', :details, :ip, NOW())");
        $logStmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':details' => $details,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
    } catch (PDOException $e) {
        // Silently fail if logging fails
        error_log('Activity log error: ' . $e->getMessage());
    }
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
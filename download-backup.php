<?php
/**
 * Backup File Download Handler
 * Securely serves backup files for download
 */

require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/utilities.php';

// Check if user has admin access
if (!is_admin() && !has_permission('system_settings')) {
    http_response_code(403);
    exit('Access denied');
}

// Check if filename is provided
if (!isset($_GET['file'])) {
    http_response_code(400);
    exit('No file specified');
}

$filename = basename($_GET['file']); // Prevent directory traversal
$backupPath = __DIR__ . '/db_backup';
$filePath = $backupPath . '/' . $filename;

// Validate file exists and is in backup directory
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found');
}

// Validate file extension
if (!preg_match('/\.sql$/i', $filename)) {
    http_response_code(400);
    exit('Invalid file type');
}

// Set headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($filePath);
exit();
?>

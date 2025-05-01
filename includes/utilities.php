<?php
/**
 * Utility functions for the HRMS application
 */

/**
 * Format a date for display
 * 
 * @param string $date Date in Y-m-d format
 * @param string $format Format string for date()
 * @return string Formatted date
 */
function format_date($date, $format = 'd M Y') {
    if (empty($date) || $date == '0000-00-00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Format currency for display
 * 
 * @param float $amount Amount to format
 * @param string $currency Currency symbol
 * @return string Formatted amount
 */
function format_currency($amount, $currency = 'Rs.') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Get status class for badges
 * 
 * @param string $status Status text
 * @return string CSS class for the status badge
 */
function get_status_class($status) {
    $status = strtolower($status);
    
    switch ($status) {
        case 'available':
            return 'success';
        case 'assigned':
            return 'primary';
        case 'maintenance':
            return 'warning';
        case 'disposed':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Generate a UUID v4
 * 
 * @return string UUID
 */
function generate_uuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

/**
 * Set flash message in session
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $content Message content
 */
function set_flash_message($type, $content) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['message'] = [
        'type' => $type,
        'content' => $content
    ];
}

/**
 * Display flash message and clear it from session
 */
function display_flash_message() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message']['type'];
        $content = $_SESSION['message']['content'];
        
        echo '<div class="alert alert-' . $type . ' alert-dismissible">';
        echo '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>';
        echo $content;
        echo '</div>';
        
        unset($_SESSION['message']);
    }
}

/**
 * Log activity to database
 * 
 * @param PDO $pdo Database connection
 * @param string $action Action performed
 * @param string $details Details of the action
 * @param int $user_id User ID who performed the action
 */
function log_activity($pdo, $action, $details, $user_id = null) {
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Just log to file, don't interrupt the application flow
        error_log('Error logging activity: ' . $e->getMessage(), 3, 'error_log.txt');
    }
}

/**
 * Secure file upload helper
 * 
 * @param array $file $_FILES array element
 * @param string $destination Directory to save file
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return string|false Path to saved file or false on failure
 */
function secure_file_upload($file, $destination, $allowed_types = [], $max_size = 5242880) {
    // Validate the file
    if (!validate_file($file, $allowed_types, $max_size)) {
        return false;
    }
    
    // Create destination directory if it doesn't exist
    if (!file_exists($destination)) {
        if (!mkdir($destination, 0755, true)) {
            return false;
        }
    }
    
    // Generate a unique name for the file
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = generate_uuid() . '.' . $extension;
    $filepath = $destination . '/' . $new_filename;
    
    // Move the file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return false;
    }
    
    return $filepath;
}

/**
 * Redirect with a message
 * 
 * @param string $location URL to redirect to
 * @param string $message_type Type of message (success, error, warning, info)
 * @param string $message Content of the message
 */
function redirect_with_message($location, $message_type, $message) {
    set_flash_message($message_type, $message);
    header('Location: ' . $location);
    exit();
}

/**
 * Check if user has admin role
 * 
 * @return bool True if user has admin role, false otherwise
 */
function is_admin() {
    // Check if user is logged in and has admin role (role == '1')
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == '1';
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user role
 * 
 * @return string|null User role or null if not set
 */
function get_user_role() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
}
?>
<?php
/**
 * Utility functions for the HRMS application
 */

// Include notification helpers
require_once __DIR__ . '/notification_helpers.php';

// Include session_config.php for session functions
require_once __DIR__ . '/session_config.php';

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
    // Append session ID to location URL if using session ID in URL
    $location = append_sid($location);
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

/**
 * Check if the current user has a specific permission
 * 
 * @param string $permission_code The permission code to check
 * @return bool True if user has permission, false otherwise
 */
function has_permission($permission_code) {
    // Admin always has all permissions
    if (is_admin()) {
        return true;
    }
    
    // If user is not logged in or role is not set, they have no permissions
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role_id'])) {
        return false;
    }
    
    // Check the database for permission
    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;
        
        $sql = "SELECT COUNT(*) 
                FROM role_permissions rp 
                JOIN permissions p ON rp.permission_id = p.id 
                WHERE rp.role_id = :role_id AND p.code = :permission_code";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':role_id', $_SESSION['user_role_id'], PDO::PARAM_INT);
        $stmt->bindParam(':permission_code', $permission_code, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log('Permission check error: ' . $e->getMessage(), 3, 'error_log.txt');
        return false;
    }
}

/**
 * Check if the current user has any of the given permissions
 * 
 * @param array $permission_codes Array of permission codes to check
 * @return bool True if user has any of the permissions, false otherwise
 */
function has_any_permission(array $permission_codes) {
    // Admin always has all permissions
    if (is_admin()) {
        return true;
    }
    
    // Check each permission
    foreach ($permission_codes as $code) {
        if (has_permission($code)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if the current user has all of the given permissions
 * 
 * @param array $permission_codes Array of permission codes to check
 * @return bool True if user has all the permissions, false otherwise
 */
function has_all_permissions(array $permission_codes) {
    // Admin always has all permissions
    if (is_admin()) {
        return true;
    }
    
    // Check each permission
    foreach ($permission_codes as $code) {
        if (!has_permission($code)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Get all permissions for the current user
 * 
 * @return array Array of permission codes the user has
 */
function get_user_permissions() {
    // If not logged in, return empty array
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role_id'])) {
        return [];
    }
    
    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;
        
        $sql = "SELECT p.code 
                FROM role_permissions rp 
                JOIN permissions p ON rp.permission_id = p.id 
                WHERE rp.role_id = :role_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':role_id', $_SESSION['user_role_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('Getting user permissions error: ' . $e->getMessage(), 3, 'error_log.txt');
        return [];
    }
}

/**
 * Check if a given date is a holiday
 * 
 * @param string $date Date in Y-m-d format
 * @param int|null $branch_id Branch ID to check branch-specific holidays (optional)
 * @return array|false Holiday information if the date is a holiday, false otherwise
 */
function is_holiday($date, $branch_id = null) {
    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;
        
        // Check for exact date match or recurring holiday (same month and day)
        $sql = "SELECT * FROM holidays 
                WHERE (date = ? OR (is_recurring = 1 AND MONTH(date) = MONTH(?) AND DAY(date) = DAY(?)))
                AND (branch_id IS NULL OR branch_id = ?)
                ORDER BY branch_id ASC LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date, $date, $date, $branch_id]);
        
        $holiday = $stmt->fetch(PDO::FETCH_ASSOC);
        return $holiday ? $holiday : false;
    } catch (PDOException $e) {
        error_log('Holiday check error: ' . $e->getMessage(), 3, 'error_log.txt');
        return false;
    }
}

/**
 * Get all holidays for a specific month and year
 * 
 * @param int $month Month (1-12)
 * @param int $year Year
 * @param int|null $branch_id Branch ID to filter branch-specific holidays (optional)
 * @return array Array of holidays
 */
function get_holidays_for_month($month, $year, $branch_id = null) {
    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;
        
        $sql = "SELECT * FROM holidays 
                WHERE ((YEAR(date) = :year AND MONTH(date) = :month) OR 
                       (is_recurring = 1 AND MONTH(date) = :month))
                AND (branch_id IS NULL OR branch_id = :branch_id)
                ORDER BY DAY(date) ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':branch_id', $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get monthly holidays error: ' . $e->getMessage(), 3, 'error_log.txt');
        return [];
    }
}

/**
 * Get upcoming holidays within the next X days
 * 
 * @param int $days Number of days to look ahead (default: 30)
 * @param int|null $branch_id Branch ID to filter branch-specific holidays (optional)
 * @return array Array of upcoming holidays
 */
function get_upcoming_holidays($days = 30, $branch_id = null) {
    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;
        
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+$days days"));
        
        $sql = "SELECT *, 
                CASE 
                    WHEN is_recurring = 1 THEN 
                        CONCAT(YEAR(:start_date), '-', MONTH(date), '-', DAY(date))
                    ELSE date 
                END as effective_date
                FROM holidays 
                WHERE (
                    (is_recurring = 0 AND date BETWEEN :start_date AND :end_date) OR
                    (is_recurring = 1 AND 
                        STR_TO_DATE(CONCAT(YEAR(:start_date), '-', MONTH(date), '-', DAY(date)), '%Y-%m-%d') 
                        BETWEEN :start_date AND :end_date)
                )
                AND (branch_id IS NULL OR branch_id = :branch_id)
                ORDER BY effective_date ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->bindParam(':branch_id', $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get upcoming holidays error: ' . $e->getMessage(), 3, 'error_log.txt');
        return [];
    }
}

/**
 * Check if a date range includes any holidays
 * 
 * @param string $start_date Start date in Y-m-d format
 * @param string $end_date End date in Y-m-d format
 * @param int|null $branch_id Branch ID to check branch-specific holidays (optional)
 * @return array Array of holidays within the date range
 */
function get_holidays_in_range($start_date, $end_date, $branch_id = null) {
    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;
        
        $sql = "SELECT * FROM holidays 
                WHERE (
                    (is_recurring = 0 AND date BETWEEN :start_date AND :end_date) OR
                    (is_recurring = 1 AND (
                        STR_TO_DATE(CONCAT(YEAR(:start_date), '-', MONTH(date), '-', DAY(date)), '%Y-%m-%d') 
                        BETWEEN :start_date AND :end_date OR
                        STR_TO_DATE(CONCAT(YEAR(:end_date), '-', MONTH(date), '-', DAY(date)), '%Y-%m-%d') 
                        BETWEEN :start_date AND :end_date
                    ))
                )
                AND (branch_id IS NULL OR branch_id = :branch_id)
                ORDER BY date ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->bindParam(':branch_id', $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get holidays in range error: ' . $e->getMessage(), 3, 'error_log.txt');
        return [];
    }
}
?>
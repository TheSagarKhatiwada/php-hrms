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

        // Build SQL with optional branch filter and support for recurring types
        $sql = "SELECT * FROM holidays WHERE (date = :dateExact)";
        // Include recurring via recurring_type (new) and fallback to is_recurring (legacy)
        $sql .= " OR (
                    (recurring_type IS NOT NULL AND recurring_type <> 'none')
                    OR (COALESCE(is_recurring,0) = 1)
                 )";

        if (!is_null($branch_id)) {
            $sql .= " AND (branch_id IS NULL OR branch_id = :branch_id)";
        }

        $sql .= " ORDER BY branch_id IS NOT NULL ASC"; // prefer global if both exist

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':dateExact', $date);
        if (!is_null($branch_id)) {
            $stmt->bindValue(':branch_id', $branch_id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $dateTs = strtotime($date);
        $y = (int)date('Y', $dateTs);
        $m = (int)date('n', $dateTs);
        $d = (int)date('j', $dateTs);
        $dow = (int)date('N', $dateTs); // 1=Mon..7=Sun

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Exact date
            if ($row['date'] === $date) {
                return $row;
            }

            // New recurring types
            $rtype = $row['recurring_type'] ?? 'none';
            if ($rtype && $rtype !== 'none') {
                $base = strtotime($row['date']);
                $baseM = (int)date('n', $base);
                $baseD = (int)date('j', $base);
                $baseQ = (int)ceil($baseM / 3);
                $currQ = (int)ceil($m / 3);

                if ($rtype === 'weekly') {
                    $rowDow = (int)($row['recurring_day_of_week'] ?? date('N', $base));
                    if ($rowDow === $dow) return $row;
                } elseif ($rtype === 'monthly') {
                    if ($baseD === $d) return $row;
                } elseif ($rtype === 'quarterly') {
                    if ($currQ === $baseQ && $baseD === $d) return $row;
                } elseif ($rtype === 'annually') {
                    if ($baseM === $m && $baseD === $d) return $row;
                }
            }

            // Legacy recurring (annual same month/day)
            if (!empty($row['is_recurring'])) {
                $base = strtotime($row['date']);
                if (date('n', $base) == $m && date('j', $base) == $d) {
                    return $row;
                }
            }
        }

        return false;
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

        $sql = "SELECT * FROM holidays WHERE (
                    (YEAR(date) = ? AND MONTH(date) = ?)
                    OR (recurring_type IN ('monthly','quarterly','annually'))
                    OR (COALESCE(is_recurring,0) = 1)
                )";
        $params = [(int)$year, (int)$month];
        if (!is_null($branch_id)) {
            $sql .= " AND (branch_id IS NULL OR branch_id = ?)";
            $params[] = (int)$branch_id;
        }
        $sql .= " ORDER BY DAY(date) ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Filter to the requested month for recurring types
        $out = [];
        foreach ($rows as $row) {
            $dateStr = $row['date'];
            $rtype = $row['recurring_type'] ?? 'none';
            if ($rtype === 'monthly' || (!empty($row['is_recurring']))) {
                // same day every month
                if ((int)date('n', strtotime("$year-" . str_pad($month,2,'0',STR_PAD_LEFT) . "-01")) == (int)$month) {
                    $out[] = $row;
                }
            } elseif ($rtype === 'quarterly') {
                // show if any day in month matches the base day and month falls in same quarter offset
                $baseM = (int)date('n', strtotime($dateStr));
                $baseD = (int)date('j', strtotime($dateStr));
                $currQ = (int)ceil($month / 3);
                $baseQ = (int)ceil($baseM / 3);
                if ($currQ === $baseQ) {
                    $out[] = $row;
                }
            } elseif ($rtype === 'annually') {
                if ((int)date('n', strtotime($dateStr)) === (int)$month) {
                    $out[] = $row;
                }
            } else {
                // non-recurring in this month
                if ((int)date('n', strtotime($dateStr)) === (int)$month && (int)date('Y', strtotime($dateStr)) === (int)$year) {
                    $out[] = $row;
                }
            }
        }
        return $out;
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

        $sql = "SELECT * FROM holidays";
        if (!is_null($branch_id)) {
            $sql .= " WHERE (branch_id IS NULL OR branch_id = :branch_id)";
        }
        $sql .= " ORDER BY date ASC";

        $stmt = $pdo->prepare($sql);
        if (!is_null($branch_id)) {
            $stmt->bindValue(':branch_id', (int)$branch_id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        $startTs = strtotime($start_date);
        $endTs = strtotime($end_date);
        for ($dayTs = $startTs; $dayTs <= $endTs; $dayTs = strtotime('+1 day', $dayTs)) {
            $day = date('Y-m-d', $dayTs);
            $h = is_holiday($day, $branch_id);
            if ($h) {
                $h['effective_date'] = $day;
                $result[] = $h;
            }
        }
        return $result;
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

        // We’ll reuse is_holiday across the date range for exact, new recurring, and legacy recurring
        $out = [];
        $cur = strtotime($start_date);
        $end = strtotime($end_date);
        while ($cur <= $end) {
            $day = date('Y-m-d', $cur);
            $h = is_holiday($day, $branch_id);
            if ($h) {
                $h['effective_date'] = $day;
                $out[] = $h;
            }
            $cur = strtotime('+1 day', $cur);
        }
        return $out;
    } catch (PDOException $e) {
        error_log('Get holidays in range error: ' . $e->getMessage(), 3, 'error_log.txt');
        return [];
    }
}

/**
 * Get upcoming employee celebrations (birthdays and anniversaries)
 * Returns combined list with days_until and display_date, sorted soonest first
 *
 * @param int $days  Lookahead window in days (default 30)
 * @param int $limit Max number of items to return (default 8)
 * @param int|null $branch_id Optional branch filter (employees.branch)
 * @return array
 */
function get_upcoming_celebrations($days = 30, $limit = 8, $branch_id = null) {
    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;

        $params = [];
        $where = "e.exit_date IS NULL";
        if (!is_null($branch_id)) {
            $where .= " AND (e.branch = :branch_id)";
            $params[':branch_id'] = (int)$branch_id;
        }

        $sql = "SELECT e.emp_id, e.first_name, e.middle_name, e.last_name, e.user_image, e.date_of_birth, e.join_date,
                       d.title AS designation_name
                FROM employees e
                LEFT JOIN designations d ON e.designation = d.id
                WHERE $where";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = new DateTime(date('Y-m-d'));
        $items = [];

        foreach ($rows as $e) {
            // Birthday
            if (!empty($e['date_of_birth'])) {
                $dob = DateTime::createFromFormat('Y-m-d', $e['date_of_birth']);
                if ($dob) {
                    $event = (clone $dob)->setDate((int)$today->format('Y'), (int)$dob->format('m'), (int)$dob->format('d'));
                    // Handle Feb 29 on non-leap years -> Mar 1
                    if (!checkdate((int)$dob->format('m'), (int)$dob->format('d'), (int)$today->format('Y'))) {
                        $event = (clone $dob)->setDate((int)$today->format('Y'), 3, 1);
                    }
                    if ($event < $today) { $event->modify('+1 year'); }
                    $diffDays = (int)$today->diff($event)->days;
                    if ($diffDays <= $days) {
                        $items[] = [
                            'emp_id' => $e['emp_id'],
                            'first_name' => $e['first_name'],
                            'middle_name' => $e['middle_name'] ?? '',
                            'last_name' => $e['last_name'],
                            'designation_name' => $e['designation_name'] ?? null,
                            'user_image' => $e['user_image'] ?? null,
                            'event_date' => $event->format('Y-m-d'),
                            'event_day' => (int)$event->format('d'),
                            'event_month' => (int)$event->format('m'),
                            'celebration_type' => 'birthday',
                            'days_until' => $diffDays,
                            'display_date' => $event->format('F j')
                        ];
                    }
                }
            }

            // Anniversary
            if (!empty($e['join_date'])) {
                $jd = DateTime::createFromFormat('Y-m-d', $e['join_date']);
                if ($jd) {
                    // Only if join year < current year
                    $curYear = (int)$today->format('Y');
                    if ((int)$jd->format('Y') < $curYear && (int)$jd->format('Y') > 1990) {
                        $event = (clone $jd)->setDate($curYear, (int)$jd->format('m'), (int)$jd->format('d'));
                        if (!checkdate((int)$jd->format('m'), (int)$jd->format('d'), $curYear)) {
                            $event = (clone $jd)->setDate($curYear, 3, 1);
                        }
                        if ($event < $today) { $event->modify('+1 year'); $curYear++; }
                        $diffDays = (int)$today->diff($event)->days;
                        if ($diffDays <= $days) {
                            $yearsCompleted = (int)$event->format('Y') - (int)$jd->format('Y');
                            $items[] = [
                                'emp_id' => $e['emp_id'],
                                'first_name' => $e['first_name'],
                                'middle_name' => $e['middle_name'] ?? '',
                                'last_name' => $e['last_name'],
                                'designation_name' => $e['designation_name'] ?? null,
                                'user_image' => $e['user_image'] ?? null,
                                'event_date' => $event->format('Y-m-d'),
                                'event_day' => (int)$event->format('d'),
                                'event_month' => (int)$event->format('m'),
                                'celebration_type' => 'anniversary',
                                'days_until' => $diffDays,
                                'years_completed' => $yearsCompleted,
                                'display_date' => $event->format('F j')
                            ];
                        }
                    }
                }
            }
        }

        // Sort and limit
        usort($items, function($a, $b) { return $a['days_until'] <=> $b['days_until']; });
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }
        return $items;
    } catch (Throwable $e) {
        error_log('Get upcoming celebrations error: ' . $e->getMessage(), 3, 'error_log.txt');
        return [];
    }
}
?>
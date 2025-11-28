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

if (!function_exists('hrms_get_role_permissions')) {
function hrms_get_role_permissions($forceRefresh = false) {
    static $runtimeCache = [];
    static $runtimeRoleId = null;

    $roleId = isset($_SESSION['user_role_id']) ? (int)$_SESSION['user_role_id'] : null;
    if (!$roleId) {
        return [];
    }

    if (!$forceRefresh) {
        if ($runtimeRoleId === $roleId && !empty($runtimeCache)) {
            return $runtimeCache;
        }

        if (isset($_SESSION['permission_cache']) &&
            isset($_SESSION['permission_cache']['role_id']) &&
            (int)$_SESSION['permission_cache']['role_id'] === $roleId) {
            $runtimeCache = $_SESSION['permission_cache']['permissions'] ?? [];
            $runtimeRoleId = $roleId;
            return $runtimeCache;
        }
    }

    $permissions = [];
    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;

        $sql = "SELECT p.code 
                FROM role_permissions rp 
                JOIN permissions p ON rp.permission_id = p.id 
                WHERE rp.role_id = :role_id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        error_log('Permission cache load error: ' . $e->getMessage(), 3, 'error_log.txt');
    }

    $_SESSION['permission_cache'] = [
        'role_id' => $roleId,
        'permissions' => $permissions,
        'loaded_at' => time(),
    ];

    $runtimeCache = $permissions;
    $runtimeRoleId = $roleId;

    return $permissions;
}
}

/**
 * Check if the current user has a specific permission
 * 
 * @param string $permission_code The permission code to check
 * @return bool True if user has permission, false otherwise
 */
function has_permission($permission_code) {
    if (is_admin()) {
        return true;
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role_id'])) {
        return false;
    }

    $permissions = hrms_get_role_permissions();
    return in_array($permission_code, $permissions, true);
}

/**
 * Check if the current user has any of the given permissions
 * 
 * @param array $permission_codes Array of permission codes to check
 * @return bool True if user has any of the permissions, false otherwise
 */
function has_any_permission(array $permission_codes) {
    if (is_admin()) {
        return true;
    }

    $permissions = hrms_get_role_permissions();
    foreach ($permission_codes as $code) {
        if (in_array($code, $permissions, true)) {
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
    if (is_admin()) {
        return true;
    }

    $permissions = hrms_get_role_permissions();
    foreach ($permission_codes as $code) {
        if (!in_array($code, $permissions, true)) {
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
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role_id'])) {
        return [];
    }

    return hrms_get_role_permissions();
}

/**
 * Convert ISO 3166-1 alpha-2 code to a flag emoji
 * @param string $iso2 Two-letter country code
 * @return string Emoji flag or empty string
 */
function country_flag_from_iso2($iso2) {
    if (empty($iso2) || !is_string($iso2)) return '';
    $iso = strtoupper(trim($iso2));
    if (strlen($iso) !== 2 || !ctype_alpha($iso)) return '';

    $a = ord($iso[0]) - ord('A');
    $b = ord($iso[1]) - ord('A');
    if ($a < 0 || $a > 25 || $b < 0 || $b > 25) return '';

    $first = 0x1F1E6 + $a;
    $second = 0x1F1E6 + $b;

    // Convert codepoints to UTF-8 characters using HTML entities
    $flag = html_entity_decode('&#' . $first . ';', ENT_NOQUOTES, 'UTF-8') . html_entity_decode('&#' . $second . ';', ENT_NOQUOTES, 'UTF-8');
    return $flag;
}

/**
 * Best-effort flag by country name fallback mapping
 * @param string $name Country display name
 * @return string Emoji or empty string
 */
function country_flag_from_name_fallback($name) {
    if (empty($name) || !is_string($name)) return '';
    $n = strtolower(trim($name));
    $map = [
        'nepal' => '🇳🇵',
        'india' => '🇮🇳',
        'united states' => '🇺🇸',
        'united states of america' => '🇺🇸',
        'united kingdom' => '🇬🇧',
        'uk' => '🇬🇧',
        'canada' => '🇨🇦',
        'australia' => '🇦🇺',
        'china' => '🇨🇳',
        'germany' => '🇩🇪',
        'france' => '🇫🇷',
        'pakistan' => '🇵🇰',
        'bangladesh' => '🇧🇩',
        'japan' => '🇯🇵',
        'united arab emirates' => '🇦🇪'
    ];

    // Exact match
    if (isset($map[$n])) {
        return $map[$n];
    }

    // Try contains
    foreach ($map as $key => $emoji) {
        if (strpos($n, $key) !== false) {
            return $emoji;
        }
    }

    return '';
}

/**
 * Resolve an emoji flag for a country row or name.
 * Accepts array item (from DB) or raw string name/iso2.
 * @param mixed $country Array or string
 * @return string Emoji flag or empty
 */
function hrms_resolve_country_flag($country) {
    if (is_array($country)) {
        // Try iso2-ish fields
        $iso = $country['iso2'] ?? $country['iso'] ?? $country['alpha2'] ?? $country['code'] ?? $country['iso_code'] ?? null;
        if ($iso) {
            $flag = country_flag_from_iso2($iso);
            if ($flag) return $flag;
        }

        // Fallback to name
        $name = $country['name'] ?? $country['country'] ?? $country['country_name'] ?? '';
        if ($name) {
            $flag = country_flag_from_name_fallback($name);
            if ($flag) return $flag;
        }
        return '';
    }

    if (is_string($country)) {
        // If looks like 2-letter ISO
        if (strlen($country) === 2 && ctype_alpha($country)) {
            return country_flag_from_iso2($country);
        }
        return country_flag_from_name_fallback($country);
    }

    return '';
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

        // We'll select rows that either cover the date as a range, or have recurring flags
        // Use positional parameters and an arguments array to avoid driver-specific named
        // parameter binding quirks that can result in "Invalid parameter number" errors.
        $sql = "SELECT * FROM holidays WHERE (
                          (start_date IS NOT NULL AND start_date <= ? AND (end_date IS NULL OR end_date >= ?))
                          OR (recurring_type IS NOT NULL AND recurring_type <> 'none')
                          OR (COALESCE(is_recurring,0) = 1)
                      )";

        $params = [$date, $date];

        if (!is_null($branch_id)) {
            $sql .= " AND (branch_id IS NULL OR branch_id = ? )";
            $params[] = (int)$branch_id;
        }

        $sql .= " ORDER BY branch_id IS NOT NULL ASC"; // prefer global if both exist

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $dateTs = strtotime($date);
        $y = (int)date('Y', $dateTs);
        $m = (int)date('n', $dateTs);
        $d = (int)date('j', $dateTs);
        $dow = (int)date('N', $dateTs); // 1=Mon..7=Sun

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // If the holiday is a range and includes the date
            if (!empty($row['start_date'])) {
                $start = $row['start_date'];
                $end = !empty($row['end_date']) ? $row['end_date'] : $row['start_date'];
                if ($start <= $date && $date <= $end) {
                    return $row;
                }
            }

            // Recurring types based on start_date
            $baseDate = $row['start_date'] ?? null;
            if ($baseDate) {
                $base = strtotime($baseDate);
                $baseM = (int)date('n', $base);
                $baseD = (int)date('j', $base);
                $baseQ = (int)ceil($baseM / 3);
                $currQ = (int)ceil($m / 3);

                $rtype = $row['recurring_type'] ?? 'none';
                if ($rtype && $rtype !== 'none') {
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
                    if ((int)date('n', $base) == $m && (int)date('j', $base) == $d) {
                        return $row;
                    }
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
        // Fetch all holidays (we'll filter in PHP to correctly handle ranges and recurring rules)
        $sql = "SELECT * FROM holidays";
        $params = [];
        if (!is_null($branch_id)) {
            $sql .= " WHERE (branch_id IS NULL OR branch_id = ? )";
            $params[] = (int)$branch_id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        $firstOfMonth = sprintf('%04d-%02d-01', $year, $month);
        $lastOfMonth = date('Y-m-d', strtotime(sprintf('%s +1 month -1 day', $firstOfMonth)));

        foreach ($rows as $row) {
            // If it's a date range and overlaps the month, include
            if (!empty($row['start_date'])) {
                $start = $row['start_date'];
                $end = !empty($row['end_date']) ? $row['end_date'] : $row['start_date'];
                if (!empty($start) && !empty($end) && $start <= $lastOfMonth && $end >= $firstOfMonth) {
                    $out[] = $row;
                    continue;
                }
                // single-date non-recurring
                if ($start && date('Y', strtotime($start)) == $year && date('n', strtotime($start)) == $month && ($row['recurring_type'] === 'none' || empty($row['recurring_type']))) {
                    $out[] = $row;
                    continue;
                }
            }

            // Recurring handling based on start_date
            $rtype = $row['recurring_type'] ?? 'none';
            if ($rtype !== 'none') {
                if ($rtype === 'monthly') {
                    $out[] = $row; // monthly recurs every month
                    continue;
                } elseif ($rtype === 'quarterly') {
                    // include if month is in same quarter as base
                    if (!empty($row['start_date'])) {
                        $baseM = (int)date('n', strtotime($row['start_date']));
                        $baseQ = (int)ceil($baseM / 3);
                        $currQ = (int)ceil($month / 3);
                        if ($baseQ === $currQ) { $out[] = $row; continue; }
                    } else {
                        $out[] = $row;
                        continue;
                    }
                } elseif ($rtype === 'annually') {
                    if (!empty($row['start_date']) && (int)date('n', strtotime($row['start_date'])) === (int)$month) {
                        $out[] = $row; continue;
                    }
                }
            }

            // Legacy recurring
            if (!empty($row['is_recurring']) && !empty($row['start_date'])) {
                if ((int)date('n', strtotime($row['start_date'])) === (int)$month) {
                    $out[] = $row; continue;
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
        $sql .= " ORDER BY start_date ASC";

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

        // Fetch all holidays once (optionally filtered by branch). Then expand each
        // holiday into effective dates that fall within [$start_date, $end_date]. This
        // avoids calling the DB repeatedly for each day in the range and correctly
        // supports multi-day holidays, recurring rules and legacy yearly recurrence.
        $sql = "SELECT * FROM holidays";
        $params = [];
        if (!is_null($branch_id)) {
            $sql .= " WHERE (branch_id IS NULL OR branch_id = ? )";
            $params[] = (int)$branch_id;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        $seen = []; // dedupe by id|date
        $rangeStartTs = strtotime($start_date);
        $rangeEndTs = strtotime($end_date);

        foreach ($rows as $row) {
            $baseDate = $row['start_date'] ?? null;

            // 1) If holiday is a date range and overlaps the requested range, expand it
            if (!empty($row['start_date'])) {
                $hStart = $row['start_date'];
                $hEnd = !empty($row['end_date']) ? $row['end_date'] : $row['start_date'];
                if ($hStart <= $end_date && $hEnd >= $start_date) {
                    $from = max($hStart, $start_date);
                    $to = min($hEnd, $end_date);
                    for ($ts = strtotime($from); $ts <= strtotime($to); $ts = strtotime('+1 day', $ts)) {
                        $d = date('Y-m-d', $ts);
                        $key = ($row['id'] ?? spl_object_hash((object)$row)) . '|' . $d;
                        if (isset($seen[$key])) continue;
                        $seen[$key] = true;
                        $r = $row; $r['effective_date'] = $d;
                        $out[] = $r;
                    }
                }
            }

            // 2) Handle recurring rules (weekly/monthly/quarterly/annually)
            $rtype = $row['recurring_type'] ?? 'none';
            if ($rtype && $rtype !== 'none') {
                // iterate days in range and emit matches according to recurring type
                for ($ts = $rangeStartTs; $ts <= $rangeEndTs; $ts = strtotime('+1 day', $ts)) {
                    $d = date('Y-m-d', $ts);
                    $y = (int)date('Y', $ts);
                    $m = (int)date('n', $ts);
                    $day = (int)date('j', $ts);
                    $dow = (int)date('N', $ts);

                    if ($baseDate) {
                        $b = strtotime($baseDate);
                        $baseM = (int)date('n', $b);
                        $baseD = (int)date('j', $b);
                        $baseQ = (int)ceil($baseM / 3);
                        $currQ = (int)ceil($m / 3);
                        $match = false;
                        if ($rtype === 'weekly') {
                            $rowDow = (int)($row['recurring_day_of_week'] ?? date('N', $b));
                            if ($rowDow === $dow) $match = true;
                        } elseif ($rtype === 'monthly') {
                            if ($baseD === $day) $match = true;
                        } elseif ($rtype === 'quarterly') {
                            if ($currQ === $baseQ && $baseD === $day) $match = true;
                        } elseif ($rtype === 'annually') {
                            if ($baseM === $m && $baseD === $day) $match = true;
                        }
                        if ($match) {
                            $key = ($row['id'] ?? spl_object_hash((object)$row)) . '|' . $d;
                            if (!isset($seen[$key])) {
                                $seen[$key] = true;
                                $r = $row; $r['effective_date'] = $d; $out[] = $r;
                            }
                        }
                    }
                }
            }

            // 3) Legacy yearly recurring flag (is_recurring)
            if (!empty($row['is_recurring']) && $baseDate) {
                $b = strtotime($baseDate);
                $baseM = (int)date('n', $b);
                $baseD = (int)date('j', $b);
                for ($ts = $rangeStartTs; $ts <= $rangeEndTs; $ts = strtotime('+1 day', $ts)) {
                    $d = date('Y-m-d', $ts);
                    if ((int)date('n', $ts) === $baseM && (int)date('j', $ts) === $baseD) {
                        $key = ($row['id'] ?? spl_object_hash((object)$row)) . '|' . $d;
                        if (!isset($seen[$key])) {
                            $seen[$key] = true;
                            $r = $row; $r['effective_date'] = $d; $out[] = $r;
                        }
                    }
                }
            }
        }

        // Sort output by effective_date ascending
        usort($out, function($a, $b){ return strcmp($a['effective_date'] ?? '', $b['effective_date'] ?? ''); });
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
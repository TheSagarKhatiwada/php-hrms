<?php
/**
 * Utility functions for the HRMS application
 */

// Include notification helpers
require_once __DIR__ . '/notification_helpers.php';

// Include session_config.php for session functions
require_once __DIR__ . '/session_config.php';

if (!function_exists('hrms_menu_permissions_catalog')) {
function hrms_menu_permissions_catalog() {
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $configPath = dirname(__DIR__) . '/config/menu_permissions.php';
    if (file_exists($configPath)) {
        $catalog = require $configPath;
    } else {
        $catalog = ['sections' => []];
    }

    return $catalog;
}
}

if (!function_exists('hrms_flatten_permission_catalog')) {
function hrms_flatten_permission_catalog() {
    $catalog = hrms_menu_permissions_catalog();
    $flattened = [];

    foreach ($catalog['sections'] as $sectionKey => $section) {
        $children = $section['children'] ?? [];
        foreach ($children as $child) {
            $childKey = $child['key'] ?? null;
            $permissions = $child['permissions'] ?? [];
            foreach ($permissions as $permission) {
                $code = $permission['code'] ?? null;
                if (!$code) {
                    continue;
                }
                $flattened[$code] = [
                    'section' => $sectionKey,
                    'menu' => $childKey,
                    'label' => $permission['label'] ?? $code,
                    'type' => $permission['type'] ?? 'view',
                    'description' => $permission['description'] ?? '',
                    'default_roles' => $permission['default_roles'] ?? [],
                ];
            }
        }
    }

    return $flattened;
}
}

if (!function_exists('hrms_sync_permissions_from_catalog')) {
function hrms_sync_permissions_from_catalog() {
    static $synced = false;
    if ($synced) {
        return;
    }

    $flattened = hrms_flatten_permission_catalog();
    if (empty($flattened)) {
        $synced = true;
        return;
    }

    try {
        require_once __DIR__ . '/db_connection.php';
        require_once __DIR__ . '/date_conversion.php';
        require_once __DIR__ . '/date_preferences.php';
        require_once __DIR__ . '/date_preferences.php';
        global $pdo;
        if (!$pdo) {
            return;
        }

        $columnsInfo = $pdo->query('SHOW COLUMNS FROM permissions')->fetchAll(PDO::FETCH_ASSOC);
        $hasCategory = false;
        foreach ($columnsInfo as $col) {
            if (($col['Field'] ?? '') === 'category') {
                $hasCategory = true;
                break;
            }
        }

        $insertSql = 'INSERT IGNORE INTO permissions (name, description' . ($hasCategory ? ', category' : '') . ') VALUES (:name, :description' . ($hasCategory ? ', :category' : '') . ')';
        $insertPermission = $pdo->prepare($insertSql);
        $selectPermissionId = $pdo->prepare('SELECT id FROM permissions WHERE name = :name LIMIT 1');

        $permissionIdCache = [];

        foreach ($flattened as $code => $meta) {
            if (isset($permissionIdCache[$code])) {
                $permissionId = $permissionIdCache[$code];
            } else {
                $selectPermissionId->execute([':name' => $code]);
                $permissionId = (int)$selectPermissionId->fetchColumn();
            }

            if ($permissionId <= 0) {
                $params = [
                    ':name' => $code,
                    ':description' => $meta['description'] ?? $code,
                ];
                if ($hasCategory) {
                    $params[':category'] = 'menu';
                }
                $insertPermission->execute($params);

                $selectPermissionId->execute([':name' => $code]);
                $permissionId = (int)$selectPermissionId->fetchColumn();
            }

            if ($permissionId <= 0) {
                continue;
            }

            $permissionIdCache[$code] = $permissionId;
        }

        $synced = true;
    } catch (PDOException $e) {
        // Swallow errors silently to avoid breaking UI
        $synced = true;
    }
}
}

if (!function_exists('hrms_seed_role_permissions_from_defaults')) {
function hrms_seed_role_permissions_from_defaults($roleId = null) {
    $flattened = hrms_flatten_permission_catalog();
    if (empty($flattened)) {
        return;
    }

    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;
        if (!$pdo) {
            return;
        }

        $columnsInfo = $pdo->query('SHOW COLUMNS FROM permissions')->fetchAll(PDO::FETCH_ASSOC);
        $hasCategory = false;
        foreach ($columnsInfo as $col) {
            if (($col['Field'] ?? '') === 'category') {
                $hasCategory = true;
                break;
            }
        }

        $selectPermissionId = $pdo->prepare('SELECT id FROM permissions WHERE name = :name LIMIT 1');
        $selectRolePermission = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_id = :role_id AND permission_id = :perm_id LIMIT 1');
        $insertRolePermission = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');

        // Build role map
        $roles = [];
        $roleNames = [];
        try {
            $roleStmt = $pdo->query('SELECT id, name FROM roles');
            foreach ($roleStmt->fetchAll(PDO::FETCH_ASSOC) as $roleRow) {
                $id = (int)($roleRow['id'] ?? 0);
                if ($id <= 0) continue;
                $roles[] = $id;
                $nameKey = strtolower(trim((string)($roleRow['name'] ?? '')));
                if ($nameKey !== '') {
                    $roleNames[$nameKey] = $id;
                }
            }
        } catch (PDOException $e) {
            $roles = [];
            $roleNames = [];
        }

        $targetRoles = [];
        if ($roleId !== null) {
            $targetRoles = [(int)$roleId];
        } else {
            $targetRoles = $roles;
        }

        foreach ($flattened as $code => $meta) {
            $defaultRoles = $meta['default_roles'] ?? [];
            if (empty($defaultRoles)) {
                continue;
            }

            // Resolve permission id
            $selectPermissionId->execute([':name' => $code]);
            $permissionId = (int)$selectPermissionId->fetchColumn();
            if ($permissionId <= 0) {
                continue;
            }

            // Determine which roles should get this permission
            $seedRoles = [];
            foreach ($defaultRoles as $target) {
                if ($target === '*' || strtolower((string)$target) === 'all') {
                    $seedRoles = $roles;
                    break;
                }
                if (is_numeric($target)) {
                    $seedRoles[] = (int)$target;
                    continue;
                }
                $key = strtolower(trim((string)$target));
                if ($key !== '' && isset($roleNames[$key])) {
                    $seedRoles[] = $roleNames[$key];
                }
            }

            $seedRoles = array_values(array_unique(array_filter($seedRoles)));
            if (empty($seedRoles)) {
                continue;
            }

            // If seeding a specific role, filter to it
            if (!empty($targetRoles)) {
                $seedRoles = array_values(array_intersect($seedRoles, $targetRoles));
            }

            if (empty($seedRoles)) {
                continue;
            }

            foreach ($seedRoles as $rid) {
                // Only insert if not already present
                $selectRolePermission->execute([':role_id' => $rid, ':perm_id' => $permissionId]);
                if ($selectRolePermission->fetchColumn()) {
                    continue;
                }
                $insertRolePermission->execute([
                    ':role_id' => $rid,
                    ':permission_id' => $permissionId,
                ]);
            }
        }
    } catch (PDOException $e) {
        // ignore
    }
}
}

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
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $unrestrictedIds = hrms_get_unrestricted_role_ids();

    $candidateIds = [];
    if (isset($_SESSION['user_role_id']) && is_numeric($_SESSION['user_role_id'])) {
        $candidateIds[] = (int)$_SESSION['user_role_id'];
    }
    if (isset($_SESSION['user_role']) && is_numeric($_SESSION['user_role'])) {
        $candidateIds[] = (int)$_SESSION['user_role'];
    }

    foreach ($candidateIds as $candidateId) {
        if (in_array($candidateId, $unrestrictedIds, true)) {
            return true;
        }
    }

    $candidateNames = [];
    if (isset($_SESSION['user_role']) && !is_numeric($_SESSION['user_role'])) {
        $candidateNames[] = strtolower(trim((string)$_SESSION['user_role']));
    }
    if (isset($_SESSION['user_role_name']) && is_string($_SESSION['user_role_name'])) {
        $candidateNames[] = strtolower(trim($_SESSION['user_role_name']));
    }

    $unrestrictedNames = ['super admin', 'super_admin', 'superadmin', 'super administrator', 'administrator', 'admin'];
    foreach ($candidateNames as $name) {
        if ($name !== '' && in_array($name, $unrestrictedNames, true)) {
            return true;
        }
    }

    return false;
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

if (!function_exists('hrms_get_unrestricted_role_ids')) {
function hrms_get_unrestricted_role_ids() {
    static $cachedIds = null;
    if (is_array($cachedIds)) {
        return $cachedIds;
    }

    $ids = [];

    $configPath = dirname(__DIR__) . '/config/access_control.php';
    if (file_exists($configPath)) {
        $config = include $configPath;
        if (is_array($config) && isset($config['unrestricted_role_ids'])) {
            foreach ((array)$config['unrestricted_role_ids'] as $configId) {
                if (is_numeric($configId)) {
                    $ids[] = (int)$configId;
                }
            }
        }
    }

    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;
        if ($pdo instanceof PDO) {
            $stmt = $pdo->prepare(
                "SELECT id FROM roles WHERE LOWER(name) IN ('super admin','super_admin','superadmin','super administrator','administrator','admin')"
            );
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $dbId) {
                if (is_numeric($dbId)) {
                    $ids[] = (int)$dbId;
                }
            }
        }
    } catch (PDOException $e) {
        // Ignore discovery failures; fall back to defaults below
    }

    if (empty($ids)) {
        $ids[] = 1;
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($value) {
        return $value > 0;
    })));

    $cachedIds = $ids;
    return $cachedIds;
}
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

        // permissions table stores short codes in the `name` column (e.g. view_attendance)
        $sql = "SELECT p.name 
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

if (!function_exists('hrms_get_permissions_for_role')) {
function hrms_get_permissions_for_role($roleId) {
    $roleId = (int)$roleId;
    if ($roleId <= 0) {
        return [];
    }

    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;

        $stmt = $pdo->prepare(
            'SELECT p.name FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = :role_id'
        );
        $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        error_log('Failed to load role permissions: ' . $e->getMessage());
    }

    return [];
}
}

if (!function_exists('hrms_get_user_permission_overrides')) {
function hrms_get_user_permission_overrides($userId, $forceRefresh = false) {
    // Returns array ['permission_code' => allowed_int(0|1)]
    static $cache = [];
    $userId = (int)$userId;
    if ($userId <= 0) return [];
    // Prefer the in-process cache
    if (!$forceRefresh && isset($cache[$userId])) return $cache[$userId];

    // If session cache exists and is newer than DB marker, use it (avoids extra query)
    if (!$forceRefresh && isset($_SESSION['user_permission_cache']) && isset($_SESSION['user_permission_cache'][$userId])) {
        $sess = $_SESSION['user_permission_cache'][$userId];
        $sessLoaded = isset($sess['loaded_at']) ? (int)$sess['loaded_at'] : 0;
        try {
            require_once __DIR__ . '/db_connection.php';
            global $pdo;
            $tsStmt = $pdo->prepare('SELECT UNIX_TIMESTAMP(permissions_updated_at) FROM employees WHERE emp_id = ? LIMIT 1');
            $tsStmt->execute([$userId]);
            $dbTs = (int)$tsStmt->fetchColumn();
            // If session loaded_at is later or equal to DB timestamp, trust session cache
            if ($sessLoaded >= $dbTs) {
                $cache[$userId] = $sess['overrides'] ?? [];
                return $cache[$userId];
            }
        } catch (PDOException $e) {
            // ignore and fall back to fresh DB load
        }
    }

    $result = [];
    try {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;
        // Load per-user overrides - permission's short code is in `permissions.name`
        $sql = "SELECT p.name, up.allowed FROM user_permissions up JOIN permissions p ON up.permission_id = p.id WHERE up.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $code = $r['name'] ?? null;
            if ($code !== null) {
                $result[$code] = (int)$r['allowed'];
            }
        }
    } catch (PDOException $e) {
        error_log('Failed to load user permission overrides: ' . $e->getMessage());
    }

    $cache[$userId] = $result;
    // Also store in session cache so future requests can avoid an extra DB timestamp check
    if (!isset($_SESSION['user_permission_cache'])) $_SESSION['user_permission_cache'] = [];
    $_SESSION['user_permission_cache'][$userId] = [
        'overrides' => $result,
        'loaded_at' => time(),
    ];
    return $result;
}
}

/**
 * Check if the current user has a specific permission
 * 
 * @param string $permission_code The permission code to check
 * @return bool True if user has permission, false otherwise
 */
function has_permission($permission_code) {
    // Admins always have permission
    if (is_admin()) {
        return true;
    }

    // If not logged in, no permissions
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role_id'])) {
        return false;
    }
    $permissions = get_user_permissions();
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
    if (empty($permission_codes)) {
        return true;
    }

    $permissions = get_user_permissions();
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

    $permissions = get_user_permissions();
    foreach ($permission_codes as $code) {
        if (!in_array($code, $permissions, true)) {
            return false;
        }
    }

    return true;
}

if (!function_exists('hrms_normalize_branch_value')) {
function hrms_normalize_branch_value($value) {
    if ($value === null) {
        return null;
    }
    $normalized = trim((string)$value);
    return $normalized === '' ? null : $normalized;
}
}

if (!function_exists('hrms_resolve_branch_assignment')) {
function hrms_resolve_branch_assignment($legacyValue, $branchIdValue) {
    $legacy = hrms_normalize_branch_value($legacyValue);
    $numeric = null;

    if ($legacy !== null && ctype_digit($legacy)) {
        $numeric = (int)$legacy;
    }

    if ($legacy === null) {
        $candidate = hrms_normalize_branch_value($branchIdValue);
        if ($candidate !== null && ctype_digit($candidate)) {
            $numeric = (int)$candidate;
            $legacy = (string)$numeric;
        }
    }

    return [
        'legacy' => $legacy,
        'numeric' => $numeric,
    ];
}
}

if (!function_exists('hrms_build_branch_filter_sql')) {
function hrms_build_branch_filter_sql(array $branchContext, array &$params, $legacyColumn = 'branch', $numericColumn = 'branch_id') {
    $legacyValue = $branchContext['legacy'] ?? null;
    $numericValue = array_key_exists('numeric', $branchContext) ? $branchContext['numeric'] : null;

    if ($legacyValue !== null) {
        $clauses = [];
        $legacyParam = ':branchLegacyFilter';
        $clauses[] = "$legacyColumn = $legacyParam";
        $params[$legacyParam] = $legacyValue;

        if ($numericValue !== null) {
            $numericParam = ':branchLegacyNumericFilter';
            $clauses[] = "(($legacyColumn IS NULL OR $legacyColumn = '') AND $numericColumn = $numericParam)";
            $params[$numericParam] = $numericValue;
        }

        return '(' . implode(' OR ', $clauses) . ')';
    }

    if ($numericValue !== null) {
        $numericParam = ':branchNumericOnlyFilter';
        $params[$numericParam] = $numericValue;
        return "(($legacyColumn IS NULL OR $legacyColumn = '') AND $numericColumn = $numericParam)";
    }

    return '';
}
}

if (!function_exists('hrms_employee_matches_branch')) {
function hrms_employee_matches_branch(array $viewerContext, array $employeeContext) {
    $viewerLegacy = $viewerContext['legacy'] ?? null;
    $viewerNumeric = array_key_exists('numeric', $viewerContext) ? $viewerContext['numeric'] : null;
    $employeeLegacy = $employeeContext['legacy'] ?? null;
    $employeeNumeric = array_key_exists('numeric', $employeeContext) ? $employeeContext['numeric'] : null;

    if ($viewerLegacy !== null && $employeeLegacy !== null) {
        return $viewerLegacy === $employeeLegacy;
    }

    if ($viewerLegacy !== null && $employeeLegacy === null && $employeeNumeric !== null && ctype_digit($viewerLegacy)) {
        return (int)$viewerLegacy === $employeeNumeric;
    }

    if ($viewerLegacy === null && $viewerNumeric !== null && $employeeLegacy !== null && ctype_digit($employeeLegacy)) {
        return $viewerNumeric === (int)$employeeLegacy;
    }

    if ($viewerNumeric !== null && $employeeNumeric !== null) {
        return $viewerNumeric === $employeeNumeric;
    }

    return false;
}
}

if (!function_exists('hrms_get_user_branch_context')) {
function hrms_get_user_branch_context($pdo, $userId) {
    $context = ['legacy' => null, 'numeric' => null, 'name' => null];
    if (!$pdo || !$userId) {
        return $context;
    }

    try {
        $stmt = $pdo->prepare('SELECT e.branch, e.branch_id, b.name FROM employees e LEFT JOIN branches b ON e.branch = b.id WHERE e.emp_id = :emp_id LIMIT 1');
        $stmt->execute([':emp_id' => $userId]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resolved = hrms_resolve_branch_assignment($row['branch'] ?? null, $row['branch_id'] ?? null);
            $resolved['name'] = $row['name'] ?? null;
            return $resolved;
        }
    } catch (PDOException $e) {
        // Fall back to empty context when lookup fails
    }

    return $context;
}
}

/**
 * Get all permissions for the current user
 * 
 * @return array Array of permission codes the user has
 */
function get_user_permissions($forceRefresh = false) {
    // Effective permissions = role permissions + user grants - user revokes
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role_id'])) {
        return [];
    }

    $userId = (int)$_SESSION['user_id'];

    static $cachedUserId = null;
    static $cachedPermissions = null;

    if (!$forceRefresh && $cachedUserId === $userId && $cachedPermissions !== null) {
        return $cachedPermissions;
    }

    // Start with role permissions
    $effective = hrms_get_role_permissions($forceRefresh);
    // Pull in user overrides
    $overrides = hrms_get_user_permission_overrides($userId, $forceRefresh);
    foreach ($overrides as $code => $allowed) {
        if ((int)$allowed === 1) {
            // grant
            if (!in_array($code, $effective, true)) {
                $effective[] = $code;
            }
        } else {
            // revoke if present
            $idx = array_search($code, $effective, true);
            if ($idx !== false) {
                unset($effective[$idx]);
            }
        }
    }

    // Re-index array
    $effective = array_values(array_unique($effective));
    $cachedPermissions = $effective;
    $cachedUserId = $userId;

    return $effective;
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

    // Prefer mb_chr for codepoint -> UTF-8 conversion when available
    if (function_exists('mb_chr')) {
        return mb_chr($first, 'UTF-8') . mb_chr($second, 'UTF-8');
    }

    // Fallback to numeric entity decoding
    return html_entity_decode('&#' . $first . ';' . '&#' . $second . ';', ENT_NOQUOTES, 'UTF-8');
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
// country flag resolution helper removed — flags are no longer rendered via helpers here

// flash messages helper: provide set/get helpers so calling code (redirect_with_message etc.) works
if (!function_exists('set_flash_message')) {
    /**
     * Add a flash message to the user's session
     * @param string $type message type (success|error|warning|info)
     * @param string $message message text
     * @return void
     */
    function set_flash_message($type, $message) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }
        $_SESSION['flash_messages'][] = [
            'type' => (string)$type,
            'message' => (string)$message,
            'ts' => time(),
        ];
    }
}

if (!function_exists('get_flash_messages')) {
    /**
     * Fetch and clear flash messages from session
     * @return array Array of messages
     */
    function get_flash_messages() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $result = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $result;
    }
}

/**
 * Resolve a flag-icon CSS class for a country record or name.
 * Returns the class suffix appropriate for the flag-icon plugin (e.g. 'flag-icon-np')
 * or an empty string if not resolvable.
 *
 * @param mixed $country Array row or string
 * @return string CSS class name (empty when not available)
 */
// flag class helper removed — flags are no longer rendered via CSS classes here

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

        $todayAd = date('Y-m-d');
        $today = new DateTime($todayAd);
        $items = [];
        $useBsMode = function_exists('hrms_should_use_bs_dates') && hrms_should_use_bs_dates();
        $todayBs = $useBsMode ? get_bs_for_ad_date($todayAd) : null;

        $resolveBsEventDate = function (string $adSourceDate) use ($today, $todayBs) {
            if (!$todayBs) {
                return null;
            }
            $bsInfo = get_bs_for_ad_date($adSourceDate);
            if (!$bsInfo) {
                return null;
            }

            $targetBsYear = (int)$todayBs['bs_year'];
            $targetBsDate = sprintf('%04d-%02d-%02d', $targetBsYear, (int)$bsInfo['bs_month'], (int)$bsInfo['bs_day']);
            $adInfo = get_ad_for_bs_date($targetBsDate);
            if (!$adInfo || empty($adInfo['ad_date'])) {
                return null;
            }

            $candidate = new DateTime($adInfo['ad_date']);
            if ($candidate < $today) {
                $targetBsYear++;
                $targetBsDate = sprintf('%04d-%02d-%02d', $targetBsYear, (int)$bsInfo['bs_month'], (int)$bsInfo['bs_day']);
                $adInfo = get_ad_for_bs_date($targetBsDate);
                if (!$adInfo || empty($adInfo['ad_date'])) {
                    return null;
                }
                $candidate = new DateTime($adInfo['ad_date']);
            }

            return $candidate->format('Y-m-d');
        };

        foreach ($rows as $e) {
            // Birthday
            if (!empty($e['date_of_birth'])) {
                $dob = DateTime::createFromFormat('Y-m-d', $e['date_of_birth']);
                if ($dob) {
                    $eventDateAd = null;
                    if ($useBsMode) {
                        $eventDateAd = $resolveBsEventDate($dob->format('Y-m-d'));
                    }
                    if (!$eventDateAd) {
                        $event = (clone $dob)->setDate((int)$today->format('Y'), (int)$dob->format('m'), (int)$dob->format('d'));
                        if (!checkdate((int)$dob->format('m'), (int)$dob->format('d'), (int)$today->format('Y'))) {
                            $event = (clone $dob)->setDate((int)$today->format('Y'), 3, 1);
                        }
                        if ($event < $today) { $event->modify('+1 year'); }
                        $eventDateAd = $event->format('Y-m-d');
                    }

                    $eventDt = new DateTime($eventDateAd);
                    $diffDays = (int)$today->diff($eventDt)->days;
                    if ($diffDays <= $days) {
                        $items[] = [
                            'emp_id' => $e['emp_id'],
                            'first_name' => $e['first_name'],
                            'middle_name' => $e['middle_name'] ?? '',
                            'last_name' => $e['last_name'],
                            'designation_name' => $e['designation_name'] ?? null,
                            'user_image' => $e['user_image'] ?? null,
                            'event_date' => $eventDateAd,
                            'event_day' => (int)$eventDt->format('d'),
                            'event_month' => (int)$eventDt->format('m'),
                            'celebration_type' => 'birthday',
                            'days_until' => $diffDays,
                            'display_date' => hrms_format_preferred_date($eventDateAd, 'F j')
                        ];
                    }
                }
            }

            // Anniversary
            if (!empty($e['join_date'])) {
                $jd = DateTime::createFromFormat('Y-m-d', $e['join_date']);
                if ($jd) {
                    $curYear = (int)$today->format('Y');
                    if ((int)$jd->format('Y') < $curYear && (int)$jd->format('Y') > 1990) {
                        $eventDateAd = null;
                        if ($useBsMode) {
                            $eventDateAd = $resolveBsEventDate($jd->format('Y-m-d'));
                        }
                        if (!$eventDateAd) {
                            $event = (clone $jd)->setDate($curYear, (int)$jd->format('m'), (int)$jd->format('d'));
                            if (!checkdate((int)$jd->format('m'), (int)$jd->format('d'), $curYear)) {
                                $event = (clone $jd)->setDate($curYear, 3, 1);
                            }
                            if ($event < $today) { $event->modify('+1 year'); $curYear++; }
                            $eventDateAd = $event->format('Y-m-d');
                        }

                        $eventDt = new DateTime($eventDateAd);
                        $diffDays = (int)$today->diff($eventDt)->days;
                        if ($diffDays <= $days) {
                            $yearsCompleted = (int)$eventDt->format('Y') - (int)$jd->format('Y');
                            $items[] = [
                                'emp_id' => $e['emp_id'],
                                'first_name' => $e['first_name'],
                                'middle_name' => $e['middle_name'] ?? '',
                                'last_name' => $e['last_name'],
                                'designation_name' => $e['designation_name'] ?? null,
                                'user_image' => $e['user_image'] ?? null,
                                'event_date' => $eventDateAd,
                                'event_day' => (int)$eventDt->format('d'),
                                'event_month' => (int)$eventDt->format('m'),
                                'celebration_type' => 'anniversary',
                                'days_until' => $diffDays,
                                'years_completed' => $yearsCompleted,
                                'display_date' => hrms_format_preferred_date($eventDateAd, 'F j')
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

if (!function_exists('get_employee_celebrations_by_date_range')) {
    /**
     * Return celebrations keyed by date for the provided AD range.
     */
    function get_employee_celebrations_by_date_range(string $startDate, string $endDate, ?int $branch_id = null): array
    {
        try {
            $rangeStart = DateTime::createFromFormat('Y-m-d', $startDate);
            $rangeEnd = DateTime::createFromFormat('Y-m-d', $endDate);
            if (!$rangeStart || !$rangeEnd) {
                return [];
            }
            if ($rangeStart > $rangeEnd) {
                [$rangeStart, $rangeEnd] = [$rangeEnd, $rangeStart];
            }
            $startStr = $rangeStart->format('Y-m-d');
            $endStr = $rangeEnd->format('Y-m-d');
            $startYear = (int)$rangeStart->format('Y');
            $endYear = (int)$rangeEnd->format('Y');

            require_once __DIR__ . '/db_connection.php';
            global $pdo;

            $params = [];
            $where = "e.exit_date IS NULL";
            if (!is_null($branch_id)) {
                $where .= " AND (e.branch = :branch_id)";
                $params[':branch_id'] = (int)$branch_id;
            }

            $sql = "SELECT e.emp_id, e.first_name, e.middle_name, e.last_name, e.date_of_birth, e.join_date,
                           d.title AS designation_name
                    FROM employees e
                    LEFT JOIN designations d ON e.designation = d.id
                    WHERE $where";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $resolveEventDate = static function (int $year, int $month, int $day): ?string {
                if (checkdate($month, $day, $year)) {
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
                if ($month === 2 && $day === 29) {
                    return sprintf('%04d-03-01', $year);
                }
                return null;
            };

            $results = [];
            foreach ($rows as $employee) {
                $displayName = trim(implode(' ', array_filter([
                    $employee['first_name'] ?? null,
                    $employee['middle_name'] ?? null,
                    $employee['last_name'] ?? null
                ])));
                if ($displayName === '') {
                    $displayName = $employee['first_name'] ?? 'Team member';
                }

                if (!empty($employee['date_of_birth'])) {
                    $dob = DateTime::createFromFormat('Y-m-d', $employee['date_of_birth']);
                    if ($dob) {
                        $birthMonth = (int)$dob->format('m');
                        $birthDay = (int)$dob->format('d');
                        for ($year = $startYear; $year <= $endYear; $year++) {
                            $eventDate = $resolveEventDate($year, $birthMonth, $birthDay);
                            if (!$eventDate || $eventDate < $startStr || $eventDate > $endStr) {
                                continue;
                            }
                            $results[] = [
                                'emp_id' => $employee['emp_id'],
                                'display_name' => $displayName,
                                'celebration_type' => 'birthday',
                                'event_date' => $eventDate,
                                'designation_name' => $employee['designation_name'] ?? null,
                                'years_completed' => null,
                            ];
                        }
                    }
                }

                if (!empty($employee['join_date'])) {
                    $joinDate = DateTime::createFromFormat('Y-m-d', $employee['join_date']);
                    if ($joinDate) {
                        $joinYear = (int)$joinDate->format('Y');
                        $joinMonth = (int)$joinDate->format('m');
                        $joinDay = (int)$joinDate->format('d');
                        for ($year = max($startYear, $joinYear + 1); $year <= $endYear; $year++) {
                            if ($year <= $joinYear) {
                                continue;
                            }
                            $eventDate = $resolveEventDate($year, $joinMonth, $joinDay);
                            if (!$eventDate || $eventDate < $startStr || $eventDate > $endStr) {
                                continue;
                            }
                            $results[] = [
                                'emp_id' => $employee['emp_id'],
                                'display_name' => $displayName,
                                'celebration_type' => 'anniversary',
                                'event_date' => $eventDate,
                                'designation_name' => $employee['designation_name'] ?? null,
                                'years_completed' => $year - $joinYear,
                            ];
                        }
                    }
                }
            }

            usort($results, static function ($a, $b) {
                $dateCompare = strcmp($a['event_date'] ?? '', $b['event_date'] ?? '');
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
                return strcmp($a['display_name'] ?? '', $b['display_name'] ?? '');
            });

            return $results;
        } catch (Throwable $e) {
            error_log('Get celebrations by range error: ' . $e->getMessage(), 3, 'error_log.txt');
            return [];
        }
    }
}
?>
<?php
/**
 * Smoke-test for permissions helper functions
 * - hrms_get_role_permissions
 * - hrms_get_user_permission_overrides
 * - has_permission
 * - get_user_permissions
 *
 * This script makes minimal changes to the DB (inserting a temporary user_permissions row) and cleans up after itself.
 */

require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/utilities.php';

// Ensure errors are visible
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Starting permissions smoke test...\n";

$stmt = $pdo->prepare("SELECT emp_id, role_id FROM employees LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "No user records found, aborting smoke test.\n";
    exit(1);
}

$userId = (int)$row['emp_id'];
$originalRoleId = isset($row['role_id']) ? (int)$row['role_id'] : null;

echo "Using user id={$userId} (role_id={$originalRoleId}) for tests\n";

// Helper for output
function ok($msg) {
    echo "[OK] " . $msg . "\n";
}
function fail($msg) {
    echo "[FAIL] " . $msg . "\n";
}

// 1) Role permissions (simulate role) - grab user's role if present
$_SESSION = [];
$_SESSION['user_id'] = $userId;
$_SESSION['user_role_id'] = $originalRoleId ?: 2; // if null use 2

$rolePerms = hrms_get_role_permissions(true);
if (is_array($rolePerms)) {
    ok('hrms_get_role_permissions returned an array with ' . count($rolePerms) . ' entries');
} else {
    fail('hrms_get_role_permissions did not return array');
}

// Choose a permission code that exists (pick first role permission if any)
$somePermission = $rolePerms[0] ?? 'view_attendance';

echo "Checking role permission membership for '{$somePermission}'\n";
if (in_array($somePermission, $rolePerms, true)) ok("User has role permission '{$somePermission}' (expected if role grants it)");
else echo "User does NOT have role permission '{$somePermission}' (this may be expected depending on role)\n";

// 2) Per-user override insert (grant and revoke)
try {
    // Find permission id for manage_user_permissions
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE name = ? LIMIT 1');
    $stmt->execute(['manage_user_permissions']);
    $permRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$permRow) {
        echo "manage_user_permissions permission not found, aborting per-user override tests.\n";
    } else {
        $permId = (int)$permRow['id'];
        echo "Testing per-user override for permission id={$permId}\n";

        // Ensure there's no existing override
        $del = $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?');
        $del->execute([$userId, $permId]);

        // Insert override to deny (allowed = 0)
        $ins = $pdo->prepare('INSERT INTO user_permissions (user_id, permission_id, allowed) VALUES (?, ?, ?)');
        $ins->execute([$userId, $permId, 0]);

        $overrides = hrms_get_user_permission_overrides($userId);
        if (array_key_exists('manage_user_permissions', $overrides) && $overrides['manage_user_permissions'] === 0) {
            ok('Inserted revoke override and hrms_get_user_permission_overrides returned expected value');
        } else {
            fail('Override insert did not appear in hrms_get_user_permission_overrides');
        }

        // Test has_permission: should be false for this user for manage_user_permissions (unless admin)
        // Ensure user is not admin for test
        $_SESSION['user_role_id'] = 2; // non-admin role
        $_SESSION['user_id'] = $userId;

        if (!has_permission('manage_user_permissions')) {
            ok('has_permission correctly denies manage_user_permissions after revoke override');
        } else {
            fail('has_permission unexpectedly allowed manage_user_permissions after revoke override');
        }

        // Now update override to allow
        $upd = $pdo->prepare('UPDATE user_permissions SET allowed = ? WHERE user_id = ? AND permission_id = ?');
        $upd->execute([1, $userId, $permId]);

        // Clear any caches used by helper (session caches)
        if (isset($_SESSION['permission_cache'])) unset($_SESSION['permission_cache']);

        // Force refresh of the overrides cache in case the helper cached the previous value
        $overrides2 = hrms_get_user_permission_overrides($userId, true);
        if (isset($overrides2['manage_user_permissions']) && $overrides2['manage_user_permissions'] === 1) {
            ok('Update to grant override reflected in hrms_get_user_permission_overrides');
        } else {
            fail('Grant override did not reflect');
        }

        if (has_permission('manage_user_permissions')) {
            ok('has_permission allowed manage_user_permissions after grant override');
        } else {
            fail('has_permission did not allow manage_user_permissions after grant override');
        }

        // Cleanup - remove override
        $del->execute([$userId, $permId]);
        echo "Cleaned up override rows.\n";
    }
} catch (PDOException $e) {
    fail('DB error during per-user override tests: ' . $e->getMessage());
}

// 3) Admin always allowed: Ensure is_admin gives superpowers
// Simulate non-admin user but set is_admin session variables
$_SESSION['user_id'] = $userId;
$_SESSION['user_role'] = '1';
$_SESSION['user_role_id'] = 99; // doesn't matter

// Force admin check via is_admin() which checks user_role == '1'
if (is_admin()) {
    ok('is_admin() returns true when session role string is "1"');
    // Regardless of overrides or role, has_permission should return true for admins
    if (has_permission('some_random_permission_that_does_not_exist')) {
        ok('has_permission returns true for an admin (always allowed)');
    } else {
        fail('has_permission returned false for admin');
    }
} else {
    fail('is_admin() did not return true when expected');
}

echo "Permissions smoke test complete.\n";

exit(0);

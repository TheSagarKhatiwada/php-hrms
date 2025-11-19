<?php
// Demo data seeder (development only)
// Usage: open /tools/seed_demo.php in a browser while logged in.
// It will create/ensure leave types and grant balances for the current user for this year.

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';

header('Content-Type: application/json');

try {
    if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'development') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Seeder allowed only in development.']);
        exit();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'You must be logged in to run the seeder.']);
        exit();
    }

    $userId = $_SESSION['user_id'];
    $year = (int)date('Y');

    // Resolve employee by emp_id (our system maps session user_id -> employees.emp_id)
    $stmt = $pdo->prepare('SELECT emp_id FROM employees WHERE emp_id = ?');
    $stmt->execute([$userId]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        // Attempt to create a minimal employee row if table allows it (fallback defaults)
        // Note: Adjust columns as needed for your schema
        $pdo->prepare("INSERT INTO employees (emp_id, first_name, last_name, join_date) VALUES (?, 'Demo', 'User', CURDATE())")
            ->execute([$userId]);
    }

    // Seed/ensure leave types
    $types = [
        ['code' => 'annual',   'name' => 'Annual Leave',   'days' => 24, 'color' => '#0d6efd'],
        ['code' => 'sick',     'name' => 'Sick Leave',     'days' => 12, 'color' => '#20c997'],
        ['code' => 'emergency','name' => 'Emergency Leave','days' => 7,  'color' => '#dc3545'],
        ['code' => 'casual',   'name' => 'Casual Leave',   'days' => 12, 'color' => '#6f42c1'],
    ];

    $ensuredTypeIds = [];

    foreach ($types as $t) {
        // Try by code first
        $stmt = $pdo->prepare('SELECT id FROM leave_types WHERE code = ?');
        $stmt->execute([$t['code']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Update to ensure active and days_allowed present
            $pdo->prepare('UPDATE leave_types SET name = ?, color = ?, is_active = 1, days_allowed = COALESCE(days_allowed, ?) WHERE id = ?')
                ->execute([$t['name'], $t['color'], $t['days'], $row['id']]);
            $ensuredTypeIds[$t['code']] = (int)$row['id'];
        } else {
            // Insert
            $pdo->prepare('INSERT INTO leave_types (name, code, color, is_active, days_allowed) VALUES (?, ?, ?, 1, ?)')
                ->execute([$t['name'], $t['code'], $t['color'], $t['days']]);
            $ensuredTypeIds[$t['code']] = (int)$pdo->lastInsertId();
        }
    }

    // Ensure leave balances for current user and current year
    $createdBalances = 0;
    foreach ($ensuredTypeIds as $code => $typeId) {
        // Get allowed days
        $stmt = $pdo->prepare('SELECT days_allowed FROM leave_types WHERE id = ?');
        $stmt->execute([$typeId]);
        $daysAllowed = (float)($stmt->fetchColumn() ?: 0);
        if ($daysAllowed <= 0) { $daysAllowed = 10; }

        // Check existing
        $stmt = $pdo->prepare('SELECT id FROM leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?');
        $stmt->execute([$userId, $typeId, $year]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            // Reset to full allocation for quick testing
            $pdo->prepare('UPDATE leave_balances SET allocated_days = ?, used_days = 0, pending_days = 0, remaining_days = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$daysAllowed, $daysAllowed, $exists['id']]);
        } else {
            $pdo->prepare('INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, pending_days, remaining_days, created_at, updated_at) VALUES (?, ?, ?, ?, 0, 0, ?, NOW(), NOW())')
                ->execute([$userId, $typeId, $year, $daysAllowed, $daysAllowed]);
            $createdBalances++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Demo data seeded. You may need to refresh the page to see updated balances in the modal.',
        'leave_types' => $ensuredTypeIds,
        'balances_created' => $createdBalances,
        'employee_id' => $userId,
        'year' => $year,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

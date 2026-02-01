<?php
$page = 'user-access';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/utilities.php';
require_once __DIR__ . '/includes/csrf_protection.php';

if (!is_logged_in() || (!is_admin() && !has_permission('manage_permission_overrides') && !has_permission('manage_user_permissions'))) {
    $_SESSION['error'] = "You don't have permission to manage user access.";
    header('Location: dashboard.php');
    exit();
}

$catalog = hrms_menu_permissions_catalog();
hrms_sync_permissions_from_catalog();
$flatCatalog = hrms_flatten_permission_catalog();

$csrfToken = generate_csrf_token();
$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$employees = [];
$targetUser = null;
$rolePermissions = [];
$userOverrides = [];
$permissionIdMap = [];
$missingCodes = [];

try {
    $stmt = $pdo->query('SELECT emp_id, first_name, last_name, office_email, role_id FROM employees WHERE login_access = 1 ORDER BY first_name ASC');
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'Failed to load employees: ' . $e->getMessage();
}

try {
    $permStmt = $pdo->query('SELECT id, name FROM permissions');
    foreach ($permStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $permissionIdMap[$row['name']] = (int)$row['id'];
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Failed to load permissions: ' . $e->getMessage();
}

if ($selectedUserId > 0) {
    try {
        $userStmt = $pdo->prepare('SELECT e.emp_id, e.first_name, e.last_name, e.role_id, r.name as role_name FROM employees e LEFT JOIN roles r ON e.role_id = r.id WHERE e.emp_id = ? LIMIT 1');
        $userStmt->execute([$selectedUserId]);
        $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($targetUser) {
            $rolePermissions = hrms_get_permissions_for_role($targetUser['role_id']);
            $userOverrides = hrms_get_user_permission_overrides($selectedUserId, true);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to load user information: ' . $e->getMessage();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_access']) && $targetUser) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Invalid token. Please try again.';
            header('Location: user-access.php?user_id=' . $selectedUserId);
            exit();
        }

        $overrides = $_POST['perm_override'] ?? [];
        if (!is_array($overrides)) {
            $overrides = [];
        }

        try {
            $pdo->beginTransaction();
            foreach ($overrides as $code => $value) {
                if (!isset($permissionIdMap[$code])) {
                    $missingCodes[] = $code;
                    continue;
                }
                $permissionId = $permissionIdMap[$code];
                if ($value === 'inherit') {
                    $del = $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?');
                    $del->execute([$selectedUserId, $permissionId]);
                    continue;
                }

                $allowed = ($value === 'allow') ? 1 : 0;
                $up = $pdo->prepare('INSERT INTO user_permissions (user_id, permission_id, allowed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)');
                $up->execute([$selectedUserId, $permissionId, $allowed]);
            }

            $touch = $pdo->prepare('UPDATE employees SET permissions_updated_at = NOW() WHERE emp_id = ?');
            $touch->execute([$selectedUserId]);

            $pdo->commit();
            $_SESSION['success'] = 'Access settings saved.';
            header('Location: user-access.php?user_id=' . $selectedUserId);
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error'] = 'Failed to save access: ' . $e->getMessage();
        }
    }
}
?>
<div class="container-fluid p-4 user-access-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Permission Overrides</h1>
            <p class="text-muted mb-0">Assign extra permissions or remove specific abilities beyond the role bundle.</p>
        </div>
        <div>
            <a href="permissions.php" class="btn btn-outline-secondary">Back to Roles &amp; Permissions</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="userSelect" class="form-label">Select Employee</label>
                    <select class="form-select" id="userSelect" name="user_id" required>
                        <option value="">Choose an employee...</option>
                        <?php foreach ($employees as $employee): ?>
                            <?php $selected = ($selectedUserId === (int)$employee['emp_id']) ? 'selected' : ''; ?>
                            <option value="<?php echo (int)$employee['emp_id']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                <?php if (!empty($employee['office_email'])): ?>
                                    (<?php echo htmlspecialchars($employee['office_email']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">Load Access</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($targetUser): ?>
    <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">Editing: <?php echo htmlspecialchars($targetUser['first_name'] . ' ' . $targetUser['last_name']); ?></h5>
                <p class="mb-0 text-muted">Role: <?php echo htmlspecialchars($targetUser['role_name'] ?? 'Unassigned'); ?></p>
            </div>
            <div>
                <span class="badge bg-primary">Role Permissions: <?php echo count($rolePermissions); ?></span>
            </div>
        </div>
    </div>

    <form method="post" class="card">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="save_access" value="1">
            <div class="accordion" id="accessAccordion">
                <?php foreach ($catalog['sections'] as $sectionKey => $section): ?>
                    <div class="accordion-item mb-3">
                        <h2 class="accordion-header" id="heading-<?php echo htmlspecialchars($sectionKey); ?>">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo htmlspecialchars($sectionKey); ?>">
                                <i class="<?php echo htmlspecialchars($section['icon']); ?> me-2"></i>
                                <?php echo htmlspecialchars($section['label']); ?>
                            </button>
                        </h2>
                        <div id="collapse-<?php echo htmlspecialchars($sectionKey); ?>" class="accordion-collapse collapse" data-bs-parent="#accessAccordion">
                            <div class="accordion-body">
                                <?php foreach ($section['children'] ?? [] as $child): ?>
                                    <?php $seenCodes = []; ?>
                                    <div class="mb-4">
                                        <h6 class="fw-semibold mb-2"><?php echo htmlspecialchars($child['label']); ?></h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Permission</th>
                                                        <th>Description</th>
                                                        <th>Role default</th>
                                                        <th class="text-center">Allow</th>
                                                        <th class="text-center">Deny</th>
                                                        <th class="text-center">Inherit</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($child['permissions'] ?? [] as $permission): ?>
                                                        <?php
                                                            $code = $permission['code'];
                                                            if (in_array($code, $seenCodes, true)) { continue; }
                                                            $seenCodes[] = $code;
                                                            $roleHas = in_array($code, $rolePermissions, true);
                                                            $overrideValue = $userOverrides[$code] ?? null;
                                                            $allowChecked = ($overrideValue === 1);
                                                            $denyChecked = ($overrideValue === 0);
                                                            $inheritChecked = (!$allowChecked && !$denyChecked);
                                                            $missing = !isset($permissionIdMap[$code]);
                                                        ?>
                                                        <tr>
                                                            <td class="fw-medium">
                                                                <?php echo htmlspecialchars($permission['label'] ?? $code); ?>
                                                                <div class="text-muted small">Code: <?php echo htmlspecialchars($code); ?></div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($permission['description'] ?? ''); ?></td>
                                                            <td>
                                                                <?php if ($roleHas): ?>
                                                                    <span class="badge bg-success">Granted</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Not granted</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <input type="radio" class="form-check-input" name="perm_override[<?php echo htmlspecialchars($code); ?>]" value="allow" <?php echo $allowChecked ? 'checked' : ''; ?> <?php echo $missing ? 'disabled' : ''; ?>>
                                                            </td>
                                                            <td class="text-center">
                                                                <input type="radio" class="form-check-input" name="perm_override[<?php echo htmlspecialchars($code); ?>]" value="deny" <?php echo $denyChecked ? 'checked' : ''; ?> <?php echo $missing ? 'disabled' : ''; ?>>
                                                            </td>
                                                            <td class="text-center">
                                                                <input type="radio" class="form-check-input" name="perm_override[<?php echo htmlspecialchars($code); ?>]" value="inherit" <?php echo $inheritChecked ? 'checked' : ''; ?> <?php echo $missing ? 'disabled' : ''; ?>>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <div>
                <?php if (!empty($missingCodes)): ?>
                    <span class="text-danger small">Missing permission codes: <?php echo htmlspecialchars(implode(', ', $missingCodes)); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <button type="submit" class="btn btn-primary px-4">Save Access Overrides</button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

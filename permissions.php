<?php
$page = 'permissions';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/utilities.php';
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/csrf_protection.php';

if (!is_logged_in() || (!is_admin() && !has_permission('manage_user_permissions'))) {
    $_SESSION['error'] = "You don't have permission to manage role permissions.";
    header('Location: dashboard.php');
    exit();
}

$catalog = hrms_menu_permissions_catalog();
hrms_sync_permissions_from_catalog();
$flatCatalog = hrms_flatten_permission_catalog();

$csrfToken = generate_csrf_token();
$selectedRoleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;

$roles = [];
try {
    $stmt = $pdo->query('SELECT id, name, description FROM roles ORDER BY name ASC');
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'Failed to load roles: ' . $e->getMessage();
}

$permissionIdMap = [];
try {
    $permStmt = $pdo->query('SELECT id, name FROM permissions');
    foreach ($permStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $permissionIdMap[$row['name']] = (int)$row['id'];
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Failed to load permissions table: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postRoleId = (int)($_POST['role_id'] ?? ($_POST['redirect_role_id'] ?? 0));
    if ($postRoleId > 0) {
        $selectedRoleId = $postRoleId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_role'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid token. Please try again.';
        header('Location: permissions.php');
        exit();
    }

    $roleName = trim($_POST['new_role_name'] ?? '');
    $roleDescription = trim($_POST['new_role_description'] ?? '');

    if ($roleName === '') {
        $_SESSION['error'] = 'Role name is required.';
        header('Location: permissions.php' . ($selectedRoleId ? '?role_id=' . $selectedRoleId : ''));
        exit();
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO roles (name, description) VALUES (:name, :description)');
        $stmt->execute([
            ':name' => $roleName,
            ':description' => $roleDescription,
        ]);
        $newRoleId = (int)$pdo->lastInsertId();
        log_activity($pdo, 'role_created', 'Created new role via Roles & Permissions page: ' . $roleName);
        $_SESSION['success'] = 'Role created successfully. Configure its permissions below.';
        header('Location: permissions.php?role_id=' . $newRoleId);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to create role: ' . $e->getMessage();
        header('Location: permissions.php' . ($selectedRoleId ? '?role_id=' . $selectedRoleId : ''));
        exit();
    }
}

// Handle permission save submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role_permissions'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid token. Please try again.';
        header('Location: permissions.php' . ($selectedRoleId ? '?role_id=' . $selectedRoleId : ''));
        exit();
    }

    $targetRoleId = (int)($_POST['role_id'] ?? 0);
    if ($targetRoleId <= 0) {
        $_SESSION['error'] = 'Please choose a role before saving permissions.';
        header('Location: permissions.php');
        exit();
    }

    $grantedCodes = array_keys($_POST['perm_grant'] ?? []);

    // Forcefully remove view_all_branch_attendance if it is toggled off
    $forceOffCode = 'view_all_branch_attendance';
    $forceOffId = $permissionIdMap[$forceOffCode] ?? null;

    try {
        $pdo->beginTransaction();
        $del = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?');
        $del->execute([$targetRoleId]);

        if (!empty($grantedCodes)) {
            $ins = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($grantedCodes as $code) {
                if (!isset($permissionIdMap[$code])) {
                    continue;
                }
                $ins->execute([$targetRoleId, $permissionIdMap[$code]]);
            }
        }

        // Additionally ensure the forced-off permission is removed when not granted
        if ($forceOffId && !in_array($forceOffCode, $grantedCodes, true)) {
            $cleanup = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?');
            $cleanup->execute([$targetRoleId, $forceOffId]);
        }

        $pdo->commit();
        $_SESSION['success'] = 'Role permissions updated.';
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Failed to save permissions: ' . $e->getMessage();
    }

    header('Location: permissions.php?role_id=' . $targetRoleId);
    exit();
}

// Handle creating new permission definitions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_permission'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid token. Please try again.';
        header('Location: permissions.php' . ($selectedRoleId ? '?role_id=' . $selectedRoleId : ''));
        exit();
    }

    $displayName = trim($_POST['permission_name'] ?? '');
    $code = trim($_POST['permission_code'] ?? '');
    $description = trim($_POST['permission_description'] ?? '');

    if ($code === '') {
        $_SESSION['error'] = 'Permission code is required.';
    } else {
        try {
            $cols = ['name', 'description'];
            $placeholders = ':name, :description';
            $hasCategory = false;
            $infoStmt = $pdo->query('SHOW COLUMNS FROM permissions');
            foreach ($infoStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if (($col['Field'] ?? '') === 'category') {
                    $hasCategory = true;
                    break;
                }
            }

            if ($hasCategory) {
                $cols[] = 'category';
                $placeholders .= ', :category';
            }

            $sql = 'INSERT INTO permissions (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name', $code, PDO::PARAM_STR);
            $stmt->bindParam(':description', $displayName, PDO::PARAM_STR);
            if ($hasCategory) {
                $categoryVal = 'system';
                $stmt->bindParam(':category', $categoryVal, PDO::PARAM_STR);
            }

            $stmt->execute();
            $_SESSION['success'] = 'Permission added.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to add permission: ' . $e->getMessage();
        }
    }

    header('Location: permissions.php' . ($selectedRoleId ? '?role_id=' . $selectedRoleId : ''));
    exit();
}

$selectedRole = null;
if ($selectedRoleId > 0) {
    try {
        $roleStmt = $pdo->prepare('SELECT id, name, description FROM roles WHERE id = ? LIMIT 1');
        $roleStmt->execute([$selectedRoleId]);
        $selectedRole = $roleStmt->fetch(PDO::FETCH_ASSOC);
        if (!$selectedRole) {
            $_SESSION['error'] = 'Role not found.';
            header('Location: permissions.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to fetch role details: ' . $e->getMessage();
    }
}

$rolePermissions = $selectedRole ? hrms_get_permissions_for_role($selectedRoleId) : [];
$assignedCount = count($rolePermissions);
$totalCatalogPermissions = count($flatCatalog);
?>

<div class="container-fluid p-4 role-permissions-page">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Roles &amp; Permissions</h1>
            <p class="text-muted mb-0">Configure the base permission set each role grants before user-level overrides.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                <i class="fas fa-plus me-2"></i>Add Role
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPermissionModal">
                <i class="fas fa-plus me-2"></i>New Permission
            </button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="roleSelect" class="form-label">Select Role</label>
                    <select class="form-select" id="roleSelect" name="role_id" required>
                        <option value="">Choose a role...</option>
                        <?php foreach ($roles as $roleOption): ?>
                            <option value="<?php echo (int)$roleOption['id']; ?>" <?php echo $selectedRoleId === (int)$roleOption['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($roleOption['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">Load Permissions</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedRole): ?>
        <div class="card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">Editing Role: <?php echo htmlspecialchars($selectedRole['name']); ?></h5>
                    <p class="mb-0 text-muted"><?php echo $selectedRole['description'] ? htmlspecialchars($selectedRole['description']) : 'No description provided.'; ?></p>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary">Granted: <?php echo $assignedCount; ?> / <?php echo $totalCatalogPermissions; ?></span>
                </div>
            </div>
        </div>

        <form method="post" class="card mb-4">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="role_id" value="<?php echo (int)$selectedRoleId; ?>">
                <input type="hidden" name="save_role_permissions" value="1">

                <div class="accordion" id="rolePermAccordion">
                    <?php foreach ($catalog['sections'] as $sectionKey => $section): ?>
                        <div class="accordion-item mb-3">
                            <h2 class="accordion-header" id="heading-<?php echo htmlspecialchars($sectionKey); ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo htmlspecialchars($sectionKey); ?>">
                                    <i class="<?php echo htmlspecialchars($section['icon']); ?> me-2"></i>
                                    <?php echo htmlspecialchars($section['label']); ?>
                                </button>
                            </h2>
                            <div id="collapse-<?php echo htmlspecialchars($sectionKey); ?>" class="accordion-collapse collapse" data-bs-parent="#rolePermAccordion">
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
                                                            <th class="text-center">Grant</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($child['permissions'] ?? [] as $permission): ?>
                                                            <?php
                                                                $code = $permission['code'];
                                                                if (in_array($code, $seenCodes, true)) { continue; }
                                                                $seenCodes[] = $code;
                                                                $granted = in_array($code, $rolePermissions, true);
                                                            ?>
                                                            <tr>
                                                                <td class="fw-medium">
                                                                    <?php echo htmlspecialchars($permission['label'] ?? $code); ?>
                                                                    <div class="text-muted small">Code: <?php echo htmlspecialchars($code); ?></div>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($permission['description'] ?? ''); ?></td>
                                                                <td class="text-center">
                                                                    <div class="form-check form-switch d-inline-flex align-items-center justify-content-center">
                                                                        <input class="form-check-input" type="checkbox" role="switch"
                                                                               id="perm-<?php echo htmlspecialchars($code); ?>"
                                                                               name="perm_grant[<?php echo htmlspecialchars($code); ?>]"
                                                                               <?php echo $granted ? 'checked' : ''; ?>>
                                                                        <label class="visually-hidden" for="perm-<?php echo htmlspecialchars($code); ?>">Grant <?php echo htmlspecialchars($code); ?></label>
                                                                    </div>
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

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-info">
            Select a role to view and edit its permissions.
        </div>
    <?php endif; ?>
</div>

<!-- Modal for adding a new permission -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRoleModalLabel">Add New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="create_role" value="1">
                    <div class="mb-3">
                        <label for="new_role_name" class="form-label">Role Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new_role_name" name="new_role_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_role_description" class="form-label">Description</label>
                        <textarea class="form-control" id="new_role_description" name="new_role_description" rows="3" placeholder="Describe what this role is used for."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for adding a new permission -->
<div class="modal fade" id="addPermissionModal" tabindex="-1" aria-labelledby="addPermissionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPermissionModalLabel">Add New Permission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="redirect_role_id" value="<?php echo (int)$selectedRoleId; ?>">
                    <div class="mb-3">
                        <label for="permission_name" class="form-label">Permission Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="permission_name" name="permission_name" required>
                        <small class="text-muted">Example: "View Employees", "Manage Reports"</small>
                    </div>
                    <div class="mb-3">
                        <label for="permission_code" class="form-label">Permission Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="permission_code" name="permission_code" required>
                        <small class="text-muted">Lowercase, underscores only (e.g., manage_reports).</small>
                    </div>
                    <div class="mb-3">
                        <label for="permission_description" class="form-label">Description</label>
                        <textarea class="form-control" id="permission_description" name="permission_description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="add_permission" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Permission</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const permissionNameInput = document.getElementById('permission_name');
    const permissionCodeInput = document.getElementById('permission_code');
    if(permissionNameInput && permissionCodeInput){
        permissionNameInput.addEventListener('input', function(){
            const code = permissionNameInput.value.trim().toLowerCase().replace(/\s+/g,'_');
            permissionCodeInput.value = code;
        });
    }
});
</script>
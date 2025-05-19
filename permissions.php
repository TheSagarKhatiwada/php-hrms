<?php
// Include session configuration first to ensure session is available
require_once 'includes/session_config.php';
require_once 'includes/utilities.php';

$page = 'permissions';

include 'includes/db_connection.php';

// Check if role is specified - Check for both 'role' and 'role_id' parameters for compatibility
if (isset($_GET['role_id']) && !empty($_GET['role_id'])) {
    $role_id = $_GET['role_id'];
} elseif (isset($_GET['role']) && !empty($_GET['role'])) {
    $role_id = $_GET['role'];
} else {
    $_SESSION['error'] = "No role specified";
    header("location: roles.php");
    exit;
}

// Get role details
try {
    $sql = "SELECT * FROM roles WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id", $role_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Role not found";
        header("location: roles.php");
        exit;
    }
    
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header("location: roles.php");
    exit;
}

// Process form submission for role permissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_permissions'])) {
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // First delete all existing permissions for this role
        $sql = "DELETE FROM role_permissions WHERE role_id = :role_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // If permissions were selected, insert them
        if (isset($_POST['permissions']) && !empty($_POST['permissions'])) {
            $permissions = $_POST['permissions'];
            
            // Prepare statement for inserting permissions
            $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)";
            $stmt = $pdo->prepare($sql);
            
            // Insert each permission
            foreach ($permissions as $permission_id) {
                $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
                $stmt->bindParam(":permission_id", $permission_id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "Permissions updated successfully for " . htmlspecialchars($role['name']) . " role";
    } catch (PDOException $e) {
        // Roll back transaction on error
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to avoid form resubmission
    header("location: permissions.php?role=" . $role_id);
    exit;
}

// Add new permission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_permission'])) {
    $name = trim($_POST['permission_name']);
    $code = trim($_POST['permission_code']);
    $description = trim($_POST['permission_description']);
    
    if (!empty($name) && !empty($code)) {
        try {
            $sql = "INSERT INTO permissions (name, code, description) VALUES (:name, :code, :description)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(":name", $name, PDO::PARAM_STR);
            $stmt->bindParam(":code", $code, PDO::PARAM_STR);
            $stmt->bindParam(":description", $description, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "New permission added successfully";
            } else {
                $_SESSION['error'] = "Something went wrong. Please try again later.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Permission name and code are required";
    }
    
    // Redirect to avoid form resubmission
    header("location: permissions.php?role=" . $role_id);
    exit;
}

// Get all permissions
try {
    $sql = "SELECT p.*, CASE WHEN rp.role_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned 
            FROM permissions p 
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id AND rp.role_id = :role_id
            ORDER BY p.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error loading permissions: " . $e->getMessage();
    $permissions = [];
}

// Group permissions by category (based on code prefix)
$grouped_permissions = [];
foreach ($permissions as $permission) {
    // Custom grouping for report permissions
    if (strpos($permission['code'], 'view_daily_report') !== false || 
        strpos($permission['code'], 'view_monthly_report') !== false) {
        $category = 'view'; // Add report view permissions to the view category
    } else if (strpos($permission['code'], 'add_daily_report') !== false) {
        $category = 'add_daily_report';
    } else if (strpos($permission['code'], 'add_monthly_report') !== false) {
        $category = 'add_monthly_report';
    } else {
        // Standard extraction of category from permission code (e.g., "manage_employees" -> "manage")
        $parts = explode('_', $permission['code']);
        $category = $parts[0];
    }
    
    if (!isset($grouped_permissions[$category])) {
        $grouped_permissions[$category] = [];
    }
    
    $grouped_permissions[$category][] = $permission;
}

// Get count of assigned permissions
$assigned_count = 0;
foreach ($permissions as $permission) {
    if ($permission['is_assigned'] == 1) {
        $assigned_count++;
    }
}

// Include the header (which includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/includes/header.php';
?>

<!-- Content Wrapper (already started in header.php) -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Manage Permissions</h1>
            <p class="text-muted mb-0">
                Configure permissions for <strong><?php echo htmlspecialchars($role['name']); ?></strong> role
            </p>
        </div>
        <div>
            <a href="roles.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Roles
            </a>
            <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#addPermissionModal">
                <i class="fas fa-plus me-2"></i> Add New Permission
            </button>
        </div>
    </div>
    
    <!-- Role Summary Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3">
            <h5 class="card-title mb-0">
                <i class="fas fa-info-circle me-2 text-primary"></i>
                Role Summary
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <p class="text-muted mb-1">Role Name</p>
                    <p class="fw-bold fs-5 mb-3"><?php echo htmlspecialchars($role['name']); ?></p>
                </div>
                <div class="col-md-5">
                    <p class="text-muted mb-1">Description</p>
                    <p class="mb-3">
                        <?php echo empty($role['description']) 
                                ? '<span class="text-muted fst-italic">No description provided</span>' 
                                : htmlspecialchars($role['description']); ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <p class="text-muted mb-1">Assigned Permissions</p>
                    <p class="fw-bold mb-3">
                        <span class="badge bg-primary fs-6"><?php echo $assigned_count; ?> 
                        of <?php echo count($permissions); ?></span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Permissions Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-key me-2 text-primary"></i>
                    Permission Settings
                </h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-success" id="select-all-btn">Select All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="deselect-all-btn">Deselect All</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?role=' . $role_id; ?>" method="post">
                <?php if (empty($permissions)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No permissions found in the system.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($grouped_permissions as $category => $category_permissions): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0 text-capitalize"><?php echo htmlspecialchars($category); ?> Permissions</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php foreach ($category_permissions as $permission): ?>
                                                <div class="col-md-12 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input permission-checkbox" 
                                                               type="checkbox" 
                                                               name="permissions[]" 
                                                               value="<?php echo $permission['id']; ?>" 
                                                               id="perm<?php echo $permission['id']; ?>"
                                                               <?php echo $permission['is_assigned'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="perm<?php echo $permission['id']; ?>">
                                                            <span class="fw-medium"><?php echo htmlspecialchars($permission['name']); ?></span>
                                                            <small class="d-block text-muted"><?php echo htmlspecialchars($permission['description']); ?></small>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex justify-content-end">
                        <input type="hidden" name="update_permissions" value="1">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i> Save Permission Changes
                        </button>
                    </div>
                <?php endif; ?>
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
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?role=' . $role_id; ?>" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="permission_name" class="form-label">Permission Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="permission_name" name="permission_name" required>
                        <small class="text-muted">Example: "View Employees", "Manage Reports"</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="permission_code" class="form-label">Permission Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="permission_code" name="permission_code" required>
                        <small class="text-muted">Example: "view_employees", "manage_reports" (no spaces, use underscores)</small>
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

<!-- Include the main footer (which closes content-wrapper, main-wrapper, etc.) -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select/Deselect All buttons
    const selectAllBtn = document.getElementById('select-all-btn');
    const deselectAllBtn = document.getElementById('deselect-all-btn');
    const checkboxes = document.querySelectorAll('.permission-checkbox');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
        });
    }
    
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        });
    }
    
    // Format permission code input to follow convention
    const permissionNameInput = document.getElementById('permission_name');
    const permissionCodeInput = document.getElementById('permission_code');
    
    if (permissionNameInput && permissionCodeInput) {
        permissionNameInput.addEventListener('input', function() {
            // Generate permission code from name (lowercase, replace spaces with underscores)
            const name = permissionNameInput.value.trim();
            const code = name.toLowerCase().replace(/\s+/g, '_');
            permissionCodeInput.value = code;
        });
    }
});
</script>
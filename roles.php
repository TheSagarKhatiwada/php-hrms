<?php
/**
 * Roles Management Page
 * This page allows administrators to manage roles in the system
 */
$page = 'roles';
// Include necessary files
require_once 'includes/header.php';
require_once 'includes/db_connection.php';
require_once 'includes/utilities.php';
require_once 'includes/csrf_protection.php';
// Check if user is logged in and has admin privileges
if (!is_logged_in() || !is_admin()) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: index.php');
    exit();
}
// Generate CSRF token
$csrf_token = generate_csrf_token();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect_with_message('roles.php', 'error', 'Invalid security token. Please try again.');
    }

    // Handle different form actions
    $action = $_POST['action'] ?? '';

    // Add new role
    if ($action === 'add_role') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            set_flash_message('error', 'Role name is required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (:name, :description)");
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->execute();
                
                log_activity($pdo, 'role_created', "Created new role: $name");
                set_flash_message('success', 'Role created successfully.');
                header('Location: roles.php');
                exit();
            } catch (PDOException $e) {
                error_log('Error creating role: ' . $e->getMessage(), 3, 'error_log.txt');
                set_flash_message('error', 'Error creating role. Please try again.');
            }
        }
    }
    
    // Update existing role
    elseif ($action === 'edit_role') {
        $role_id = (int)($_POST['role_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || $role_id <= 0) {
            set_flash_message('error', 'Invalid role data.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE roles SET name = :name, description = :description WHERE id = :id");
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':id', $role_id, PDO::PARAM_INT);
                $stmt->execute();
                
                log_activity($pdo, 'role_updated', "Updated role ID: $role_id");
                set_flash_message('success', 'Role updated successfully.');
                header('Location: roles.php');
                exit();
            } catch (PDOException $e) {
                error_log('Error updating role: ' . $e->getMessage(), 3, 'error_log.txt');
                set_flash_message('error', 'Error updating role. Please try again.');
            }
        }
    }
    
    // Delete role
    elseif ($action === 'delete_role') {
        $role_id = (int)($_POST['role_id'] ?? 0);
        
        // Check if the role is in use
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE role_id = :role_id");
            $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                set_flash_message('error', 'This role cannot be deleted because it is assigned to employees.');
            } else {
                // Delete role_permissions first (to maintain referential integrity)
                $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id");
                $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
                $stmt->execute();
                
                // Now delete the role
                $stmt = $pdo->prepare("DELETE FROM roles WHERE id = :id");
                $stmt->bindParam(':id', $role_id, PDO::PARAM_INT);
                $stmt->execute();
                
                log_activity($pdo, 'role_deleted', "Deleted role ID: $role_id");
                set_flash_message('success', 'Role deleted successfully.');
            }
            
            header('Location: roles.php');
            exit();
        } catch (PDOException $e) {
            error_log('Error deleting role: ' . $e->getMessage(), 3, 'error_log.txt');
            set_flash_message('error', 'Error deleting role. Please try again.');
        }
    }
}

// Get all roles
try {
    $stmt = $pdo->query("SELECT * FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching roles: ' . $e->getMessage(), 3, 'error_log.txt');
    $roles = [];
    set_flash_message('error', 'Error loading roles. Please try again.');
}

// Include the header (which includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Role Management</h1>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
            <i class="fas fa-plus me-2"></i> Add New Role
        </button>
    </div>

    <?php if (isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
        <div class="alert alert-<?php echo isset($_SESSION['success']) ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php 
                echo isset($_SESSION['success']) ? $_SESSION['success'] : $_SESSION['error']; 
                unset($_SESSION['success']);
                unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Roles Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="roles-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th class="text-center">Created At</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                        <tr>
                            <td class="text-center align-middle"><?php echo htmlspecialchars($role['id']); ?></td>
                            <td class="align-middle fw-bold"><?php echo htmlspecialchars($role['name']); ?></td>
                            <td class="align-middle"><?php echo htmlspecialchars($role['description'] ?? 'N/A'); ?></td>
                            <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($role['created_at'])); ?></td>
                            <td class="text-center align-middle">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $role['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $role['id']; ?>">
                                        <li>
                                            <a class="dropdown-item" href="permissions.php?role_id=<?php echo $role['id']; ?>">
                                                <i class="fas fa-lock me-2"></i> Permissions
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item edit-role-btn" href="#" 
                                                data-id="<?php echo $role['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($role['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($role['description'] ?? ''); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editRoleModal">
                                                <i class="fas fa-edit me-2"></i> Edit
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger delete-role-btn" href="#"
                                                data-id="<?php echo $role['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($role['name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#deleteRoleModal">
                                                <i class="fas fa-trash me-2"></i> Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div> <!-- /.container-fluid -->

<!-- Modals remain outside the main content flow, before the final footer include -->
<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="roles.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_role">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addRoleModalLabel">Add New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add-role-name" class="form-label">Role Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add-role-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="add-role-description" class="form-label">Description</label>
                        <textarea class="form-control" id="add-role-description" name="description" rows="3" placeholder="Enter role description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="roles.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit_role">
                <input type="hidden" name="role_id" id="edit-role-id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editRoleModalLabel">Edit Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-role-name" class="form-label">Role Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-role-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-role-description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit-role-description" name="description" rows="3" placeholder="Enter role description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Role Modal -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-labelledby="deleteRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="roles.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="delete_role">
                <input type="hidden" name="role_id" id="delete-role-id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteRoleModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the role <strong id="delete-role-name"></strong>?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. If the role is assigned to any employees, the deletion will fail.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Role</button>
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
    // Initialize DataTable
    const rolesTable = new DataTable('#roles-table', {
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        order: [[0, 'asc']], // Sort by ID by default
        pageLength: 10,
        language: {
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            }
        }
    });
    
    // Edit Role Modal Handler
    const editRoleModal = document.getElementById('editRoleModal');
    if (editRoleModal) {
        editRoleModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const description = button.getAttribute('data-description');
            
            document.getElementById('edit-role-id').value = id;
            document.getElementById('edit-role-name').value = name;
            document.getElementById('edit-role-description').value = description;
        });
    }
    
    // Delete Role Modal Handler
    const deleteRoleModal = document.getElementById('deleteRoleModal');
    if (deleteRoleModal) {
        deleteRoleModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            
            document.getElementById('delete-role-id').value = id;
            document.getElementById('delete-role-name').textContent = name;
        });
    }
});
</script>
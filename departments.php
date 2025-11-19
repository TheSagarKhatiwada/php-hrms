<?php
/**
 * Department Management Page
 * This page allows administrators to manage organization departments
 */
$page = 'departments';
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
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect_with_message('departments.php', 'error', 'Invalid security token. Please try again.');
    }

    // Handle different form actions
    $action = $_POST['action'] ?? '';

    // Add new department
    if ($action === 'add_department') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
        
        if (empty($name)) {
            $_SESSION['error'] = 'Department name is required.';
        } else {
            try {
                $sql = "INSERT INTO departments (name, description, manager_id) VALUES (:name, :description, :manager_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':manager_id', $manager_id, $manager_id !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->execute();
                
                log_activity($pdo, 'department_created', "Created new department: $name");
                $_SESSION['success'] = 'Department created successfully.';
                header('Location: departments.php');
                exit();
            } catch (PDOException $e) {
                error_log('Error creating department: ' . $e->getMessage(), 3, 'error_log.txt');
                $_SESSION['error'] = 'Error creating department. Please try again.';
            }
        }
    }
    
    // Update existing department
    elseif ($action === 'edit_department') {
        $department_id = (int)($_POST['department_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
        
        if (empty($name) || $department_id <= 0) {
            $_SESSION['error'] = 'Invalid department data.';
        } else {
            try {
                $sql = "UPDATE departments SET name = :name, description = :description, manager_id = :manager_id, updated_at = NOW() WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':manager_id', $manager_id, $manager_id !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->bindParam(':id', $department_id, PDO::PARAM_INT);
                $stmt->execute();
                
                log_activity($pdo, 'department_updated', "Updated department ID: $department_id");
                $_SESSION['success'] = 'Department updated successfully.';
                header('Location: departments.php');
                exit();
            } catch (PDOException $e) {
                error_log('Error updating department: ' . $e->getMessage(), 3, 'error_log.txt');
                $_SESSION['error'] = 'Error updating department. Please try again.';
            }
        }
    }
    
    // Delete department
    elseif ($action === 'delete_department') {
        $department_id = (int)($_POST['department_id'] ?? 0);
        
        // Check if the department is in use
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department = :department_id");
            $stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $_SESSION['error'] = 'This department cannot be deleted because it is assigned to employees.';
            } else {
                // Delete the department
                $stmt = $pdo->prepare("DELETE FROM departments WHERE id = :id");
                $stmt->bindParam(':id', $department_id, PDO::PARAM_INT);
                $stmt->execute();
                
                log_activity($pdo, 'department_deleted', "Deleted department ID: $department_id");
                $_SESSION['success'] = 'Department deleted successfully.';
            }
            
            header('Location: departments.php');
            exit();
        } catch (PDOException $e) {
            error_log('Error deleting department: ' . $e->getMessage(), 3, 'error_log.txt');
            $_SESSION['error'] = 'Error deleting department. Please try again.';
        }
    }
}

// Get all departments with manager names
try {
    $sql = "SELECT d.*, CONCAT(e.first_name, ' ', COALESCE(e.middle_name, ''), ' ', e.last_name) AS manager_name 
        FROM departments d 
        LEFT JOIN employees e ON d.manager_id = e.emp_id 
        ORDER BY d.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching departments: ' . $e->getMessage(), 3, 'error_log.txt');
    $departments = [];
    $_SESSION['error'] = 'Error loading departments. Please try again.';
}

// Include the header (which includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Department Management</h1>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
            <i class="fas fa-plus me-2"></i> Add New Department
        </button>
    </div>

    <!-- Departments Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="departments-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Manager</th>
                            <th class="text-center">Created At</th>
                            <th class="text-center">Last Updated</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $department): ?>
                        <tr>
                            <td class="text-center align-middle"><?php echo htmlspecialchars($department['id']); ?></td>
                            <td class="align-middle fw-bold"><?php echo htmlspecialchars($department['name']); ?></td>
                            <td class="align-middle"><?php echo empty($department['description']) ? '<span class="text-muted">Not provided</span>' : htmlspecialchars($department['description']); ?></td>
                            <td class="align-middle"><?php echo empty($department['manager_name']) ? '<span class="badge bg-secondary">Not Assigned</span>' : htmlspecialchars($department['manager_name']); ?></td>
                            <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($department['created_at'])); ?></td>
                            <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($department['updated_at'])); ?></td>
                            <td class="text-center align-middle">
                                <div class="dropdown">
                                    <a href="#" class="text-secondary" role="button" id="dropdownMenuButton<?php echo $department['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $department['id']; ?>">
                                        <li>
                                            <a class="dropdown-item edit-department-btn" href="#" 
                                                data-id="<?php echo $department['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($department['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($department['description'] ?? ''); ?>"
                                                data-manager="<?php echo htmlspecialchars($department['manager_id'] ?? ''); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editDepartmentModal">
                                                <i class="fas fa-edit me-2"></i> Edit
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger delete-department-btn" href="#"
                                                data-id="<?php echo $department['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($department['name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#deleteDepartmentModal">
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

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="departments.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_department">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addDepartmentModalLabel">Add New Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add-department-name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add-department-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="add-department-description" class="form-label">Description</label>
                        <textarea class="form-control" id="add-department-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="add-department-manager" class="form-label">Department Manager</label>
                        <select class="form-select" id="add-department-manager" name="manager_id">
                            <option value="">-- Select Manager --</option>
                            <?php
                            // Fetch all active employees
                            try {
                                $sql = "SELECT emp_id AS id, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name FROM employees WHERE exit_date IS NULL ORDER BY first_name ASC";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                                
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['full_name']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error loading employees</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="departments.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit_department">
                <input type="hidden" name="department_id" id="edit-department-id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editDepartmentModalLabel">Edit Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-department-name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-department-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-department-description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit-department-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-department-manager" class="form-label">Department Manager</label>
                        <select class="form-select" id="edit-department-manager" name="manager_id">
                            <option value="">-- Select Manager --</option>
                            <?php
                            // Fetch all active employees
                            try {
                                $sql = "SELECT emp_id AS id, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name FROM employees WHERE exit_date IS NULL ORDER BY first_name ASC";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                                
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['full_name']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error loading employees</option>";
                            }
                            ?>
                        </select>
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

<!-- Delete Department Modal -->
<div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-labelledby="deleteDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="departments.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="delete_department">
                <input type="hidden" name="department_id" id="delete-department-id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDepartmentModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the department <strong id="delete-department-name"></strong>?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. If the department is assigned to any employees, the deletion will fail.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include the main footer (which closes content-wrapper, main-wrapper, etc.) -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- SweetAlert2 Flash Messages -->
<script>
<?php if (isset($_SESSION['success'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: <?php echo json_encode($_SESSION['success']); ?>,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    <?php unset($_SESSION['success']); ?>
<?php elseif (isset($_SESSION['error'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: <?php echo json_encode($_SESSION['error']); ?>,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true
    });
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>

<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    const departmentsTable = new DataTable('#departments-table', {
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
    
    // Edit Department Modal Handler
    const editDepartmentModal = document.getElementById('editDepartmentModal');
    if (editDepartmentModal) {
        editDepartmentModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const description = button.getAttribute('data-description');
            const manager = button.getAttribute('data-manager');
            
            document.getElementById('edit-department-id').value = id;
            document.getElementById('edit-department-name').value = name;
            document.getElementById('edit-department-description').value = description;
            
            const managerSelect = document.getElementById('edit-department-manager');
            if (managerSelect) {
                for (let i = 0; i < managerSelect.options.length; i++) {
                    if (managerSelect.options[i].value === manager) {
                        managerSelect.options[i].selected = true;
                        break;
                    }
                }
            }
        });
    }
    
    // Delete Department Modal Handler
    const deleteDepartmentModal = document.getElementById('deleteDepartmentModal');
    if (deleteDepartmentModal) {
        deleteDepartmentModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            
            document.getElementById('delete-department-id').value = id;
            document.getElementById('delete-department-name').textContent = name;
        });
    }
});
</script>
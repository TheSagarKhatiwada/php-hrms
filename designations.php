<?php
/**
 * Designation Management Page
 * This page allows administrators to manage job designations
 */
$page = 'designations';
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
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        header('Location: designations.php');
        exit();
    }

    // Handle different form actions
    $action = $_POST['action'] ?? '';

    // Add new designation
    if ($action === 'add_designation') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        
        if (empty($title)) {
            set_flash_message('error', 'Designation title is required.');
        } else {
            try {
                $sql = "INSERT INTO designations (title, description, department_id) VALUES (:title, :description, :department_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':department_id', $department_id, $department_id !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->execute();
                
                log_activity($pdo, 'designation_created', "Created new designation: $title");
                $_SESSION['success'] = 'Designation created successfully.';
                header('Location: designations.php');
                exit();
            } catch (PDOException $e) {
                error_log('Error creating designation: ' . $e->getMessage(), 3, 'error_log.txt');
                $_SESSION['error'] = 'Error creating designation. Please try again.';
            }
        }
    }
    
    // Update existing designation
    elseif ($action === 'edit_designation') {
        $designation_id = (int)($_POST['designation_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        
        if (empty($title) || $designation_id <= 0) {
            set_flash_message('error', 'Invalid designation data.');
        } else {
            try {
                $sql = "UPDATE designations SET title = :title, description = :description, department_id = :department_id, updated_at = NOW() WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':department_id', $department_id, $department_id !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->bindParam(':id', $designation_id, PDO::PARAM_INT);
                $stmt->execute();
                
                log_activity($pdo, 'designation_updated', "Updated designation ID: $designation_id");
                $_SESSION['success'] = 'Designation updated successfully.';
                header('Location: designations.php');
                exit();
            } catch (PDOException $e) {
                error_log('Error updating designation: ' . $e->getMessage(), 3, 'error_log.txt');
                $_SESSION['error'] = 'Error updating designation. Please try again.';
            }
        }
    }
    
    // Delete designation
    elseif ($action === 'delete_designation') {
        $designation_id = (int)($_POST['designation_id'] ?? 0);
        
        // Check if the designation is in use
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE designation = :designation_id");
            $stmt->bindParam(':designation_id', $designation_id, PDO::PARAM_INT);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                set_flash_message('error', 'This designation cannot be deleted because it is assigned to employees.');
            } else {
                // Delete the designation
                $stmt = $pdo->prepare("DELETE FROM designations WHERE id = :id");
                $stmt->bindParam(':id', $designation_id, PDO::PARAM_INT);
                $stmt->execute();
                
                log_activity($pdo, 'designation_deleted', "Deleted designation ID: $designation_id");
                $_SESSION['success'] = 'Designation deleted successfully.';
            }
            
            header('Location: designations.php');
            exit();
        } catch (PDOException $e) {
            error_log('Error deleting designation: ' . $e->getMessage(), 3, 'error_log.txt');
            $_SESSION['error'] = 'Error deleting designation. Please try again.';
        }
    }
}

// Get all designations with department names
try {
    $sql = "SELECT d.*, dept.name AS department_name 
            FROM designations d 
            LEFT JOIN departments dept ON d.department_id = dept.id 
            ORDER BY d.title ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $designations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching designations: ' . $e->getMessage(), 3, 'error_log.txt');
    $designations = [];
    set_flash_message('error', 'Error loading designations. Please try again.');
}

// Include the header (which includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Designation Management</h1>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDesignationModal">
            <i class="fas fa-plus me-2"></i> Add New Designation
        </button>
    </div>
    
    <!-- Designations Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="designations-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Department</th>
                            <th class="text-center">Created At</th>
                            <th class="text-center">Last Updated</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($designations as $designation): ?>
                        <tr>
                            <td class="text-center align-middle"><?php echo htmlspecialchars($designation['id']); ?></td>
                            <td class="align-middle fw-bold"><?php echo htmlspecialchars($designation['title']); ?></td>
                            <td class="align-middle"><?php echo empty($designation['description']) ? '<span class="text-muted">Not provided</span>' : htmlspecialchars($designation['description']); ?></td>
                            <td class="align-middle"><?php echo empty($designation['department_name']) ? '<span class="badge bg-secondary">Not Assigned</span>' : htmlspecialchars($designation['department_name']); ?></td>
                            <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($designation['created_at'])); ?></td>
                            <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($designation['updated_at'])); ?></td>
                            <td class="text-center align-middle">
                                <div class="dropdown">
                                    <a href="#" class="text-secondary" role="button" id="dropdownMenuButton<?php echo $designation['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $designation['id']; ?>">
                                        <li>
                                            <a class="dropdown-item edit-designation-btn" href="#" 
                                                data-id="<?php echo $designation['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($designation['title']); ?>"
                                                data-description="<?php echo htmlspecialchars($designation['description'] ?? ''); ?>"
                                                data-department="<?php echo htmlspecialchars($designation['department_id'] ?? ''); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editDesignationModal">
                                                <i class="fas fa-edit me-2"></i> Edit
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger delete-designation-btn" href="#"
                                                data-id="<?php echo $designation['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($designation['title']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#deleteDesignationModal">
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

<!-- Add Designation Modal -->
<div class="modal fade" id="addDesignationModal" tabindex="-1" aria-labelledby="addDesignationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="designations.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_designation">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addDesignationModalLabel">Add New Designation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add-designation-title" class="form-label">Designation Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add-designation-title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="add-designation-description" class="form-label">Description</label>
                        <textarea class="form-control" id="add-designation-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="add-designation-department" class="form-label">Department</label>
                        <select class="form-select" id="add-designation-department" name="department_id">
                            <option value="">-- Select Department --</option>
                            <?php
                            // Fetch all departments
                            try {
                                $sql = "SELECT id, name FROM departments ORDER BY name ASC";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                                
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['name']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error loading departments</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Designation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Designation Modal -->
<div class="modal fade" id="editDesignationModal" tabindex="-1" aria-labelledby="editDesignationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="designations.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit_designation">
                <input type="hidden" name="designation_id" id="edit-designation-id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editDesignationModalLabel">Edit Designation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-designation-title" class="form-label">Designation Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-designation-title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-designation-description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit-designation-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-designation-department" class="form-label">Department</label>
                        <select class="form-select" id="edit-designation-department" name="department_id">
                            <option value="">-- Select Department --</option>
                            <?php
                            // Fetch all departments
                            try {
                                $sql = "SELECT id, name FROM departments ORDER BY name ASC";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute();
                                
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['name']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error loading departments</option>";
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

<!-- Delete Designation Modal -->
<div class="modal fade" id="deleteDesignationModal" tabindex="-1" aria-labelledby="deleteDesignationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="designations.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="delete_designation">
                <input type="hidden" name="designation_id" id="delete-designation-id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDesignationModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the designation <strong id="delete-designation-title"></strong>?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. If the designation is assigned to any employees, the deletion will fail.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Designation</button>
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
    const designationsTable = new DataTable('#designations-table', {
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
    
    // Edit Designation Modal Handler
    const editDesignationModal = document.getElementById('editDesignationModal');
    if (editDesignationModal) {
        editDesignationModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const title = button.getAttribute('data-title');
            const description = button.getAttribute('data-description');
            const department = button.getAttribute('data-department');
            
            document.getElementById('edit-designation-id').value = id;
            document.getElementById('edit-designation-title').value = title;
            document.getElementById('edit-designation-description').value = description;
            
            const departmentSelect = document.getElementById('edit-designation-department');
            if (departmentSelect) {
                for (let i = 0; i < departmentSelect.options.length; i++) {
                    if (departmentSelect.options[i].value === department) {
                        departmentSelect.options[i].selected = true;
                        break;
                    }
                }
            }
        });
    }
    
    // Delete Designation Modal Handler
    const deleteDesignationModal = document.getElementById('deleteDesignationModal');
    if (deleteDesignationModal) {
        deleteDesignationModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const title = button.getAttribute('data-title');
            
            document.getElementById('delete-designation-id').value = id;
            document.getElementById('delete-designation-title').textContent = title;
        });
    }
});
</script>
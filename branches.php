<?php
/**
 * Branch Management Page
 * This page allows administrators to manage organization branches
 */
$page = 'branches';
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
        redirect_with_message('branches.php', 'error', 'Invalid security token. Please try again.');
    }

    // Handle different form actions
    $action = $_POST['action'] ?? '';

    // Add new branch
    if ($action === 'add_branch') {
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            set_flash_message('error', 'Branch name is required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO branches (name) VALUES (:name)");
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->execute();
                
                log_activity($pdo, 'branch_created', "Created new branch: $name");
                set_flash_message('success', 'Branch created successfully.');
                header('Location: branches.php');
                exit();
            } catch (PDOException $e) {
                error_log('Error creating branch: ' . $e->getMessage(), 3, 'error_log.txt');
                set_flash_message('error', 'Error creating branch. Please try again.');
            }
        }
    }
    
    // Update existing branch
    elseif ($action === 'edit_branch') {
        $branch_id = (int)($_POST['branch_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name) || $branch_id <= 0) {
            set_flash_message('error', 'Invalid branch data.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE branches SET name = :name, updated_at = NOW() WHERE id = :id");
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindParam(':id', $branch_id, PDO::PARAM_INT);
                $stmt->execute();
                
                log_activity($pdo, 'branch_updated', "Updated branch ID: $branch_id");
                set_flash_message('success', 'Branch updated successfully.');
                header('Location: branches.php');
                exit();
            } catch (PDOException $e) {
                error_log('Error updating branch: ' . $e->getMessage(), 3, 'error_log.txt');
                set_flash_message('error', 'Error updating branch. Please try again.');
            }
        }
    }
    
    // Delete branch
    elseif ($action === 'delete_branch') {
        $branch_id = (int)($_POST['branch_id'] ?? 0);
        
        // Check if the branch is in use
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE branch = :branch_id");
            $stmt->bindParam(':branch_id', $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                set_flash_message('error', 'This branch cannot be deleted because it is assigned to employees.');
            } else {
                // Delete the branch
                $stmt = $pdo->prepare("DELETE FROM branches WHERE id = :id");
                $stmt->bindParam(':id', $branch_id, PDO::PARAM_INT);
                $stmt->execute();
                
                log_activity($pdo, 'branch_deleted', "Deleted branch ID: $branch_id");
                set_flash_message('success', 'Branch deleted successfully.');
            }
            
            header('Location: branches.php');
            exit();
        } catch (PDOException $e) {
            error_log('Error deleting branch: ' . $e->getMessage(), 3, 'error_log.txt');
            set_flash_message('error', 'Error deleting branch. Please try again.');
        }
    }
}

// Get all branches
try {
    $stmt = $pdo->query("SELECT * FROM branches ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching branches: ' . $e->getMessage(), 3, 'error_log.txt');
    $branches = [];
    set_flash_message('error', 'Error loading branches. Please try again.');
}

// Include the header (which includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Branch Management</h1>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBranchModal">
            <i class="fas fa-plus me-2"></i> Add New Branch
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
    
    <!-- Branches Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="branches-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Name</th>
                            <th class="text-center">Created At</th>
                            <th class="text-center">Last Updated</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $branch): ?>
                        <tr>
                            <td class="text-center align-middle"><?php echo htmlspecialchars($branch['id']); ?></td>
                            <td class="align-middle fw-bold"><?php echo htmlspecialchars($branch['name']); ?></td>
                            <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($branch['created_at'])); ?></td>
                            <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($branch['updated_at'])); ?></td>
                            <td class="text-center align-middle">
                                <div class="dropdown">
                                    <a href="#" class="text-secondary" role="button" id="dropdownMenuButton<?php echo $branch['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $branch['id']; ?>">
                                        <li>
                                            <a class="dropdown-item edit-branch-btn" href="#" 
                                                data-id="<?php echo $branch['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($branch['name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editBranchModal">
                                                <i class="fas fa-edit me-2"></i> Edit
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger delete-branch-btn" href="#"
                                                data-id="<?php echo $branch['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($branch['name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#deleteBranchModal">
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
<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1" aria-labelledby="addBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="branches.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_branch">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addBranchModalLabel">Add New Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add-branch-name" class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add-branch-name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1" aria-labelledby="editBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="branches.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit_branch">
                <input type="hidden" name="branch_id" id="edit-branch-id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editBranchModalLabel">Edit Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-branch-name" class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-branch-name" name="name" required>
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

<!-- Delete Branch Modal -->
<div class="modal fade" id="deleteBranchModal" tabindex="-1" aria-labelledby="deleteBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="branches.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="delete_branch">
                <input type="hidden" name="branch_id" id="delete-branch-id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteBranchModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the branch <strong id="delete-branch-name"></strong>?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. If the branch is assigned to any employees, the deletion will fail.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Branch</button>
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
    const branchesTable = new DataTable('#branches-table', {
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
    
    // Edit Branch Modal Handler
    const editBranchModal = document.getElementById('editBranchModal');
    if (editBranchModal) {
        editBranchModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            
            document.getElementById('edit-branch-id').value = id;
            document.getElementById('edit-branch-name').value = name;
        });
    }
    
    // Delete Branch Modal Handler
    const deleteBranchModal = document.getElementById('deleteBranchModal');
    if (deleteBranchModal) {
        deleteBranchModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            
            document.getElementById('delete-branch-id').value = id;
            document.getElementById('delete-branch-name').textContent = name;
        });
    }
});
</script>
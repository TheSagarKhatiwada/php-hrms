<?php
// Include session configuration first to ensure session is available
require_once 'includes/session_config.php';
require_once 'includes/utilities.php';

$page = 'leave-types';

// Check if user has admin access
if (!is_admin()) {
    header('Location: dashboard.php');
    exit();
}

include 'includes/db_connection.php';

// Handle CRUD operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $description = trim($_POST['description']);
                $days_allowed = (int)$_POST['days_allowed_per_year'];
                $is_paid = isset($_POST['is_paid']) ? 1 : 0;
                $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;
                $max_consecutive = !empty($_POST['max_consecutive_days']) ? (int)$_POST['max_consecutive_days'] : null;
                $min_notice = (int)$_POST['min_notice_days'];
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO leave_types (name, code, description, days_allowed_per_year, is_paid, requires_approval, max_consecutive_days, min_notice_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $code, $description, $days_allowed, $is_paid, $requires_approval, $max_consecutive, $min_notice]);
                    $_SESSION['success'] = "Leave type created successfully!";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error creating leave type: " . $e->getMessage();
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $description = trim($_POST['description']);
                $days_allowed = (int)$_POST['days_allowed_per_year'];
                $is_paid = isset($_POST['is_paid']) ? 1 : 0;
                $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;
                $max_consecutive = !empty($_POST['max_consecutive_days']) ? (int)$_POST['max_consecutive_days'] : null;
                $min_notice = (int)$_POST['min_notice_days'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                try {
                    $stmt = $pdo->prepare("UPDATE leave_types SET name = ?, code = ?, description = ?, days_allowed_per_year = ?, is_paid = ?, requires_approval = ?, max_consecutive_days = ?, min_notice_days = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $code, $description, $days_allowed, $is_paid, $requires_approval, $max_consecutive, $min_notice, $is_active, $id]);
                    $_SESSION['success'] = "Leave type updated successfully!";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error updating leave type: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                try {
                    // Check if there are any leave requests using this type
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE leave_type_id = ?");
                    $stmt->execute([$id]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count > 0) {
                        $_SESSION['error'] = "Cannot delete leave type: It is being used in leave requests.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM leave_types WHERE id = ?");
                        $stmt->execute([$id]);
                        $_SESSION['success'] = "Leave type deleted successfully!";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error deleting leave type: " . $e->getMessage();
                }
                break;
        }
        header('Location: leave-types.php?_nocache=' . time());
        exit();
    }
}

// Fetch all leave types
try {
    $stmt = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC");
    $leaveTypes = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching leave types: " . $e->getMessage();
    $leaveTypes = [];
}

// Force fresh load by checking for cache-busting parameter
if (!isset($_GET['_nocache'])) {
    header("Location: leave-types.php?_nocache=" . time());
    exit();
}

// Include the header (which includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/includes/header.php';
?>

<!-- Content Wrapper (already started in header.php) -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Leave Types</h1>
            <p class="text-muted mb-0">Configure different types of leaves and their policies</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" id="refreshLeaveTypes" class="btn btn-outline-primary" onclick="forceRefresh()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeaveTypeModal">
                <i class="fas fa-plus me-2"></i> Add Leave Type
            </button>
        </div>
    </div>
    
    <!-- Leave Types Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="leave-types-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th class="text-center">Days/Year</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Approval</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaveTypes as $type): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($type['name']); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($type['code']); ?></span>
                                </td>
                                <td>
                                    <div class="small text-muted" style="max-width: 200px;">
                                        <?php echo htmlspecialchars($type['description'] ?: 'No description'); ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold"><?php echo $type['days_allowed_per_year']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $type['is_paid'] ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo $type['is_paid'] ? 'Paid' : 'Unpaid'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($type['requires_approval']): ?>
                                        <i class="fas fa-check-circle text-warning" title="Requires Approval"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle text-muted" title="No Approval Required"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $type['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $type['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <a href="#" class="text-secondary" role="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item edit-leave-type" href="#"
                                                   data-bs-toggle="modal" 
                                                   data-bs-target="#editLeaveTypeModal"
                                                   data-id="<?php echo $type['id']; ?>"
                                                   data-name="<?php echo htmlspecialchars($type['name']); ?>"
                                                   data-code="<?php echo htmlspecialchars($type['code']); ?>"
                                                   data-description="<?php echo htmlspecialchars($type['description']); ?>"
                                                   data-days="<?php echo $type['days_allowed_per_year']; ?>"
                                                   data-paid="<?php echo $type['is_paid']; ?>"
                                                   data-approval="<?php echo $type['requires_approval']; ?>"
                                                   data-max-consecutive="<?php echo $type['max_consecutive_days']; ?>"
                                                   data-min-notice="<?php echo $type['min_notice_days']; ?>"
                                                   data-active="<?php echo $type['is_active']; ?>">
                                                    <i class="fas fa-edit me-2"></i> Edit
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger delete-leave-type" href="#"
                                                   data-bs-toggle="modal" 
                                                   data-bs-target="#deleteLeaveTypeModal"
                                                   data-id="<?php echo $type['id']; ?>"
                                                   data-name="<?php echo htmlspecialchars($type['name']); ?>">
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
</div>

<!-- Add Leave Type Modal -->
<div class="modal fade" id="addLeaveTypeModal" tabindex="-1" aria-labelledby="addLeaveTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLeaveTypeModalLabel">Add Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="code" name="code" required 
                                   placeholder="e.g., AL, SL, ML" style="text-transform: uppercase;">
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="days_allowed_per_year" class="form-label">Days Allowed Per Year</label>
                            <input type="number" class="form-control" id="days_allowed_per_year" name="days_allowed_per_year" 
                                   min="0" max="365" value="0">
                        </div>
                        <div class="col-md-6">
                            <label for="min_notice_days" class="form-label">Minimum Notice Days</label>
                            <input type="number" class="form-control" id="min_notice_days" name="min_notice_days" 
                                   min="0" value="0">
                        </div>
                        <div class="col-md-6">
                            <label for="max_consecutive_days" class="form-label">Max Consecutive Days</label>
                            <input type="number" class="form-control" id="max_consecutive_days" name="max_consecutive_days" 
                                   min="1" placeholder="Leave empty for no limit">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Settings</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_paid" name="is_paid" checked>
                                <label class="form-check-label" for="is_paid">
                                    Paid Leave
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="requires_approval" name="requires_approval" checked>
                                <label class="form-check-label" for="requires_approval">
                                    Requires Approval
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Leave Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Leave Type Modal -->
<div class="modal fade" id="editLeaveTypeModal" tabindex="-1" aria-labelledby="editLeaveTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLeaveTypeModalLabel">Edit Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_code" class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_code" name="code" required style="text-transform: uppercase;">
                        </div>
                        <div class="col-12">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_days_allowed_per_year" class="form-label">Days Allowed Per Year</label>
                            <input type="number" class="form-control" id="edit_days_allowed_per_year" name="days_allowed_per_year" 
                                   min="0" max="365">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_min_notice_days" class="form-label">Minimum Notice Days</label>
                            <input type="number" class="form-control" id="edit_min_notice_days" name="min_notice_days" min="0">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_max_consecutive_days" class="form-label">Max Consecutive Days</label>
                            <input type="number" class="form-control" id="edit_max_consecutive_days" name="max_consecutive_days" 
                                   min="1" placeholder="Leave empty for no limit">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Settings</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_paid" name="is_paid">
                                <label class="form-check-label" for="edit_is_paid">
                                    Paid Leave
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_requires_approval" name="requires_approval">
                                <label class="form-check-label" for="edit_requires_approval">
                                    Requires Approval
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Leave Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Leave Type Modal -->
<div class="modal fade" id="deleteLeaveTypeModal" tabindex="-1" aria-labelledby="deleteLeaveTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteLeaveTypeModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <p>Are you sure you want to delete the leave type "<span id="delete_name" class="fw-bold"></span>"?</p>
                    <p class="text-danger small">This action cannot be undone and will fail if there are existing leave requests using this type.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
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
    new DataTable('#leave-types-table', {
        responsive: true,
        lengthChange: false,
        autoWidth: false,
        order: [[0, 'asc']], // Sort by name
        pageLength: 10,
        language: {
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            }
        }
    });
    
    // Handle edit leave type modal
    const editModal = document.getElementById('editLeaveTypeModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            // Extract data
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const code = button.getAttribute('data-code');
            const description = button.getAttribute('data-description');
            const days = button.getAttribute('data-days');
            const paid = button.getAttribute('data-paid') === '1';
            const approval = button.getAttribute('data-approval') === '1';
            const maxConsecutive = button.getAttribute('data-max-consecutive');
            const minNotice = button.getAttribute('data-min-notice');
            const active = button.getAttribute('data-active') === '1';
            
            // Set form values
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_code').value = code;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_days_allowed_per_year').value = days;
            document.getElementById('edit_is_paid').checked = paid;
            document.getElementById('edit_requires_approval').checked = approval;
            document.getElementById('edit_max_consecutive_days').value = maxConsecutive || '';
            document.getElementById('edit_min_notice_days').value = minNotice;
            document.getElementById('edit_is_active').checked = active;
        });
    }
    
    // Handle delete leave type modal
    const deleteModal = document.getElementById('deleteLeaveTypeModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_name').textContent = name;
        });
    }
    
    // Auto-uppercase code fields
    document.getElementById('code').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    
    document.getElementById('edit_code').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
});

function forceRefresh() {
    window.location.href = 'leave-types.php?_nocache=' + new Date().getTime();
}
</script>

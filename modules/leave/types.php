<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';

// Check if user is logged in and has admin/hr permissions
if (!isset($_SESSION['user_id']) || !is_admin()) {
    header("Location: ../../index.php");
    exit();
}

// Handle form submissions
if ($_POST) {
    try {
        if (isset($_POST['add_type'])) {
            // Add new leave type
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $default_days = intval($_POST['default_days']);
            $color = $_POST['color'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name)) {
                throw new Exception("Leave type name is required.");
            }
              // Check if name already exists
            $check_sql = "SELECT id FROM leave_types WHERE name = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$name]);
            if ($check_stmt->rowCount() > 0) {
                throw new Exception("Leave type name already exists.");
            }
              $sql = "INSERT INTO leave_types (name, description, days_allowed, color, is_active) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);            
            if ($stmt->execute([$name, $description, $default_days, $color, $is_active])) {
                $_SESSION['success_message'] = "Leave type added successfully!";
            } else {
                throw new Exception("Failed to add leave type.");
            }
            
        } elseif (isset($_POST['edit_type'])) {
            // Edit existing leave type
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $default_days = intval($_POST['default_days']);
            $color = $_POST['color'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name)) {
                throw new Exception("Leave type name is required.");
            }
              // Check if name already exists (excluding current record)
            $check_sql = "SELECT id FROM leave_types WHERE name = ? AND id != ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$name, $id]);
            if ($check_stmt->rowCount() > 0) {
                throw new Exception("Leave type name already exists.");
            }
              $sql = "UPDATE leave_types SET name = ?, description = ?, days_allowed = ?, color = ?, is_active = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);            
            if ($stmt->execute([$name, $description, $default_days, $color, $is_active, $id])) {
                $_SESSION['success_message'] = "Leave type updated successfully!";
            } else {
                throw new Exception("Failed to update leave type.");
            }
            
        } elseif (isset($_POST['delete_type'])) {
            // Delete leave type (soft delete by setting is_active = 0)
            $id = intval($_POST['id']);
              // Check if leave type is used in any requests
            $check_sql = "SELECT COUNT(*) as count FROM leave_requests WHERE leave_type_id = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$id]);
            $result = $check_stmt->fetch();
            
            if ($result['count'] > 0) {
                throw new Exception("Cannot delete leave type. It is used in " . $result['count'] . " leave request(s). You can deactivate it instead.");
            }
            
            $sql = "DELETE FROM leave_types WHERE id = ?";
            $stmt = $pdo->prepare($sql);            
            if ($stmt->execute([$id])) {
                $_SESSION['success_message'] = "Leave type deleted successfully!";
            } else {
                throw new Exception("Failed to delete leave type.");
            }
        }
        
        header("Location: types.php");
        exit();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get all leave types
$sql = "SELECT lt.*, 
    COALESCE(COUNT(lr.id), 0) as usage_count,
    COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END), 0) as total_days_used
    FROM leave_types lt
    LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id
    GROUP BY lt.id
    ORDER BY lt.name";
$leave_types_result = $pdo->query($sql);
$leave_types = $leave_types_result->fetchAll();

include '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-cog me-2"></i>Leave Types</h1>
        </div>
        <?php
            $leaveToolbarIsAdmin = true;
            $leaveToolbarAddButton = [
                'type' => 'link',
                'label' => 'Add Leave Type',
                'icon' => 'fas fa-plus',
                'classes' => 'btn btn-primary',
                'url' => '#addLeaveTypeForm',
                'attributes' => [
                    'onclick' => "const form=document.getElementById('addLeaveTypeForm'); if(form){ form.scrollIntoView({behavior:'smooth'});} return false;",
                ],
                'page' => 'types.php',
            ];
            $leaveToolbarInline = true;
            include __DIR__ . '/partials/action-toolbar.php';
        ?>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <i class="fas fa-check me-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

            <div class="row">                <!-- Add New Leave Type -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-plus me-2"></i>Add Leave Type</h6>
                        </div>
                        <form method="POST" id="addLeaveTypeForm">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           placeholder="e.g., Annual Leave" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="3" placeholder="Brief description of this leave type..."></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="default_days" class="form-label">Days Allowed per Year <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="default_days" name="default_days" 
                                           min="0" max="365" value="20" required>
                                    <small class="form-text text-muted">Number of days allocated per year for this leave type.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="color" class="form-label">Color</label>
                                    <input type="color" class="form-control" id="color" name="color" 
                                           value="#007bff" style="height: 40px;">
                                    <small class="form-text text-muted">Color used in calendar and charts.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                </div>
                                
                                <button type="submit" name="add_type" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-1"></i>Add Leave Type
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Leave Types List -->
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-list me-2"></i>Existing Leave Types</h6>
                        </div>                        <div class="card-body">
                            <?php if (count($leave_types) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="leaveTypesTable" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Default Days</th>
                                                <th>Usage</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($leave_types as $type): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="color-indicator me-2" 
                                                                 style="width: 20px; height: 20px; background-color: <?php echo $type['color']; ?>; border-radius: 50%;"></div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                                                                <?php if ($type['description']): ?>
                                                                    <br><small class="text-muted"><?php echo htmlspecialchars($type['description']); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $type['days_allowed']; ?> days</span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo isset($type['usage_count']) ? (int)$type['usage_count'] : 0; ?> requests<br>
                                                            <?php echo isset($type['total_days_used']) ? (int)$type['total_days_used'] : 0; ?> days used
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php if ($type['is_active']): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-info edit-btn" 
                                                                    data-id="<?php echo $type['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($type['name']); ?>"                                                                data-description="<?php echo htmlspecialchars($type['description']); ?>"
                                                                    data-days="<?php echo $type['days_allowed']; ?>"
                                                                    data-color="<?php echo $type['color']; ?>"
                                                                    data-active="<?php echo $type['is_active']; ?>"
                                                                    title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <?php if ((isset($type['usage_count']) ? (int)$type['usage_count'] : 0) == 0): ?>
                                                                <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                                        data-id="<?php echo $type['id']; ?>"
                                                                        data-name="<?php echo htmlspecialchars($type['name']); ?>"
                                                                        title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php endif; ?>                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-tags fa-3x mb-3"></i>
                                    <h5 class="text-muted">No Leave Types Found</h5>
                                    <p class="text-muted">Start by adding your first leave type.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Edit Leave Type Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Edit Leave Type</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editLeaveTypeForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="form-group">
                        <label for="edit_name">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                      <div class="form-group">
                        <label for="edit_default_days">Days Allowed per Year <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_default_days" name="default_days" 
                               min="0" max="365" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_color">Color</label>
                        <input type="color" class="form-control" id="edit_color" name="color" style="height: 40px;">
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="edit_is_active" name="is_active">
                            <label class="custom-control-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                </div>
                                <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_type" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Delete Leave Type</h4>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="delete_id">
                    <p>Are you sure you want to delete the leave type "<span id="delete_name"></span>"?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_type" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Robust init supporting Bootstrap 5 (no jQuery plugin) with jQuery fallback
(function() {
    function onReady(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
    onReady(function() {
        try {
            if (window.jQuery && $.fn.DataTable) {
                $('#leaveTypesTable').DataTable({
                    responsive: true,
                    autoWidth: false,
                    columnDefs: [{ orderable: false, targets: [4] }]
                });
            }
        } catch (e) { /* ignore */ }

        // Prepare Bootstrap 5 modals
        var editEl = document.getElementById('editModal');
        var delEl = document.getElementById('deleteModal');
        var bsEdit = (window.bootstrap && editEl) ? new bootstrap.Modal(editEl) : null;
        var bsDel = (window.bootstrap && delEl) ? new bootstrap.Modal(delEl) : null;

        // Delegated click handlers (works after DataTables redraws)
        document.addEventListener('click', function(ev){
            var btn = ev.target.closest('.edit-btn');
            if (!btn) return;
            // Populate form fields
            document.getElementById('edit_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_name').value = btn.getAttribute('data-name') || '';
            document.getElementById('edit_description').value = btn.getAttribute('data-description') || '';
            document.getElementById('edit_default_days').value = btn.getAttribute('data-days') || '';
            document.getElementById('edit_color').value = btn.getAttribute('data-color') || '#007bff';
            document.getElementById('edit_is_active').checked = (btn.getAttribute('data-active') == '1');
            // Show modal
            if (bsEdit) bsEdit.show();
            else if (window.jQuery && $('#editModal').modal) { $('#editModal').modal('show'); }
        });

        document.addEventListener('click', function(ev){
            var btn = ev.target.closest('.delete-btn');
            if (!btn) return;
            document.getElementById('delete_id').value = btn.getAttribute('data-id');
            document.getElementById('delete_name').textContent = btn.getAttribute('data-name') || '';
            if (bsDel) bsDel.show();
            else if (window.jQuery && $('#deleteModal').modal) { $('#deleteModal').modal('show'); }
        });

        // Form validation (works with/without jQuery)
        function attachValidation(form){
            if (!form) return;
            form.addEventListener('submit', function(e){
                var name = (form.querySelector('input[name="name"]').value || '').trim();
                var days = parseInt(form.querySelector('input[name="default_days"]').value || '0', 10);
                if (name.length < 2) { e.preventDefault(); alert('Leave type name must be at least 2 characters long.'); return false; }
                if (isNaN(days) || days < 0 || days > 365) { e.preventDefault(); alert('Default days must be between 0 and 365.'); return false; }
            });
        }
        attachValidation(document.getElementById('addLeaveTypeForm'));
        attachValidation(document.getElementById('editLeaveTypeForm'));
    });
})();
</script>

<?php include '../../includes/footer.php'; ?>

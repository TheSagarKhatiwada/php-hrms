<?php
/**
 * Salary Components Management
 * Manage earnings and deductions types
 */
$page = 'salary-components';
require_once '../../includes/header.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/csrf_protection.php';

// Check permissions
if (!is_logged_in() || !is_admin()) {
    redirect_with_message('../../index.php', 'error', 'You do not have permission to access this page.');
}

$csrf_token = generate_csrf_token();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect_with_message('components.php', 'error', 'Invalid security token.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'earning';
        $is_taxable = isset($_POST['is_taxable']) ? 1 : 0;
        $status = $_POST['status'] ?? 'active';
        
        if (empty($name)) {
            set_flash_message('error', 'Component name is required.');
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO salary_components (name, type, is_taxable, status) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $type, $is_taxable, $status]);
                    log_activity($pdo, 'salary_component_created', "Created component: $name");
                    set_flash_message('success', 'Component added successfully.');
                } else {
                    $id = (int)$_POST['id'];
                    $stmt = $pdo->prepare("UPDATE salary_components SET name = ?, type = ?, is_taxable = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $type, $is_taxable, $status, $id]);
                    log_activity($pdo, 'salary_component_updated', "Updated component ID: $id");
                    set_flash_message('success', 'Component updated successfully.');
                }
                header('Location: components.php');
                exit();
            } catch (PDOException $e) {
                error_log("Salary component error: " . $e->getMessage());
                set_flash_message('error', 'Database error occurred.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            // Check usage
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employee_salary_structures WHERE component_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                set_flash_message('error', 'Cannot delete component in use by employees.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM salary_components WHERE id = ?");
                $stmt->execute([$id]);
                log_activity($pdo, 'salary_component_deleted', "Deleted component ID: $id");
                set_flash_message('success', 'Component deleted successfully.');
            }
        } catch (PDOException $e) {
            set_flash_message('error', 'Error deleting component.');
        }
        header('Location: components.php');
        exit();
    }
}

// Fetch components
$components = $pdo->query("SELECT * FROM salary_components ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fs-2 fw-bold mb-1">Salary Components</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#componentModal" onclick="resetForm()">
            <i class="fas fa-plus me-2"></i> Add Component
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th class="text-center">Taxable</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($components as $comp): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($comp['name']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $comp['type'] === 'earning' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($comp['type']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($comp['is_taxable']): ?>
                                    <i class="fas fa-check text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-muted"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $comp['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($comp['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary me-1" 
                                    onclick='editComponent(<?php echo json_encode($comp); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteComponent(<?php echo $comp['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($components)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No components defined yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="componentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="componentId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Component</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="compName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" id="compType" class="form-select">
                            <option value="earning">Earning</option>
                            <option value="deduction">Deduction</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_taxable" id="compTaxable" class="form-check-input" checked>
                        <label class="form-check-label">Is Taxable?</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="compStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="post" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('componentId').value = '';
    document.getElementById('compName').value = '';
    document.getElementById('compType').value = 'earning';
    document.getElementById('compTaxable').checked = true;
    document.getElementById('compStatus').value = 'active';
    document.getElementById('modalTitle').innerText = 'Add Component';
}

function editComponent(comp) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('componentId').value = comp.id;
    document.getElementById('compName').value = comp.name;
    document.getElementById('compType').value = comp.type;
    document.getElementById('compTaxable').checked = comp.is_taxable == 1;
    document.getElementById('compStatus').value = comp.status;
    document.getElementById('modalTitle').innerText = 'Edit Component';
    
    new bootstrap.Modal(document.getElementById('componentModal')).show();
}

function deleteComponent(id) {
    if (confirm('Are you sure you want to delete this component?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>

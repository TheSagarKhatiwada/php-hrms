<?php
/**
 * Hierarchy Setup Wizard
 * Helps administrators set up the organizational hierarchy
 */
$page = 'hierarchy-setup';

require_once 'includes/header.php';
require_once 'includes/db_connection.php';
require_once 'includes/utilities.php';
require_once 'includes/hierarchy_helpers.php';

// Check if user is admin
if (!is_admin()) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: dashboard.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'apply_migration') {
        try {
            $pdo->beginTransaction();
            
            // Add supervisor_id column if it doesn't exist
            $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'supervisor_id'");
            if ($stmt->rowCount() == 0) {
                // Use VARCHAR(20) to match employees.emp_id schema
                $pdo->exec("ALTER TABLE employees ADD COLUMN supervisor_id VARCHAR(20) NULL AFTER role_id");
                $pdo->exec("CREATE INDEX idx_employees_supervisor ON employees(supervisor_id)");
                // Attempt to add a foreign key to employees(emp_id) if possible
                try {
                    $pdo->exec("ALTER TABLE employees ADD CONSTRAINT employees_supervisor_fk FOREIGN KEY (supervisor_id) REFERENCES employees(emp_id) ON DELETE SET NULL");
                } catch (Exception $fkEx) {
                    // Proceed without FK if it fails due to existing data or engine constraints
                }
            }
            
            // Add department_id column if it doesn't exist
            $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'department_id'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE employees ADD COLUMN department_id INT(11) NULL AFTER designation");
                $pdo->exec("ALTER TABLE employees ADD CONSTRAINT employees_department_fk FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL");
                $pdo->exec("CREATE INDEX idx_employees_department ON employees(department_id)");
            }
            
            $pdo->commit();
            $_SESSION['success'] = 'Database migration completed successfully!';
        } catch (Exception $e) {
            $pdo->rollback();
            $_SESSION['error'] = 'Migration failed: ' . $e->getMessage();
        }
    }
    
    if ($action === 'bulk_assign') {
        $assignments = $_POST['assignments'] ?? [];
        $success_count = 0;
        $error_count = 0;
        
        foreach ($assignments as $emp_id => $supervisor_id) {
            $supervisor_id = !empty($supervisor_id) ? $supervisor_id : null;
            
            try {
                // Validate assignment to prevent circular hierarchy
                if ($supervisor_id && !canSupervise($pdo, $supervisor_id, $emp_id)) {
                    $error_count++;
                    continue;
                }
                
                $stmt = $pdo->prepare("UPDATE employees SET supervisor_id = ? WHERE emp_id = ?");
                $stmt->execute([$supervisor_id, $emp_id]);
                $success_count++;
            } catch (Exception $e) {
                $error_count++;
            }
        }
        
        $_SESSION['success'] = "Updated {$success_count} employee assignments.";
        if ($error_count > 0) {
            $_SESSION['warning'] = "{$error_count} assignments failed due to validation errors.";
        }
    }
    
    header('Location: hierarchy-setup.php');
    exit();
}

// Check if migration is needed
$migration_needed = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'supervisor_id'");
    $migration_needed = ($stmt->rowCount() == 0);
} catch (Exception $e) {
    $migration_needed = true;
}

// Get employees for assignment
$employees = [];
if (!$migration_needed) {
    $stmt = $pdo->query("
          SELECT e.*, d.title as designation_title, dept.name as department_name,
              s.first_name as supervisor_first_name, s.middle_name as supervisor_middle_name, s.last_name as supervisor_last_name
        FROM employees e
        LEFT JOIN designations d ON e.designation_id = d.id
    LEFT JOIN departments dept ON e.department_id = dept.id
    LEFT JOIN employees s ON e.supervisor_id = s.emp_id
    WHERE (e.exit_date IS NULL OR e.exit_date = '0000-00-00' OR e.exit_date = '')
        ORDER BY dept.name, e.first_name, e.last_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get potential supervisors
$potential_supervisors = [];
if (!$migration_needed) {
    $stmt = $pdo->query("
                SELECT emp_id, CONCAT(CONCAT_WS(' ', first_name, middle_name, last_name), ' (', emp_id, ')') as full_name
        FROM employees 
        WHERE (exit_date IS NULL OR exit_date = '0000-00-00' OR exit_date = '')
        ORDER BY first_name, last_name
    ");
    $potential_supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">
                <i class="fas fa-cogs me-2"></i>Hierarchy Setup Wizard
            </h1>
            <p class="text-muted mb-0">Configure your organizational hierarchy and reporting structure</p>
        </div>
        <a href="organizational-chart.php" class="btn btn-outline-primary">
            <i class="fas fa-sitemap me-1"></i>View Org Chart
        </a>
    </div>

    <?php if ($migration_needed): ?>
    <!-- Migration Required -->
    <div class="card border-warning">
        <div class="card-header bg-warning text-dark">
            <h5 class="card-title mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>Database Migration Required
            </h5>
        </div>
        <div class="card-body">
            <p class="mb-3">
                Your database needs to be updated to support organizational hierarchy features. 
                This migration will add the necessary fields to track supervisor relationships.
            </p>
            
            <div class="alert alert-info mb-3">
                <h6><i class="fas fa-info-circle me-2"></i>What will be added:</h6>
                <ul class="mb-0">
                    <li><code>supervisor_id</code> field to employees table</li>
                    <li><code>department_id</code> field to employees table (proper foreign key)</li>
                    <li>Database indexes for better performance</li>
                    <li>Foreign key constraints for data integrity</li>
                </ul>
            </div>
            
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="apply_migration">
                <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to apply the database migration?')">
                    <i class="fas fa-database me-2"></i>Apply Migration
                </button>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Hierarchy Setup -->
    <div class="row">
        <!-- Instructions -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-lightbulb me-2"></i>Setup Instructions
                    </h5>
                </div>
                <div class="card-body">
                    <ol class="list-unstyled">
                        <li class="mb-3">
                            <span class="badge bg-primary rounded-pill me-2">1</span>
                            <strong>Review Employees</strong>
                            <p class="text-muted small mb-0">Check the list of all active employees below.</p>
                        </li>
                        <li class="mb-3">
                            <span class="badge bg-primary rounded-pill me-2">2</span>
                            <strong>Assign Supervisors</strong>
                            <p class="text-muted small mb-0">Select the direct supervisor for each employee.</p>
                        </li>
                        <li class="mb-3">
                            <span class="badge bg-primary rounded-pill me-2">3</span>
                            <strong>Save Changes</strong>
                            <p class="text-muted small mb-0">Apply all assignments at once.</p>
                        </li>
                        <li class="mb-0">
                            <span class="badge bg-success rounded-pill me-2">4</span>
                            <strong>View Org Chart</strong>
                            <p class="text-muted small mb-0">Check the organizational chart to verify the structure.</p>
                        </li>
                    </ol>
                    
                    <div class="alert alert-warning mt-4">
                        <small><i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Note:</strong> Top-level employees (like CEO, President) should not have a supervisor assigned.</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Employee Assignment -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>Employee Supervisor Assignment
                    </h5>
                    <small class="text-muted"><?php echo count($employees); ?> employees</small>
                </div>
                <div class="card-body">
                    <form method="POST" id="hierarchy-form">
                        <input type="hidden" name="action" value="bulk_assign">
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Current Supervisor</th>
                                        <th>Assign Supervisor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo $employee['user_image'] ?: 'resources/userimg/default-image.jpg'; ?>" 
                                                     alt="Profile" class="rounded-circle me-3" width="40" height="40">
                                                <div>
                                                    <strong><?php echo htmlspecialchars(trim($employee['first_name'] . ' ' . ($employee['middle_name'] ?? '') . ' ' . $employee['last_name'])); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($employee['emp_id']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($employee['department_name'] ?: 'Not Assigned'); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($employee['designation_title'] ?: 'No Designation'); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($employee['supervisor_id']): ?>
                                                <span class="text-success">
                                                    <i class="fas fa-user-tie me-1"></i>
                                                    <?php echo htmlspecialchars(trim(($employee['supervisor_first_name'] ?? '') . ' ' . ($employee['supervisor_middle_name'] ?? '') . ' ' . ($employee['supervisor_last_name'] ?? ''))); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-crown me-1"></i>Top Level
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                        <select name="assignments[<?php echo $employee['emp_id']; ?>]" class="form-select form-select-sm">
                                                <option value="">-- No Supervisor (Top Level) --</option>
                                                <?php foreach ($potential_supervisors as $supervisor): ?>
                            <?php if ($supervisor['emp_id'] != $employee['emp_id']): // Can't supervise self ?>
                            <option value="<?php echo $supervisor['emp_id']; ?>" 
                                <?php echo ($supervisor['emp_id'] == $employee['supervisor_id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($supervisor['full_name']); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save All Assignments
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <a href="employees.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-users mb-2 d-block"></i>
                                Manage Employees
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="departments.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-building mb-2 d-block"></i>
                                Manage Departments
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="designations.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-id-badge mb-2 d-block"></i>
                                Manage Designations
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add validation to prevent circular hierarchy
document.getElementById('hierarchy-form')?.addEventListener('submit', function(e) {
    // Add validation logic here if needed
    const formData = new FormData(this);
    // You can add client-side validation here
});

// Auto-save functionality (optional)
function autoSave() {
    // Implement auto-save if needed
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

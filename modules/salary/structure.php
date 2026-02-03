<?php
/**
 * Employee Salary Structure
 * Assign components to employees
 */
$page = 'salary-structure';
require_once '../../includes/header.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/csrf_protection.php';

if (!is_logged_in() || !is_admin()) {
    redirect_with_message('../../index.php', 'error', 'Permission denied.');
}

$csrf_token = generate_csrf_token();
$employee_id = $_GET['emp_id'] ?? '';

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect_with_message("structure.php?emp_id=$employee_id", 'error', 'Invalid token.');
    }

    $emp_id = $_POST['employee_id'];
    $effective_date = $_POST['effective_date'];
    $components = $_POST['components'] ?? [];

    try {
        $pdo->beginTransaction();

        // Clear existing structure for this employee (simple approach: delete all and re-insert)
        // In a more complex system, we might version these instead of overwriting.
        $stmt = $pdo->prepare("DELETE FROM employee_salary_structures WHERE employee_id = ?");
        $stmt->execute([$emp_id]);

        $insertStmt = $pdo->prepare("INSERT INTO employee_salary_structures (employee_id, component_id, amount, effective_date) VALUES (?, ?, ?, ?)");

        foreach ($components as $comp_id => $amount) {
            if ($amount > 0) {
                $insertStmt->execute([$emp_id, $comp_id, $amount, $effective_date]);
            }
        }

        $pdo->commit();
        log_activity($pdo, 'salary_structure_updated', "Updated salary structure for Employee: $emp_id");
        set_flash_message('success', 'Salary structure saved successfully.');
        header("Location: structure.php?emp_id=$emp_id");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Salary structure error: " . $e->getMessage());
        set_flash_message('error', 'Database error occurred.');
    }
}

// Fetch Employees
$employees = $pdo->query("SELECT emp_id, first_name, last_name FROM employees WHERE status='active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Components
$salary_components = $pdo->query("SELECT * FROM salary_components WHERE status='active' ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Current Structure if employee selected
$current_structure = [];
if ($employee_id) {
    $stmt = $pdo->prepare("SELECT component_id, amount, effective_date FROM employee_salary_structures WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $current_structure[$row['component_id']] = $row['amount'];
        // Just take the first effective date found for display
        if (!isset($effective_date_val)) $effective_date_val = $row['effective_date'];
    }
}
?>

<div class="container-fluid p-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Select Employee</div>
                <div class="card-body">
                    <form method="get">
                        <select name="emp_id" class="form-select mb-3" onchange="this.form.submit()">
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['emp_id']; ?>" <?php echo $employee_id === $emp['emp_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?> (<?php echo $emp['emp_id']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <?php if ($employee_id): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span>Salary Structure</span>
                    <span class="badge bg-info text-dark">Monthly Values</span>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee_id); ?>">
                        
                        <div class="mb-4">
                            <label class="form-label">Effective Date</label>
                            <input type="date" name="effective_date" class="form-control w-auto" 
                                value="<?php echo $effective_date_val ?? date('Y-m-d'); ?>" required>
                        </div>

                        <h6 class="text-success border-bottom pb-2 mb-3">Earnings</h6>
                        <?php foreach ($salary_components as $comp): ?>
                            <?php if ($comp['type'] === 'earning'): ?>
                            <div class="row mb-3 align-items-center">
                                <label class="col-sm-4 col-form-label"><?php echo htmlspecialchars($comp['name']); ?></label>
                                <div class="col-sm-6">
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" step="0.01" name="components[<?php echo $comp['id']; ?>]" 
                                            class="form-control earning-input" 
                                            value="<?php echo $current_structure[$comp['id']] ?? ''; ?>" 
                                            placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <h6 class="text-danger border-bottom pb-2 mb-3 mt-4">Deductions</h6>
                        <?php foreach ($salary_components as $comp): ?>
                            <?php if ($comp['type'] === 'deduction'): ?>
                            <div class="row mb-3 align-items-center">
                                <label class="col-sm-4 col-form-label"><?php echo htmlspecialchars($comp['name']); ?></label>
                                <div class="col-sm-6">
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" step="0.01" name="components[<?php echo $comp['id']; ?>]" 
                                            class="form-control deduction-input" 
                                            value="<?php echo $current_structure[$comp['id']] ?? ''; ?>" 
                                            placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="alert alert-light border mt-4">
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Total Earnings:</span>
                                <span id="totalEarnings">0.00</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold text-danger">
                                <span>Total Deductions:</span>
                                <span id="totalDeductions">0.00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold fs-5">
                                <span>Net Salary:</span>
                                <span id="netSalary">0.00</span>
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-4">Save Structure</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
                <div class="alert alert-info">Please select an employee to view or edit their salary structure.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calculateTotals = () => {
        let earnings = 0;
        let deductions = 0;

        document.querySelectorAll('.earning-input').forEach(input => {
            earnings += parseFloat(input.value || 0);
        });

        document.querySelectorAll('.deduction-input').forEach(input => {
            deductions += parseFloat(input.value || 0);
        });

        document.getElementById('totalEarnings').innerText = earnings.toFixed(2);
        document.getElementById('totalDeductions').innerText = deductions.toFixed(2);
        document.getElementById('netSalary').innerText = (earnings - deductions).toFixed(2);
    };

    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', calculateTotals);
    });

    calculateTotals();
});
</script>

<?php require_once '../../includes/footer.php'; ?>

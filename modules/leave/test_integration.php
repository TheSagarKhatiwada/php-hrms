<?php
/**
 * Test Leave System Integration
 * Creates sample data and tests the accrual system
 */

require_once '../../includes/db_connection.php';
require_once '../../includes/session_config.php';
require_once 'accrual.php';

// Check admin access
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'hr'])) {
    header('Location: ../../dashboard.php');
    exit();
}

$test_results = [];

if ($_POST && isset($_POST['run_tests'])) {
    try {
        // Test 1: Check if employees exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE exit_date IS NULL");
        $active_employees = $stmt->fetchColumn();
        $test_results['employees'] = $active_employees;
        
        // Test 2: Check if leave types exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM leave_types WHERE is_active = 1");
        $active_leave_types = $stmt->fetchColumn();
        $test_results['leave_types'] = $active_leave_types;
        
        // Test 3: Check existing accruals
        $stmt = $pdo->query("SELECT COUNT(*) FROM leave_accruals WHERE accrual_year = YEAR(CURDATE())");
        $existing_accruals = $stmt->fetchColumn();
        $test_results['existing_accruals'] = $existing_accruals;
        
        // Test 4: Check leave balances
        $stmt = $pdo->query("SELECT COUNT(*) FROM leave_balances WHERE year = YEAR(CURDATE())");
        $leave_balances = $stmt->fetchColumn();
        $test_results['leave_balances'] = $leave_balances;
        
        // Test 5: Sample balance calculation for first employee
        $stmt = $pdo->query("SELECT id FROM employees WHERE exit_date IS NULL LIMIT 1");
        $first_employee = $stmt->fetchColumn();
        
        if ($first_employee) {
            $balance = getEmployeeLeaveBalance($first_employee);
            $test_results['sample_balance'] = $balance;
        }
        
        // Test 6: Try a sample balance check
        if ($first_employee && $active_leave_types > 0) {
            $stmt = $pdo->query("SELECT id FROM leave_types WHERE is_active = 1 LIMIT 1");
            $first_leave_type = $stmt->fetchColumn();
            
            if ($first_leave_type) {
                $balance_check = checkLeaveBalance($first_employee, $first_leave_type, 5);
                $test_results['balance_check'] = $balance_check;
            }
        }
        
    } catch (Exception $e) {
        $error_message = "Test failed: " . $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">
                <i class="fas fa-vial text-primary me-2"></i>Test Leave System
            </h1>
            <p class="text-muted mb-0">Test the integrated leave accrual system</p>
        </div>
        <div class="d-flex gap-2">
            <a href="setup_accruals.php" class="btn btn-outline-success">
                <i class="fas fa-rocket me-1"></i>Setup Accruals
            </a>
            <a href="accrual.php" class="btn btn-outline-primary">
                <i class="fas fa-coins me-1"></i>Accrual Management
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($test_results)): ?>
        <!-- Test Results -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header py-3">
                        <h6 class="m-0 fw-bold text-success">
                            <i class="fas fa-check-circle me-2"></i>System Test Results
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Database Status</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-users me-2 text-info"></i>
                                        <strong>Active Employees:</strong> 
                                        <span class="badge bg-primary"><?= $test_results['employees'] ?></span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-list me-2 text-info"></i>
                                        <strong>Active Leave Types:</strong> 
                                        <span class="badge bg-primary"><?= $test_results['leave_types'] ?></span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-calendar me-2 text-info"></i>
                                        <strong>Existing Accruals:</strong> 
                                        <span class="badge bg-<?= $test_results['existing_accruals'] > 0 ? 'success' : 'warning' ?>">
                                            <?= $test_results['existing_accruals'] ?>
                                        </span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-chart-pie me-2 text-info"></i>
                                        <strong>Leave Balances:</strong> 
                                        <span class="badge bg-<?= $test_results['leave_balances'] > 0 ? 'success' : 'warning' ?>">
                                            <?= $test_results['leave_balances'] ?>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary">Integration Test</h6>
                                <?php if (isset($test_results['sample_balance'])): ?>
                                    <p class="small text-muted">Sample employee balance data:</p>
                                    <div class="border rounded p-2 mb-3" style="font-size: 0.8rem; background-color: #f8f9fa;">
                                        <pre><?= htmlspecialchars(json_encode($test_results['sample_balance'], JSON_PRETTY_PRINT)) ?></pre>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($test_results['balance_check'])): ?>
                                    <p class="small text-muted">Balance check result (requesting 5 days):</p>
                                    <div class="alert alert-<?= $test_results['balance_check']['can_apply'] ? 'success' : 'warning' ?> py-2">
                                        <i class="fas fa-<?= $test_results['balance_check']['can_apply'] ? 'check' : 'exclamation-triangle' ?> me-2"></i>
                                        <?= htmlspecialchars($test_results['balance_check']['message']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header py-3">
                        <h6 class="m-0 fw-bold text-primary">
                            <i class="fas fa-lightbulb me-2"></i>Next Steps
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($test_results['existing_accruals'] == 0): ?>
                            <div class="alert alert-warning py-2 mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No accruals found. Run the accrual setup first.
                            </div>
                            <a href="setup_accruals.php" class="btn btn-success btn-sm d-block mb-2">
                                <i class="fas fa-rocket me-1"></i>Setup Accruals
                            </a>
                        <?php else: ?>
                            <div class="alert alert-success py-2 mb-3">
                                <i class="fas fa-check me-2"></i>
                                Accrual system is ready!
                            </div>
                        <?php endif; ?>
                        
                        <a href="request.php" class="btn btn-primary btn-sm d-block mb-2">
                            <i class="fas fa-plus me-1"></i>Test Leave Request
                        </a>
                        <a href="accrual.php" class="btn btn-outline-primary btn-sm d-block">
                            <i class="fas fa-coins me-1"></i>Manage Accruals
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Test Form -->
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header py-3">
                        <h6 class="m-0 fw-bold text-primary">
                            <i class="fas fa-play me-2"></i>Run System Tests
                        </h6>
                    </div>
                    <div class="card-body text-center">
                        <p class="text-muted">
                            Run comprehensive tests to verify the leave accrual system integration.
                        </p>
                        <form method="POST">
                            <button type="submit" name="run_tests" class="btn btn-primary btn-lg">
                                <i class="fas fa-vial me-2"></i>Run Tests
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Information Panel -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>System Integration Status</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-primary">✅ Completed</h6>
                            <ul class="small">
                                <li>Accrual system implementation</li>
                                <li>Balance validation in request form</li>
                                <li>Dynamic balance display</li>
                                <li>Navigation integration</li>
                                <li>Admin interface for accrual management</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-primary">🔄 In Progress</h6>
                            <ul class="small">
                                <li>Testing with real employee data</li>
                                <li>Monthly accrual processing</li>
                                <li>Balance recalculation verification</li>
                                <li>UI/UX refinements</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-primary">📋 Next Steps</h6>
                            <ul class="small">
                                <li>Set up automated monthly processing</li>
                                <li>Create user documentation</li>
                                <li>Performance optimization</li>
                                <li>Backup and maintenance procedures</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

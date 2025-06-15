<?php
/**
 * Leave Accrual Setup Script
 * This script initializes leave accruals for all employees from January to current month
 */

require_once '../../includes/db_connection.php';
require_once '../../includes/session_config.php';
require_once 'accrual.php';

// Check admin access
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'hr'])) {
    header('Location: ../../dashboard.php');
    exit();
}

$setup_complete = false;
$results = [];

if ($_POST && isset($_POST['initialize_accruals'])) {
    try {
        $year = (int)($_POST['year'] ?? date('Y'));
        $current_month = (int)date('n');
        
        $pdo->beginTransaction();
        
        // Initialize accruals from January to current month
        for ($month = 1; $month <= $current_month; $month++) {
            $result = processMonthlyAccrual($month, $year);
            $results[] = [
                'month' => $month,
                'year' => $year,
                'processed_count' => $result['processed_count'],
                'errors' => $result['errors']
            ];
        }
        
        $pdo->commit();
        $setup_complete = true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Setup failed: " . $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">
                <i class="fas fa-rocket text-primary me-2"></i>Leave Accrual Setup
            </h1>
            <p class="text-muted mb-0">Initialize leave accruals for all employees</p>
        </div>
        <div class="d-flex gap-2">
            <a href="accrual.php" class="btn btn-outline-primary">
                <i class="fas fa-coins me-1"></i>Accrual Management
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($setup_complete): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>Accrual setup completed successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <!-- Results Summary -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 fw-bold text-success">
                    <i class="fas fa-chart-line me-2"></i>Setup Results
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Year</th>
                                <th>Processed Count</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr>
                                    <td><?= date('F', mktime(0, 0, 0, $result['month'], 1)) ?></td>
                                    <td><?= $result['year'] ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= $result['processed_count'] ?> accruals</span>
                                    </td>
                                    <td>
                                        <?php if (empty($result['errors'])): ?>
                                            <span class="badge bg-success">Success</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">With Errors</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="accrual.php" class="btn btn-primary">
                <i class="fas fa-coins me-1"></i>Go to Accrual Management
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-tachometer-alt me-1"></i>View Dashboard
            </a>
        </div>

    <?php else: ?>
        <!-- Setup Form -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header py-3">
                        <h6 class="m-0 fw-bold text-primary">
                            <i class="fas fa-cogs me-2"></i>Initialize Leave Accruals
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>About This Setup:</strong>
                            <ul class="mb-0 mt-2">
                                <li>This will process leave accruals from January to the current month (<?= date('F Y') ?>)</li>
                                <li>Each employee will earn their monthly leave allocation based on their hire date</li>
                                <li>Pro-rata calculations will be applied for employees who joined mid-year</li>
                                <li>Only leave types marked as 'annual' and 'casual' will be processed</li>
                            </ul>
                        </div>

                        <form method="POST">
                            <div class="mb-4">
                                <label for="year" class="form-label">Year to Initialize</label>
                                <select class="form-select" id="year" name="year" required>
                                    <option value="<?= date('Y') ?>" selected><?= date('Y') ?> (Current Year)</option>
                                    <option value="<?= date('Y') - 1 ?>"><?= date('Y') - 1 ?></option>
                                </select>
                                <div class="form-text">
                                    Select the year for which to initialize accruals. Usually the current year.
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="initialize_accruals" class="btn btn-primary btn-lg">
                                    <i class="fas fa-rocket me-2"></i>Initialize Leave Accruals
                                </button>
                            </div>
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
                    <h6 class="mb-3"><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">What happens during initialization?</h6>
                            <p class="small text-muted">
                                The system will create accrual records for each active employee for each eligible leave type, 
                                calculating the correct monthly earning amount based on their hire date and the leave type's annual allocation.
                            </p>
                            
                            <h6 class="text-primary">Is it safe to run multiple times?</h6>
                            <p class="small text-muted">
                                Yes, the system checks for existing accrual records and will not create duplicates. 
                                However, it's recommended to run this only once during initial setup.
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">What about employees who join later?</h6>
                            <p class="small text-muted">
                                New employees will have their accruals processed automatically during the next monthly accrual run. 
                                You can also run manual accruals from the Accrual Management page.
                            </p>
                            
                            <h6 class="text-primary">How to process future months?</h6>
                            <p class="small text-muted">
                                Use the Accrual Management page to process monthly accruals going forward. 
                                This can be automated with a cron job or done manually each month.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<?php
/**
 * Leave Accrual System
 * Handles monthly leave earning calculations and balance updates
 * 
 * This system implements progressive leave earning where employees earn
 * leave days gradually throughout the year instead of getting full allocation upfront
 */

require_once '../../includes/db_connection.php';
require_once '../../includes/session_config.php';
require_once 'config.php';

// Check if user has admin access for manual runs
// if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'hr'])) {
//     if (!defined('CRON_RUN')) {
//         header('Location: ../../dashboard.php');
//         exit();
//     }
// }

/**
 * Calculate monthly accrual for an employee and leave type
 */
function calculateMonthlyAccrual($employee_id, $leave_type_id, $total_annual_days, $month = null, $year = null) {
    global $pdo;
    
    $month = $month ?: date('n'); // Current month if not specified
    $year = $year ?: date('Y');   // Current year if not specified
      // Get employee start date to determine pro-rata calculation
    $stmt = $pdo->prepare("SELECT join_date FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        return 0;
    }

    $join_date = new DateTime($employee['join_date']);
    $current_date = new DateTime("$year-$month-01");
    
    // If employee joined after current month, no accrual
    if ($join_date > $current_date) {
        return 0;
    }
    
    // Calculate basic monthly accrual (annual days / 12)
    $monthly_accrual = round($total_annual_days / 12, 2);
    
    // Pro-rata calculation for joining month
    $join_month = (int)$join_date->format('n');
    $join_year = (int)$join_date->format('Y');

    if ($join_year == $year && $join_month == $month) {
        // Calculate pro-rata for partial month
        $days_in_month = (int)$current_date->format('t');
        $days_worked = $days_in_month - (int)$join_date->format('j') + 1;
        $monthly_accrual = round(($total_annual_days / 12) * ($days_worked / $days_in_month), 2);
    }
    
    return $monthly_accrual;
}

/**
 * Process monthly accrual for all employees
 */
function processMonthlyAccrual($month = null, $year = null) {
    global $pdo;
    
    $month = $month ?: date('n');
    $year = $year ?: date('Y');
    
    $processed_count = 0;
    $errors = [];
    
    try {
        $pdo->beginTransaction();
          // Get all active employees
        $stmt = $pdo->query("
            SELECT id, first_name, last_name, join_date, exit_date 
            FROM employees 
            WHERE exit_date IS NULL OR exit_date > CURDATE()
        ");
        $employees = $stmt->fetchAll();
        
        // Get all leave types that allow accrual
        $stmt = $pdo->query("
            SELECT id, name, days_allowed, code 
            FROM leave_types 
            WHERE is_active = 1 AND code IN ('annual', 'casual')
        ");
        $leave_types = $stmt->fetchAll();
        
        foreach ($employees as $employee) {
            foreach ($leave_types as $leave_type) {
                // Check if accrual already processed for this month
                $stmt = $pdo->prepare("
                    SELECT id FROM leave_accruals 
                    WHERE employee_id = ? AND leave_type_id = ? 
                    AND accrual_month = ? AND accrual_year = ?
                ");
                $stmt->execute([$employee['id'], $leave_type['id'], $month, $year]);
                
                if ($stmt->fetch()) {
                    continue; // Already processed
                }
                
                // Calculate monthly accrual
                $accrual_amount = calculateMonthlyAccrual(
                    $employee['id'], 
                    $leave_type['id'], 
                    $leave_type['days_allowed'],
                    $month,
                    $year
                );
                
                if ($accrual_amount > 0) {
                    // Insert accrual record
                    $stmt = $pdo->prepare("
                        INSERT INTO leave_accruals 
                        (employee_id, leave_type_id, accrual_month, accrual_year, 
                         accrued_days, processed_date, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $employee['id'], 
                        $leave_type['id'], 
                        $month, 
                        $year, 
                        $accrual_amount
                    ]);
                    
                    // Update leave balance
                    updateLeaveBalance($employee['id'], $leave_type['id'], $year);
                    
                    $processed_count++;
                }
            }
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "Error processing accruals: " . $e->getMessage();
    }
    
    return [
        'processed_count' => $processed_count,
        'errors' => $errors
    ];
}

/**
 * Update employee leave balance based on accruals and usage
 */
function updateLeaveBalance($employee_id, $leave_type_id, $year) {
    global $pdo;
    
    // Calculate total accrued days for the year
    $stmt = $pdo->prepare("
        SELECT SUM(accrued_days) as total_accrued 
        FROM leave_accruals 
        WHERE employee_id = ? AND leave_type_id = ? AND accrual_year = ?
    ");
    $stmt->execute([$employee_id, $leave_type_id, $year]);
    $accrual_data = $stmt->fetch();
    $total_accrued = $accrual_data['total_accrued'] ?: 0;
    
    // Calculate used days
    $stmt = $pdo->prepare("
        SELECT SUM(days_requested) as used_days 
        FROM leave_requests 
        WHERE employee_id = ? AND leave_type_id = ? 
        AND status = 'approved' AND YEAR(start_date) = ?
    ");
    $stmt->execute([$employee_id, $leave_type_id, $year]);
    $usage_data = $stmt->fetch();
    $used_days = $usage_data['used_days'] ?: 0;
    
    // Calculate pending days
    $stmt = $pdo->prepare("
        SELECT SUM(days_requested) as pending_days 
        FROM leave_requests 
        WHERE employee_id = ? AND leave_type_id = ? 
        AND status = 'pending' AND YEAR(start_date) = ?
    ");
    $stmt->execute([$employee_id, $leave_type_id, $year]);
    $pending_data = $stmt->fetch();
    $pending_days = $pending_data['pending_days'] ?: 0;
    
    $remaining_days = $total_accrued - $used_days;
    
    // Update or insert balance record
    $stmt = $pdo->prepare("
        INSERT INTO leave_balances 
        (employee_id, leave_type_id, year, allocated_days, used_days, pending_days, remaining_days, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
        allocated_days = VALUES(allocated_days),
        used_days = VALUES(used_days),
        pending_days = VALUES(pending_days),
        remaining_days = VALUES(remaining_days),
        updated_at = NOW()
    ");
    $stmt->execute([
        $employee_id, 
        $leave_type_id, 
        $year, 
        $total_accrued, 
        $used_days, 
        $pending_days, 
        $remaining_days
    ]);
}

/**
 * Get employee's current leave balance including accrued amounts
 */
function getEmployeeLeaveBalance($employee_id, $leave_type_id = null, $year = null) {
    global $pdo;
    
    $year = $year ?: date('Y');
    
    $where_clause = "WHERE lb.employee_id = ? AND lb.year = ?";
    $params = [$employee_id, $year];
    
    if ($leave_type_id) {
        $where_clause .= " AND lb.leave_type_id = ?";
        $params[] = $leave_type_id;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            lb.*,
            lt.name as leave_type_name,
            lt.color,
            lt.code,
            COALESCE(SUM(la.accrued_days), 0) as total_accrued_ytd
        FROM leave_balances lb
        JOIN leave_types lt ON lb.leave_type_id = lt.id
        LEFT JOIN leave_accruals la ON lb.employee_id = la.employee_id 
            AND lb.leave_type_id = la.leave_type_id 
            AND lb.year = la.accrual_year
            AND la.accrual_month <= MONTH(CURDATE())
        $where_clause
        GROUP BY lb.id, lt.name, lt.color, lt.code
        ORDER BY lt.name
    ");
    $stmt->execute($params);
    
    return $leave_type_id ? $stmt->fetch() : $stmt->fetchAll();
}

/**
 * Check if employee has sufficient balance for leave request
 */
function checkLeaveBalance($employee_id, $leave_type_id, $requested_days, $year = null) {
    $year = $year ?: date('Y');
    
    $balance = getEmployeeLeaveBalance($employee_id, $leave_type_id, $year);
    
    if (!$balance) {
        return [
            'can_apply' => false,
            'message' => 'No leave balance found for this leave type.',
            'available_days' => 0
        ];
    }
    
    $available_days = $balance['remaining_days'];
    
    if ($requested_days > $available_days) {
        return [
            'can_apply' => false,
            'message' => "Insufficient leave balance. Available: {$available_days} days, Requested: {$requested_days} days.",
            'available_days' => $available_days
        ];
    }
    
    return [
        'can_apply' => true,
        'message' => 'Sufficient balance available.',
        'available_days' => $available_days
    ];
}

// Handle manual execution (admin interface)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    switch ($_POST['action']) {
        case 'run_monthly_accrual':
            $month = (int)($_POST['month'] ?? date('n'));
            $year = (int)($_POST['year'] ?? date('Y'));
            
            $result = processMonthlyAccrual($month, $year);
            
            if (empty($result['errors'])) {
                $response['success'] = true;
                $response['message'] = "Monthly accrual processed successfully. {$result['processed_count']} records created.";
            } else {
                $response['message'] = 'Errors occurred: ' . implode(', ', $result['errors']);
            }
            break;
            
        case 'recalculate_balances':
            $year = (int)($_POST['year'] ?? date('Y'));
            
            try {
                // Get all employees and leave types
                $stmt = $pdo->query("SELECT id FROM employees WHERE exit_date IS NULL");
                $employees = $stmt->fetchAll();
                
                $stmt = $pdo->query("SELECT id FROM leave_types WHERE is_active = 1");
                $leave_types = $stmt->fetchAll();
                
                $updated_count = 0;
                
                foreach ($employees as $employee) {
                    foreach ($leave_types as $leave_type) {
                        updateLeaveBalance($employee['id'], $leave_type['id'], $year);
                        $updated_count++;
                    }
                }
                
                $response['success'] = true;
                $response['message'] = "Leave balances recalculated successfully. {$updated_count} records updated.";
                
            } catch (Exception $e) {
                $response['message'] = "Error recalculating balances: " . $e->getMessage();
            }
            break;
    }
    
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    } else {
        $_SESSION['accrual_message'] = $response['message'];
        $_SESSION['accrual_success'] = $response['success'];
        header('Location: accrual.php');
        exit();
    }
}

// If running as cron job, process current month
if (defined('CRON_RUN')) {
    $result = processMonthlyAccrual();
    echo "Monthly accrual processing completed.\n";
    echo "Records processed: {$result['processed_count']}\n";
    if (!empty($result['errors'])) {
        echo "Errors: " . implode("\n", $result['errors']) . "\n";
    }
    exit();
}

// Admin interface
$page = 'Leave Accrual Management';
include '../../includes/header.php';

// Get current month/year accrual status
$current_month = date('n');
$current_year = date('Y');

$stmt = $pdo->prepare("
    SELECT COUNT(*) as processed_count 
    FROM leave_accruals 
    WHERE accrual_month = ? AND accrual_year = ?
");
$stmt->execute([$current_month, $current_year]);
$current_month_status = $stmt->fetch();

// Get recent accrual history
$stmt = $pdo->prepare("
    SELECT 
        la.*,
        e.first_name, e.last_name,
        lt.name as leave_type_name
    FROM leave_accruals la
    JOIN employees e ON la.employee_id = e.emp_id
    JOIN leave_types lt ON la.leave_type_id = lt.id
    ORDER BY la.processed_date DESC
    LIMIT 20
");
$stmt->execute();
$recent_accruals = $stmt->fetchAll();
?>

<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">
                <i class="fas fa-calculator me-2"></i>Leave Accrual Management
            </h1>
            <p class="text-muted mb-0">Manage monthly leave earning and balance calculations</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-success">
                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
            </a>
            <a href="balance.php" class="btn btn-outline-info">
                <i class="fas fa-chart-pie me-1"></i>View Balances
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['accrual_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['accrual_success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <?php echo htmlspecialchars($_SESSION['accrual_message']); ?>
        </div>
        <?php unset($_SESSION['accrual_message'], $_SESSION['accrual_success']); ?>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Current Month Status -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-calendar-check me-2"></i>Current Month Status
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <div class="mb-3">
                            <h4 class="text-<?php echo $current_month_status['processed_count'] > 0 ? 'success' : 'warning'; ?>">
                                <?php echo date('F Y'); ?>
                            </h4>
                            <p class="text-muted mb-0">
                                <?php if ($current_month_status['processed_count'] > 0): ?>
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    Processed (<?php echo $current_month_status['processed_count']; ?> records)
                                <?php else: ?>
                                    <i class="fas fa-clock text-warning me-1"></i>
                                    Pending Processing
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="run_monthly_accrual">
                            <input type="hidden" name="month" value="<?php echo $current_month; ?>">
                            <input type="hidden" name="year" value="<?php echo $current_year; ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-play me-1"></i>
                                <?php echo $current_month_status['processed_count'] > 0 ? 'Reprocess' : 'Process'; ?> Current Month
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Processing -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-cogs me-2"></i>Manual Processing
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6>Run Monthly Accrual</h6>
                            <p class="text-muted small">Process leave accrual for a specific month</p>
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="run_monthly_accrual">
                                <div class="row g-2 mb-3">
                                    <div class="col-sm-6">
                                        <label class="form-label">Month</label>
                                        <select name="month" class="form-select form-select-sm">
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?php echo $m; ?>" <?php echo $m == $current_month ? 'selected' : ''; ?>>
                                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">Year</label>
                                        <select name="year" class="form-select form-select-sm">
                                            <?php for ($y = $current_year - 1; $y <= $current_year + 1; $y++): ?>
                                                <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                                    <?php echo $y; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calculator me-1"></i>Process Accrual
                                </button>
                            </form>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Recalculate Balances</h6>
                            <p class="text-muted small">Recalculate all leave balances for a year</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="recalculate_balances">
                                <div class="mb-3">
                                    <label class="form-label">Year</label>
                                    <select name="year" class="form-select form-select-sm">
                                        <?php for ($y = $current_year - 1; $y <= $current_year + 1; $y++): ?>
                                            <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                                <?php echo $y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-sync-alt me-1"></i>Recalculate Balances
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Accruals -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-history me-2"></i>Recent Accrual History
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_accruals)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No Accrual Records</h6>
                            <p class="text-muted">No leave accruals have been processed yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Month/Year</th>
                                        <th>Accrued Days</th>
                                        <th>Processed Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_accruals as $accrual): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($accrual['first_name'] . ' ' . $accrual['last_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo htmlspecialchars($accrual['leave_type_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('F Y', mktime(0, 0, 0, $accrual['accrual_month'], 1, $accrual['accrual_year'])); ?>
                                            </td>
                                            <td>
                                                <strong class="text-success"><?php echo $accrual['accrued_days']; ?> days</strong>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y g:i A', strtotime($accrual['processed_date'])); ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Information Panel -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Leave Accrual System Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">How It Works:</h6>
                            <ul class="small">
                                <li>Employees earn leave days progressively throughout the year</li>
                                <li>Monthly accrual = Annual leave allocation ÷ 12 months</li>
                                <li>Pro-rata calculation for new employees based on joining date</li>
                                <li>Balances are updated automatically after each accrual run</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Leave Types Eligible:</h6>
                            <ul class="small">
                                <li><strong>Annual Leave:</strong> Standard vacation days</li>
                                <li><strong>Casual Leave:</strong> Short-term personal leave</li>
                                <li><em>Note:</em> Sick leave and other types are allocated in full upfront</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh current month status every 30 seconds
setInterval(function() {
    // This could be enhanced with AJAX to update status without page reload
}, 30000);
</script>

<?php include '../../includes/footer.php'; ?>

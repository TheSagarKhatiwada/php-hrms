<?php
/**
 * Process Payroll
 * Calculate and generate monthly payroll
 */
$page = 'process-payroll';
require_once '../../includes/header.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/csrf_protection.php';

if (!is_logged_in() || !is_admin()) {
    redirect_with_message('../../index.php', 'error', 'Permission denied.');
}

$csrf_token = generate_csrf_token();

$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$action = $_GET['action'] ?? 'view'; // view, calculate, finalize

// Helper to get days in month
function get_days_in_month($m, $y) {
    return cal_days_in_month(CAL_GREGORIAN, $m, $y);
}

// 1. Calculate Payroll Preview
if ($action === 'calculate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die('Invalid token');
    
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    // Check if already finalized
    $stmt = $pdo->prepare("SELECT id FROM payroll_runs WHERE month = ? AND year = ? AND status = 'finalized'");
    $stmt->execute([$month, $year]);
    if ($stmt->fetch()) {
        redirect_with_message("process.php?month=$month&year=$year", 'error', 'Payroll for this month is already finalized.');
    }

    try {
        $pdo->beginTransaction();

        // Create or Update Draft Run
        $stmt = $pdo->prepare("SELECT id FROM payroll_runs WHERE month = ? AND year = ?");
        $stmt->execute([$month, $year]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($run) {
            $run_id = $run['id'];
            // Clear existing details for re-calculation
            $pdo->prepare("DELETE FROM payroll_details WHERE payroll_run_id = ?")->execute([$run_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO payroll_runs (month, year, processed_date, status, processed_by) VALUES (?, ?, NOW(), 'draft', ?)");
            $stmt->execute([$month, $year, $_SESSION['user_id']]);
            $run_id = $pdo->lastInsertId();
        }

        // Fetch Active Employees with Salary Structure
        $sql = "SELECT e.emp_id, e.join_date, e.work_start_time, e.work_end_time 
                FROM employees e 
                WHERE e.status = 'active' AND e.join_date <= ?";
        $last_day_of_month = "$year-$month-" . get_days_in_month($month, $year);
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$last_day_of_month]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_days_in_month = get_days_in_month($month, $year);

        // 1a. Fetch Holidays for this month
        $holiday_dates = [];
        try {
            $hStmt = $pdo->prepare("SELECT start_date, end_date FROM holidays WHERE status='active' AND (
                (MONTH(start_date) = ? AND YEAR(start_date) = ?) OR 
                (MONTH(end_date) = ? AND YEAR(end_date) = ?)
            )");
            $hStmt->execute([$month, $year, $month, $year]);
            while ($hRow = $hStmt->fetch(PDO::FETCH_ASSOC)) {
                $start = new DateTime($hRow['start_date']);
                $end = new DateTime($hRow['end_date']);
                // Clamp to current month
                $monthStart = new DateTime("$year-$month-01");
                $monthEnd = new DateTime("$year-$month-$total_days_in_month");
                
                if ($start < $monthStart) $start = clone $monthStart;
                if ($end > $monthEnd) $end = clone $monthEnd;

                while ($start <= $end) {
                    $holiday_dates[] = $start->format('Y-m-d');
                    $start->modify('+1 day');
                }
            }
        } catch (Exception $e) { /* Ignore holiday fetch errors */ }
        $holiday_dates = array_unique($holiday_dates);

        foreach ($employees as $emp) {
            // 1. Calculate Payable Days (Robust Logic)
            // Logic: Total Days - (Unauthorized Absences + Unpaid Leaves)
            // Unauthorized Absence = Working Day (Not Weekend, Not Holiday) AND No Attendance AND No Approved Leave
            
            $payable_days = 0;
            $unauthorized_absences = 0;
            $unpaid_leave_days = 0;

            // Fetch Attendance Dates for this employee
            $att_dates = [];
            $attStmt = $pdo->prepare("SELECT DISTINCT date FROM attendance_logs WHERE emp_id = ? AND MONTH(date) = ? AND YEAR(date) = ?");
            $attStmt->execute([$emp['emp_id'], $month, $year]);
            while ($r = $attStmt->fetch(PDO::FETCH_COLUMN)) {
                $att_dates[] = $r;
            }

            // Fetch Approved Leave Ranges
            $leaves = [];
            $lStmt = $pdo->prepare("SELECT start_date, end_date, lt.is_paid 
                                    FROM leave_requests lr
                                    JOIN leave_types lt ON lr.leave_type_id = lt.id
                                    WHERE lr.employee_id = ? AND lr.status = 'approved'
                                    AND ((MONTH(start_date) = ? AND YEAR(start_date) = ?) OR (MONTH(end_date) = ? AND YEAR(end_date) = ?))");
            $lStmt->execute([$emp['emp_id'], $month, $year, $month, $year]);
            $leave_records = $lStmt->fetchAll(PDO::FETCH_ASSOC);

            // Expand leaves into date map: date => is_paid (bool)
            $leave_map = [];
            foreach ($leave_records as $lr) {
                $s = new DateTime($lr['start_date']);
                $e = new DateTime($lr['end_date']);
                // Clamp
                $monthStart = new DateTime("$year-$month-01");
                $monthEnd = new DateTime("$year-$month-$total_days_in_month");
                if ($s < $monthStart) $s = clone $monthStart;
                if ($e > $monthEnd) $e = clone $monthEnd;

                while ($s <= $e) {
                    $dStr = $s->format('Y-m-d');
                    // If multiple leaves overlap, prioritize paid (shouldn't happen ideally)
                    if (!isset($leave_map[$dStr]) || $lr['is_paid']) {
                        $leave_map[$dStr] = (bool)$lr['is_paid'];
                    }
                    $s->modify('+1 day');
                }
            }

            // Iterate Day by Day
            for ($d = 1; $d <= $total_days_in_month; $d++) {
                $current_date_str = sprintf("%04d-%02d-%02d", $year, $month, $d);
                $current_ts = strtotime($current_date_str);
                $day_of_week = date('N', $current_ts); // 1 (Mon) to 7 (Sun)

                // Check Joining Date
                if ($current_date_str < $emp['join_date']) {
                    continue; // Not employed yet
                }

                // 1. Is it a Weekend? (Assuming Sunday is paid off)
                // TODO: Make weekend configurable. For now, Sunday (7) is off.
                $is_weekend = ($day_of_week == 7);

                // 2. Is it a Holiday?
                $is_holiday = in_array($current_date_str, $holiday_dates);

                // 3. Is Present?
                $is_present = in_array($current_date_str, $att_dates);

                // 4. Is on Leave?
                $is_on_leave = isset($leave_map[$current_date_str]);
                $is_paid_leave = $is_on_leave && $leave_map[$current_date_str];

                if ($is_present) {
                    $payable_days++;
                } elseif ($is_paid_leave) {
                    $payable_days++;
                } elseif ($is_weekend || $is_holiday) {
                    // Weekends/Holidays are paid unless sandwiched by unpaid leave (Sandwich rule ignored for MVP)
                    // If on unpaid leave specifically covering this day, it's unpaid.
                    if ($is_on_leave && !$is_paid_leave) {
                        $unpaid_leave_days++;
                    } else {
                        $payable_days++;
                    }
                } elseif ($is_on_leave && !$is_paid_leave) {
                    $unpaid_leave_days++;
                } else {
                    // Not Weekend, Not Holiday, Not Present, Not on Leave => Unauthorized Absence
                    $unauthorized_absences++;
                }
            }
            
            // Final Safety Check
            if ($payable_days < 0) $payable_days = 0;

            // 2. Fetch Salary Structure
            $structStmt = $pdo->prepare("SELECT ess.amount, sc.name, sc.type 
                                         FROM employee_salary_structures ess
                                         JOIN salary_components sc ON ess.component_id = sc.id
                                         WHERE ess.employee_id = ?");
            $structStmt->execute([$emp['emp_id']]);
            $components = $structStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($components)) continue; // Skip if no structure

            $gross = 0;
            $deductions = 0;
            $items = [];

            foreach ($components as $comp) {
                // Pro-rate calculation: (Amount / Total Days) * Payable Days
                $amount = ($comp['amount'] / $total_days_in_month) * $payable_days;
                $amount = round($amount, 2);

                if ($comp['type'] === 'earning') {
                    $gross += $amount;
                } else {
                    $deductions += $amount;
                }

                $items[] = [
                    'name' => $comp['name'],
                    'type' => $comp['type'],
                    'amount' => $amount
                ];
            }

            $net = $gross - $deductions;

            // Insert Detail
            $insDetail = $pdo->prepare("INSERT INTO payroll_details (payroll_run_id, employee_id, gross_salary, total_deductions, net_salary, payable_days) VALUES (?, ?, ?, ?, ?, ?)");
            $insDetail->execute([$run_id, $emp['emp_id'], $gross, $deductions, $net, $payable_days]);
            $detail_id = $pdo->lastInsertId();

            // Insert Items
            $insItem = $pdo->prepare("INSERT INTO payroll_items (payroll_detail_id, component_name, component_type, amount) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $insItem->execute([$detail_id, $item['name'], $item['type'], $item['amount']]);
            }
        }

        $pdo->commit();
        set_flash_message('success', 'Payroll calculated successfully. Please review and finalize.');
        header("Location: process.php?month=$month&year=$year");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Payroll calc error: " . $e->getMessage());
        set_flash_message('error', 'Error calculating payroll: ' . $e->getMessage());
    }
}

// 2. Finalize Payroll
if ($action === 'finalize' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die('Invalid token');
    $run_id = (int)$_POST['run_id'];
    
    $pdo->prepare("UPDATE payroll_runs SET status = 'finalized', processed_date = NOW() WHERE id = ?")->execute([$run_id]);
    set_flash_message('success', 'Payroll finalized successfully!');
    header("Location: process.php?month=$month&year=$year");
    exit();
}

// Fetch Current Run Data
$run_data = null;
$payroll_details = [];
$stmt = $pdo->prepare("SELECT * FROM payroll_runs WHERE month = ? AND year = ?");
$stmt->execute([$month, $year]);
$run_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($run_data) {
    $stmt = $pdo->prepare("SELECT pd.*, e.first_name, e.last_name 
                           FROM payroll_details pd 
                           JOIN employees e ON pd.employee_id = e.emp_id 
                           WHERE pd.payroll_run_id = ?");
    $stmt->execute([$run_data['id']]);
    $payroll_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fs-2 fw-bold mb-1">Process Payroll</h1>
        <div>
            <form class="d-flex gap-2" method="get">
                <select name="month" class="form-select">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="form-select">
                    <?php for($y=date('Y'); $y>=2023; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-secondary">Go</button>
            </form>
        </div>
    </div>

    <?php if (!$run_data): ?>
        <div class="alert alert-warning">
            No payroll data found for <strong><?php echo date('F Y', mktime(0,0,0,$month,1,$year)); ?></strong>.
        </div>
        <form method="post" action="process.php?action=calculate">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-calculator me-2"></i> Calculate Payroll
            </button>
        </form>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    Status: 
                    <span class="badge bg-<?php echo $run_data['status'] === 'finalized' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($run_data['status']); ?>
                    </span>
                    <span class="text-muted ms-2 small">Processed: <?php echo $run_data['processed_date']; ?></span>
                </div>
                <div>
                    <?php if ($run_data['status'] === 'draft'): ?>
                        <form method="post" action="process.php?action=calculate" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="month" value="<?php echo $month; ?>">
                            <input type="hidden" name="year" value="<?php echo $year; ?>">
                            <button type="submit" class="btn btn-outline-primary btn-sm me-2">Re-Calculate</button>
                        </form>
                        <form method="post" action="process.php?action=finalize" class="d-inline" onsubmit="return confirm('Are you sure? This will lock the payroll.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="run_id" value="<?php echo $run_data['id']; ?>">
                            <button type="submit" class="btn btn-success btn-sm">Finalize</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th class="text-center">Payable Days</th>
                                <th class="text-end">Gross Pay</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end">Net Pay</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payroll_details as $row): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <div class="small text-muted"><?php echo $row['employee_id']; ?></div>
                                </td>
                                <td class="text-center"><?php echo $row['payable_days']; ?></td>
                                <td class="text-end"><?php echo number_format($row['gross_salary'], 2); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($row['total_deductions'], 2); ?></td>
                                <td class="text-end fw-bold text-success"><?php echo number_format($row['net_salary'], 2); ?></td>
                                <td class="text-center">
                                    <a href="payslip.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-file-pdf"></i> Payslip
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>

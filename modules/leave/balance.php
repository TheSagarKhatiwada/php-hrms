<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin_user = is_admin();

// Get employee ID for balance calculation
$employee_id = $user_id;
if ($is_admin_user && isset($_GET['employee_id'])) {
    $employee_id = intval($_GET['employee_id']);
}

// Get current year
$current_year = date('Y');
$selected_year = $_GET['year'] ?? $current_year;

// Get employee details
$employee_sql = "SELECT first_name, last_name, emp_id FROM employees WHERE emp_id = ?";
$employee_stmt = $pdo->prepare($employee_sql);
$employee_stmt->execute([$employee_id]);
$employee = $employee_stmt->fetch();

if (!$employee) {
    $_SESSION['error_message'] = "Employee not found.";
    header("Location: " . (!$is_admin_user ? 'my-requests.php' : 'requests.php'));
    exit();
}

// Get leave balance by leave type
$balance_sql = "SELECT 
    lt.id,
    lt.name as leave_type,
    lt.color,
    lt.days_allowed as allocated_days,
    COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END), 0) as used_days,
    COALESCE(SUM(CASE WHEN lr.status = 'pending' THEN lr.days_requested ELSE 0 END), 0) as pending_days,
    (lt.days_allowed - COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END), 0)) as remaining_days
    FROM leave_types lt
    LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id 
        AND lr.employee_id = ? 
        AND YEAR(lr.start_date) = ?
    GROUP BY lt.id, lt.name, lt.color, lt.days_allowed
    ORDER BY lt.name";

$balance_stmt = $pdo->prepare($balance_sql);
$balance_stmt->execute([$employee_id, $selected_year]);
$balance_result = $balance_stmt->fetchAll();

// Get leave history for the selected year
$history_sql = "SELECT lr.*, lt.name as leave_type_name, lt.color
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.employee_id = ? AND YEAR(lr.start_date) = ?
                ORDER BY lr.start_date DESC";

$history_stmt = $pdo->prepare($history_sql);
$history_stmt->execute([$employee_id, $selected_year]);
$history_result = $history_stmt->fetchAll();

// Calculate totals
$total_allocated = 0;
$total_used = 0;
$total_pending = 0;
$total_remaining = 0;

$balance_data = [];
foreach ($balance_result as $balance) {
    $balance_data[] = $balance;
    $total_allocated += $balance['allocated_days'];
    $total_used += $balance['used_days'];
    $total_pending += $balance['pending_days'];
    $total_remaining += $balance['remaining_days'];
}

// Get available years
$years_sql = "SELECT DISTINCT YEAR(start_date) as year              FROM leave_requests 
              WHERE employee_id = ? 
              ORDER BY year DESC";
$years_stmt = $pdo->prepare($years_sql);
$years_stmt->execute([$employee_id]);
$years_result = $years_stmt->fetchAll();

// Get all employees for admin/hr dropdown
$employees_result = null;
if ($is_admin_user) {
    $employees_sql = "SELECT emp_id, first_name, last_name FROM employees ORDER BY first_name, last_name";
    $employees_result = $pdo->query($employees_sql)->fetchAll();
}

include '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-chart-pie me-2"></i>Leave Balance</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Leave Module</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Leave Balance</li>
                </ol>
            </nav>
        </div>        <div class="d-flex gap-2">
            <?php if (!$is_admin_user): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                    <i class="fas fa-plus me-1"></i>Apply Leave
                </button>
                <a href="my-requests.php" class="btn btn-outline-success">
                    <i class="fas fa-list me-1"></i>My Requests
                </a>
            <?php else: ?>
                <a href="requests.php" class="btn btn-outline-success">
                    <i class="fas fa-list me-1"></i>All Requests
                </a>
                <a href="reports.php" class="btn btn-outline-secondary">
                    <i class="fas fa-chart-bar me-1"></i>Reports
                </a>
            <?php endif; ?>
            <a href="calendar.php" class="btn btn-outline-info">
                <i class="fas fa-calendar me-1"></i>Calendar View
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-filter me-2"></i>Filters</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <?php if ($is_admin_user): ?>
                    <div class="col-md-4">
                        <label class="form-label">Employee</label>
                        <select name="employee_id" class="form-select">
                            <?php foreach ($employees_result as $emp): ?>
                                <option value="<?php echo $emp['emp_id']; ?>" 
                                        <?php echo $employee_id == $emp['emp_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['emp_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        <?php for ($year = $current_year; $year >= $current_year - 5; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Employee Info and Statistics -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-primary">
                <i class="fas fa-user me-2"></i>
                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> 
                - Leave Balance for <?php echo $selected_year; ?>
            </h6>
        </div>        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-3 col-6">
                    <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-info bg-opacity-10 p-3 rounded-3 me-3">
                                    <i class="fas fa-calendar-plus text-info fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-muted">Total Allocated</h6>
                                    <h2 class="mb-0 fw-bold"><?php echo $total_allocated; ?></h2>
                                </div>
                            </div>
                            <div class="d-flex align-items-center text-info">
                                <i class="fas fa-calendar me-1 small"></i>
                                <span class="small">Annual allocation</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                                    <i class="fas fa-calendar-check text-success fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-muted">Days Used</h6>
                                    <h2 class="mb-0 fw-bold"><?php echo $total_used; ?></h2>
                                </div>
                            </div>
                            <div class="d-flex align-items-center text-success">
                                <i class="fas fa-check me-1 small"></i>
                                <span class="small">Approved leaves</span>
                            </div>
                        </div>
                    </div>
                </div>                <div class="col-lg-3 col-6">
                    <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                                    <i class="fas fa-clock text-warning fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-muted">Pending</h6>
                                    <h2 class="mb-0 fw-bold"><?php echo $total_pending; ?></h2>
                                </div>
                            </div>
                            <div class="d-flex align-items-center text-warning">
                                <i class="fas fa-hourglass-half me-1 small"></i>
                                <span class="small">Awaiting approval</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary bg-opacity-20 p-3 rounded-3 me-3">
                                    <i class="fas fa-calendar-day text-light fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-muted">Remaining</h6>
                                    <h2 class="mb-0 fw-bold"><?php echo $total_remaining; ?></h2>
                                </div>
                            </div>
                            <div class="d-flex align-items-center text-primary">
                                <i class="fas fa-calendar-days me-1 small"></i>
                                <span class="small">Available days</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>    
    </div>


    <div class="row"><!-- Leave Balance Breakdown -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-chart-bar me-2"></i>Leave Balance by Type</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($balance_data)): ?>
                        <?php foreach ($balance_data as $balance): ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">
                                        <span class="badge" style="background-color: <?php echo $balance['color'] ?? '#007bff'; ?>; color: white;">
                                            <?php echo htmlspecialchars($balance['leave_type']); ?>
                                        </span>
                                    </h6>
                                    <div class="text-end">
                                        <span class="badge bg-success"><?php echo $balance['remaining_days']; ?> remaining</span>
                                        <?php if ($balance['pending_days'] > 0): ?>
                                            <span class="badge bg-warning"><?php echo $balance['pending_days']; ?> pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="progress mb-2" style="height: 25px;">
                                    <?php 
                                    $used_percentage = $balance['allocated_days'] > 0 ? ($balance['used_days'] / $balance['allocated_days']) * 100 : 0;
                                    $pending_percentage = $balance['allocated_days'] > 0 ? ($balance['pending_days'] / $balance['allocated_days']) * 100 : 0;
                                    ?>
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $used_percentage; ?>%"
                                         title="Used: <?php echo $balance['used_days']; ?> days">
                                        <?php if ($used_percentage > 15): ?>
                                            Used: <?php echo $balance['used_days']; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo $pending_percentage; ?>%"
                                         title="Pending: <?php echo $balance['pending_days']; ?> days">
                                        <?php if ($pending_percentage > 15): ?>
                                            Pending: <?php echo $balance['pending_days']; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-3">
                                        <small class="text-muted">Allocated: <strong><?php echo $balance['allocated_days']; ?></strong></small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Used: <strong><?php echo $balance['used_days']; ?></strong></small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Pending: <strong><?php echo $balance['pending_days']; ?></strong></small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Remaining: <strong><?php echo $balance['remaining_days']; ?></strong></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Leave Types Found</h5>
                            <p class="text-muted">No leave types are configured for this system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>        <!-- Leave Balance Chart -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-chart-pie me-2"></i>Usage Overview</h6>
                </div><div class="card-body">
                    <div style="position: relative; height: 300px;">
                        <canvas id="leaveBalanceChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="fas fa-square text-success"></i> Used</span>
                            <span><?php echo $total_used; ?> days</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="fas fa-square text-warning"></i> Pending</span>
                            <span><?php echo $total_pending; ?> days</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><i class="fas fa-square text-primary"></i> Available</span>
                            <span><?php echo $total_remaining; ?> days</span>
                        </div>
                    </div>
                </div>        </div>
    </div><!-- Leave History -->
    <?php if (count($history_result) > 0): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-history me-2"></i>Leave History (<?php echo $selected_year; ?>)</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="historyTable">
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Applied Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_result as $history): ?>
                                <tr>
                                    <td>
                                        <span class="badge" style="background-color: <?php echo $history['color'] ?? '#007bff'; ?>; color: white;">
                                            <?php echo htmlspecialchars($history['leave_type_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($history['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($history['end_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $history['days_requested']; ?> day<?php echo $history['days_requested'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_icon = '';
                                        switch ($history['status']) {
                                            case 'pending':
                                                $status_class = 'bg-warning';
                                                $status_icon = 'clock';
                                                break;
                                            case 'approved':
                                                $status_class = 'bg-success';
                                                $status_icon = 'check-circle';
                                                break;
                                            case 'rejected':
                                                $status_class = 'bg-danger';
                                                $status_icon = 'times-circle';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'bg-secondary';
                                                $status_icon = 'ban';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                            <?php echo ucfirst($history['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($history['applied_date'])); ?></td>
                                    <td>
                                        <a href="view.php?id=<?php echo $history['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Wait for both DOM and Chart.js to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing chart...');
    
    // Initialize DataTable for history (if jQuery is available)
    if (typeof $ !== 'undefined' && $('#historyTable').length) {
        $('#historyTable').DataTable({
            "responsive": true,
            "autoWidth": false,
            "order": [[5, "desc"]], // Sort by Applied Date descending
            "pageLength": 10,
            "language": {
                "emptyTable": "No leave history found for the selected year."
            }
        });    }
    
    // Create pie chart for leave balance
    try {
        const ctx = document.getElementById('leaveBalanceChart');
        if (!ctx) {
            console.error('Canvas element not found');
            return;
        }
        
        const chartContext = ctx.getContext('2d');
          // Debug: Log the data values
        const chartData = {
            used: <?php echo $total_used ?? 0; ?>,
            pending: <?php echo $total_pending ?? 0; ?>,
            remaining: <?php echo $total_remaining ?? 0; ?>
        };
        console.log('Chart data:', chartData);
        
        // Check if we have any data to display
        const totalData = chartData.used + chartData.pending + chartData.remaining;
        if (totalData === 0) {
            ctx.parentElement.innerHTML = '<div class="text-center text-muted"><i class="fas fa-chart-pie fa-3x mb-3"></i><p>No leave data available</p></div>';
            return;
        }
          const chart = new Chart(chartContext, {
            type: 'doughnut',
            data: {
                labels: ['Used', 'Pending', 'Available'],
                datasets: [{
                    data: [
                        <?php echo $total_used ?? 0; ?>, 
                        <?php echo $total_pending ?? 0; ?>, 
                        <?php echo $total_remaining ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#28a745', // Success green for used
                        '#ffc107', // Warning yellow for pending
                        '#007bff'  // Primary blue for available
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} days (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
          console.log('Chart created successfully');
        
    } catch (error) {
        console.error('Error creating chart:', error);
    }
});
</script>

<style>
/* Dashboard Stats Card Styling */
.dashboard-stat-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.dashboard-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1) !important;
}

.stat-icon {
    transition: transform 0.2s ease-in-out;
}

.dashboard-stat-card:hover .stat-icon {
    transform: scale(1.1);
}

/* Calendar and UI Theme Support */
.card-header {
    background: transparent !important;
    border-bottom: 1px solid var(--bs-border-color) !important;
}

/* Light theme specific */
[data-bs-theme="light"] .card {
    background-color: #ffffff;
    border-color: rgba(0,0,0,0.125);
}

[data-bs-theme="light"] .card-header {
    background-color: rgba(0,0,0,0.03) !important;
}

[data-bs-theme="light"] .table {
    color: var(--bs-body-color);
}

[data-bs-theme="light"] .text-muted {
    color: #6c757d !important;
}

/* Dark theme specific */
[data-bs-theme="dark"] .card {
    background-color: var(--bs-dark);
    border-color: var(--bs-border-color);
}

[data-bs-theme="dark"] .card-header {
    background-color: rgba(255,255,255,0.05) !important;
    border-bottom-color: var(--bs-border-color) !important;
}

[data-bs-theme="dark"] .table {
    color: var(--bs-body-color);
}

[data-bs-theme="dark"] .table-hover tbody tr:hover {
    background-color: rgba(255,255,255,0.075);
}

[data-bs-theme="dark"] .text-muted {
    color: var(--bs-gray-400) !important;
}

[data-bs-theme="dark"] .breadcrumb-item + .breadcrumb-item::before {
    color: var(--bs-gray-500);
}

[data-bs-theme="dark"] .form-select,
[data-bs-theme="dark"] .form-control {
    background-color: var(--bs-dark);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
}

[data-bs-theme="dark"] .form-select:focus,
[data-bs-theme="dark"] .form-control:focus {
    background-color: var(--bs-dark);
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25);
}

/* Badge styling for both themes */
[data-bs-theme="dark"] .badge {
    border: 1px solid rgba(255,255,255,0.1);
}

/* Chart container theme support */
[data-bs-theme="dark"] #leaveBalanceChart {
    background: transparent;
}

/* Button theme adjustments */
[data-bs-theme="dark"] .btn-outline-primary {
    border-color: var(--bs-primary);
    color: var(--bs-primary);
}

[data-bs-theme="dark"] .btn-outline-primary:hover {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
    color: #fff;
}

/* Progress bar theme support */
[data-bs-theme="dark"] .progress {
    background-color: rgba(255,255,255,0.1);
}

/* Timeline styling for calendar components */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 1rem;
}

.timeline-marker {
    position: absolute;
    left: -35px;
    top: 5px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: var(--bs-primary);
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: -31px;
    top: 15px;
    width: 2px;
    height: calc(100% + 0.5rem);
    background-color: var(--bs-border-color);
}

[data-bs-theme="dark"] .timeline-item:not(:last-child)::after {
    background-color: var(--bs-gray-600);
}

.timeline-content {
    background-color: var(--bs-light);
    border-radius: 0.375rem;
    padding: 0.75rem;
    border: 1px solid var(--bs-border-color);
}

[data-bs-theme="dark"] .timeline-content {
    background-color: rgba(255,255,255,0.05);
    border-color: var(--bs-border-color);
}

/* DataTable theme support */
[data-bs-theme="dark"] .dataTables_wrapper .dataTables_length,
[data-bs-theme="dark"] .dataTables_wrapper .dataTables_filter,
[data-bs-theme="dark"] .dataTables_wrapper .dataTables_info,
[data-bs-theme="dark"] .dataTables_wrapper .dataTables_paginate {
    color: var(--bs-body-color);
}

[data-bs-theme="dark"] .dataTables_wrapper .dataTables_paginate .paginate_button {
    color: var(--bs-body-color) !important;
}

[data-bs-theme="dark"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: rgba(255,255,255,0.1) !important;
    border-color: var(--bs-border-color) !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .dashboard-stat-card {
        margin-bottom: 1rem;
    }
    
    .col-lg-3.col-6 {
        flex: 0 0 auto;
        width: 50%;
    }
}

@media (max-width: 576px) {
    .col-lg-3.col-6 {
        width: 100%;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>

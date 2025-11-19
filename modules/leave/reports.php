<?php
session_start();
include_once '../../includes/config.php';
include_once '../../includes/utilities.php';
include_once '../../includes/header.php';

// Check if user is logged in and is admin/HR
if (!isset($_SESSION['user_id']) || !is_admin()) {
    header("Location: ../../index.php");
    exit();
}

// Get filter parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$department_id = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$leave_type_id = isset($_GET['leave_type']) ? (int)$_GET['leave_type'] : 0;
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Fetch departments for filter
$departments_stmt = $pdo->prepare("SELECT id, name FROM departments ORDER BY name");
$departments_stmt->execute();
$departments_result = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch leave types for filter
$leave_types_stmt = $pdo->prepare("SELECT id, name, color FROM leave_types WHERE status = 'active' ORDER BY name");
$leave_types_stmt->execute();
$leave_types_result = $leave_types_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build date filter
$date_filter = "YEAR(lr.start_date) = $year";
if ($month > 0) {
    $date_filter .= " AND MONTH(lr.start_date) = $month";
}

// Build department filter
$dept_filter = "";
if ($department_id > 0) {
    $dept_filter = " AND e.department_id = $department_id";
}

// Build leave type filter
$type_filter = "";
if ($leave_type_id > 0) {
    $type_filter = " AND lr.leave_type_id = $leave_type_id";
}

// Generate report data based on type
switch ($report_type) {
    case 'summary':
        // Leave summary by type
        $summary_query = "
            SELECT 
                lt.name as leave_type,
                lt.color,
                COUNT(lr.id) as total_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END) as total_days_taken
            FROM leave_types lt
            LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id 
                AND lr.deleted_at IS NULL 
                AND $date_filter $dept_filter
            LEFT JOIN employees e ON lr.employee_id = e.emp_id
            WHERE lt.status = 'active'
            GROUP BY lt.id, lt.name, lt.color
            ORDER BY total_requests DESC        ";
        $summary_stmt = $pdo->prepare($summary_query);
        $summary_stmt->execute();
        $summary_result = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'department':
        // Leave breakdown by department
        $dept_query = "
            SELECT 
                d.name as department,
                COUNT(lr.id) as total_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END) as total_days_taken,
                COUNT(DISTINCT lr.employee_id) as employees_took_leave
            FROM departments d
            LEFT JOIN employees e ON d.id = e.department_id
            LEFT JOIN leave_requests lr ON e.emp_id = lr.employee_id 
                AND lr.deleted_at IS NULL 
                AND $date_filter $type_filter
            GROUP BY d.id, d.name
            HAVING total_requests > 0 OR d.id = $department_id
            ORDER BY total_requests DESC        ";
        $dept_stmt = $pdo->prepare($dept_query);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'employee':
        // Individual employee report
        $emp_query = "
            SELECT 
                e.first_name,
                e.last_name,
                e.employee_id,
                d.name as department,
                COUNT(lr.id) as total_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END) as total_days_taken
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN leave_requests lr ON e.emp_id = lr.employee_id 
                AND lr.deleted_at IS NULL 
                AND $date_filter $type_filter
            WHERE e.status = 'active' $dept_filter
            GROUP BY e.emp_id, e.first_name, e.last_name, e.employee_id, d.name
            HAVING total_requests > 0
            ORDER BY total_days_taken DESC        ";
        $emp_stmt = $pdo->prepare($emp_query);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'trends':
        // Monthly trends
        $trends_query = "
            SELECT 
                YEAR(lr.start_date) as year,
                MONTH(lr.start_date) as month,
                COUNT(lr.id) as total_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END) as total_days_taken
            FROM leave_requests lr
            LEFT JOIN employees e ON lr.employee_id = e.emp_id
            WHERE lr.deleted_at IS NULL 
                AND YEAR(lr.start_date) = $year 
                $dept_filter $type_filter
            GROUP BY YEAR(lr.start_date), MONTH(lr.start_date)
            ORDER BY year, month        ";
        $trends_stmt = $pdo->prepare($trends_query);
        $trends_stmt->execute();
        $trends_result = $trends_stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}

// Get overall statistics
$stats_query = "
    SELECT 
        COUNT(lr.id) as total_requests,
        SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
        SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END) as total_days_taken,
        COUNT(DISTINCT lr.employee_id) as employees_took_leave
    FROM leave_requests lr
    LEFT JOIN employees e ON lr.employee_id = e.emp_id
    WHERE lr.deleted_at IS NULL AND $date_filter $dept_filter $type_filter
";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "Leave Reports";
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Leave Reports</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Leave Module</a></li>
                        <li class="breadcrumb-item active">Reports</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Filter Section -->
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter"></i> Report Filters</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-2">
                            <label>Report Type</label>
                            <select name="report_type" class="form-control">
                                <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Leave Summary</option>
                                <option value="department" <?php echo $report_type == 'department' ? 'selected' : ''; ?>>By Department</option>
                                <option value="employee" <?php echo $report_type == 'employee' ? 'selected' : ''; ?>>By Employee</option>
                                <option value="trends" <?php echo $report_type == 'trends' ? 'selected' : ''; ?>>Monthly Trends</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Year</label>
                            <select name="year" class="form-control">
                                <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Month</label>
                            <select name="month" class="form-control">
                                <option value="0" <?php echo $month == 0 ? 'selected' : ''; ?>>All Months</option>
                                <?php for($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Department</label>
                            <select name="department" class="form-control">
                                <option value="0">All Departments</option>                                <?php foreach($departments_result as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $department_id == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Leave Type</label>
                            <select name="leave_type" class="form-control">
                                <option value="0">All Types</option>                                <?php foreach($leave_types_result as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" <?php echo $leave_type_id == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics Overview -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo number_format($stats['total_requests']); ?></h3>
                            <p>Total Requests</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo number_format($stats['approved_requests']); ?></h3>
                            <p>Approved Requests</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo number_format($stats['pending_requests']); ?></h3>
                            <p>Pending Requests</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><?php echo number_format($stats['total_days_taken']); ?></h3>
                            <p>Total Days Taken</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Options -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="btn-group float-right">
                        <button type="button" class="btn btn-outline-primary" onclick="exportReport('pdf')">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="exportReport('excel')">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </div>

            <!-- Report Content -->
            <?php if ($report_type == 'summary'): ?>
                <!-- Leave Summary Report -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-pie"></i> Leave Summary by Type</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Pending</th>
                                        <th>Rejected</th>
                                        <th>Days Taken</th>
                                        <th>Approval Rate</th>
                                    </tr>
                                </thead>
                                <tbody>                                    <?php foreach($summary_result as $row): ?>
                                        <?php $approval_rate = $row['total_requests'] > 0 ? round(($row['approved_requests'] / $row['total_requests']) * 100, 1) : 0; ?>
                                        <tr>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo $row['color']; ?>; color: white;">
                                                    <?php echo htmlspecialchars($row['leave_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $row['total_requests']; ?></td>
                                            <td><span class="badge badge-success"><?php echo $row['approved_requests']; ?></span></td>
                                            <td><span class="badge badge-warning"><?php echo $row['pending_requests']; ?></span></td>
                                            <td><span class="badge badge-danger"><?php echo $row['rejected_requests']; ?></span></td>
                                            <td><strong><?php echo $row['total_days_taken']; ?></strong></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $approval_rate; ?>%;">
                                                        <?php echo $approval_rate; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'department'): ?>
                <!-- Department Report -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-building"></i> Leave Report by Department</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Pending</th>
                                        <th>Rejected</th>
                                        <th>Days Taken</th>
                                        <th>Employees on Leave</th>
                                    </tr>
                                </thead>
                                <tbody>                                    <?php foreach($dept_result as $row): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['department']); ?></strong></td>
                                            <td><?php echo $row['total_requests']; ?></td>
                                            <td><span class="badge badge-success"><?php echo $row['approved_requests']; ?></span></td>
                                            <td><span class="badge badge-warning"><?php echo $row['pending_requests']; ?></span></td>
                                            <td><span class="badge badge-danger"><?php echo $row['rejected_requests']; ?></span></td>
                                            <td><strong><?php echo $row['total_days_taken']; ?></strong></td>
                                            <td><?php echo $row['employees_took_leave']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'employee'): ?>
                <!-- Employee Report -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-users"></i> Leave Report by Employee</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Employee ID</th>
                                        <th>Department</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Pending</th>
                                        <th>Rejected</th>
                                        <th>Days Taken</th>
                                    </tr>
                                </thead>
                                <tbody>                                    <?php foreach($emp_result as $row): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td><?php echo $row['total_requests']; ?></td>
                                            <td><span class="badge badge-success"><?php echo $row['approved_requests']; ?></span></td>
                                            <td><span class="badge badge-warning"><?php echo $row['pending_requests']; ?></span></td>
                                            <td><span class="badge badge-danger"><?php echo $row['rejected_requests']; ?></span></td>
                                            <td><strong><?php echo $row['total_days_taken']; ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'trends'): ?>
                <!-- Trends Report -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-line"></i> Monthly Leave Trends for <?php echo $year; ?></h3>
                    </div>
                    <div class="card-body">
                        <div id="trendsChart" style="height: 400px;"></div>
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Days Taken</th>
                                    </tr>
                                </thead>
                                <tbody>                                    <?php foreach($trends_result as $row): ?>
                                        <tr>
                                            <td><strong><?php echo date('F', mktime(0,0,0,$row['month'],1)); ?></strong></td>
                                            <td><?php echo $row['total_requests']; ?></td>
                                            <td><span class="badge badge-success"><?php echo $row['approved_requests']; ?></span></td>
                                            <td><strong><?php echo $row['total_days_taken']; ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>

<script>
// Export functionality
function exportReport(format) {
    var params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open('export-reports.php?' + params.toString(), '_blank');
}

<?php if ($report_type == 'trends'): ?>
// Generate trends chart
document.addEventListener('DOMContentLoaded', function() {
    var trendsData = [
        <?php        $months = [];
        $requests = [];
        $approved = [];
        $days = [];
        foreach($trends_result as $row): 
            $months[] = "'" . date('M', mktime(0,0,0,$row['month'],1)) . "'";
            $requests[] = $row['total_requests'];
            $approved[] = $row['approved_requests'];
            $days[] = $row['total_days_taken'];
        endforeach;
        ?>
        {
            x: [<?php echo implode(',', $months); ?>],
            y: [<?php echo implode(',', $requests); ?>],
            type: 'scatter',
            mode: 'lines+markers',
            name: 'Total Requests',
            line: {color: '#007bff'}
        },
        {
            x: [<?php echo implode(',', $months); ?>],
            y: [<?php echo implode(',', $approved); ?>],
            type: 'scatter',
            mode: 'lines+markers',
            name: 'Approved',
            line: {color: '#28a745'}
        },
        {
            x: [<?php echo implode(',', $months); ?>],
            y: [<?php echo implode(',', $days); ?>],
            type: 'scatter',
            mode: 'lines+markers',
            name: 'Days Taken',
            yaxis: 'y2',
            line: {color: '#ffc107'}
        }
    ];

    var layout = {
        title: 'Leave Trends - <?php echo $year; ?>',
        xaxis: {title: 'Month'},
        yaxis: {title: 'Number of Requests'},
        yaxis2: {
            title: 'Days Taken',
            overlaying: 'y',
            side: 'right'
        },
        showlegend: true
    };

    Plotly.newPlot('trendsChart', trendsData, layout);
});
<?php endif; ?>
</script>

<style>
@media print {
    .btn, .card-header .btn-tool, .breadcrumb, .content-header {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
    }
}
</style>

<?php include_once '../../includes/footer.php'; ?>

<?php
/**
 * Leave Module - Main Dashboard
 * Comprehensive leave management system for HRMS
 */

// Include session configuration first to ensure session is available
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';

$page = 'leave_dashboard';
$home = '../../';

// Check if user has access
if (!is_logged_in()) {
    header('Location: ../../index.php');
    exit();
}

include '../../includes/db_connection.php';

// Get current user info
$currentUserId = $_SESSION['user_id'] ?? null;
$isAdmin = is_admin();

// Get employee ID for current user
$currentEmployeeId = null;
if ($currentUserId) {
    try {
        // Use the user_id directly as it's already the primary key ID
        $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE emp_id = ?");
        $stmt->execute([$currentUserId]);
        $result = $stmt->fetch();
        if ($result) {
            $currentEmployeeId = $result['emp_id']; // Use the primary key ID
        }
    } catch (PDOException $e) {
        error_log("Error fetching employee ID: " . $e->getMessage());
    }
}

// Initialize variables
$leaveStats = [
    'total_requests' => 0,
    'pending_requests' => 0,
    'approved_this_month' => 0,
    'my_pending' => 0,
    'my_approved' => 0,
    'my_remaining_days' => 0
];

$recentRequests = [];
$myRecentRequests = [];
$leaveCalendar = [];

try {
    // Debug: Check basic database connection and tables
    $stmt = $pdo->query("SHOW TABLES LIKE 'leave_requests'");
    $tableExists = $stmt->fetch();
    echo "<!-- Debug: leave_requests table exists: " . ($tableExists ? 'YES' : 'NO') . " -->";
    
    if ($tableExists) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests");
        $totalCount = $stmt->fetchColumn();
        echo "<!-- Debug: Total leave_requests in database: " . $totalCount . " -->";
    }
    
    // Get leave statistics
    if ($isAdmin) {
        // Admin stats - all employees
        $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests");
        $leaveStats['total_requests'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
        $leaveStats['pending_requests'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved' AND MONTH(start_date) = MONTH(CURDATE()) AND YEAR(start_date) = YEAR(CURDATE())");
        $leaveStats['approved_this_month'] = $stmt->fetchColumn();        // Recent leave requests for admin
        $stmt = $pdo->prepare("
            SELECT lr.*, lt.name as leave_type_name, lt.code as leave_type_code, lt.color as leave_type_color,
                   e.first_name, e.last_name, e.emp_id, e.user_image,
                   d.title as designation_title, b.name as branch_name,
                   CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                   COALESCE(lr.days_requested, DATEDIFF(lr.end_date, lr.start_date) + 1) as total_days
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            JOIN employees e ON lr.employee_id = e.emp_id
            LEFT JOIN designations d ON e.designation_id = d.id
            LEFT JOIN branches b ON e.branch = b.id
            ORDER BY lr.applied_date DESC
            LIMIT 10
        ");        $stmt->execute();
        $recentRequests = $stmt->fetchAll();
        
        // Debug output for admin
        echo "<!-- Admin Debug: recentRequests count: " . count($recentRequests) . " -->";
        if (count($recentRequests) > 0) {
            echo "<!-- First request: " . json_encode($recentRequests[0]) . " -->";
        }
    }
    
    // Employee-specific stats
    if ($currentEmployeeId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
        $stmt->execute([$currentEmployeeId]);
        $leaveStats['my_pending'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'approved' AND YEAR(start_date) = YEAR(CURDATE())");
        $stmt->execute([$currentEmployeeId]);
        $leaveStats['my_approved'] = $stmt->fetchColumn();
        
        // Calculate remaining leave days
        $stmt = $pdo->prepare("
            SELECT SUM(lr.total_days) as used_days
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = ? 
            AND lr.status = 'approved' 
            AND YEAR(lr.start_date) = YEAR(CURDATE())
            AND lt.is_paid = 1
        ");
        $stmt->execute([$currentEmployeeId]);
        $usedDays = $stmt->fetchColumn() ?: 0;
        
        // Get total allowed days (assuming 21 days default, can be made configurable)
        $totalAllowedDays = 21;
        $leaveStats['my_remaining_days'] = max(0, $totalAllowedDays - $usedDays);        // Recent requests for employee
        $stmt = $pdo->prepare("
            SELECT lr.*, lt.name as leave_type_name, lt.code as leave_type_code, lt.color as leave_type_color,
                   COALESCE(lr.total_days, DATEDIFF(lr.end_date, lr.start_date) + 1) as total_days
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = ?
            ORDER BY lr.applied_date DESC
            LIMIT 5
        ");        $stmt->execute([$currentEmployeeId]);
        $myRecentRequests = $stmt->fetchAll();
        
        // Debug output for employee
        echo "<!-- Employee Debug: myRecentRequests count: " . count($myRecentRequests) . " -->";
        echo "<!-- Employee ID: " . $currentEmployeeId . " -->";
        if (count($myRecentRequests) > 0) {
            echo "<!-- First request: " . json_encode($myRecentRequests[0]) . " -->";
        }
    }
      // Get leave calendar data for current month
    $stmt = $pdo->prepare("
        SELECT lr.*, lt.name as leave_type_name, lt.code as leave_type_code,
               e.first_name, e.last_name, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        JOIN employees e ON lr.employee_id = e.emp_id
        WHERE lr.status = 'approved'
        AND (
            (MONTH(lr.start_date) = MONTH(CURDATE()) AND YEAR(lr.start_date) = YEAR(CURDATE()))
            OR (MONTH(lr.end_date) = MONTH(CURDATE()) AND YEAR(lr.end_date) = YEAR(CURDATE()))
            OR (lr.start_date <= LAST_DAY(CURDATE()) AND lr.end_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01'))
        )
        ORDER BY lr.start_date
    ");
    $stmt->execute();
    $leaveCalendar = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Leave dashboard error: " . $e->getMessage());
}

// Include header
$home = '../../';
require_once '../../includes/header.php';

?>

<!-- Main content -->
<div class="container-fluid p-4">    <!-- Page header -->
    <div class="d-flex justify-content-between flex-wrap gap-3 align-items-center mb-3">
        <div>
            <h1 class="fs-2 mb-1"><i class="fas fa-calendar-alt me-2"></i>Leave Dashboard</h1>
            <p class="text-muted mb-0">Quick access to every leave and holiday workflow</p>
        </div>
        <?php
            $isAdmin = $isAdmin ?? (function_exists('is_admin') ? is_admin() : false);
            $leaveToolbarShowDashboardButton = false;
            $leaveToolbarPrimaryLinks = [[
                'url' => 'holidays.php',
                'label' => 'Holidays',
                'icon' => 'fas fa-umbrella-beach',
                'classes' => 'btn btn-outline-warning',
                'page' => 'holidays.php',
            ]];
            $leaveToolbarInline = true;
            include __DIR__ . '/partials/action-toolbar.php';
        ?>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <?php if ($isAdmin): ?>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3 stat-icon">
                            <i class="fas fa-calendar-alt text-primary fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">Total Requests</h6>
                            <h2 class="mb-0 fw-bold"><?= $leaveStats['total_requests'] ?></h2>
                        </div>
                    </div>
                    <div class="d-flex align-items-center text-primary">
                        <i class="fas fa-calendar me-1 small"></i>
                        <span class="small">All time requests</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-warning bg-opacity-10 p-3 rounded-3 stat-icon">
                            <i class="fas fa-clock text-warning fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">Pending Approval</h6>
                            <h2 class="mb-0 fw-bold"><?= $leaveStats['pending_requests'] ?></h2>
                        </div>
                    </div>
                    <div class="d-flex align-items-center text-warning">
                        <i class="fas fa-hourglass-half me-1 small"></i>
                        <span class="small">Awaiting review</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 stat-icon">
                            <i class="fas fa-check-circle text-success fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">Approved This Month</h6>
                            <h2 class="mb-0 fw-bold"><?= $leaveStats['approved_this_month'] ?></h2>
                        </div>
                    </div>
                    <div class="d-flex align-items-center text-success">
                        <i class="fas fa-thumbs-up me-1 small"></i>
                        <span class="small">Current month</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-info bg-opacity-10 p-3 rounded-3 stat-icon">
                            <i class="fas fa-user-clock text-info fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">Remaining Days</h6>
                            <h2 class="mb-0 fw-bold"><?= $leaveStats['my_remaining_days'] ?></h2>
                        </div>
                    </div>
                    <div class="d-flex align-items-center text-info">
                        <i class="fas fa-calendar-days me-1 small"></i>
                        <span class="small">Available leave</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">        <!-- Recent Leave Requests -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <?= $isAdmin ? 'Recent Leave Requests' : 'My Leave Requests' ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (($isAdmin && !empty($recentRequests)) || (!$isAdmin && !empty($myRecentRequests))): ?>                    <div class="table-responsive">
                        <table class="table table-hover" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <?php if ($isAdmin): ?>
                                    <th>Employee</th>
                                    <?php endif; ?>
                                    <th>Leave Type</th>
                                    <th>Duration</th>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $requests = $isAdmin ? $recentRequests : $myRecentRequests;
                                foreach ($requests as $request): 
                                ?>
                                <tr>
                                    <?php if ($isAdmin): ?>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= htmlspecialchars(!empty($request['user_image']) ? $home . $request['user_image'] : $home . 'resources/userimg/default-image.jpg') ?>" 
                                                 alt="Employee" 
                                                 class="rounded-circle me-2" 
                                                 style="width: 32px; height: 32px; object-fit: cover;">
                                            <div>
                                                <div class="fw-medium"><?= htmlspecialchars($request['employee_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($request['designation_title'] ?: 'N/A') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <?php endif; ?>                                    <td>
                                        <span class="badge" style="background-color: <?= htmlspecialchars($request['leave_type_color'] ?? '#007bff') ?>; color: white;"><?= htmlspecialchars($request['leave_type_name']) ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <?= date('M d', strtotime($request['start_date'])) ?> - 
                                            <?= date('M d, Y', strtotime($request['end_date'])) ?>
                                        </div>
                                        <small class="text-muted"><?= $request['days_requested'] ?> day(s)</small>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($request['applied_date'])) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($request['status']) {
                                            case 'pending':
                                                $statusClass = 'bg-warning';
                                                break;
                                            case 'approved':
                                                $statusClass = 'bg-success';
                                                break;
                                            case 'rejected':
                                                $statusClass = 'bg-danger';
                                                break;
                                            default:
                                                $statusClass = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= ucfirst($request['status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="view.php?id=<?= $request['id'] ?>">
                                                    <i class="fas fa-eye me-2"></i>View Details
                                                </a></li>
                                                <?php if ($isAdmin && $request['status'] == 'pending'): ?>
                                                <li><a class="dropdown-item" href="approve.php?id=<?= $request['id'] ?>">
                                                    <i class="fas fa-check me-2"></i>Approve
                                                </a></li>
                                                <li><a class="dropdown-item" href="reject.php?id=<?= $request['id'] ?>">
                                                    <i class="fas fa-times me-2"></i>Reject
                                                </a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>                    <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-calendar-alt fa-2x mb-2"></i><br>
                        No leave requests found. Request your first leave to see it here!
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Leave Calendar & Quick Actions -->
        <div class="col-lg-4">            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                            <i class="fas fa-plus me-2"></i>New Leave Request
                        </button>
                        <a href="my-requests.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>My Requests
                        </a>
                        <a href="balance.php" class="btn btn-outline-info">
                            <i class="fas fa-chart-bar me-2"></i>Leave Balance
                        </a>
                        <?php if ($isAdmin): ?>
                        <hr>
                        <a href="requests.php" class="btn btn-outline-warning">
                            <i class="fas fa-clipboard-list me-2"></i>All Requests
                        </a>
                        <a href="types.php" class="btn btn-outline-success">
                            <i class="fas fa-cog me-2"></i>Manage Types
                        </a>
                        <a href="reports.php" class="btn btn-outline-dark">
                            <i class="fas fa-chart-line me-2"></i>Reports
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>            <!-- Leave Calendar Summary -->
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">This Month's Leave Calendar</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($leaveCalendar)): ?>
                    <div class="timeline">
                        <?php foreach (array_slice($leaveCalendar, 0, 5) as $leave): ?>
                        <div class="timeline-item mb-3">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($leave['employee_name']) ?></h6>
                                        <p class="mb-1 text-muted small"><?= htmlspecialchars($leave['leave_type_name']) ?></p>
                                    </div>
                                    <small class="text-muted">
                                        <?= date('M d', strtotime($leave['start_date'])) ?>
                                        <?php if ($leave['start_date'] != $leave['end_date']): ?>
                                        - <?= date('M d', strtotime($leave['end_date'])) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($leaveCalendar) > 5): ?>
                        <div class="text-center">
                            <a href="calendar.php" class="btn btn-sm btn-outline-primary">
                                View Full Calendar
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-calendar fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No leaves scheduled this month</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.text-gray-800 {
    color: #5a5c69 !important;
}

.text-gray-300 {
    color: #dddfeb !important;
}

.text-xs {
    font-size: 0.75rem !important;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
}

.timeline-marker {
    position: absolute;
    left: -35px;
    top: 5px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: -31px;
    top: 15px;
    width: 2px;
    height: calc(100% + 10px);
    background-color: #dee2e6;
}
</style>

<?php require_once '../../includes/footer.php'; ?>

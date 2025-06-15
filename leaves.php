<?php
// Include session configuration first to ensure session is available
require_once 'includes/session_config.php';
require_once 'includes/utilities.php';

$page = 'leaves';

// Check if user has admin access
if (!is_admin()) {
    header('Location: dashboard.php');
    exit();
}

include 'includes/db_connection.php';

// Fetch leave statistics
try {
    // Total leave requests
    $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests");
    $totalLeaveRequests = $stmt->fetchColumn();
    
    // Pending leave requests
    $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
    $pendingRequests = $stmt->fetchColumn();
    
    // Approved leave requests this month
    $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved' AND MONTH(start_date) = MONTH(CURDATE())");
    $approvedThisMonth = $stmt->fetchColumn();
    
    // Active leave types
    $stmt = $pdo->query("SELECT COUNT(*) FROM leave_types WHERE is_active = 1");
    $activeLeaveTypes = $stmt->fetchColumn();
      // Recent leave requests (last 20)
    $stmt = $pdo->prepare("
        SELECT lr.*, lt.name as leave_type_name, lt.code as leave_type_code,
               e.first_name, e.last_name, e.emp_id, e.user_image,
               d.title as designation_title, b.name as branch_name,
               CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        JOIN employees e ON lr.employee_id = e.emp_id
        LEFT JOIN designations d ON e.designation = d.id
        LEFT JOIN branches b ON e.branch = b.id
        ORDER BY lr.applied_date DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recentLeaveRequests = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching leave data: " . $e->getMessage();
    $totalLeaveRequests = $pendingRequests = $approvedThisMonth = $activeLeaveTypes = 0;
    $recentLeaveRequests = [];
}

// Force fresh load by checking for cache-busting parameter
if (!isset($_GET['_nocache'])) {
    header("Location: leaves.php?_nocache=" . time());
    exit();
}

// Include the header (which includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/includes/header.php';
?>

<!-- Content Wrapper (already started in header.php) -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Leave Management</h1>
            <p class="text-muted mb-0">Manage employee leave requests and policies</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" id="refreshLeaves" class="btn btn-outline-primary" onclick="forceRefresh()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <a href="leave-requests.php" class="btn btn-success">
                <i class="fas fa-plus me-2"></i> New Leave Request
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-3">
                                <i class="fas fa-calendar-alt text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold text-dark fs-3"><?php echo $totalLeaveRequests; ?></div>
                            <div class="text-muted small">Total Requests</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning bg-opacity-10 p-3 rounded-3">
                                <i class="fas fa-clock text-warning fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold text-dark fs-3"><?php echo $pendingRequests; ?></div>
                            <div class="text-muted small">Pending Approval</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 p-3 rounded-3">
                                <i class="fas fa-check-circle text-success fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold text-dark fs-3"><?php echo $approvedThisMonth; ?></div>
                            <div class="text-muted small">Approved This Month</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 p-3 rounded-3">
                                <i class="fas fa-list text-info fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold text-dark fs-3"><?php echo $activeLeaveTypes; ?></div>
                            <div class="text-muted small">Active Leave Types</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row g-4 mb-4">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="leave-requests.php" class="btn btn-primary w-100 d-flex flex-column align-items-center p-3 h-100">
                                <i class="fas fa-calendar-plus fs-4 mb-2"></i>
                                <span>New Leave Request</span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="leave-types.php" class="btn btn-success w-100 d-flex flex-column align-items-center p-3 h-100">
                                <i class="fas fa-tags fs-4 mb-2"></i>
                                <span>Manage Leave Types</span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="leave-balances.php" class="btn btn-info w-100 d-flex flex-column align-items-center p-3 h-100">
                                <i class="fas fa-balance-scale fs-4 mb-2"></i>
                                <span>Leave Balances</span>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="leave-calendar.php" class="btn btn-warning w-100 d-flex flex-column align-items-center p-3 h-100">
                                <i class="fas fa-calendar fs-4 mb-2"></i>
                                <span>Leave Calendar</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Leave Requests -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0">Recent Leave Requests</h5>
                    <a href="leave-requests.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-list me-1"></i> View All Requests
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentLeaveRequests)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Leave Requests</h5>
                            <p class="text-muted">No leave requests have been submitted yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="recent-leaves-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Period</th>
                                        <th>Days</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLeaveRequests as $request): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo htmlspecialchars($request['user_image'] ?: 'resources/images/default-user.png'); ?>" 
                                                         alt="Employee" 
                                                         class="rounded-circle me-3" 
                                                         style="width: 40px; height: 40px; object-fit: cover;">
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($request['employee_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($request['designation_title'] ?: 'Not Assigned'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($request['leave_type_name']); ?></span>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <strong>From:</strong> <?php echo date('M d, Y', strtotime($request['start_date'])); ?><br>
                                                    <strong>To:</strong> <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="fw-bold"><?php echo $request['total_days']; ?></span>
                                                <small class="text-muted d-block">days</small>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($request['applied_date'])); ?></td>
                                            <td>
                                                <?php
                                                $statusClasses = [
                                                    'pending' => 'bg-warning',
                                                    'approved' => 'bg-success',
                                                    'rejected' => 'bg-danger',
                                                    'cancelled' => 'bg-secondary'
                                                ];
                                                $statusClass = $statusClasses[$request['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($request['status']); ?></span>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <a href="#" class="text-secondary" role="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="view-leave-request.php?id=<?php echo $request['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i> View Details
                                                            </a>
                                                        </li>
                                                        <?php if ($request['status'] == 'pending'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" href="approve-leave.php?id=<?php echo $request['id']; ?>">
                                                                    <i class="fas fa-check me-2"></i> Approve
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="reject-leave.php?id=<?php echo $request['id']; ?>">
                                                                    <i class="fas fa-times me-2"></i> Reject
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
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
</div>

<!-- Include the main footer (which closes content-wrapper, main-wrapper, etc.) -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable for recent leaves
    if (document.getElementById('recent-leaves-table')) {
        new DataTable('#recent-leaves-table', {
            responsive: true,
            lengthChange: false,
            autoWidth: false,
            order: [[4, 'desc']], // Sort by applied date
            pageLength: 10,
            language: {
                paginate: {
                    previous: '<i class="fas fa-chevron-left"></i>',
                    next: '<i class="fas fa-chevron-right"></i>'
                }
            }
        });
    }
});

function forceRefresh() {
    window.location.href = 'leaves.php?_nocache=' + new Date().getTime();
}
</script>

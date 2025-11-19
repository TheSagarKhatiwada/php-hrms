<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !is_admin()) {
    header("Location: ../../index.php");
    exit();
}

$request_id = $_GET['id'] ?? 0;

if (!$request_id) {
    $_SESSION['error_message'] = "Invalid request ID.";
    header("Location: requests.php");
    exit();
}

// Handle approval
if ($_POST && isset($_POST['approve_request'])) {
    try {
        $approval_comments = trim($_POST['approval_comments'] ?? '');
        
        // Get request details first
        $check_sql = "SELECT lr.*, e.first_name, e.last_name 
                     FROM leave_requests lr 
                     JOIN employees e ON lr.employee_id = e.emp_id 
                     WHERE lr.id = ? AND lr.status = 'pending'";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$request_id]);
        $request_data = $check_stmt->fetch();
        
        if (!$request_data) {
            throw new Exception("Leave request not found or already processed.");
        }
        // Build update dynamically based on available columns
        $params = [];
        $setParts = [];
        // Always set status/approved_by/reviewed_date
        $setParts[] = "status = 'approved'";
        $setParts[] = "approved_by = ?"; $params[] = $_SESSION['user_id'];
        $setParts[] = "reviewed_date = NOW()";

        // Check for optional columns to avoid SQL errors on legacy schemas
        $hasApprovalComments = false;
        $hasApprovedDate = false;
        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM `leave_requests` LIKE 'approval_comments'");
            $hasApprovalComments = $colStmt && $colStmt->rowCount() > 0;
        } catch (Throwable $e) { /* ignore */ }
        try {
            $colStmt2 = $pdo->query("SHOW COLUMNS FROM `leave_requests` LIKE 'approved_date'");
            $hasApprovedDate = $colStmt2 && $colStmt2->rowCount() > 0;
        } catch (Throwable $e) { /* ignore */ }

        if ($hasApprovalComments) {
            $setParts[] = "approval_comments = ?"; $params[] = $approval_comments;
        }
        if ($hasApprovedDate) {
            $setParts[] = "approved_date = NOW()";
        }

        $sql = "UPDATE leave_requests SET " . implode(', ', $setParts) . " WHERE id = ? AND status = 'pending'";
        $params[] = $request_id;
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute($params) && $stmt->rowCount() > 0) {
            // Send notification
            include_once 'notifications.php';
            sendLeaveNotification('approved', $request_id, [
                'approved_by' => $_SESSION['user_id'],
                'comments' => $approval_comments
            ]);
            
            $_SESSION['success_message'] = "Leave request approved successfully!";
            
            // TODO: Send email notification to employee
            // You can add email notification logic here
            
            header("Location: requests.php");
            exit();
        } else {
            throw new Exception("Failed to approve leave request or request already processed.");
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get leave request details
$sql = "SELECT lr.*, 
               e.first_name, e.last_name, e.emp_id, e.email,
               lt.name as leave_type_name, lt.color
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.emp_id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    $_SESSION['error_message'] = "Leave request not found.";
    header("Location: requests.php");
    exit();
}

// Check if already processed
if ($request['status'] != 'pending') {
    $_SESSION['error_message'] = "This leave request has already been processed.";
    header("Location: view.php?id=" . $request_id);
    exit();
}

// Set page title
$page = 'All Leave Requests';

include '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">
                <i class="fas fa-check-circle text-success me-2"></i>Approve Leave Request
            </h1>
            <p class="text-muted mb-0">Review and approve leave request #<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="requests.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Requests
            </a>
            <a href="view.php?id=<?php echo $request_id; ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-1"></i>View Details
            </a>
            <a href="reject.php?id=<?php echo $request_id; ?>" class="btn btn-outline-danger">
                <i class="fas fa-times me-1"></i>Reject Instead
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <i class="fas fa-ban me-2"></i><?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Request Details (Full Width) -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">                <div class="card-header bg-primary text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>Leave Request #<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?> - Details
                        </h5>
                        <span class="badge bg-warning text-dark">
                            <i class="fas fa-clock me-1"></i>Pending Approval
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Employee:</th>
                                    <td>
                                        <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($request['emp_id']); ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($request['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Leave Type:</th>
                                    <td>
                                        <span class="badge" style="background-color: <?php echo $request['color'] ?? '#007bff'; ?>; color: white;">
                                            <?php echo htmlspecialchars($request['leave_type_name']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Start Date:</th>
                                    <td><?php echo date('F d, Y', strtotime($request['start_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>End Date:</th>
                                    <td><?php echo date('F d, Y', strtotime($request['end_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Duration:</th>
                                    <td>
                                        <span class="badge bg-info text-white">
                                            <?php echo $request['days_requested']; ?> 
                                            day<?php echo $request['days_requested'] != 1 ? 's' : ''; ?>
                                        </span>
                                        <?php if ($request['is_half_day']): ?>
                                            <br><small class="text-muted">
                                                Half Day - <?php echo ucfirst($request['half_day_period']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Applied Date:</th>
                                    <td><?php echo date('F d, Y \a\t g:i A', strtotime($request['applied_date'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><i class="fas fa-comment me-2"></i>Reason for Leave:</h6>
                            <div class="card border-0">
                                <div class="card-body">
                                    <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approval Form (Full Width) -->
    <div class="row mt-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm border-success">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-check-circle me-2"></i>Approve Leave Request
                    </h5>
                </div>
                <form method="POST" id="approvalForm">
                    <div class="card-body">
                        <div class="alert alert-info border-0">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle fa-2x text-info me-3"></i>
                                <div>
                                    <h6 class="alert-heading mb-1">Approval Confirmation</h6>
                                    <p class="mb-0">You are about to approve this leave request. The employee will be notified of the approval.</p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="approval_comments" class="form-label fw-medium">
                                        Approval Comments <span class="text-muted">(Optional)</span>
                                    </label>
                                    <textarea class="form-control" id="approval_comments" name="approval_comments" 
                                              rows="6" placeholder="Add any comments, conditions, or notes about this approval..."></textarea>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        These comments will be visible to the employee and stored for record keeping.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-medium">Quick Comments:</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-success quick-comment" 
                                            data-comment="Approved. Enjoy your leave and have a safe trip.">
                                        <i class="fas fa-smile me-1"></i>Standard Approval
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success quick-comment" 
                                            data-comment="Approved with the understanding that handover documentation has been completed.">
                                        <i class="fas fa-handshake me-1"></i>With Handover
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success quick-comment" 
                                            data-comment="Approved. Please ensure urgent matters are delegated appropriately.">
                                        <i class="fas fa-tasks me-1"></i>With Delegation
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success quick-comment" 
                                            data-comment="Approved. Please remain accessible for urgent matters if needed.">
                                        <i class="fas fa-phone me-1"></i>With Availability
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex gap-2">
                                <button type="submit" name="approve_request" class="btn btn-success">
                                    <i class="fas fa-check me-2"></i>Approve Request
                                </button>
                                <a href="reject.php?id=<?php echo $request_id; ?>" class="btn btn-danger">
                                    <i class="fas fa-times me-2"></i>Reject Instead
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Employee Leave Balance (Left Side) -->
        <div class="col-lg-4">
            <?php
            // Get employee's current year leave balance
            $balance_sql = "SELECT 
                lt.name as leave_type,
                COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END), 0) as used_days,
                lt.days_allowed as total_days,
                (lt.days_allowed - COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END), 0)) as remaining_days
                FROM leave_types lt
                LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id 
                    AND lr.employee_id = ? 
                    AND YEAR(lr.start_date) = YEAR(CURDATE())
                    AND lr.id != ?
                WHERE lt.id = ?
                GROUP BY lt.id, lt.name, lt.days_allowed";
            $balance_stmt = $pdo->prepare($balance_sql);
            $balance_stmt->execute([$request['employee_id'], $request['id'], $request['leave_type_id']]);
            $balance = $balance_stmt->fetch();
            ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i>Employee Leave Balance
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <h6 class="mb-3"><?php echo htmlspecialchars($balance['leave_type']); ?></h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-2 bg-success bg-opacity-10 rounded">
                                    <h5 class="text-success mb-1"><?php echo $balance['remaining_days']; ?></h5>
                                    <small class="text-muted">Remaining</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-info bg-opacity-10 rounded">
                                    <h5 class="text-info mb-1"><?php echo $balance['used_days']; ?></h5>
                                    <small class="text-muted">Used</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <?php 
                            $used_percentage = $balance['total_days'] > 0 ? ($balance['used_days'] / $balance['total_days']) * 100 : 0;
                            ?>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-info" role="progressbar" 
                                     style="width: <?php echo $used_percentage; ?>%"></div>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                Total: <?php echo $balance['total_days']; ?> days
                            </small>
                        </div>
                    </div>

                    <?php if (($balance['remaining_days'] - $request['days_requested']) < 0): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This approval will exceed balance by 
                            <?php echo abs($balance['remaining_days'] - $request['days_requested']); ?> days.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Best Practices -->
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="card-title mb-0"><i class="fas fa-star me-2"></i>Best Practices</h6>
                </div>
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge border border-primary rounded-pill text-info">Review Notice Period</span>
                        <span class="badge border border-primary rounded-pill text-info">Check Overlapping Leaves</span>
                        <span class="badge border border-primary rounded-pill text-info">Consider Business Impact</span>
                        <span class="badge border border-primary rounded-pill text-info">Document Decision</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <!-- Approval Guidelines and Best Practices (Full Width) -->
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-lightbulb me-2"></i>Approval Guidelines</h5>
                </div>
                <div class="card-body py-2">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Check Balance:</strong> Verify employee has sufficient leave days
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Team Coverage:</strong> Ensure adequate team coverage during absence
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Policy Compliance:</strong> Confirm request follows company policies
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Documentation:</strong> Add comments for future reference
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Notification:</strong> Employee will be notified automatically
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle quick comment buttons
    const quickCommentButtons = document.querySelectorAll('.quick-comment');
    const commentsTextarea = document.getElementById('approval_comments');
    
    quickCommentButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const comment = this.getAttribute('data-comment');
            const currentText = commentsTextarea.value.trim();
            
            if (currentText === '') {
                commentsTextarea.value = comment;
            } else {
                commentsTextarea.value = currentText + '\n\n' + comment;
            }
        });
    });

    // Form validation
    const approvalForm = document.getElementById('approvalForm');
    if (approvalForm) {
        approvalForm.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to approve this leave request?\n\nThe employee will be notified of the approval.')) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>

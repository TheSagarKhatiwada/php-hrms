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

// Get employee ID for current user (primary key ID, not emp_id)
// Note: $user_id from session is already the primary key ID from employees table
$currentEmployeeId = $user_id;

if (!$currentEmployeeId) {
    header("Location: ../../index.php");
    exit();
}

// Get request ID
$request_id = $_GET['id'] ?? 0;

if (!$request_id) {
    header("Location: my-requests.php");
    exit();
}

// Get leave request details - using PDO instead of mysqli
$sql = "SELECT lr.*, 
               e.first_name, e.last_name, e.emp_id, e.email, e.phone,
               lt.name as leave_type_name, lt.color,
               reviewer.first_name as reviewer_first_name, reviewer.last_name as reviewer_last_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.emp_id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        LEFT JOIN employees reviewer ON lr.approved_by = reviewer.emp_id
        WHERE lr.id = ?";

// Add permission check - employees can only view their own requests
if (!$is_admin_user) {
    $sql .= " AND lr.employee_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$request_id, $currentEmployeeId]);
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$request_id]);
}

$request = $stmt->fetch();

if (!$request) {
    $_SESSION['error_message'] = "Leave request not found or access denied.";
    header("Location: " . (!$is_admin_user ? 'my-requests.php' : 'requests.php'));
    exit();
}

// Set page title
$page = 'Leave Request Details';

include '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 mb-1">
                <i class="fas fa-eye me-2 text-primary"></i>Leave Request Details
            </h1>
            <p class="text-muted mb-0">
                #<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?> 
                - <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) . ' - ' . date('F d, Y', strtotime($request['start_date'])) . ' to ' . date('F d, Y', strtotime($request['end_date'])); ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if (!$is_admin_user): ?>
                <a href="my-requests.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to My Requests
                </a>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                    <i class="fas fa-plus me-1"></i>New Request
                </button>
            <?php else: ?>
                <a href="requests.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to All Requests
                </a>
                <?php if ($request['status'] == 'pending'): ?>
                    <a href="approve.php?id=<?php echo $request['id']; ?>" class="btn btn-outline-success">
                        <i class="fas fa-check me-1"></i>Approve
                    </a>
                    <a href="reject.php?id=<?php echo $request['id']; ?>" class="btn btn-outline-danger">
                        <i class="fas fa-times me-1"></i>Reject
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <i class="fas fa-ban me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>    <!-- Request Details (Full Width) -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>Leave Request #<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?> - Details
                        </h5>
                        <?php
                        $status_class = '';
                        $status_icon = '';
                        $status_bg = '';
                        switch ($request['status']) {
                            case 'pending':
                                $status_class = 'warning';
                                $status_icon = 'clock';
                                $status_bg = 'bg-warning text-dark';
                                break;
                            case 'approved':
                                $status_class = 'success';
                                $status_icon = 'check-circle';
                                $status_bg = 'bg-success';
                                break;
                            case 'rejected':
                                $status_class = 'danger';
                                $status_icon = 'times-circle';
                                $status_bg = 'bg-danger';
                                break;
                            case 'cancelled':
                                $status_class = 'secondary';
                                $status_icon = 'ban';
                                $status_bg = 'bg-secondary';
                                break;
                        }
                        ?>
                        <span class="badge <?php echo $status_bg; ?>">
                            <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                            <?php echo ucfirst($request['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
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
                                    <td>
                                        <?php if ($request['email']): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($request['email']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($request['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td>
                                        <?php if ($request['phone']): ?>
                                            <a href="tel:<?php echo htmlspecialchars($request['phone']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($request['phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
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
                                <?php if ($request['status'] != 'pending' && $request['reviewed_date']): ?>
                                <tr>
                                    <th>Reviewed Date:</th>
                                    <td>
                                        <?php echo date('F d, Y \a\t g:i A', strtotime($request['reviewed_date'])); ?>
                                        <?php if ($request['reviewer_first_name']): ?>
                                            <br><small class="text-muted">
                                                by <?php echo htmlspecialchars($request['reviewer_first_name'] . ' ' . $request['reviewer_last_name']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><i class="fas fa-comment me-2"></i>Reason for Leave:</h6>
                            <div class="card border-1">
                                <div class="card-body">
                                    <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($request['approval_comments'])): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><i class="fas fa-check-circle text-success me-2"></i>Approval Comments:</h6>
                            <div class="card bg-success bg-opacity-10 border-success">
                                <div class="card-body">
                                    <?php echo nl2br(htmlspecialchars($request['approval_comments'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($request['rejection_reason'])): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><i class="fas fa-exclamation-triangle text-danger me-2"></i>Rejection Reason:</h6>
                            <div class="card bg-danger bg-opacity-10 border-danger">
                                <div class="card-body">
                                    <?php echo nl2br(htmlspecialchars($request['rejection_reason'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>                </div>
            </div>
        </div>
    </div>    <!-- Timeline & Actions (Two Column Layout) -->
    <div class="row mt-3">
        <!-- Timeline Column (Left) -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>Request Timeline
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <!-- Request Submitted -->
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary">
                                <i class="fas fa-paper-plane text-white"></i>
                            </div>
                            <div class="timeline-content">
                                <h6 class="fw-bold mb-1">Request Submitted</h6>
                                <p class="text-muted mb-1">
                                    <?php echo date('M d, Y \a\t g:i A', strtotime($request['applied_date'])); ?>
                                </p>
                                <small class="text-muted">
                                    Submitted by <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                </small>
                            </div>
                        </div>
                        
                        <?php if ($request['status'] != 'pending'): ?>
                        <!-- Request Reviewed -->
                        <div class="timeline-item">
                            <div class="timeline-marker bg-<?php echo $status_class; ?>">
                                <i class="fas fa-<?php echo $status_icon; ?> text-white"></i>
                            </div>
                            <div class="timeline-content">
                                <h6 class="fw-bold mb-1">Request <?php echo ucfirst($request['status']); ?></h6>
                                <p class="text-muted mb-1">
                                    <?php echo $request['reviewed_date'] ? date('M d, Y \a\t g:i A', strtotime($request['reviewed_date'])) : 'N/A'; ?>
                                </p>
                                <small class="text-muted">
                                    <?php if ($request['reviewer_first_name']): ?>
                                        Reviewed by <?php echo htmlspecialchars($request['reviewer_first_name'] . ' ' . $request['reviewer_last_name']); ?>
                                    <?php else: ?>
                                        Processed by system
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Pending Status -->
                        <div class="timeline-item">
                            <div class="timeline-marker bg-warning">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                            <div class="timeline-content">
                                <h6 class="fw-bold mb-1">Awaiting Review</h6>
                                <small class="text-muted">
                                    Request is pending approval from management
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Actions & Stats -->
        <div class="col-lg-4">
            <!-- Available Actions Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-secondary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cogs me-2"></i>Available Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if (!$is_admin_user): ?>
                            <!-- Employee Actions -->
                            <?php if ($request['status'] == 'pending'): ?>
                                <button class="btn btn-outline-danger" onclick="cancelRequest(<?php echo $request['id']; ?>)">
                                    <i class="fas fa-times me-2"></i>Cancel Request
                                </button>
                            <?php endif; ?>
                            <a href="calendar.php" class="btn btn-outline-info">
                                <i class="fas fa-calendar me-2"></i>View Calendar
                            </a>
                            
                        <?php else: ?>
                            <!-- Admin/HR Actions -->
                            <a href="calendar.php" class="btn btn-outline-info">
                                <i class="fas fa-calendar me-2"></i>View Calendar
                            </a>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Statistics Card -->
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Quick Statistics
                    </h5>
                </div>                <div class="card-body">
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="p-2 bg-primary bg-opacity-10 rounded">
                                <div class="text-light fw-bold"><?php echo $request['days_requested']; ?></div>
                                <small class="text-muted">Days Requested</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-success bg-opacity-10 rounded">
                                <div class="text-success fw-bold">
                                    <?php
                                    // Fetch available leave for this type for the employee
                                    $available = 0;
                                    try {
                                        $leaveTypeId = $request['leave_type_id'];
                                        $stmtAvail = $pdo->prepare("SELECT available FROM leave_balances WHERE employee_id = ? AND leave_type_id = ?");
                                        $stmtAvail->execute([$request['employee_id'], $leaveTypeId]);
                                        $rowAvail = $stmtAvail->fetch();
                                        if ($rowAvail) {
                                            $available = $rowAvail['available'];
                                        }
                                    } catch (Exception $e) {
                                        $available = 'N/A';
                                    }
                                    echo htmlspecialchars($available);
                                    ?>
                                </div>
                                <small class="text-muted">Available</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($request['status'] == 'pending'): ?>
                    <div class="mt-3 p-2 bg-warning bg-opacity-10 rounded">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle text-warning me-2"></i>
                            <small class="text-warning fw-bold">
                                Pending approval from management
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Request Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="cancelModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Cancel Leave Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <i class="fas fa-question-circle text-warning display-4 mb-3"></i>
                    <h6 class="fw-bold">Are you sure you want to cancel this leave request?</h6>
                    <p class="text-muted mb-0">This action cannot be undone. Your leave request will be permanently cancelled.</p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>No, Keep It
                </button>
                <button type="button" class="btn btn-danger" id="confirmCancel">
                    <i class="fas fa-check me-1"></i>Yes, Cancel Request
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS for Timeline -->
<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--bs-border-color);
}

.timeline-item {
    position: relative;
    margin-bottom: 25px;
}

.timeline-marker {
    position: absolute;
    left: -23px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    border: 3px solid var(--bs-body-bg);
    box-shadow: 0 0 0 2px var(--bs-border-color);
}

.timeline-content {
    border: 1px solid var(--bs-border-color);
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #007bff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Dark theme support */
[data-bs-theme="dark"] .timeline-content {
    border-color: var(--bs-gray-600);
    color: var(--bs-gray-100);
}
[data-bs-theme="dark"] .timeline-content .text-muted {
    color: var(--bs-gray-400) !important;
}

/* Light theme support */
[data-bs-theme="light"] .timeline-content {
    background: var(--bs-gray-50);
    border-color: var(--bs-gray-300);
    color: var(--bs-gray-900);
}

[data-bs-theme="light"] .timeline-content .text-muted {
    color: var(--bs-gray-600) !important;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<script>
let requestToCancel = null;

function cancelRequest(requestId) {
    requestToCancel = requestId;
    const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
    modal.show();
}

document.getElementById('confirmCancel').addEventListener('click', function() {
    if (requestToCancel) {
        // Show loading state
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Cancelling...';
        this.disabled = true;
        
        // Send AJAX request to cancel the leave request
        fetch('cancel-request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'request_id=' + requestToCancel
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                const modal = bootstrap.Modal.getInstance(document.getElementById('cancelModal'));
                modal.hide();
                
                // Show success alert
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>
                    Leave request cancelled successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.container-fluid').firstChild);
                
                // Redirect after delay
                setTimeout(() => {
                    window.location.href = 'my-requests.php';
                }, 2000);
            } else {
                throw new Error(data.message || 'Unknown error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
            
            // Reset button
            this.innerHTML = '<i class="fas fa-check me-1"></i>Yes, Cancel Request';
            this.disabled = false;
        });
    }
});

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.classList.contains('show')) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 150);
            }
        });
    }, 5000);
});
</script>

<?php include '../../includes/footer.php'; ?>

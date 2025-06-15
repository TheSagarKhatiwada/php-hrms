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

// Get employee ID for current user (primary key ID, not emp_id)
// Note: $user_id from session is already the primary key ID from employees table
$currentEmployeeId = $user_id;

if (!$currentEmployeeId) {
    header("Location: ../../index.php");
    exit();
}

// Get user's leave requests
$sql = "SELECT lr.*, lt.name as leave_type_name, lt.color
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.employee_id = ?
        ORDER BY lr.applied_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$currentEmployeeId]);
$requests_result = $stmt->fetchAll();

// Get leave statistics
$stats_sql = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
    SUM(CASE WHEN status = 'approved' THEN days_requested ELSE 0 END) as total_days_taken
    FROM leave_requests 
    WHERE employee_id = ? AND YEAR(start_date) = YEAR(CURDATE())";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute([$currentEmployeeId]);
$stats = $stats_stmt->fetch();

include '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-list-alt me-2"></i>My Leave Requests</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-success">
                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
            </a>
            <a href="calendar.php" class="btn btn-outline-info">
                <i class="fas fa-calendar me-1"></i>Calendar
            </a>
            <a href="request.php" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>Apply for Leave
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <i class="fas fa-check me-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                Total Requests
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $stats['total_requests'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                Pending
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $stats['pending_requests'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                Approved
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $stats['approved_requests'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-secondary text-uppercase mb-1">
                                Total Days Taken
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $stats['total_days_taken'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-times fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>    <!-- Leave Requests Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-table me-2"></i>Leave Request History</h6>
        </div>
        <div class="card-body">
            <?php if (count($requests_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="leaveRequestsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Applied Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests_result as $request): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">
                                            #<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: <?php echo $request['color'] ?? '#007bff'; ?>; color: white;">
                                            <?php echo htmlspecialchars($request['leave_type_name']); ?>
                                        </span>                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $request['days_requested']; ?> 
                                            <?php echo $request['is_half_day'] ? '(Half Day)' : 'days'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_icon = '';
                                        switch ($request['status']) {
                                            case 'pending':
                                                $status_class = 'warning';
                                                $status_icon = 'clock';
                                                break;
                                            case 'approved':
                                                $status_class = 'success';
                                                $status_icon = 'check-circle';
                                                break;
                                            case 'rejected':
                                                $status_class = 'danger';
                                                $status_icon = 'times-circle';
                                                break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($request['applied_date'])); ?></td>
                                    <td>
                                        <a href="view.php?id=<?php echo $request['id']; ?>" 
                                           class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($request['status'] == 'pending'): ?>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="cancelRequest(<?php echo $request['id']; ?>)" 
                                                    title="Cancel Request">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-calendar-times fa-3x mb-3"></i>
                    <h5 class="text-muted">No Leave Requests Found</h5>
                    <p class="text-muted">You haven't submitted any leave requests yet.</p>
                    <a href="request.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Apply for Leave
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Cancel Request Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Cancel Leave Request</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this leave request?</p>
                <p class="text-danger"><strong>Note:</strong> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep It</button>
                <button type="button" class="btn btn-danger" id="confirmCancel">Yes, Cancel Request</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#leaveRequestsTable').DataTable({
        "responsive": true,
        "autoWidth": false,
        "order": [[6, "desc"]], // Sort by Applied Date descending
        "pageLength": 10,
        "language": {
            "emptyTable": "No leave requests found",
            "zeroRecords": "No matching leave requests found"
        }
    });
});

let requestToCancel = null;

function cancelRequest(requestId) {
    requestToCancel = requestId;
    $('#cancelModal').modal('show');
}

$('#confirmCancel').click(function() {
    if (requestToCancel) {
        // Send AJAX request to cancel the leave request
        $.ajax({
            url: 'cancel-request.php',
            type: 'POST',
            data: { request_id: requestToCancel },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message and reload page
                    alert('Leave request cancelled successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while cancelling the request.');
            }
        });
    }
    $('#cancelModal').modal('hide');
});
</script>

<?php include '../../includes/footer.php'; ?>
        "language": {
            "emptyTable": "No leave requests found",
            "zeroRecords": "No matching leave requests found"
        }
    });
});

let requestToCancel = null;

function cancelRequest(requestId) {
    requestToCancel = requestId;
    $('#cancelModal').modal('show');
}

$('#confirmCancel').click(function() {
    if (requestToCancel) {
        // Send AJAX request to cancel the leave request
        $.ajax({
            url: 'cancel-request.php',
            type: 'POST',
            data: { request_id: requestToCancel },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message and reload page
                    alert('Leave request cancelled successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while cancelling the request.');
            }
        });
    }
    $('#cancelModal').modal('hide');
});
</script>

<?php include '../../includes/footer.php'; ?>

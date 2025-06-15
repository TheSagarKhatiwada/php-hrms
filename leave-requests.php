<?php
// Include session configuration first to ensure session is available
require_once 'includes/session_config.php';
require_once 'includes/utilities.php';

$page = 'leave-requests';

include 'includes/db_connection.php';

// Check user permissions
$isAdmin = is_admin();
$currentUserId = $_SESSION['user_id'] ?? null;

// Get employee ID for current user
$currentEmployeeId = null;
if ($currentUserId) {
    try {
        $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE emp_id = ?");
        $stmt->execute([$currentUserId]);
        $result = $stmt->fetch();
        if ($result) {
            $currentEmployeeId = $result['emp_id'];
        }
    } catch (PDOException $e) {
        // Handle error
    }
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $employee_id = $isAdmin ? (int)$_POST['employee_id'] : $currentEmployeeId;
                $leave_type_id = (int)$_POST['leave_type_id'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $reason = trim($_POST['reason']);
                $emergency_contact = trim($_POST['emergency_contact']);
                $emergency_phone = trim($_POST['emergency_phone']);
                
                // Calculate total days
                $start = new DateTime($start_date);
                $end = new DateTime($end_date);
                $interval = $start->diff($end);
                $total_days = $interval->days + 1;
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, reason, emergency_contact, emergency_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$employee_id, $leave_type_id, $start_date, $end_date, $total_days, $reason, $emergency_contact, $emergency_phone]);
                    $_SESSION['success'] = "Leave request submitted successfully!";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error submitting leave request: " . $e->getMessage();
                }
                break;
                
            case 'approve':
                if ($isAdmin) {
                    $id = (int)$_POST['id'];
                    $approved_by = $currentEmployeeId;
                    
                    try {
                        $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_date = NOW() WHERE id = ?");
                        $stmt->execute([$approved_by, $id]);
                        $_SESSION['success'] = "Leave request approved successfully!";
                    } catch (PDOException $e) {
                        $_SESSION['error'] = "Error approving leave request: " . $e->getMessage();
                    }
                }
                break;
                
            case 'reject':
                if ($isAdmin) {
                    $id = (int)$_POST['id'];
                    $rejection_reason = trim($_POST['rejection_reason']);
                    $approved_by = $currentEmployeeId;
                    
                    try {
                        $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'rejected', approved_by = ?, approved_date = NOW(), rejection_reason = ? WHERE id = ?");
                        $stmt->execute([$approved_by, $rejection_reason, $id]);
                        $_SESSION['success'] = "Leave request rejected successfully!";
                    } catch (PDOException $e) {
                        $_SESSION['error'] = "Error rejecting leave request: " . $e->getMessage();
                    }
                }
                break;
                
            case 'cancel':
                $id = (int)$_POST['id'];
                // Users can only cancel their own requests
                $whereClause = $isAdmin ? "id = ?" : "id = ? AND employee_id = ?";
                $params = $isAdmin ? [$id] : [$id, $currentEmployeeId];
                
                try {
                    $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE $whereClause");
                    $stmt->execute($params);
                    $_SESSION['success'] = "Leave request cancelled successfully!";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error cancelling leave request: " . $e->getMessage();
                }
                break;
        }
        header('Location: leave-requests.php?_nocache=' . time());
        exit();
    }
}

// Fetch leave requests based on user role
try {
    $whereClause = $isAdmin ? "" : "WHERE lr.employee_id = ?";
    $params = $isAdmin ? [] : [$currentEmployeeId];
    
    $stmt = $pdo->prepare("
        SELECT lr.*, lt.name as leave_type_name, lt.code as leave_type_code,
               e.first_name, e.last_name, e.emp_id, e.user_image,
               d.title as designation_title, b.name as branch_name,
               CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               approver.first_name as approver_first_name, 
               approver.last_name as approver_last_name
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        JOIN employees e ON lr.employee_id = e.emp_id
        LEFT JOIN employees approver ON lr.approved_by = approver.emp_id
        LEFT JOIN designations d ON e.designation = d.id
        LEFT JOIN branches b ON e.branch = b.id
        $whereClause
        ORDER BY lr.applied_date DESC
    ");
    $stmt->execute($params);
    $leaveRequests = $stmt->fetchAll();
    
    // Fetch active leave types for the form
    $stmt = $pdo->query("SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name ASC");
    $activeLeaveTypes = $stmt->fetchAll();
    
    // Fetch employees for admin form
    $employees = [];
    if ($isAdmin) {
        $stmt = $pdo->query("SELECT emp_id, first_name, last_name, CONCAT(first_name, ' ', last_name) as full_name FROM employees WHERE exit_date IS NULL ORDER BY first_name ASC");
        $employees = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching leave requests: " . $e->getMessage();
    $leaveRequests = [];
    $activeLeaveTypes = [];
    $employees = [];
}

// Force fresh load by checking for cache-busting parameter
if (!isset($_GET['_nocache'])) {
    header("Location: leave-requests.php?_nocache=" . time());
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
            <h1 class="fs-2 fw-bold mb-1">Leave Requests</h1>
            <p class="text-muted mb-0"><?php echo $isAdmin ? 'Manage all employee leave requests' : 'Your leave requests and applications'; ?></p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" id="refreshLeaveRequests" class="btn btn-outline-primary" onclick="forceRefresh()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeaveRequestModal">
                <i class="fas fa-plus me-2"></i> New Leave Request
            </button>
        </div>
    </div>
    
    <!-- Leave Requests Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if (empty($leaveRequests)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Leave Requests</h5>
                    <p class="text-muted">No leave requests have been submitted yet.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeaveRequestModal">
                        <i class="fas fa-plus me-2"></i> Submit Your First Request
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="leave-requests-table" class="table table-hover">
                        <thead>
                            <tr>
                                <?php if ($isAdmin): ?>
                                    <th>Employee</th>
                                <?php endif; ?>
                                <th>Leave Type</th>
                                <th>Period</th>
                                <th class="text-center">Days</th>
                                <th>Applied Date</th>
                                <th class="text-center">Status</th>
                                <th>Reason</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaveRequests as $request): ?>
                                <tr>
                                    <?php if ($isAdmin): ?>
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
                                    <?php endif; ?>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($request['leave_type_name']); ?></span>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($request['leave_type_code']); ?></small>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <strong>From:</strong> <?php echo date('M d, Y', strtotime($request['start_date'])); ?><br>
                                            <strong>To:</strong> <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold fs-5"><?php echo $request['total_days']; ?></span>
                                        <small class="text-muted d-block">days</small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($request['applied_date'])); ?></td>
                                    <td class="text-center">
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
                                        <?php if ($request['approved_date']): ?>
                                            <br><small class="text-muted"><?php echo date('M d, Y', strtotime($request['approved_date'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small" style="max-width: 200px;">
                                            <?php echo htmlspecialchars(substr($request['reason'], 0, 100)); ?>
                                            <?php if (strlen($request['reason']) > 100): ?>...<?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <a href="#" class="text-secondary" role="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </a>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item view-leave-request" href="#"
                                                       data-bs-toggle="modal" 
                                                       data-bs-target="#viewLeaveRequestModal"
                                                       data-id="<?php echo $request['id']; ?>"
                                                       data-employee="<?php echo htmlspecialchars($request['employee_name']); ?>"
                                                       data-leave-type="<?php echo htmlspecialchars($request['leave_type_name']); ?>"
                                                       data-start="<?php echo $request['start_date']; ?>"
                                                       data-end="<?php echo $request['end_date']; ?>"
                                                       data-days="<?php echo $request['total_days']; ?>"
                                                       data-reason="<?php echo htmlspecialchars($request['reason']); ?>"
                                                       data-status="<?php echo $request['status']; ?>"
                                                       data-applied="<?php echo $request['applied_date']; ?>"
                                                       data-approved="<?php echo $request['approved_date']; ?>"
                                                       data-approver="<?php echo htmlspecialchars($request['approver_first_name'] . ' ' . $request['approver_last_name']); ?>"
                                                       data-rejection="<?php echo htmlspecialchars($request['rejection_reason']); ?>"
                                                       data-emergency-contact="<?php echo htmlspecialchars($request['emergency_contact']); ?>"
                                                       data-emergency-phone="<?php echo htmlspecialchars($request['emergency_phone']); ?>">
                                                        <i class="fas fa-eye me-2"></i> View Details
                                                    </a>
                                                </li>
                                                
                                                <?php if ($isAdmin && $request['status'] == 'pending'): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-success approve-leave" href="#"
                                                           data-bs-toggle="modal" 
                                                           data-bs-target="#approveLeaveModal"
                                                           data-id="<?php echo $request['id']; ?>"
                                                           data-employee="<?php echo htmlspecialchars($request['employee_name']); ?>">
                                                            <i class="fas fa-check me-2"></i> Approve
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-danger reject-leave" href="#"
                                                           data-bs-toggle="modal" 
                                                           data-bs-target="#rejectLeaveModal"
                                                           data-id="<?php echo $request['id']; ?>"
                                                           data-employee="<?php echo htmlspecialchars($request['employee_name']); ?>">
                                                            <i class="fas fa-times me-2"></i> Reject
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php if ($request['status'] == 'pending' && ($isAdmin || $request['employee_id'] == $currentEmployeeId)): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-warning cancel-leave" href="#"
                                                           data-bs-toggle="modal" 
                                                           data-bs-target="#cancelLeaveModal"
                                                           data-id="<?php echo $request['id']; ?>"
                                                           data-employee="<?php echo htmlspecialchars($request['employee_name']); ?>">
                                                            <i class="fas fa-ban me-2"></i> Cancel
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

<!-- Add Leave Request Modal -->
<div class="modal fade" id="addLeaveRequestModal" tabindex="-1" aria-labelledby="addLeaveRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLeaveRequestModalLabel">New Leave Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row g-3">
                        <?php if ($isAdmin): ?>
                            <div class="col-12">
                                <label for="employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                                <select class="form-select" id="employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['emp_id']; ?>">
                                            <?php echo htmlspecialchars($employee['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <div class="col-12">
                            <label for="leave_type_id" class="form-label">Leave Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="leave_type_id" name="leave_type_id" required>
                                <option value="">Select Leave Type</option>
                                <?php foreach ($activeLeaveTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            data-days="<?php echo $type['days_allowed_per_year']; ?>"
                                            data-paid="<?php echo $type['is_paid']; ?>">
                                        <?php echo htmlspecialchars($type['name']); ?>
                                        <?php if ($type['days_allowed_per_year'] > 0): ?>
                                            (<?php echo $type['days_allowed_per_year']; ?> days/year)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="col-12">
                            <div class="alert alert-info" id="days_calculation" style="display: none;">
                                <strong>Total Days:</strong> <span id="total_days">0</span> days
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="4" required
                                      placeholder="Please provide a detailed reason for your leave request..."></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="emergency_contact" class="form-label">Emergency Contact Name</label>
                            <input type="text" class="form-control" id="emergency_contact" name="emergency_contact"
                                   placeholder="Contact person during leave">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="emergency_phone" class="form-label">Emergency Contact Phone</label>
                            <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone"
                                   placeholder="Contact phone number">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Leave Request Modal -->
<div class="modal fade" id="viewLeaveRequestModal" tabindex="-1" aria-labelledby="viewLeaveRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewLeaveRequestModalLabel">Leave Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Employee:</label>
                        <p id="view_employee" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Leave Type:</label>
                        <p id="view_leave_type" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Start Date:</label>
                        <p id="view_start_date" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">End Date:</label>
                        <p id="view_end_date" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Total Days:</label>
                        <p id="view_total_days" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Status:</label>
                        <p id="view_status" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Reason:</label>
                        <p id="view_reason" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6" id="view_emergency_contact_section">
                        <label class="form-label fw-bold">Emergency Contact:</label>
                        <p id="view_emergency_contact" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6" id="view_emergency_phone_section">
                        <label class="form-label fw-bold">Emergency Phone:</label>
                        <p id="view_emergency_phone" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Applied Date:</label>
                        <p id="view_applied_date" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-md-6" id="view_approved_section" style="display: none;">
                        <label class="form-label fw-bold">Approved Date:</label>
                        <p id="view_approved_date" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-12" id="view_approver_section" style="display: none;">
                        <label class="form-label fw-bold">Approved/Rejected By:</label>
                        <p id="view_approver" class="form-control-plaintext"></p>
                    </div>
                    <div class="col-12" id="view_rejection_section" style="display: none;">
                        <label class="form-label fw-bold">Rejection Reason:</label>
                        <p id="view_rejection_reason" class="form-control-plaintext text-danger"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Leave Modal -->
<div class="modal fade" id="approveLeaveModal" tabindex="-1" aria-labelledby="approveLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveLeaveModalLabel">Approve Leave Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="id" id="approve_id">
                    <p>Are you sure you want to approve the leave request for <span id="approve_employee" class="fw-bold"></span>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Leave Modal -->
<div class="modal fade" id="rejectLeaveModal" tabindex="-1" aria-labelledby="rejectLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectLeaveModalLabel">Reject Leave Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" id="reject_id">
                    <p>Rejecting leave request for <span id="reject_employee" class="fw-bold"></span>.</p>
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required
                                  placeholder="Please provide a reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Leave Modal -->
<div class="modal fade" id="cancelLeaveModal" tabindex="-1" aria-labelledby="cancelLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelLeaveModalLabel">Cancel Leave Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="id" id="cancel_id">
                    <p>Are you sure you want to cancel the leave request for <span id="cancel_employee" class="fw-bold"></span>?</p>
                    <p class="text-warning small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Cancel Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include the main footer (which closes content-wrapper, main-wrapper, etc.) -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    const tableColumns = <?php echo $isAdmin ? '8' : '7'; ?>; // Adjust based on admin view
    new DataTable('#leave-requests-table', {
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        order: [[<?php echo $isAdmin ? '4' : '3'; ?>, 'desc']], // Sort by applied date
        pageLength: 10,
        language: {
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            }
        }
    });
    
    // Calculate total days when dates change
    function calculateDays() {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            
            if (end >= start) {
                const timeDiff = end.getTime() - start.getTime();
                const dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
                
                document.getElementById('total_days').textContent = dayDiff;
                document.getElementById('days_calculation').style.display = 'block';
                
                // Update end date minimum
                document.getElementById('end_date').min = startDate;
            } else {
                document.getElementById('days_calculation').style.display = 'none';
            }
        } else {
            document.getElementById('days_calculation').style.display = 'none';
        }
    }
    
    // Add event listeners for date calculation
    document.getElementById('start_date').addEventListener('change', calculateDays);
    document.getElementById('end_date').addEventListener('change', calculateDays);
    
    // View leave request modal handler
    const viewModal = document.getElementById('viewLeaveRequestModal');
    if (viewModal) {
        viewModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            // Set basic info
            document.getElementById('view_employee').textContent = button.getAttribute('data-employee');
            document.getElementById('view_leave_type').textContent = button.getAttribute('data-leave-type');
            document.getElementById('view_start_date').textContent = new Date(button.getAttribute('data-start')).toLocaleDateString();
            document.getElementById('view_end_date').textContent = new Date(button.getAttribute('data-end')).toLocaleDateString();
            document.getElementById('view_total_days').textContent = button.getAttribute('data-days') + ' days';
            document.getElementById('view_reason').textContent = button.getAttribute('data-reason');
            document.getElementById('view_applied_date').textContent = new Date(button.getAttribute('data-applied')).toLocaleDateString();
            
            // Set status with badge
            const status = button.getAttribute('data-status');
            const statusClasses = {
                'pending': 'badge bg-warning',
                'approved': 'badge bg-success',
                'rejected': 'badge bg-danger',
                'cancelled': 'badge bg-secondary'
            };
            document.getElementById('view_status').innerHTML = `<span class="${statusClasses[status] || 'badge bg-secondary'}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
            
            // Emergency contact
            const emergencyContact = button.getAttribute('data-emergency-contact');
            const emergencyPhone = button.getAttribute('data-emergency-phone');
            
            if (emergencyContact) {
                document.getElementById('view_emergency_contact').textContent = emergencyContact;
                document.getElementById('view_emergency_contact_section').style.display = 'block';
            } else {
                document.getElementById('view_emergency_contact_section').style.display = 'none';
            }
            
            if (emergencyPhone) {
                document.getElementById('view_emergency_phone').textContent = emergencyPhone;
                document.getElementById('view_emergency_phone_section').style.display = 'block';
            } else {
                document.getElementById('view_emergency_phone_section').style.display = 'none';
            }
            
            // Approval info
            const approvedDate = button.getAttribute('data-approved');
            const approver = button.getAttribute('data-approver');
            const rejectionReason = button.getAttribute('data-rejection');
            
            if (approvedDate && approvedDate !== 'null') {
                document.getElementById('view_approved_date').textContent = new Date(approvedDate).toLocaleDateString();
                document.getElementById('view_approved_section').style.display = 'block';
            } else {
                document.getElementById('view_approved_section').style.display = 'none';
            }
            
            if (approver && approver !== 'null' && approver.trim()) {
                document.getElementById('view_approver').textContent = approver;
                document.getElementById('view_approver_section').style.display = 'block';
            } else {
                document.getElementById('view_approver_section').style.display = 'none';
            }
            
            if (rejectionReason && rejectionReason !== 'null') {
                document.getElementById('view_rejection_reason').textContent = rejectionReason;
                document.getElementById('view_rejection_section').style.display = 'block';
            } else {
                document.getElementById('view_rejection_section').style.display = 'none';
            }
        });
    }
    
    // Approve leave modal handler
    const approveModal = document.getElementById('approveLeaveModal');
    if (approveModal) {
        approveModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('approve_id').value = button.getAttribute('data-id');
            document.getElementById('approve_employee').textContent = button.getAttribute('data-employee');
        });
    }
    
    // Reject leave modal handler
    const rejectModal = document.getElementById('rejectLeaveModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('reject_id').value = button.getAttribute('data-id');
            document.getElementById('reject_employee').textContent = button.getAttribute('data-employee');
        });
    }
    
    // Cancel leave modal handler
    const cancelModal = document.getElementById('cancelLeaveModal');
    if (cancelModal) {
        cancelModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('cancel_id').value = button.getAttribute('data-id');
            document.getElementById('cancel_employee').textContent = button.getAttribute('data-employee');
        });
    }
});

function forceRefresh() {
    window.location.href = 'leave-requests.php?_nocache=' + new Date().getTime();
}
</script>

<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once 'accrual.php'; // Include accrual system

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get employee ID for current user (user_id is already the primary key ID)
$currentEmployeeId = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    if ($result) {
        $currentEmployeeId = $result['id']; // Use the primary key ID
    }
} catch (PDOException $e) {
    error_log("Error fetching employee ID: " . $e->getMessage());
    header("Location: ../../index.php");
    exit();
}

if (!$currentEmployeeId) {
    header("Location: ../../index.php");
    exit();
}

// Handle form submission
if ($_POST && isset($_POST['submit_request'])) {
    try {
        $leave_type_id = intval($_POST['leave_type_id']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = trim($_POST['reason']);
        $is_half_day = isset($_POST['is_half_day']) ? 1 : 0;
        $half_day_period = $is_half_day ? $_POST['half_day_period'] : null;
        
        // Validate dates
        if (strtotime($start_date) > strtotime($end_date)) {
            throw new Exception("End date cannot be earlier than start date.");
        }
        
        if (strtotime($start_date) < strtotime(date('Y-m-d'))) {
            throw new Exception("Cannot apply for leave in the past.");        }
        
        // Calculate number of days
        $days = 1;
        if (!$is_half_day) {
            $datetime1 = new DateTime($start_date);
            $datetime2 = new DateTime($end_date);
            $interval = $datetime1->diff($datetime2);
            $days = $interval->days + 1;
        } else {
            $days = 0.5;
        }
        
        // Check leave balance using accrual system
        $balance_check = checkLeaveBalance($currentEmployeeId, $leave_type_id, $days);
        
        if (!$balance_check['can_apply']) {
            throw new Exception($balance_check['message']);
        }
        
        // Check for overlapping leave requests
        $check_sql = "SELECT id FROM leave_requests 
             WHERE employee_id = ? 
                     AND status != 'rejected' 
                     AND ((start_date <= ? AND end_date >= ?) 
                          OR (start_date <= ? AND end_date >= ?)
                          OR (start_date >= ? AND end_date <= ?))";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$currentEmployeeId, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date]);
        
        if ($check_stmt->rowCount() > 0) {
            throw new Exception("You already have a leave request for the selected dates.");
        }
        
        // Insert leave request
        $sql = "INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, days_requested, status, is_half_day, half_day_period, applied_date) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$currentEmployeeId, $leave_type_id, $start_date, $end_date, $reason, $days, $is_half_day, $half_day_period])) {
            $request_id = $pdo->lastInsertId();
            
            // Send notification
            include_once 'notifications.php';
            sendLeaveNotification('submitted', $request_id);
            
            $_SESSION['success_message'] = "Leave request submitted successfully!";
            header("Location: my-requests.php");
            exit();
        } else {
            throw new Exception("Error submitting leave request.");
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get leave types
$leave_types_sql = "SELECT * FROM leave_types ORDER BY name";
$leave_types_result = $pdo->query($leave_types_sql);
$leave_types = $leave_types_result->fetchAll();

// Get employee leave balance using accrual system
$balance_result = getEmployeeLeaveBalance($currentEmployeeId);

include '../../includes/header.php';
?>

<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">
                <i class="fas fa-calendar-plus me-2 text-primary"></i>Apply for Leave
            </h1>
        </div>
        <div class="d-flex gap-2">
            <a href="my-requests.php" class="btn btn-outline-primary">
                <i class="fas fa-list me-1"></i>My Requests
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-ban me-2"></i><?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>    <div class="row">
        <!-- Leave Balance Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-chart-pie me-2"></i>Leave Balance
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (count($balance_result) > 0): ?>
                        <?php foreach ($balance_result as $balance): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold"><?php echo htmlspecialchars($balance['leave_type_name']); ?></span>
                                    <span class="badge <?php echo $balance['remaining_days'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $balance['remaining_days']; ?> days left
                                    </span>
                                </div>
                                <div class="progress mt-1" style="height: 6px;">
                                    <?php 
                                    $used_percentage = $balance['allocated_days'] > 0 ? ($balance['used_days'] / $balance['allocated_days']) * 100 : 0;
                                    ?>
                                    <div class="progress-bar bg-primary" role="progressbar" 
                                         style="width: <?php echo $used_percentage; ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    Used: <?php echo $balance['used_days']; ?> / <?php echo $balance['allocated_days']; ?> days
                                    <br>
                                    <span class="text-info">Accrued to date: <?php echo $balance['total_accrued_ytd']; ?> days</span>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted py-4">No leave balances found. Please contact HR to initialize your leave accruals.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
          <!-- Leave Request Form -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-edit me-2"></i>Leave Request Form
                    </h6>
                </div>
                <form method="POST" id="leaveRequestForm">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="leave_type_id" class="form-label">Leave Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="leave_type_id" name="leave_type_id" required>
                                        <option value="">Select Leave Type</option>
                                        <?php foreach ($leave_types as $type): ?>
                                            <?php 
                                            // Find corresponding balance for this leave type
                                            $type_balance = null;
                                            foreach ($balance_result as $balance) {
                                                if ($balance['leave_type_id'] == $type['id']) {
                                                    $type_balance = $balance;
                                                    break;
                                                }
                                            }
                                            ?>
                                            <option value="<?php echo $type['id']; ?>" 
                                                    data-days="<?php echo $type_balance ? $type_balance['total_accrued_ytd'] : 0; ?>"
                                                    data-available="<?php echo $type_balance ? $type_balance['remaining_days'] : 0; ?>">
                                                <?php echo htmlspecialchars($type['name']); ?>
                                                <?php if ($type_balance): ?>
                                                    (<?php echo $type_balance['remaining_days']; ?> days available)
                                                <?php else: ?>
                                                    (No balance)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_half_day" name="is_half_day" value="1">
                                        <label class="form-check-label" for="is_half_day">
                                            Half Day Leave
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3" id="half_day_period_group" style="display: none;">
                                    <label class="form-label">Half Day Period</label>
                                    <select class="form-select" name="half_day_period">
                                        <option value="morning">Morning (First Half)</option>
                                        <option value="afternoon">Afternoon (Second Half)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for Leave <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="4" 
                                      placeholder="Please provide a detailed reason for your leave request..." required></textarea>                        </div>

                        <div class="alert alert-info" id="days_info" style="display: none;">
                            <i class="fas fa-info-circle"></i> 
                            <span id="days_text">Total days: 0</span>
                        </div>
                    </div>
                    <div class="card-footer d-flex gap-2">
                        <button type="submit" name="submit_request" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Submit Request
                        </button>
                        <a href="my-requests.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>View My Requests
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle half day checkbox
    $('#is_half_day').change(function() {
        if ($(this).is(':checked')) {
            $('#half_day_period_group').show();
            $('#end_date').val($('#start_date').val()).prop('disabled', true);
        } else {
            $('#half_day_period_group').hide();
            $('#end_date').prop('disabled', false);
        }
        calculateDays();
    });

    // Handle date changes
    $('#start_date, #end_date').change(function() {
        if ($('#start_date').val() && $('#end_date').val()) {
            if ($('#start_date').val() > $('#end_date').val()) {
                $('#end_date').val($('#start_date').val());
            }
            calculateDays();
        }
    });

    // Sync end date with start date for half day
    $('#start_date').change(function() {
        if ($('#is_half_day').is(':checked')) {
            $('#end_date').val($(this).val());
        }
        calculateDays();
    });

    function calculateDays() {
        if ($('#start_date').val() && $('#end_date').val()) {
            if ($('#is_half_day').is(':checked')) {
                $('#days_info').show();
                $('#days_text').text('Total days: 0.5');
            } else {
                var start = new Date($('#start_date').val());
                var end = new Date($('#end_date').val());
                var timeDiff = end.getTime() - start.getTime();
                var daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
                
                $('#days_info').show();
                $('#days_text').text('Total days: ' + daysDiff);
            }
        } else {
            $('#days_info').hide();
        }
    }

    // Form validation
    $('#leaveRequestForm').submit(function(e) {
        var startDate = new Date($('#start_date').val());
        var endDate = new Date($('#end_date').val());
        var today = new Date();
        today.setHours(0, 0, 0, 0);

        if (startDate < today) {
            e.preventDefault();
            alert('Cannot apply for leave in the past.');
            return false;
        }

        if (startDate > endDate) {
            e.preventDefault();
            alert('End date cannot be earlier than start date.');
            return false;
        }

        if ($('#reason').val().trim().length < 10) {
            e.preventDefault();
            alert('Please provide a detailed reason (at least 10 characters).');
            return false;
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>

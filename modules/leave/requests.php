<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !is_admin()) {
    header("Location: ../../index.php");
    exit();
}

// Handle bulk actions
if ($_POST && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_requests = $_POST['selected_requests'] ?? [];
      if (!empty($selected_requests) && in_array($action, ['approve', 'reject'])) {
        $status = $action == 'approve' ? 'approved' : 'rejected';
        $placeholders = str_repeat('?,', count($selected_requests) - 1) . '?';
          $sql = "UPDATE leave_requests SET status = ?, approved_by = ?, reviewed_date = NOW() 
                WHERE id IN ($placeholders) AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $params = array_merge([$status, $_SESSION['user_id']], $selected_requests);
        
        if ($stmt->execute($params)) {
            $affected = $stmt->rowCount();
            $_SESSION['success_message'] = "$affected leave request(s) {$status} successfully!";
        }
    }
    header("Location: requests.php");
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$employee_filter = $_GET['employee'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$leave_type_filter = $_GET['leave_type'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($status_filter != 'all') {
    $where_conditions[] = "lr.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($employee_filter)) {
    $where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
    $search_term = "%$employee_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

if (!empty($date_from)) {
    $where_conditions[] = "lr.start_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "lr.end_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($leave_type_filter)) {
    $where_conditions[] = "lr.leave_type_id = ?";
    $params[] = $leave_type_filter;
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get leave requests with employee details
$sql = "SELECT lr.*, 
               e.first_name, e.last_name, e.emp_id, e.email,
               lt.name as leave_type_name, lt.color,
               reviewer.first_name as reviewer_first_name, reviewer.last_name as reviewer_last_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.emp_id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        LEFT JOIN employees reviewer ON lr.approved_by = reviewer.emp_id
        $where_clause        ORDER BY lr.applied_date DESC";

if (!empty($params)) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests_result = $stmt->fetchAll();
} else {
    $requests_result = $pdo->query($sql)->fetchAll();
}

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
    SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
    FROM leave_requests lr
    WHERE YEAR(lr.start_date) = YEAR(CURDATE())";
$stats = $pdo->query($stats_sql)->fetch();

// Get employees for filter dropdown
$employees_sql = "SELECT emp_id, first_name, last_name FROM employees ORDER BY first_name, last_name";
$employees_result = $pdo->query($employees_sql)->fetchAll();

// Get leave types for filter dropdown
$leave_types_sql = "SELECT id, name FROM leave_types ORDER BY name";
$leave_types_result = $pdo->query($leave_types_sql)->fetchAll();

// Set page title
$page = 'All Leave Requests';

include '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-list me-2"></i>Leave Requests</h1>
        </div>
        <?php
            $leaveToolbarInline = true;
            include __DIR__ . '/partials/action-toolbar.php';
        ?>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <i class="fas fa-check me-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                Total Requests
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo $stats['total_requests'] ?? 0; ?>
                            </div>
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
                                Pending Review
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo $stats['pending_requests'] ?? 0; ?>
                            </div>
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
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo $stats['approved_requests'] ?? 0; ?>
                            </div>
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
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1">
                                Rejected
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo $stats['rejected_requests'] ?? 0; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-filter me-2"></i>Filters</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Employee</label>
                    <input type="text" name="employee" class="form-control" 
                           placeholder="Search employee..." value="<?php echo htmlspecialchars($employee_filter); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Leave Type</label>
                    <select name="leave_type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($leave_types_result as $type): ?>
                            <option value="<?php echo $type['id']; ?>" 
                                    <?php echo $leave_type_filter == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>    <!-- Leave Requests Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-table me-2"></i>Leave Requests</h6>
        </div>
        <div class="card-body">
            <?php if (count($requests_result) > 0): ?>
                <form id="bulkActionForm" method="POST">
                    <!-- Bulk Actions -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <select name="bulk_action" class="form-select" required>
                                    <option value="">Select Action</option>
                                    <option value="approve">Approve Selected</option>
                                    <option value="reject">Reject Selected</option>
                                </select>
                                <button type="submit" class="btn btn-primary" onclick="return confirmBulkAction()">
                                    Apply
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <span id="selectedCount">0</span> requests selected
                        </div>
                    </div>                    <div class="table-responsive">
                        <table class="table table-hover" id="leaveRequestsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th width="30px">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th>Request ID</th>
                                    <th>Employee</th>
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
                                            <?php if ($request['status'] == 'pending'): ?>
                                                <input type="checkbox" name="selected_requests[]" 
                                                       value="<?php echo $request['id']; ?>" class="request-checkbox">
                                            <?php endif; ?>
                                        </td>                                        <td>
                                            <span class="badge bg-secondary text-white">
                                                #<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($request['emp_id']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: <?php echo $request['color'] ?? '#007bff'; ?>; color: white;">
                                                <?php echo htmlspecialchars($request['leave_type_name']); ?>
                                            </span>
                                            <?php if ($request['is_half_day']): ?>
                                                <br><small class="text-muted">
                                                    Half Day (<?php echo ucfirst($request['half_day_period']); ?>)
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $request['days_requested']; ?> day<?php echo $request['days_requested'] != 1 ? 's' : ''; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_icon = '';
                                            switch ($request['status']) {
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
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                            <?php if ($request['status'] != 'pending' && $request['reviewer_first_name']): ?>
                                                <br><small class="text-muted">
                                                    by <?php echo htmlspecialchars($request['reviewer_first_name'] . ' ' . $request['reviewer_last_name']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($request['applied_date'])); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $request['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($request['status'] == 'pending'): ?>
                                                    <a href="approve.php?id=<?php echo $request['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="reject.php?id=<?php echo $request['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                    No leave requests match your current filters.
                </div>
            <?php endif; ?>
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
</style>

<script>
(function setupRequestsTable(){
    function initWithJQ(){
        var $ = window.jQuery;
        // Initialize DataTable and keep a reference
        var table = $('#leaveRequestsTable').DataTable({
                responsive: true,
                autoWidth: false,
                order: [[8, 'desc']], // Sort by Applied Date descending
                pageLength: 25,
                columnDefs: [
                        { orderable: false, targets: [0, 9] } // Disable sorting for checkbox and actions columns
                ]
        });

        // Delegated handler for Select All to survive DataTables redraws
        $(document).on('change', '#selectAll', function() {
                var checked = $(this).is(':checked');
                // Only toggle checkboxes on the current page
                var nodes = table.rows({ page: 'current' }).nodes();
                $(nodes).find('.request-checkbox').prop('checked', checked);
                updateSelectedCount();
                syncSelectAllState(table);
        });

        // Delegated handler for individual checkboxes
        $(document).on('change', '.request-checkbox', function() {
                updateSelectedCount();
                syncSelectAllState(table);
        });

        // On table draw, resync header selectAll state for current page
        table.on('draw', function() {
                syncSelectAllState(table);
        });
    }

    function waitForJQ(){
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable){
            try { initWithJQ(); return; } catch(e) { /* fallback continues */ }
        }
        setTimeout(waitForJQ, 60);
    }

    // Start waiting for jQuery/DataTables; vanilla fallbacks below are also wired
    waitForJQ();
})();

// Vanilla helpers to work even if jQuery/DataTables aren’t available
function getDT() {
    if (window.jQuery && $.fn && typeof $.fn.DataTable === 'function') {
        var tbl = $('#leaveRequestsTable').DataTable();
        return tbl || null;
    }
    return null;
}

function getPageNodes() {
    var dt = getDT();
    if (dt) { return dt.rows({ page: 'current' }).nodes(); }
    // Fallback: use tbody rows
    var tbody = document.querySelector('#leaveRequestsTable tbody');
    return tbody ? tbody.querySelectorAll('tr') : [];
}

function togglePageCheckboxes(checked) {
    var nodes = getPageNodes();
    if (nodes && nodes.length !== undefined) {
        $(nodes).find('.request-checkbox').prop('checked', checked);
    } else if (nodes && nodes.forEach) {
        nodes.forEach(function(tr){
            tr.querySelectorAll('.request-checkbox').forEach(function(cb){ cb.checked = checked; });
        });
    }
}

document.addEventListener('DOMContentLoaded', function(){
    var selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function(){
            var checked = !!this.checked;
            togglePageCheckboxes(checked);
            if (window.jQuery) { updateSelectedCount(); var dt = getDT(); if (dt) syncSelectAllState(dt); }
            else { updateSelectedCountVanilla(); }
        });
    }
    // Delegate row checkbox changes (vanilla)
    var tbody = document.querySelector('#leaveRequestsTable tbody');
    if (tbody) {
        tbody.addEventListener('change', function(e){
            if (e.target && e.target.classList && e.target.classList.contains('request-checkbox')){
                if (window.jQuery) { updateSelectedCount(); var dt = getDT(); if (dt) syncSelectAllState(dt); }
                else { updateSelectedCountVanilla(); syncSelectAllStateVanilla(); }
            }
        });
    }
});

function updateSelectedCountVanilla(){
    var tbody = document.querySelector('#leaveRequestsTable tbody');
    var count = 0;
    if (tbody){ count = tbody.querySelectorAll('.request-checkbox:checked').length; }
    var el = document.getElementById('selectedCount');
    if (el) el.textContent = count;
}

function syncSelectAllStateVanilla(){
    var tbody = document.querySelector('#leaveRequestsTable tbody');
    var selectAll = document.getElementById('selectAll');
    if (!tbody || !selectAll) return;
    var pageCbs = tbody.querySelectorAll('.request-checkbox');
    if (pageCbs.length === 0) { selectAll.indeterminate = false; selectAll.checked = false; return; }
    var checkedOnPage = tbody.querySelectorAll('.request-checkbox:checked').length;
    if (checkedOnPage === 0) { selectAll.indeterminate = false; selectAll.checked = false; }
    else if (checkedOnPage === pageCbs.length) { selectAll.indeterminate = false; selectAll.checked = true; }
    else { selectAll.indeterminate = true; }
}

function updateSelectedCount() {
    // Count checked checkboxes across the DOM (current page)
    var count = $('.request-checkbox:checked').length;
    $('#selectedCount').text(count);
}

function syncSelectAllState(table) {
    var nodes = table.rows({ page: 'current' }).nodes();
    var $pageCbs = $(nodes).find('.request-checkbox');
    if ($pageCbs.length === 0) {
        $('#selectAll').prop('indeterminate', false).prop('checked', false);
        return;
    }
    var checkedOnPage = $pageCbs.filter(':checked').length;
    if (checkedOnPage === 0) {
        $('#selectAll').prop('indeterminate', false).prop('checked', false);
    } else if (checkedOnPage === $pageCbs.length) {
        $('#selectAll').prop('indeterminate', false).prop('checked', true);
    } else {
        $('#selectAll').prop('indeterminate', true);
    }
}

function confirmBulkAction() {
    var selectedCount = $('.request-checkbox:checked').length;
    var action = $('select[name="bulk_action"]').val();
    
    if (selectedCount === 0) {
        alert('Please select at least one leave request.');
        return false;
    }
    
    if (!action) {
        alert('Please select an action.');
        return false;
    }
    
    return confirm(`Are you sure you want to ${action} ${selectedCount} leave request(s)?`);
}
</script>

<?php include '../../includes/footer.php'; ?>

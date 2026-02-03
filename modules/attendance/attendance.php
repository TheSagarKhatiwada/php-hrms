<?php
// Start output buffering to prevent issues with redirects
ob_start();

$page = 'attendance';

include '../../includes/session_config.php';
include '../../includes/db_connection.php';
include '../../includes/utilities.php';
include '../../includes/reason_helpers.php';
include '../../includes/csrf_protection.php';
// Shared manual attendance reasons used across the page
$manualAttendanceReasons = function_exists('hrms_reason_label_map')
  ? hrms_reason_label_map()
  : [
      '1' => 'Card Forgot',
      '2' => 'Card Lost',
      '3' => 'Forgot to Punch',
      '4' => 'Office Work Delay',
      '5' => 'Field Visit'
    ];

$requestTabPermissionCodes = ['request_attendance'];
$canViewAttendanceRequests = function_exists('has_any_permission') ? has_any_permission($requestTabPermissionCodes) : false;
$canRequestAttendance = function_exists('has_permission') ? has_permission('request_attendance') : false;
$canApproveRequests = function_exists('has_permission') ? has_permission('process_attendance_requests') : false;
$canRequestForOthers = function_exists('has_permission') ? has_permission('request_attendance_for_others') : false;
$canRequestMultiBranch = function_exists('has_permission') ? has_permission('request_attendance_multi_branch') : false;
$canViewAttendance = function_exists('has_permission') ? has_permission('view_attendance') : false;
$canViewOthersAttendance = function_exists('has_permission') ? has_permission('view_others_attendance') : false;
$canViewAllBranchAttendance = function_exists('has_permission') ? has_permission('view_all_branch_attendance') : false;
$hasBranchOnlyView = false; // removed redundant permission

$attendanceRequestStatusOptions = [
  'pending' => 'Pending',
  'approved' => 'Approved',
  'rejected' => 'Rejected',
  'cancelled' => 'Cancelled',
];
$attendanceRequestBadgeClasses = [
  'pending' => 'bg-warning text-dark',
  'approved' => 'bg-success',
  'rejected' => 'bg-danger',
  'cancelled' => 'bg-secondary',
];
$attendanceRequestStatusFilter = isset($_GET['request_status']) ? strtolower(trim((string)$_GET['request_status'])) : 'pending';
if ($attendanceRequestStatusFilter !== 'all' && !isset($attendanceRequestStatusOptions[$attendanceRequestStatusFilter])) {
  $attendanceRequestStatusFilter = 'pending';
}
$attendanceRequestRows = [];
$attendanceRequestStatusBreakdown = array_fill_keys(array_keys($attendanceRequestStatusOptions), 0);
$attendanceRequestTotalCount = 0;
$attendanceRequestFetchLimit = 50;
$attendanceRequestCsrfToken = function_exists('generate_csrf_token') ? generate_csrf_token() : null;

if ($canViewAttendanceRequests) {
  try {
    $summaryStmt = $pdo->query('SELECT status, COUNT(*) as total FROM attendance_requests GROUP BY status');
    while ($summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC)) {
      $statusKey = $summaryRow['status'] ?? '';
      if (isset($attendanceRequestStatusBreakdown[$statusKey])) {
        $attendanceRequestStatusBreakdown[$statusKey] = (int) $summaryRow['total'];
        $attendanceRequestTotalCount += (int) $summaryRow['total'];
      }
    }
  } catch (PDOException $summaryError) {
    error_log('Attendance request summary failed: ' . $summaryError->getMessage());
  }

  try {
    $requestSql = "SELECT ar.*, 
        emp.first_name AS emp_first_name, emp.middle_name AS emp_middle_name, emp.last_name AS emp_last_name, emp.emp_id AS emp_code,
        branch.name AS emp_branch_name,
        requester.first_name AS requester_first_name, requester.last_name AS requester_last_name,
        reviewer.first_name AS reviewer_first_name, reviewer.last_name AS reviewer_last_name
      FROM attendance_requests ar
      LEFT JOIN employees emp ON ar.emp_id = emp.emp_id
      LEFT JOIN branches branch ON emp.branch = branch.id
      LEFT JOIN employees requester ON ar.requested_by = requester.emp_id
      LEFT JOIN employees reviewer ON ar.reviewed_by = reviewer.emp_id
      WHERE 1=1";

    $requestParams = [];
    if ($attendanceRequestStatusFilter !== 'all') {
      $requestSql .= ' AND ar.status = :statusFilter';
      $requestParams[':statusFilter'] = $attendanceRequestStatusFilter;
    }

    $requestSql .= ' ORDER BY ar.created_at DESC LIMIT :limitRows';
    $requestStmt = $pdo->prepare($requestSql);
    foreach ($requestParams as $param => $value) {
      $requestStmt->bindValue($param, $value, PDO::PARAM_STR);
    }
    $requestStmt->bindValue(':limitRows', $attendanceRequestFetchLimit, PDO::PARAM_INT);
    $requestStmt->execute();
    $attendanceRequestRows = $requestStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (PDOException $requestError) {
    error_log('Attendance request fetch failed: ' . $requestError->getMessage());
  }
}

// Determine whether current user can manage attendance (admin or permission)
$canManageAttendance = (function_exists('is_admin') && is_admin()) || (function_exists('has_permission') && has_permission('manage_attendance'));

// Get current session user id (employee id in this app)
$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserEmp = null;
$currentUserBranchId = null;
$requestEmployeeLockedToSelf = false;

// Default: page is accessible only to admins or users with view/manage permissions.
$isAdmin = function_exists('is_admin') && is_admin();
$isLimitedNonAdmin = false; // true when user is non-admin and permitted but limited to self
$isBranchRestrictedViewer = false; // true when user can see only their branch but not all branches
if (!$isAdmin) {
  if (!$currentUserId) {
    set_flash_message('error', 'You must be logged in to access this page.');
    header('Location: ../../index.php');
    exit();
  }

  // Load the employee record for the current user to check mach_id_not_applicable
  try {
    $uStmt = $pdo->prepare('SELECT emp_id, branch, first_name, middle_name, last_name, mach_id_not_applicable FROM employees WHERE emp_id = ? LIMIT 1');
    $uStmt->execute([$currentUserId]);
    $currentUserEmp = $uStmt->fetch(PDO::FETCH_ASSOC);
    $currentUserBranchId = $currentUserEmp['branch'] ?? null;
  } catch (PDOException $e) {
    $currentUserEmp = null;
  }

  if (!$currentUserEmp) {
    set_flash_message('error', 'Your employee record was not found.');
    header('Location: ../../index.php');
    exit();
  }

  // If machine-not-applicable is explicitly set (1), deny access.
  if (isset($currentUserEmp['mach_id_not_applicable']) && (int)$currentUserEmp['mach_id_not_applicable'] === 1) {
    set_flash_message('error', 'Attendance view is not available for employees marked as "Machine Not Applicable".');
    header('Location: ../../index.php');
    exit();
  }

  // Decide visibility scope for non-admins
  if ($canManageAttendance) {
    $isLimitedNonAdmin = false;
  } elseif ($canViewAllBranchAttendance) {
    $isLimitedNonAdmin = false;
  } elseif ($canViewOthersAttendance) {
    $isBranchRestrictedViewer = true;
  } elseif ($canViewAttendance) {
    $isLimitedNonAdmin = true; // self-only view
  } elseif ($canRequestAttendance) {
    // allow opening page for requesting even without view permission
    $isLimitedNonAdmin = true;
  } else {
    set_flash_message('error', 'You do not have permission to view attendance.');
    header('Location: ../../index.php');
    exit();
  }
}

// Debug: Add error display for development
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Check if we need to open the modal in manual mode
$openManualModal = isset($_GET['action']) && $_GET['action'] === 'manual';
$showAttendanceFilters = ($canManageAttendance || $canViewAllBranchAttendance || $canViewOthersAttendance || $isBranchRestrictedViewer);

// Fetching attendance data
try {
  // Read filters from query params
  $selectedBranch = isset($_GET['branch']) && $_GET['branch'] !== '' ? (int)$_GET['branch'] : null;
  // Employee filter (emp_id)
  $selectedEmployee = isset($_GET['employee']) && $_GET['employee'] !== '' ? trim($_GET['employee']) : null;
  // default to "last 7 days" (inclusive)
  $defaultDateTo = date('Y-m-d');
  $defaultDateFrom = date('Y-m-d', strtotime('-6 days'));
  $dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : $defaultDateFrom;
  $dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : $defaultDateTo;
  // Allowed limits -- non-admin (limited) users get a smaller set
  // Default for full users will be 500, and include larger options 1000 and 2000
  $allowedLimits = [50, 100, 200, 500, 1000, 2000];
  if (!empty($isLimitedNonAdmin)) {
    $allowedLimits = [50, 100, 200];
  }
  // Default limit depends on user type: limited users default to 200, full users default to 500
  $defaultLimit = !empty($isLimitedNonAdmin) ? 200 : 500;
  $limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowedLimits, true) ? (int)$_GET['limit'] : $defaultLimit;

  // If branch-only viewer is active, lock filters to their branch
  if (!empty($isBranchRestrictedViewer) && !empty($currentUserEmp['branch'])) {
    $selectedBranch = (int)$currentUserEmp['branch'];
  }

  // For branch-restricted viewers, default employee filter to self unless a specific employee is chosen
  if (!empty($isBranchRestrictedViewer) && empty($selectedEmployee) && !empty($currentUserEmp['emp_id'])) {
    $selectedEmployee = (string)$currentUserEmp['emp_id'];
  }

      // If limited non-admin is viewing, force branch and employee to current user
  if (!empty($isLimitedNonAdmin) && !empty($currentUserEmp) && !$isBranchRestrictedViewer) {
    // override any provided branch/employee -> non-admins can only view their own attendance
    $selectedEmployee = (string)$currentUserEmp['emp_id'];
    $selectedBranch = isset($currentUserEmp['branch']) ? (int)$currentUserEmp['branch'] : $selectedBranch;

    // Allowed window: previous 3 months up to today. Allow user to pick within this window.
    $threeMonthsAgo = date('Y-m-d', strtotime('-3 months'));
    $today = date('Y-m-d');

    // Normalize incoming date_from/date_to but clamp to allowed window
    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
      $candidateFrom = $_GET['date_from'];
      $dateFrom = $candidateFrom < $threeMonthsAgo ? $threeMonthsAgo : $candidateFrom;
    } else {
      // Ensure default isn't older than allowed
      if ($dateFrom < $threeMonthsAgo) $dateFrom = $threeMonthsAgo;
    }

    if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
      $candidateTo = $_GET['date_to'];
      $dateTo = $candidateTo > $today ? $today : $candidateTo;
    } else {
      if ($dateTo > $today) $dateTo = $today;
    }

    if ($dateFrom > $dateTo) {
      $dateFrom = $dateTo;
    }

    // Limit must be one of the allowedLimits for limited users and not exceed 200
    $limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowedLimits, true) ? (int)$_GET['limit'] : 200;
    $limit = min($limit, 200);
  }

  // Build aggregated SQL (one row per emp_id + date)
  // We'll aggregate MIN(time) as in_time and MAX(time) as out_time and fetch the in/out method/reason using subqueries
  $innerWhere = [];
  $innerParams = [];
  if ($selectedBranch) {
    $innerWhere[] = 'e.branch = :branch';
    $innerParams[':branch'] = $selectedBranch;
  }
  if ($selectedEmployee) {
    $innerWhere[] = 'a.emp_id = :employee';
    $innerParams[':employee'] = $selectedEmployee;
  }
  if ($dateFrom && $dateTo) {
    $innerWhere[] = 'a.date BETWEEN :date_from AND :date_to';
    $innerParams[':date_from'] = $dateFrom;
    $innerParams[':date_to'] = $dateTo;
  } elseif ($dateFrom) {
    $innerWhere[] = 'a.date >= :date_from';
    $innerParams[':date_from'] = $dateFrom;
  } elseif ($dateTo) {
    $innerWhere[] = 'a.date <= :date_to';
    $innerParams[':date_to'] = $dateTo;
  }

  $innerWhereSql = '';
  if (!empty($innerWhere)) {
    $innerWhereSql = 'WHERE ' . implode(' AND ', $innerWhere);
  }

  // inner aggregated query groups by emp_id and date
  $sql = "SELECT ag.emp_id, ag.date, ag.in_time, ag.out_time, ag.cnt, ag.mach_id AS log_mach_id,
            e.first_name, e.last_name, e.middle_name, e.branch AS emp_branch, e.emp_id AS empid, e.user_image, e.mach_id,
            b.name AS branch_name, d.title AS designation,
            -- in/out reason & method (subqueries referencing aggregated values)
            (SELECT manual_reason FROM attendance_logs l WHERE l.emp_id = ag.emp_id AND l.date = ag.date AND l.time = ag.in_time LIMIT 1) AS in_reason,
            (SELECT method FROM attendance_logs l WHERE l.emp_id = ag.emp_id AND l.date = ag.date AND l.time = ag.in_time LIMIT 1) AS in_method,
            (SELECT manual_reason FROM attendance_logs l WHERE l.emp_id = ag.emp_id AND l.date = ag.date AND l.time = ag.out_time LIMIT 1) AS out_reason,
            (SELECT method FROM attendance_logs l WHERE l.emp_id = ag.emp_id AND l.date = ag.date AND l.time = ag.out_time LIMIT 1) AS out_method
          FROM (
                 SELECT a.emp_id, a.date,
                   MIN(a.time) AS in_time,
                   CASE WHEN COUNT(*)>1 THEN MAX(a.time) ELSE NULL END AS out_time,
                   COUNT(*) AS cnt,
                   MAX(a.mach_id) AS mach_id
            FROM attendance_logs a
            LEFT JOIN employees e ON a.emp_id = e.emp_id
            $innerWhereSql
            GROUP BY a.emp_id, a.date
          ) ag
          LEFT JOIN employees e ON ag.emp_id = e.emp_id
          LEFT JOIN branches b ON e.branch = b.id
          LEFT JOIN designations d ON e.designation_id = d.id
          ORDER BY ag.date DESC, ag.in_time DESC";

  // Merge params for execution
  $params = $innerParams;

  // Append limit
  $sql .= ' LIMIT ' . $limit;

  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
  }
  $stmt->execute();
  $attendanceRecords = $stmt->fetchAll();

  // (Removed verbose debug logging previously writing to debug_log.txt)
  
} catch (PDOException $e) {
  // (Removed debug_log.txt logging; rely on standard PHP error log if configured)
    $attendanceRecords = []; // Set empty array to prevent errors in the view
    $_SESSION['error'] = "Error fetching attendance data: " . $e->getMessage();
}

// Force fresh load by checking for cache-busting parameter
// Only redirect if not already redirected and no POST data
if (!isset($_GET['_nocache']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  // Ensure no output has been sent before redirect
  if (!headers_sent()) {
    // Preserve existing query params and add cache-buster
    $qs = $_GET;
    $qs['_nocache'] = time();
    $target = 'attendance.php';
    $queryString = http_build_query($qs);
    // Clean any output buffer before redirect
    ob_clean();
  header('Location: ' . $target . '?' . $queryString);
    exit();
  }
}

// Clean the output buffer and start fresh for HTML output
ob_end_clean();
ob_start();

// Include the header after data loading (includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/../../includes/header.php';

// Prepare primary color RGB for transparent backgrounds
$primaryHex = defined('PRIMARY_COLOR') ? PRIMARY_COLOR : '#0d6efd';
$primaryHex = str_replace('#', '', $primaryHex);
if(strlen($primaryHex) == 3) {
    $primaryR = hexdec(substr($primaryHex, 0, 1).substr($primaryHex, 0, 1));
    $primaryG = hexdec(substr($primaryHex, 1, 1).substr($primaryHex, 1, 1));
    $primaryB = hexdec(substr($primaryHex, 2, 1).substr($primaryHex, 2, 1));
} else {
    $primaryR = hexdec(substr($primaryHex, 0, 2));
    $primaryG = hexdec(substr($primaryHex, 2, 2));
    $primaryB = hexdec(substr($primaryHex, 4, 2));
}
$primaryRgb = "$primaryR, $primaryG, $primaryB";
?>

<style>
.card-body{
  padding: 0;
}

.form-check-label {
    cursor: pointer;
}

.form-check-input:checked + .form-check-label {
    color: #0d6efd;
    font-weight: 500;
}

#selectAllDays + .form-check-label {
    color: #0d6efd;
    cursor: pointer;
}

.alert-info {
  border-left: 4px solid var(--bs-info-border-subtle, var(--bs-info));
  background-color: var(--bs-info-bg-subtle);
}

.form-text.request-attendance-help {
  color: var(--bs-body-color);
  opacity: 0.85;
}

body.dark-mode .form-text.request-attendance-help {
  color: rgba(255, 255, 255, 0.75);
  opacity: 1;
}

.nav-tabs .nav-link {
    border-radius: 0.375rem 0.375rem 0 0;
    background-color: var(--bs-secondary, 0.7);
    color: #ffffff;
    margin-right: 2px;
    border: 1px solid transparent;
}

.nav-tabs .nav-link:hover {
    background-color: rgba(var(--bs-secondary-rgb), 0.85);
    color: #ffffff;
}

.nav-tabs .nav-link.active {
    border-color: none;
    background-color: #fff;
    color: #495057;
    font-weight: 500;
}

.tab-pane {
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    padding: 1rem;
}
</style>

<!-- Content Wrapper (already started in header.php) -->
<!-- <div class="content-wrapper"> --> <!-- This div is opened in header.php -->
    <!-- Topbar is included in header.php -->
    
    <!-- Main content -->
    <div class="container-fluid p-4">
      <!-- Page header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 class="fs-2 fw-bold mb-1">Attendance Records & Requests</h1>
        </div>
        <div class="d-flex gap-2">
          <?php if ($canManageAttendance || $canRequestAttendance): ?>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAttendanceModal">
            <i class="fas fa-plus me-2"></i> Add Attendance
          </button>
          <?php endif; ?>
        </div>
      </div>
      
      <?php
        // Determine active tab
        $activeTab = 'records';
        if ($canViewAttendanceRequests && isset($_GET['action']) && $_GET['action'] === 'request') {
          $activeTab = 'requests';
        }
      ?>

      <ul class="nav nav-tabs mb-0" id="mainPageTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link <?php echo $activeTab === 'records' ? 'active' : ''; ?>" id="attendance-records-tab" data-bs-toggle="tab" data-bs-target="#attendance-records-pane" type="button" role="tab" aria-controls="attendance-records-pane" aria-selected="<?php echo $activeTab === 'records' ? 'true' : 'false'; ?>">Attendance Records</button>
        </li>
        <?php if ($canViewAttendanceRequests): ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?php echo $activeTab === 'requests' ? 'active' : ''; ?>" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests-pane" type="button" role="tab" aria-controls="requests-pane" aria-selected="<?php echo $activeTab === 'requests' ? 'true' : 'false'; ?>">Requests</button>
        </li>
        <?php endif; ?>
      </ul>
      
      <!-- Attendance Filters + Table Card -->
      <div class="card border-0 shadow-sm" style="border-top-left-radius: 0;">
        <div class="card-body">
          <div class="tab-content" id="mainPageTabsContent">
            <!-- Attendance Records Tab -->
            <div class="tab-pane fade <?php echo $activeTab === 'records' ? 'show active' : ''; ?>" id="attendance-records-pane" role="tabpanel" aria-labelledby="attendance-records-tab" tabindex="0">
          <!-- Filters -->
          <?php if (empty($isLimitedNonAdmin)): ?>
          <form method="get" class="row g-3 mb-3 align-items-end">
            <div class="col-md-2">
              <label for="filter_branch" class="form-label">Branch</label>
              <?php if (!empty($isBranchRestrictedViewer)): ?>
                <?php
                  // show static branch label for branch-restricted users
                  try {
                    $branchLabelStmt = $pdo->prepare('SELECT name FROM branches WHERE id = ? LIMIT 1');
                    $branchLabelStmt->execute([ (int)$selectedBranch ]);
                    $branchLabel = $branchLabelStmt->fetchColumn() ?: '';
                  } catch (Exception $ex) { $branchLabel = ''; }
                ?>
                <select class="form-select" name="branch">
                  <option value="<?php echo htmlspecialchars($selectedBranch ?? ''); ?>" selected><?php echo htmlspecialchars($branchLabel); ?></option>
                </select>
              <?php else: ?>
              <select id="filter_branch" name="branch" class="form-select">
                <option value="">All Branches</option>
                <?php 
                  // populate branches
                  try {
                    $branchesStmt = $pdo->query("SELECT id, name FROM branches ORDER BY name");
                    while ($br = $branchesStmt->fetch(PDO::FETCH_ASSOC)) {
                      $sel = (isset($_GET['branch']) && $_GET['branch'] !== '' && (int)$_GET['branch'] === (int)$br['id']) ? 'selected' : '';
                      echo '<option value="' . (int)$br['id'] . '" ' . $sel . '>' . htmlspecialchars($br['name'] ?? '') . '</option>';
                    }
                  } catch (Exception $ex) { /* ignore */ }
                ?>
              </select>
              <?php endif; ?>
            </div>
            <div class="col-md-3">
              <label for="filter_employee" class="form-label">Employee</label>
              <?php if (!empty($isLimitedNonAdmin)): ?>
                <?php
                  // limited non-admin: show their own emp code/name, hide select
                  $user_display_name = htmlspecialchars(trim(($currentUserEmp['first_name'] ?? '') . ' ' . ($currentUserEmp['middle_name'] ?? '') . ' ' . ($currentUserEmp['last_name'] ?? '')));
                ?>
                <select class="form-select" name="employee">
                  <option value="<?php echo htmlspecialchars($currentUserEmp['emp_id'] ?? ''); ?>" selected><?php echo htmlspecialchars($currentUserEmp['emp_id'] ?? '') . ' - ' . $user_display_name; ?></option>
                </select>
              <?php else: ?>
              <select id="filter_employee" name="employee" class="form-select">
                <option value="">All Employees</option>
                <?php
                    try {
                    // If date range is available, include employees employed in that range OR with attendance in that range
                    if (!empty($selectedBranch)) {
                      if (!empty($dateFrom) && !empty($dateTo)) {
                        $empStmt = $pdo->prepare("SELECT emp_id, first_name, middle_name, last_name, mach_id FROM employees WHERE branch = ? AND ((join_date <= ? AND (exit_date IS NULL OR exit_date >= ?)) OR emp_id IN (SELECT emp_id FROM attendance_logs WHERE date BETWEEN ? AND ?)) ORDER BY first_name, last_name");
                        $empStmt->execute([(int)$selectedBranch, $dateTo, $dateFrom, $dateFrom, $dateTo]);
                      } else {
                        $empStmt = $pdo->prepare("SELECT emp_id, first_name, middle_name, last_name, mach_id FROM employees WHERE branch = ? AND status = 'active' ORDER BY first_name, last_name");
                        $empStmt->execute([(int)$selectedBranch]);
                      }
                    } else {
                      if (!empty($dateFrom) && !empty($dateTo)) {
                        $empStmt = $pdo->prepare("SELECT emp_id, first_name, middle_name, last_name, mach_id FROM employees WHERE ((join_date <= ? AND (exit_date IS NULL OR exit_date >= ?)) OR emp_id IN (SELECT emp_id FROM attendance_logs WHERE date BETWEEN ? AND ?)) AND status IN ('active','inactive') ORDER BY first_name, last_name");
                        // status IN clause ensures we fetch all employees regardless of current active flag but still ordered
                        $empStmt->execute([$dateTo, $dateFrom, $dateFrom, $dateTo]);
                      } else {
                        $empStmt = $pdo->query("SELECT emp_id, first_name, middle_name, last_name, mach_id FROM employees WHERE status = 'active' ORDER BY first_name, last_name");
                      }
                    }
                    while ($e = $empStmt->fetch(PDO::FETCH_ASSOC)) {
                      $eid = htmlspecialchars((string)($e['emp_id'] ?? ''), ENT_QUOTES);
                      $ename = htmlspecialchars(trim(($e['first_name'] ?? '') . ' ' . ($e['middle_name'] ?? '') . ' ' . ($e['last_name'] ?? '')));
                      $sel = ($selectedEmployee !== null && $selectedEmployee === (string)$e['emp_id']) ? 'selected' : '';
                      echo '<option value="' . $eid . '" ' . $sel . '>' . $eid . ' - ' . $ename . '</option>';
                    }
                  } catch (Exception $ex) { }
                ?>
              </select>
              <?php endif; ?>
            </div>
            <div class="col-md-2">
              <label for="filter_date_from" class="form-label">From Date</label>
              <input type="date" id="filter_date_from" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFrom ?? ''); ?>">
            </div>
            <div class="col-md-2">
              <label for="filter_date_to" class="form-label">To Date</label>
              <input type="date" id="filter_date_to" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dateTo ?? ''); ?>">
            </div>
            <div class="col-md-2">
              <label for="filter_limit" class="form-label">Limit</label>
              <select id="filter_limit" name="limit" class="form-select">
                <?php 
                  $limits = $allowedLimits;
                  $selLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : $limit;
                  foreach ($limits as $l) {
                    $sel = $selLimit === $l ? 'selected' : '';
                    echo '<option value="' . $l . '" ' . $sel . '>' . $l . '</option>';
                  }
                ?>
              </select>
            </div>
            <div class="col-md-1 d-grid">
              <button type="submit" class="btn btn-outline-primary">Apply</button>
            </div>
          </form>
          <?php else: ?>
          <?php
            // For limited non-admins we show a compact filter (period + limit) while branch/employee are locked
            try {
              $branchNameStmt = $pdo->prepare('SELECT name FROM branches WHERE id = ? LIMIT 1');
              $branchNameStmt->execute([ (int)$currentUserEmp['branch'] ]);
              $branchName = $branchNameStmt->fetchColumn() ?: '';
            } catch (Exception $ex) { $branchName = ''; }
          ?>
          <form method="get" class="row g-3 mb-3 align-items-end">
            <div class="col-md-3">
              <label for="filter_branch" class="form-label">Branch</label>
              <select id="filter_branch" name="branch" class="form-select">
                <option value="<?php echo htmlspecialchars($selectedBranch ?? ''); ?>" selected><?php echo htmlspecialchars($branchName); ?></option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="filter_employee" class="form-label">Employee</label>
              <select id="filter_employee" name="employee" class="form-select">
                <option value="<?php echo htmlspecialchars($currentUserEmp['emp_id'] ?? ''); ?>" selected><?php echo htmlspecialchars($currentUserEmp['emp_id'] ?? '') . ' - ' . htmlspecialchars(trim(($currentUserEmp['first_name']??'') . ' ' . ($currentUserEmp['middle_name']??'') . ' ' . ($currentUserEmp['last_name']??''))); ?></option>
              </select>
            </div>
            <div class="col-md-2">
              <label for="filter_date_from" class="form-label">From Date</label>
              <input type="date" id="filter_date_from" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFrom ?? ''); ?>">
            </div>
            <div class="col-md-2">
              <label for="filter_date_to" class="form-label">To Date</label>
              <input type="date" id="filter_date_to" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dateTo ?? ''); ?>">
            </div>
            <div class="col-md-1">
              <label for="filter_limit" class="form-label">Limit</label>
              <select id="filter_limit" name="limit" class="form-select">
                <?php 
                  $limits = $allowedLimits;
                  $selLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : $limit;
                  foreach ($limits as $l) {
                    $sel = $selLimit === $l ? 'selected' : '';
                    echo '<option value="' . $l . '" ' . $sel . '>' . $l . '</option>';
                  }
                ?>
              </select>
            </div>
            <div class="col-md-1 d-grid">
              <button type="submit" class="btn btn-outline-primary">Apply</button>
            </div>
          </form>
          <?php endif; ?>
          <div class="table-responsive">
            <table id="attendance-table" class="table table-sm table-hover">
              <thead>
                <tr>
                  <th class="text-center">ID</th>
                  <th>Employee</th>
                  <?php if (empty($isLimitedNonAdmin)): ?>
                  <th class="text-center">Branch</th>
                  <?php endif; ?>
                  <th class="text-center">Date</th>
                  <th class="text-center">In</th>
                  <th class="text-center">Out</th>
                  <?php if (!empty($isLimitedNonAdmin)): ?>
                  <th class="text-center">Remarks</th>
                  <?php else: ?>
                  <th class="text-center">Actions</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php 
                // Debug information (only show in development)
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    echo "<!-- DEBUG: Found " . count($attendanceRecords) . " attendance records -->";
                }

                if (!empty($attendanceRecords)) {
                    foreach ($attendanceRecords as $record): ?>
                <tr>
                  <td class="text-center align-middle"><?php echo htmlspecialchars($record['emp_id'] ?? ''); ?></td>
                  <td>
                    <div class="d-flex align-items-center">
                      <img src="<?php 
                        $imagePath = $record['user_image'] ?: '../../resources/userimg/default-image.jpg';
                        // If the image path doesn't start with ../ or http, it's stored without the relative path
                        if (!empty($record['user_image']) && strpos($record['user_image'], '../') !== 0 && strpos($record['user_image'], 'http') !== 0) {
                          $imagePath = '../../' . $record['user_image'];
                        }
                        echo htmlspecialchars($imagePath ?? '');
                      ?>" 
                           alt="Employee" 
                           class="rounded-circle me-3" 
                           style="width: 40px; height: 40px; object-fit: cover;"
                           onerror="this.src='../../resources/userimg/default-image.jpg'">
                      <div>
                        <?php
                          $fullName = trim(
                          ($record['first_name'] ?? '') . ' ' .
                          ($record['middle_name'] ?? '') . ' ' .
                          ($record['last_name'] ?? '')
                          );
                          if ($fullName !== '') {
                          echo '<div class="fw-bold">' . htmlspecialchars($fullName) . '</div>';
                          } elseif (!empty($record['mach_id']) || !empty($record['log_mach_id'])) {
                          $displayMachId = !empty($record['mach_id']) ? $record['mach_id'] : $record['log_mach_id'];
                          echo '<div class="fw-bold">(' . htmlspecialchars($displayMachId) . ')</div>';
                          } else {
                          echo '<div class="fw-bold">(N/A)</div>';
                          }
                        ?>
                        <small class="text-muted"><?php echo htmlspecialchars(($record['designation'] ?? '') !== '' ? $record['designation'] : 'Not Assigned'); ?></small>
                      </div>
                    </div>
                  </td>
                  <?php if (empty($isLimitedNonAdmin)): ?>
                  <td class="text-center align-middle"><?php echo htmlspecialchars($record['branch_name'] ?? ''); ?></td>
                  <?php endif; ?>
                  <td class="text-center align-middle"><?php echo !empty($record['date']) ? htmlspecialchars(hrms_format_preferred_date($record['date'], 'M d, Y')) : '-'; ?></td>
                  <td class="text-center align-middle">
                    <?php if (!empty($record['in_time'])): ?>
                      <div class="fw-semibold"><?php echo htmlspecialchars($record['in_time']); ?></div>
                      <small class="text-muted">
                        <?php
                        // method label
                        $m = isset($record['in_method']) ? (int)$record['in_method'] : null;
                        $mLabel = 'Unknown';
                        if ($m !== null) {
                          switch ($m) { case 0: $mLabel='Auto'; break; case 1: $mLabel='Manual'; break; case 2: $mLabel='Web'; break; }
                        }
                        // reason parsing
                        $inReasonRaw = $record['in_reason'] ?? '';
                        $inReasonLabel = '';
                        $inRemarks = '';
                        if ($inReasonRaw !== '') {
                          if (strpos($inReasonRaw, '||') !== false) { [$rId,$rRem] = array_map('trim', explode('||',$inReasonRaw,2)); }
                          elseif (strpos($inReasonRaw, '|') !== false) { [$rId,$rRem] = array_map('trim', explode('|',$inReasonRaw,2)); }
                          else { $rId = trim($inReasonRaw); $rRem = ''; }
                          $reasonMap = $manualAttendanceReasons;
                          if (is_numeric($rId) && isset($reasonMap[$rId])) $inReasonLabel = $reasonMap[$rId]; else $inReasonLabel = $rId;
                          $inRemarks = $rRem;
                        }
                        $parts = array_filter([$mLabel, $inReasonLabel, $inRemarks]);
                        echo htmlspecialchars(implode(' | ', $parts));
                        ?>
                      </small>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <!-- method column removed per request; keep Out cell next so columns align with header -->
                  <td class="text-center align-middle">
                    <?php if (!empty($record['out_time'])): ?>
                      <div class="fw-semibold"><?php echo htmlspecialchars($record['out_time']); ?></div>
                      <small class="text-muted">
                        <?php
                        $m = isset($record['out_method']) ? (int)$record['out_method'] : null;
                        $mLabel = 'Unknown'; if ($m !== null) { switch ($m) { case 0: $mLabel='Auto'; break; case 1: $mLabel='Manual'; break; case 2: $mLabel='Web'; break; } }
                        $outReasonRaw = $record['out_reason'] ?? '';
                        $outReasonLabel = ''; $outRemarks = '';
                        if ($outReasonRaw !== '') {
                          if (strpos($outReasonRaw, '||') !== false) { [$rId,$rRem] = array_map('trim', explode('||',$outReasonRaw,2)); }
                          elseif (strpos($outReasonRaw, '|') !== false) { [$rId,$rRem] = array_map('trim', explode('|',$outReasonRaw,2)); }
                          else { $rId = trim($outReasonRaw); $rRem = ''; }
                          $reasonMap = $manualAttendanceReasons;
                          if (is_numeric($rId) && isset($reasonMap[$rId])) $outReasonLabel = $reasonMap[$rId]; else $outReasonLabel = $rId;
                          $outRemarks = $rRem;
                        }
                        $parts = array_filter([$mLabel, $outReasonLabel, $outRemarks]);
                        echo htmlspecialchars(implode(' | ', $parts));
                        ?>
                      </small>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <?php if (!empty($isLimitedNonAdmin)): ?>
                  <td class="text-center align-middle small text-muted">
                    <?php
                      // Build a concise remarks string from in_reason and out_reason (if present)
                      $reasonMap = $manualAttendanceReasons;
                      $inRaw = $record['in_reason'] ?? '';
                      $outRaw = $record['out_reason'] ?? '';
                      $segments = [];
                      if (!empty($inRaw)) {
                        $rId=''; $rRem='';
                        if (strpos($inRaw, '||') !== false) { [$rId,$rRem] = array_map('trim', explode('||', $inRaw, 2)); }
                        elseif (strpos($inRaw, '|') !== false) { [$rId,$rRem] = array_map('trim', explode('|', $inRaw, 2)); }
                        else { $rId = trim($inRaw); $rRem = ''; }
                        $label = (is_numeric($rId) && isset($reasonMap[$rId])) ? $reasonMap[$rId] : $rId;
                        $segments[] = 'In: ' . ($label ?: '-') . ($rRem ? ' (' . $rRem . ')' : '');
                      }
                      if (!empty($outRaw)) {
                        $rId=''; $rRem='';
                        if (strpos($outRaw, '||') !== false) { [$rId,$rRem] = array_map('trim', explode('||', $outRaw, 2)); }
                        elseif (strpos($outRaw, '|') !== false) { [$rId,$rRem] = array_map('trim', explode('|', $outRaw, 2)); }
                        else { $rId = trim($outRaw); $rRem = ''; }
                        $label = (is_numeric($rId) && isset($reasonMap[$rId])) ? $reasonMap[$rId] : $rId;
                        $segments[] = 'Out: ' . ($label ?: '-') . ($rRem ? ' (' . $rRem . ')' : '');
                      }
                      echo htmlspecialchars(implode(' · ', $segments) ?: '-');
                    ?>
                  </td>
                  <?php else: ?>
                  <td class="text-center align-middle">
                    <button class="btn btn-sm btn-outline-secondary show-logs" type="button"
                      data-emp="<?php echo htmlspecialchars($record['emp_id'] ?? ''); ?>"
                      data-date="<?php echo htmlspecialchars($record['date'] ?? ''); ?>"
                      data-emp-name="<?php echo htmlspecialchars(trim(($record['first_name'] ?? '') . ' ' . ($record['middle_name'] ?? '') . ' ' . ($record['last_name'] ?? ''))); ?>"
                      data-emp-image="<?php echo htmlspecialchars($record['user_image'] ?? ''); ?>"
                      data-designation="<?php echo htmlspecialchars($record['designation'] ?? ''); ?>"
                      data-branch-name="<?php echo htmlspecialchars($record['branch_name'] ?? ''); ?>"
                    >Details</button>
                  </td>
                  <?php endif; ?>
                </tr>
        <?php 
          endforeach; 
        }
        // Note: Do not render a colspan row here; DataTables does not support colspan in tbody.
        ?>
              </tbody>
            </table>
          </div>
            </div> <!-- End Attendance Records Tab -->

            <!-- Requests Tab -->
            <?php if ($canViewAttendanceRequests): ?>
            <div class="tab-pane fade <?php echo $activeTab === 'requests' ? 'show active' : ''; ?>" id="requests-pane" role="tabpanel" aria-labelledby="requests-tab" tabindex="0">
              <?php if ($canViewAttendanceRequests): ?>
                  <!-- Admin Request Management Content -->
                  <div class="mb-3">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                      <span class="badge bg-primary">Total: <?php echo number_format($attendanceRequestTotalCount); ?></span>
                      <?php foreach ($attendanceRequestStatusOptions as $statusKey => $statusLabel): ?>
                        <span class="badge <?php echo $attendanceRequestBadgeClasses[$statusKey] ?? 'bg-secondary'; ?>">
                          <?php echo htmlspecialchars($statusLabel); ?>:
                          <?php echo number_format($attendanceRequestStatusBreakdown[$statusKey] ?? 0); ?>
                        </span>
                      <?php endforeach; ?>
                    </div>

                    <form method="get" class="row g-2 align-items-end mb-3">
                      <div class="col-sm-6 col-md-4">
                        <label for="requestStatusFilter" class="form-label">Status Filter</label>
                        <select id="requestStatusFilter" name="request_status" class="form-select" onchange="this.form.submit()">
                          <option value="all" <?php echo $attendanceRequestStatusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                          <?php foreach ($attendanceRequestStatusOptions as $statusKey => $statusLabel): ?>
                            <option value="<?php echo htmlspecialchars($statusKey); ?>" <?php echo $attendanceRequestStatusFilter === $statusKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusLabel); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <input type="hidden" name="action" value="request">
                      <?php foreach ($_GET as $existingKey => $existingValue): ?>
                        <?php if (in_array($existingKey, ['request_status', 'action'], true)) continue; ?>
                        <?php if (is_array($existingValue)) continue; ?>
                        <input type="hidden" name="<?php echo htmlspecialchars($existingKey); ?>" value="<?php echo htmlspecialchars($existingValue); ?>">
                      <?php endforeach; ?>
                    </form>

                    <div class="table-responsive">
                      <table class="table table-sm align-middle table-hover">
                        <thead>
                          <tr>
                            <th>Employee</th>
                            <th>Requested For</th>
                            <th>Reason</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if (empty($attendanceRequestRows)): ?>
                            <tr>
                              <td colspan="6" class="text-center text-muted py-4">No attendance requests found for this filter.</td>
                            </tr>
                          <?php else: ?>
                            <?php foreach ($attendanceRequestRows as $requestRow): ?>
                              <?php
                                $employeeName = trim(implode(' ', array_filter([
                                  $requestRow['emp_first_name'] ?? '',
                                  $requestRow['emp_middle_name'] ?? '',
                                  $requestRow['emp_last_name'] ?? '',
                                ])));
                                $requesterName = trim(implode(' ', array_filter([
                                  $requestRow['requester_first_name'] ?? '',
                                  $requestRow['requester_last_name'] ?? '',
                                ])));
                                $reviewerName = trim(implode(' ', array_filter([
                                  $requestRow['reviewer_first_name'] ?? '',
                                  $requestRow['reviewer_last_name'] ?? '',
                                ])));
                                $statusKey = $requestRow['status'] ?? 'pending';
                                $statusLabel = $attendanceRequestStatusOptions[$statusKey] ?? ucfirst($statusKey);
                                $badgeClass = $attendanceRequestBadgeClasses[$statusKey] ?? 'bg-secondary';
                                $submittedAt = $requestRow['created_at'] ? date('M d, Y h:i A', strtotime($requestRow['created_at'])) : '—';
                                $requestDateTime = $requestRow['request_date']
                                  ? date('M d, Y', strtotime($requestRow['request_date'])) . ' • ' . ($requestRow['request_time'] ? date('h:i A', strtotime($requestRow['request_time'])) : '--')
                                  : '—';
                                $reasonDisplay = $requestRow['reason_label'] ?? $requestRow['reason_code'];
                                $remarksDisplay = $requestRow['remarks'] ?? '';
                                $reviewNotes = $requestRow['review_notes'] ?? '';
                              ?>
                              <tr>
                                <td>
                                  <div class="fw-semibold"><?php echo htmlspecialchars($employeeName ?: $requestRow['emp_code']); ?></div>
                                  <div class="small text-muted">Emp ID: <?php echo htmlspecialchars($requestRow['emp_code']); ?></div>
                                  <?php if (!empty($requestRow['emp_branch_name'])): ?>
                                    <div class="small text-muted">Branch: <?php echo htmlspecialchars($requestRow['emp_branch_name']); ?></div>
                                  <?php endif; ?>
                                </td>
                                <td>
                                  <div><?php echo htmlspecialchars($requestDateTime); ?></div>
                                  <?php if ($remarksDisplay): ?>
                                    <div class="small text-muted">Remarks: <?php echo htmlspecialchars($remarksDisplay); ?></div>
                                  <?php endif; ?>
                                </td>
                                <td>
                                  <div><?php echo htmlspecialchars($reasonDisplay ?: '—'); ?></div>
                                  <?php if ($requesterName): ?>
                                    <div class="small text-muted">By: <?php echo htmlspecialchars($requesterName); ?></div>
                                  <?php endif; ?>
                                </td>
                                <td>
                                  <div><?php echo htmlspecialchars($submittedAt); ?></div>
                                  <?php if ($reviewerName && $requestRow['reviewed_at']): ?>
                                    <div class="small text-muted">Reviewed by <?php echo htmlspecialchars($reviewerName); ?></div>
                                  <?php endif; ?>
                                  <?php if ($reviewNotes): ?>
                                    <div class="small text-muted">Notes: <?php echo htmlspecialchars($reviewNotes); ?></div>
                                  <?php endif; ?>
                                </td>
                                <td>
                                  <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                </td>
                                <td class="text-end">
                                  <?php if ($statusKey === 'pending' && $canApproveRequests): ?>
                                    <button type="button" class="btn btn-sm btn-primary btn-review-request" data-bs-toggle="modal" data-bs-target="#reviewRequestModal"
                                      data-id="<?php echo $requestRow['id']; ?>"
                                      data-emp="<?php echo htmlspecialchars($employeeName ?: $requestRow['emp_code']); ?>"
                                      data-date="<?php echo htmlspecialchars($requestDateTime); ?>"
                                      data-time="<?php echo htmlspecialchars($requestRow['request_time'] ? date('h:i A', strtotime($requestRow['request_time'])) : '--'); ?>"
                                      data-reason="<?php echo htmlspecialchars($reasonDisplay ?: '—'); ?>"
                                      data-remarks="<?php echo htmlspecialchars($remarksDisplay); ?>"
                                    >
                                      Review
                                    </button>
                                  <?php elseif ($statusKey === 'pending' && !$canApproveRequests): ?>
                                    <span class="text-muted">View only</span>
                                  <?php else: ?>
                                    <span class="text-muted">No actions</span>
                                  <?php endif; ?>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </tbody>
                      </table>
                      <?php if (!empty($attendanceRequestRows) && count($attendanceRequestRows) === $attendanceRequestFetchLimit): ?>
                        <p class="small text-muted mb-0">Showing latest <?php echo $attendanceRequestFetchLimit; ?> requests. Apply filters for older records.</p>
                      <?php endif; ?>
                    </div>
                  </div>

              <?php elseif (!empty($isLimitedNonAdmin) && !empty($currentUserEmp)): ?>
                  <!-- Employee My Requests Content -->
                  <?php
                    // Fetch my requests
                    $myRequests = [];
                    try {
                        $stmt = $pdo->prepare("
                            SELECT ar.*, 
                                   e.first_name as emp_first_name, e.last_name as emp_last_name,
                                   rv.first_name as reviewer_first_name, rv.last_name as reviewer_last_name
                            FROM attendance_requests ar
                            LEFT JOIN employees e ON ar.emp_id = e.emp_id
                            LEFT JOIN employees rv ON ar.reviewed_by = rv.emp_id
                            WHERE ar.emp_id = ?
                            ORDER BY ar.created_at DESC
                            LIMIT 50
                        ");
                        $stmt->execute([$currentUserEmp['emp_id']]);
                        $myRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        // Handle error silently or log
                    }
                  ?>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th>Time</th>
                          <th>Reason</th>
                          <th>Status</th>
                          <th>Submitted</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($myRequests)): ?>
                          <tr><td colspan="6" class="text-center text-muted">No requests found.</td></tr>
                        <?php else: ?>
                          <?php foreach ($myRequests as $req): ?>
                            <?php
                                $statusClass = 'bg-secondary';
                                if ($req['status'] === 'approved') $statusClass = 'bg-success';
                                elseif ($req['status'] === 'rejected') $statusClass = 'bg-danger';
                                elseif ($req['status'] === 'pending') $statusClass = 'bg-warning text-dark';
                                elseif ($req['status'] === 'cancelled') $statusClass = 'bg-secondary';
                                
                                $reasonLabel = $req['reason_code']; // Or map it if you have the map available
                                if (isset($manualAttendanceReasons[$req['reason_code']])) {
                                    $reasonLabel = $manualAttendanceReasons[$req['reason_code']];
                                }
                            ?>
                            <tr>
                              <td><?php echo htmlspecialchars(date('M d, Y', strtotime($req['request_date']))); ?></td>
                              <td><?php echo htmlspecialchars(date('h:i A', strtotime($req['request_time']))); ?></td>
                              <td>
                                <div><?php echo htmlspecialchars($reasonLabel); ?></div>
                                <?php if (!empty($req['remarks'])): ?>
                                  <small class="text-muted"><?php echo htmlspecialchars($req['remarks']); ?></small>
                                <?php endif; ?>
                              </td>
                              <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($req['status'])); ?></span></td>
                              <td>
                                <div><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($req['created_at']))); ?></div>
                                <?php if (!empty($req['review_notes'])): ?>
                                  <div class="mt-1 small text-wrap" style="max-width: 250px;">
                                    <span class="fw-bold text-dark">Admin Remarks:</span><br>
                                    <span class="text-muted"><?php echo htmlspecialchars($req['review_notes']); ?></span>
                                  </div>
                                <?php endif; ?>
                              </td>
                              <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                  <form action="cancel_attendance_request.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this request?');" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel Request</button>
                                  </form>
                                <?php endif; ?>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
              <?php else: ?>
                <div class="alert alert-info">You do not have permission to view requests.</div>
              <?php endif; ?>
            </div> <!-- End Requests Tab -->
            <?php endif; ?>
          </div> <!-- End Tab Content -->
        </div> <!-- End Card Body -->
      </div> <!-- End Card -->
    </div> <!-- /.container-fluid -->
    
<!-- Modals remain outside the main content flow, before the final footer include -->
<?php if ($canManageAttendance || $canRequestAttendance): ?>
<?php
  $attendanceTabConfig = [
    'upload' => [
      'label' => 'Upload',
      'button_id' => 'upload-tab',
      'pane_id' => 'upload-tab-pane',
      'permissions' => ['manage_attendance', 'attendance_tab_upload']
    ],
    'manual_single' => [
      'label' => 'Manual (Single)',
      'button_id' => 'manual-single-tab',
      'pane_id' => 'manual-single-tab-pane',
      'permissions' => ['manage_attendance', 'attendance_tab_manual_single']
    ],
    'manual_periodic' => [
      'label' => 'Manual (Periodic)',
      'button_id' => 'manual-periodic-tab',
      'pane_id' => 'manual-periodic-tab-pane',
      'permissions' => ['manage_attendance', 'attendance_tab_manual_periodic']
    ],
    'request' => [
      'label' => 'Requests',
      'button_id' => 'request-tab',
      'pane_id' => 'request-tab-pane',
      'permissions' => ['request_attendance']
    ],
  ];

  $attendanceTabAccess = [];
  foreach ($attendanceTabConfig as $tabKey => $tabMeta) {
    if ($tabKey === 'request') {
      $attendanceTabAccess[$tabKey] = $canViewAttendanceRequests;
      continue;
    }
    $attendanceTabAccess[$tabKey] = has_any_permission($tabMeta['permissions']);
  }

  $defaultAttendanceTabKey = null;
  foreach ($attendanceTabConfig as $tabKey => $tabMeta) {
    if (!empty($attendanceTabAccess[$tabKey])) {
      $defaultAttendanceTabKey = $tabKey;
      break;
    }
  }

  $currentUserBranchId = $currentUserEmp['branch'] ?? null;
  $requestBranchLocked = !$isAdmin && !$canRequestMultiBranch;
  $requestBranchSelection = $requestBranchLocked ? ($currentUserBranchId ?? null) : ($currentUserBranchId ?? null);
  $requestEmployeeLockedToSelf = !$isAdmin && !$canRequestForOthers;
  $requestBranchOptions = [];
  $requestBranchSingle = false;
  try {
    if ($requestBranchLocked && $requestBranchSelection) {
      $stmt = $pdo->prepare('SELECT id, name FROM branches WHERE id = ? LIMIT 1');
      $stmt->execute([(int)$requestBranchSelection]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row) $requestBranchOptions[] = $row;
    } else {
      $stmt = $pdo->query('SELECT id, name FROM branches ORDER BY name');
      $requestBranchOptions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  } catch (Exception $ex) { $requestBranchOptions = []; }

    // If there's exactly one available branch (and it's not already locked), auto-select it.
    if (!$requestBranchLocked && count($requestBranchOptions) === 1) {
      $requestBranchSingle = true;
      if (empty($requestBranchSelection)) {
        $requestBranchSelection = (int)($requestBranchOptions[0]['id'] ?? 0);
      }
    }

    $requestEmployeeOptions = [];
    $requestSelectedEmployee = null;
  if ($requestEmployeeLockedToSelf && $currentUserId) {
    $requestEmployeeOptions[] = [
      'emp_id' => $currentUserId,
      'label' => trim(($currentUserEmp['first_name'] ?? '') . ' ' . ($currentUserEmp['middle_name'] ?? '') . ' ' . ($currentUserEmp['last_name'] ?? '')) ?: $currentUserId,
    ];
      $requestSelectedEmployee = $currentUserId;
  } elseif ($requestBranchSelection && !$requestEmployeeLockedToSelf) {
    try {
      $requestExitCutoff = date('Y-m-d', strtotime('-30 days'));
      $stmt = $pdo->prepare('SELECT emp_id, first_name, middle_name, last_name FROM employees WHERE branch = ? AND status = "active" AND (mach_id_not_applicable IS NULL OR mach_id_not_applicable = 0) AND (exit_date IS NULL OR exit_date >= ?) ORDER BY first_name, last_name');
      $stmt->execute([(int)$requestBranchSelection, $requestExitCutoff]);
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $requestEmployeeOptions[] = [
          'emp_id' => $row['emp_id'],
          'label' => trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: $row['emp_id'],
        ];
      }
    } catch (Exception $ex) { $requestEmployeeOptions = []; }
  }

    // If only one employee is available (and not locked), preselect it
    if (!$requestEmployeeLockedToSelf && empty($requestSelectedEmployee) && count($requestEmployeeOptions) === 1) {
      $requestSelectedEmployee = $requestEmployeeOptions[0]['emp_id'] ?? null;
    }
?>
<!-- Add Attendance Modal -->
<div class="modal fade" id="addAttendanceModal" tabindex="-1" aria-labelledby="addAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addAttendanceModalLabel">Add Attendance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Tabs -->
        <ul class="nav nav-tabs" id="attendanceTabs" role="tablist">
          <?php foreach ($attendanceTabConfig as $tabKey => $tabMeta): ?>
            <?php if (empty($attendanceTabAccess[$tabKey])) continue; ?>
            <?php $isActiveTab = ($tabKey === $defaultAttendanceTabKey); ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link <?php echo $isActiveTab ? 'active' : ''; ?>" id="<?php echo htmlspecialchars($tabMeta['button_id']); ?>" data-bs-toggle="tab" data-bs-target="#<?php echo htmlspecialchars($tabMeta['pane_id']); ?>" type="button" role="tab" aria-controls="<?php echo htmlspecialchars($tabMeta['pane_id']); ?>" aria-selected="<?php echo $isActiveTab ? 'true' : 'false'; ?>">
                <?php echo htmlspecialchars($tabMeta['label']); ?>
              </button>
            </li>
          <?php endforeach; ?>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content mt-3" id="attendanceTabsContent">
          <!-- Upload Tab -->
          <?php if (!empty($attendanceTabAccess['upload'])): ?>
          <div class="tab-pane fade <?php echo $defaultAttendanceTabKey === 'upload' ? 'show active' : ''; ?>" id="upload-tab-pane" role="tabpanel" aria-labelledby="upload-tab" tabindex="0">
            <form action="upload-attendance.php" method="post" enctype="multipart/form-data">
              <div class="row">
                <div class="col-md-9 mb-3">
                  <label for="attendanceFile" class="form-label">Upload Attendance File <span class="text-danger">*</span></label>
                  <input type="file" class="form-control" id="attendanceFile" name="attendanceFile" accept=".txt" required>
                  <div class="form-text text-primary">Upload a text file with attendance records</div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary w-100">Upload & Process</button>
                </div>
              </div>
            </form>
          </div>
          <?php endif; ?>
          
          <!-- Manual (Single) Tab -->
          <?php if (!empty($attendanceTabAccess['manual_single'])): ?>
          <div class="tab-pane fade <?php echo $defaultAttendanceTabKey === 'manual_single' ? 'show active' : ''; ?>" id="manual-single-tab-pane" role="tabpanel" aria-labelledby="manual-single-tab" tabindex="0">
            <form id="manualAttendance" method="POST" action="record_manual_attendance.php">
              <div class="row g-3">
                <div class="col-md-6 mb-3">
                  <label for="empBranch" class="form-label">Branch <span class="text-danger">*</span></label>
                  <select class="form-select" id="empBranch" name="empBranch" required>
                    <option value="" selected disabled>Select Branch</option>
                    <?php 
                    $branchQuery = "SELECT DISTINCT id, name FROM branches";
                    $stmt = $pdo->query($branchQuery);
                    while ($row = $stmt->fetch()) {
                        echo "<option value='{$row['id']}'>{$row['name']}</option>";
                    }
                    ?>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="emp_id" class="form-label">Employee <span class="text-danger">*</span></label>
                  <select class="form-select" id="emp_id" name="empId" required>
                    <option value="">Select Employee</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="attendanceDate" class="form-label">Attendance Date <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" id="attendanceDate" name="attendanceDate" 
                         value="<?php echo date('Y-m-d'); ?>" 
                         min="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" 
                         max="<?php echo date('Y-m-d'); ?>" 
                         required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="attendanceTime" class="form-label">Attendance Time <span class="text-danger">*</span></label>
                  <input type="time" class="form-control" id="attendanceTime" name="attendanceTime" step="1" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                  <select class="form-select" id="reason" name="reason" required>
                    <option value="" selected disabled>Select Reason</option>
                    <?php foreach ($manualAttendanceReasons as $reasonValue => $reasonLabel): ?>
                      <option value="<?php echo htmlspecialchars($reasonValue); ?>"><?php echo htmlspecialchars($reasonLabel); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="remarks" class="form-label">Remarks</label>
                  <input type="text" class="form-control" id="remarks" name="remarks" placeholder="Optional">
                </div>
                <div class="col-12 text-end">
                  <button type="submit" class="btn btn-primary">Save Attendance</button>
                </div>
              </div>
            </form>
          </div>
              <?php endif; ?>
          
          <!-- Manual (Periodic) Tab -->
          <?php if (!empty($attendanceTabAccess['manual_periodic'])): ?>
          <div class="tab-pane fade <?php echo $defaultAttendanceTabKey === 'manual_periodic' ? 'show active' : ''; ?>" id="manual-periodic-tab-pane" role="tabpanel" aria-labelledby="manual-periodic-tab" tabindex="0">
            <form id="manualPeriodicAttendance" method="POST" action="record_manual_attendance_periodic.php">
              <div class="row g-3">
                <div class="col-md-6 mb-3">
                  <label for="periodicEmpBranch" class="form-label">Branch <span class="text-danger">*</span></label>
                  <select class="form-select" id="periodicEmpBranch" name="empBranch" required>
                    <option value="" selected disabled>Select Branch</option>
                    <?php 
                    $branchQuery = "SELECT DISTINCT id, name FROM branches";
                    $stmt = $pdo->query($branchQuery);
                    while ($row = $stmt->fetch()) {
                        echo "<option value='{$row['id']}'>{$row['name']}</option>";
                    }
                    ?>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="periodic_emp_id" class="form-label">Employee <span class="text-danger">*</span></label>
                  <select class="form-select" id="periodic_emp_id" name="empId" required>
                    <option value="">Select Employee</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="periodicStartDate" class="form-label">Start Date <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" id="periodicStartDate" name="startDate" 
                         min="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" 
                         max="<?php echo date('Y-m-d'); ?>" 
                         required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="periodicEndDate" class="form-label">End Date <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" id="periodicEndDate" name="endDate" 
                         min="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" 
                         max="<?php echo date('Y-m-d'); ?>" 
                         required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="periodicInTime" class="form-label">In Time <span class="text-danger">*</span></label>
                  <input type="time" class="form-control" id="periodicInTime" name="inTime" step="1" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="periodicOutTime" class="form-label">Out Time <span class="text-danger">*</span></label>
                  <input type="time" class="form-control" id="periodicOutTime" name="outTime" step="1" required>
                </div>
                <div class="col-md-12 mb-3">
                  <label class="form-label">Working Days <span class="text-danger">*</span></label>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="monday" name="workingDays[]">
                        <label class="form-check-label" for="monday">Monday</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="2" id="tuesday" name="workingDays[]">
                        <label class="form-check-label" for="tuesday">Tuesday</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="3" id="wednesday" name="workingDays[]">
                        <label class="form-check-label" for="wednesday">Wednesday</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="4" id="thursday" name="workingDays[]">
                        <label class="form-check-label" for="thursday">Thursday</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="5" id="friday" name="workingDays[]">
                        <label class="form-check-label" for="friday">Friday</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="6" id="saturday" name="workingDays[]">
                        <label class="form-check-label" for="saturday">Saturday</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="0" id="sunday" name="workingDays[]">
                        <label class="form-check-label" for="sunday">Sunday</label>
                      </div>
                      <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="selectAllDays">
                        <label class="form-check-label fw-bold text-primary" for="selectAllDays">Select All</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="periodicReason" class="form-label">Reason <span class="text-danger">*</span></label>
                  <select class="form-select" id="periodicReason" name="reason" required>
                    <option value="" selected disabled>Select Reason</option>
                    <?php foreach ($manualAttendanceReasons as $reasonValue => $reasonLabel): ?>
                      <option value="<?php echo htmlspecialchars($reasonValue); ?>"><?php echo htmlspecialchars($reasonLabel); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="periodicRemarks" class="form-label">Remarks</label>
                  <input type="text" class="form-control" id="periodicRemarks" name="remarks" placeholder="Optional">
                </div>
                <div class="col-12">
                  <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> This will create attendance records for the selected employee on the specified working days within the date range.
                  </div>
                </div>
                <div class="col-12 text-end">
                  <button type="submit" class="btn btn-primary">Save Periodic Attendance</button>
                </div>
              </div>
            </form>
          </div>
          <?php endif; ?>

          <!-- Request Tab -->
          <?php if (!empty($attendanceTabAccess['request'])): ?>
          <div class="tab-pane fade <?php echo $defaultAttendanceTabKey === 'request' ? 'show active' : ''; ?>" id="request-tab-pane" role="tabpanel" aria-labelledby="request-tab" tabindex="0">
            <div class="row">
              <div class="col-12">
                <form class="request-attendance-form" method="POST" action="submit_attendance_request.php">
                  <?php if ($attendanceRequestCsrfToken): ?>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($attendanceRequestCsrfToken); ?>">
                  <?php endif; ?>
                  <div class="row g-3">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Branch <span class="text-danger">*</span></label>
                      <select class="form-select" id="requestEmpBranch" name="request_branch" required>
                        <option value="" disabled <?php echo empty($requestBranchSelection) ? 'selected' : ''; ?>>Select Branch</option>
                        <?php foreach ($requestBranchOptions as $row): ?>
                          <?php
                            $isSelectedBranch = (!empty($requestBranchSelection) && (int)$requestBranchSelection === (int)$row['id']);
                            $isBranchAllowed = $canRequestMultiBranch || $isAdmin || ((int)$row['id'] === (int)$currentUserBranchId);
                          ?>
                          <option value="<?php echo (int)$row['id']; ?>" <?php echo $isSelectedBranch ? 'selected' : ''; ?> <?php echo $isBranchAllowed ? '' : 'disabled'; ?>><?php echo htmlspecialchars($row['name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Employee <span class="text-danger">*</span></label>
                      <select class="form-select" id="request_emp_id_admin" name="emp_id" required>
                        <option value="" disabled <?php echo empty($requestSelectedEmployee) ? 'selected' : ''; ?>>Select Employee</option>
                        <?php foreach ($requestEmployeeOptions as $row): ?>
                          <?php
                            $selEmp = (!empty($requestSelectedEmployee) && $requestSelectedEmployee == $row['emp_id']) || ($currentUserId && $row['emp_id'] == $currentUserId && empty($requestSelectedEmployee)) ? 'selected' : '';
                            $empAllowed = $isAdmin || $canRequestForOthers || ($currentUserId && $row['emp_id'] == $currentUserId);
                          ?>
                          <option value="<?php echo htmlspecialchars($row['emp_id']); ?>" <?php echo $selEmp; ?> <?php echo $empAllowed ? '' : 'disabled'; ?>><?php echo htmlspecialchars($row['label']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="request_attendance_date_admin" class="form-label">Date <span class="text-danger">*</span></label>
                      <input type="date" class="form-control" id="request_attendance_date_admin" name="request_date" required
                             min="<?php echo htmlspecialchars(date('Y-m-d', strtotime('-30 days'))); ?>"
                             max="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                      <div class="form-text request-attendance-help">Requests must be within the last 30 days.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="request_attendance_time_admin" class="form-label">Time <span class="text-danger">*</span></label>
                      <input type="time" class="form-control" id="request_attendance_time_admin" name="request_time" required step="1">
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="request_reason_admin" class="form-label">Reason <span class="text-danger">*</span></label>
                      <select class="form-select" id="request_reason_admin" name="reason_code" required>
                        <option value="" disabled selected>Select Reason</option>
                        <?php foreach ($manualAttendanceReasons as $reasonValue => $reasonLabel): ?>
                          <option value="<?php echo htmlspecialchars($reasonValue); ?>"><?php echo htmlspecialchars($reasonLabel); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="request_remarks_admin" class="form-label">Remarks</label>
                      <textarea class="form-control" id="request_remarks_admin" name="remarks" rows="1" placeholder="Optional contextual notes"></textarea>
                    </div>
                    <div class="col-12 text-end">
                      <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>

          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Review Request Modal -->
<div class="modal fade" id="reviewRequestModal" tabindex="-1" aria-labelledby="reviewRequestModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="reviewRequestModalLabel">Review Attendance Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="reviewRequestForm" method="POST" action="review_attendance_request.php">
        <?php if ($attendanceRequestCsrfToken): ?>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($attendanceRequestCsrfToken); ?>">
        <?php endif; ?>
        <input type="hidden" name="request_id" id="review_request_id">
        <input type="hidden" name="review_action" id="review_action">
        
        <div class="modal-body">
          <div class="mb-3">
            <label class="fw-bold">Employee:</label>
            <div id="review_emp_name"></div>
          </div>
          <div class="row mb-3">
            <div class="col-6">
              <label class="fw-bold">Date:</label>
              <div id="review_date"></div>
            </div>
            <div class="col-6">
              <label class="fw-bold">Time:</label>
              <div id="review_time"></div>
            </div>
          </div>
          <div class="mb-3">
            <label class="fw-bold">Reason:</label>
            <div id="review_reason"></div>
          </div>
          <div class="mb-3">
            <label class="fw-bold">User Remarks:</label>
            <div id="review_user_remarks" class="text-muted fst-italic"></div>
          </div>
          
          <hr>
          
          <div class="mb-3">
            <label for="review_notes" class="form-label">Approval/Rejection Remarks</label>
            <textarea class="form-control" id="review_notes" name="review_notes" rows="3" placeholder="Enter reason for rejection or approval notes..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" onclick="submitReview('reject')">Reject</button>
          <button type="button" class="btn btn-success" onclick="submitReview('approve')">Approve</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>


<!-- Edit Attendance Modal -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-labelledby="editAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editAttendanceModalLabel">Edit Attendance Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editAttendanceForm" method="POST" action="update_attendance.php">
        <div class="modal-body">
          <input type="hidden" id="edit_attendance_id" name="attendanceId">
          
          <!-- Employee Details Section -->
          <div class="p-3 mb-3 rounded">
            <div class="d-flex align-items-center">
              <img id="edit_emp_image" src="" alt="Employee Image" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
              <div>
                <h5 id="edit_emp_name" class="mb-1"></h5>
                <p class="mb-0 text-muted">
                  <span id="edit_emp_designation"></span> • 
                  <span id="edit_emp_branch"></span>
                </p>
              </div>
            </div>
          </div>
          
          <!-- Attendance Details Section -->
          <div class="row g-3">
            <div class="col-md-6 mb-3">
              <label for="edit_attendance_date" class="form-label">Attendance Date</label>
              <input type="date" class="form-control" id="edit_attendance_date" name="attendanceDate" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="edit_attendance_time" class="form-label">Attendance Time</label>
              <input type="time" class="form-control" id="edit_attendance_time" name="attendanceTime" step="1" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="edit_reason" class="form-label">Reason</label>
              <select class="form-select" id="edit_reason" name="reason" required>
                <?php foreach ($manualAttendanceReasons as $reasonValue => $reasonLabel): ?>
                  <option value="<?php echo htmlspecialchars($reasonValue); ?>"><?php echo htmlspecialchars($reasonLabel); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="edit_remarks" class="form-label">Remarks</label>
              <input type="text" class="form-control" id="edit_remarks" name="remarks" placeholder="Optional remarks">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Attendance</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Attendance Modal -->
<div class="modal fade" id="deleteAttendanceModal" tabindex="-1" aria-labelledby="deleteAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteAttendanceModalLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete this attendance record? This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
      </div>
    </div>
  </div>
</div>

      <!-- Attendance Logs Details Modal -->
      <div class="modal fade" id="attendanceLogsModal" tabindex="-1" aria-labelledby="attendanceLogsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="attendanceLogsModalLabel">Attendance Logs</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div id="attendanceLogsSummary" class="mb-2 small text-muted"></div>
              <div class="table-responsive">
                <table class="table table-sm" id="attendanceLogsTable">
                  <thead><tr><th>Time</th><th>Method</th><th>Reason / Remarks</th><th class="text-end">Actions</th></tr></thead>
                  <tbody id="attendanceLogsBody"><tr><td colspan="4" class="text-center small text-muted">Loading...</td></tr></tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>

<!-- Include the main footer (which closes content-wrapper, main-wrapper, etc.) -->
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Small helpers to safely escape text for HTML insertion
  function escapeHtml(str){ return (str||'').toString().replace(/[&<>"']/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[m]; }); }
  function escapeAttr(s){ return escapeHtml(s); }
  // Initialize DataTable
  const attendanceTable = new DataTable('#attendance-table', {
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    ordering: false, // Disable sorting
    pageLength: 10,
    language: {
  emptyTable: 'No attendance records found.',
      paginate: {
        previous: '<i class="fas fa-chevron-left"></i>',
        next: '<i class="fas fa-chevron-right"></i>'
      }
    }
  });

  const manualReasonMap = <?php echo json_encode($manualAttendanceReasons); ?>;

  // Branch change - fetch employees (manual modal)
  const manualEmpBranch = document.getElementById('empBranch');
  const manualEmpSelect = document.getElementById('emp_id');
  if (manualEmpBranch && manualEmpSelect) {
    manualEmpBranch.addEventListener('change', function() {
      const branch = this.value;
      if (branch) {
        fetch('fetch_attendance_employees.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            branch: branch,
            date_from: limitedMaxDateFrom || '',
            date_to: todayDate || ''
          })
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.text();
        })
        .then(data => {
          manualEmpSelect.innerHTML = data;
        })
        .catch(error => {
          console.error('Error fetching employees:', error);
          manualEmpSelect.innerHTML = '<option value="">Error loading employees</option>';
        });
      } else {
        manualEmpSelect.innerHTML = '<option value="">Select Employee</option>';
      }
    });
  }

  // Expose permission flag to client-side
  const canManageAttendance = <?php echo json_encode($canManageAttendance); ?>;
  const isLimitedNonAdmin = <?php echo json_encode(!empty($isLimitedNonAdmin)); ?>;
  const isBranchRestrictedViewer = <?php echo json_encode(!empty($isBranchRestrictedViewer)); ?>;
  const limitedMaxDateFrom = <?php echo json_encode(date('Y-m-d', strtotime('-30 days'))); ?>;
  const todayDate = <?php echo json_encode(date('Y-m-d')); ?>;
  const canApproveRequests = <?php echo json_encode($canApproveRequests); ?>;
  const canRequestForOthers = <?php echo json_encode($canRequestForOthers); ?>;
  const canRequestMultiBranch = <?php echo json_encode($canRequestMultiBranch || $isAdmin); ?>;
  const canRequestAttendance = <?php echo json_encode($canRequestAttendance); ?>;
  const currentUserId = <?php echo json_encode($currentUserId); ?>;
  const currentUserBranchId = <?php echo json_encode($currentUserBranchId); ?>;
  const requestEmployeeLockedToSelf = <?php echo json_encode($requestEmployeeLockedToSelf); ?>;

  // Also make the top filter employee dropdown dynamic when branch filter changes
  const filterBranch = document.getElementById('filter_branch');
  const filterEmployee = document.getElementById('filter_employee');
  const initialSelectedEmployee = <?php echo json_encode($selectedEmployee ?? ''); ?>;
  if (filterBranch && filterEmployee) {
    filterBranch.addEventListener('change', function(){
      const branchVal = this.value || '';
      const dateFromVal = (document.getElementById('filter_date_from') || {}).value || '';
      const dateToVal = (document.getElementById('filter_date_to') || {}).value || '';
      // fetch filtered employees
      fetch('../../fetch_users.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ branch: branchVal, date_from: dateFromVal, date_to: dateToVal })
      })
      .then(r => r.text())
      .then(html => {
        // fetch_users.php returns <option> elements — keep "All Employees" as default
        filterEmployee.innerHTML = '<option value="">All Employees</option>' + html;
        // Try to keep selected employee if present in response
        if(initialSelectedEmployee){
          const opt = filterEmployee.querySelector('option[value="' + initialSelectedEmployee + '"]');
          if(opt) opt.selected = true;
        }
      })
      .catch(err => { console.error('Failed to load employees for branch', err); });
    });
  }

  // Branch change for periodic attendance - fetch employees
  const periodicEmpBranch = document.getElementById('periodicEmpBranch');
  const periodicEmpSelect = document.getElementById('periodic_emp_id');
  if (periodicEmpBranch && periodicEmpSelect) {
    periodicEmpBranch.addEventListener('change', function() {
      const branch = this.value;
      if (branch) {
        const startDateVal = (document.getElementById('periodicStartDate') || {}).value || '';
        const endDateVal = (document.getElementById('periodicEndDate') || {}).value || '';
        fetch('fetch_attendance_employees.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            branch: branch,
            date_from: startDateVal || limitedMaxDateFrom || '',
            date_to: endDateVal || todayDate || ''
          })
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.text();
        })
        .then(data => {
          periodicEmpSelect.innerHTML = data;
        })
        .catch(error => {
          console.error('Error fetching employees:', error);
          periodicEmpSelect.innerHTML = '<option value="">Error loading employees</option>';
        });
      } else {
        periodicEmpSelect.innerHTML = '<option value="">Select Employee</option>';
      }
    });
  }

  const requestEmpBranch = document.getElementById('requestEmpBranch');
  const requestEmpSelect = document.getElementById('request_emp_id_admin');
  if (requestEmpBranch && requestEmpSelect) {
    const loadEmployeesForBranch = (branch) => {
      if (!branch) {
        requestEmpSelect.innerHTML = '<option value="">Select Employee</option>';
        return;
      }
      fetch('fetch_attendance_employees.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          branch: branch,
          date_from: limitedMaxDateFrom || '',
          date_to: todayDate || ''
        })
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.text();
      })
      .then(data => {
        requestEmpSelect.innerHTML = data;
      })
      .catch(error => {
        console.error('Error fetching employees:', error);
        requestEmpSelect.innerHTML = '<option value="">Error loading employees</option>';
      });
    };

    if (requestEmployeeLockedToSelf) {
      if (currentUserId && !requestEmpBranch.value) {
        requestEmpBranch.value = currentUserBranchId || '';
      }
      return;
    }

    if (!canRequestMultiBranch && currentUserBranchId && !requestEmpBranch.value) {
      requestEmpBranch.value = currentUserBranchId;
    }

    if (requestEmpBranch.value) {
      loadEmployeesForBranch(requestEmpBranch.value);
    }

    requestEmpBranch.addEventListener('change', function() {
      loadEmployeesForBranch(this.value);
    });
  }

  // Date validation for periodic attendance
  const periodicStartDate = document.getElementById('periodicStartDate');
  const periodicEndDate = document.getElementById('periodicEndDate');
  if (periodicStartDate && periodicEndDate) {
    periodicStartDate.addEventListener('change', function() {
      const startDate = this.value;
      if (startDate) {
        periodicEndDate.min = startDate;
        if (periodicEndDate.value && periodicEndDate.value < startDate) {
          periodicEndDate.value = startDate;
        }
      }
    });

    periodicEndDate.addEventListener('change', function() {
      const endDate = this.value;
      if (endDate) {
        periodicStartDate.max = endDate;
        if (periodicStartDate.value && periodicStartDate.value > endDate) {
          periodicStartDate.value = endDate;
        }
      }
    });
  }

  // Select all days checkbox handler
  const selectAllDaysCheckbox = document.getElementById('selectAllDays');
  if (selectAllDaysCheckbox) {
    selectAllDaysCheckbox.addEventListener('change', function() {
      document.querySelectorAll('input[name="workingDays[]"]').forEach(checkbox => {
        checkbox.checked = this.checked;
      });
    });
  }

  // Individual day checkbox handler to update "select all" state
  const workingDayCheckboxes = document.querySelectorAll('input[name="workingDays[]"]');
  if (workingDayCheckboxes.length && selectAllDaysCheckbox) {
    workingDayCheckboxes.forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        const allCheckboxes = document.querySelectorAll('input[name="workingDays[]"]');
        const checkedCheckboxes = document.querySelectorAll('input[name="workingDays[]"]:checked');
        selectAllDaysCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
        selectAllDaysCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
      });
    });
  }

  // Debug: Add form submission handler for periodic attendance
  const periodicAttendanceForm = document.getElementById('manualPeriodicAttendance');
  if (periodicAttendanceForm) {
    periodicAttendanceForm.addEventListener('submit', function(e) {
      const checkedDays = document.querySelectorAll('input[name="workingDays[]"]:checked');
      if (checkedDays.length === 0) {
        e.preventDefault();
        alert('Please select at least one working day.');
        return false;
      }
    });
  }

  // Edit Attendance Modal Handler
  const editAttendanceModal = document.getElementById('editAttendanceModal');
  if (editAttendanceModal) {
    editAttendanceModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      
      // Extract data
      const id = button.getAttribute('data-id');
      const date = button.getAttribute('data-date');
      const time = button.getAttribute('data-time');
      const reason = button.getAttribute('data-reason');
      const remarks = button.getAttribute('data-remarks');
      const empName = button.getAttribute('data-emp-name');
      const empId = button.getAttribute('data-emp-id');
      const empImage = button.getAttribute('data-emp-image');
      const designation = button.getAttribute('data-designation');
      const branch = button.getAttribute('data-branch');
      
      // Set form values
      document.getElementById('edit_attendance_id').value = id;
      document.getElementById('edit_attendance_date').value = date;
      document.getElementById('edit_attendance_time').value = time;
      document.getElementById('edit_reason').value = reason || "1";
      document.getElementById('edit_remarks').value = remarks || "";
      
      // Set employee details
      document.getElementById('edit_emp_name').textContent = empName;
      document.getElementById('edit_emp_designation').textContent = designation || "Not Assigned";
      document.getElementById('edit_emp_branch').textContent = branch;
      
      // Set employee image
      const imageElem = document.getElementById('edit_emp_image');
      let imagePath = empImage || '../../resources/userimg/default-image.jpg';
      // If the image path doesn't start with ../ or http, it's stored without the relative path
      if (empImage && !empImage.startsWith('../') && !empImage.startsWith('http')) {
        imagePath = '../../' + empImage;
      }
      imageElem.src = imagePath;
      imageElem.onerror = function() { this.src = '../../resources/userimg/default-image.jpg'; };
    });
  }
  
  // Delete Attendance Modal Handler
  const deleteAttendanceModal = document.getElementById('deleteAttendanceModal');
  if (deleteAttendanceModal) {
    deleteAttendanceModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      const id = button.getAttribute('data-id');
      document.getElementById('confirmDeleteBtn').href = 'delete-attendance.php?id=' + id;
    });
  }

    // Details / Logs view for aggregated rows
    const attendanceTableEl = document.getElementById('attendance-table');
    if (attendanceTableEl) {
      attendanceTableEl.addEventListener('click', function(e){
        const btn = e.target.closest('.show-logs');
        if(!btn) return;
        const emp = btn.dataset.emp;
        const date = btn.dataset.date;
        const modalEl = document.getElementById('attendanceLogsModal');
        const body = document.getElementById('attendanceLogsBody');
        const summary = document.getElementById('attendanceLogsSummary');
        if(!modalEl || !body) return;
        body.innerHTML = '<tr><td colspan="4" class="text-center small text-muted">Loading...</td></tr>';
        summary.textContent = '';
        fetch('fetch-logs.php', { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body: new URLSearchParams({ emp_id: emp, date: date }), credentials:'include' })
        .then(r=>r.json())
        .then(res=>{
          if(res.status !== 'ok'){ body.innerHTML = `<tr><td colspan="4" class="text-center text-danger">${res.message||'Failed to load logs'}</td></tr>`; return; }
          const rows = res.data || [];
          if(!rows.length){ body.innerHTML = '<tr><td colspan="4" class="text-center small text-muted">No logs found</td></tr>'; }
          else {
            body.innerHTML = rows.map(l=>{
              const m = (l.method||'');
              const methodLabel = m==0? 'Auto' : (m==1? 'Manual' : (m==2? 'Web' : 'Unknown'));
              const raw = l.manual_reason||'';
              let rId='', rRem='';
              if(raw.indexOf('||')!==-1){ [rId, rRem] = raw.split('||').map(s=>s.trim()); }
              else if(raw.indexOf('|')!==-1){ [rId, rRem] = raw.split('|').map(s=>s.trim()); }
              else { rId = raw.trim(); }
              const reasonLabel = (/^\d+$/.test(rId) && manualReasonMap[rId]) ? manualReasonMap[rId] : rId;
              const reasonText = [reasonLabel, rRem].filter(Boolean).join(' - ');
              // Actions: edit (open editAttendanceModal populated) and delete (open deleteAttendanceModal)
              const showActions = canManageAttendance && parseInt(l.method)===1;
              return `<tr>
                <td>${escapeHtml(l.time)}</td>
                <td>${escapeHtml(methodLabel)}</td>
                <td>${escapeHtml(reasonText)}</td>
                <td class="text-end">
                  ${ showActions ? `<button class="btn btn-sm btn-outline-primary me-2 log-edit" data-id="${l.id}" data-date="${l.date}" data-time="${l.time}" data-reason="${escapeAttr(l.manual_reason||'')}" data-emp-id="${escapeAttr(l.emp_id)}">Edit</button>` : '' }
                  ${ showActions ? `<button class="btn btn-sm btn-outline-danger log-delete" data-id="${l.id}">Delete</button>` : '' }
                </td>
              </tr>`;
            }).join('');
            // prefer a friendly display name when available (data-emp-name), otherwise fall back to numeric emp id
            const displayEmp = (btn.dataset.empName && btn.dataset.empName.trim()) ? btn.dataset.empName.trim() : emp;
            summary.textContent = `Showing ${rows.length} log(s) for ${displayEmp} on ${date}`;
            // attach handlers for edit/delete within modal
            const empName = btn.dataset.empName || '';
            const empImage = btn.dataset.empImage || '';
            const empDesignation = btn.dataset.designation || '';
            const empBranchName = btn.dataset.branchName || '';

            body.querySelectorAll('.log-edit').forEach(b=>{
              b.addEventListener('click', e=>{
                const id = b.dataset.id;
                const d = b.dataset.date;
                const t = b.dataset.time;
                const rr = b.dataset.reason || '';
                // populate edit modal fields
                const editModal = new bootstrap.Modal(document.getElementById('editAttendanceModal'));
                document.getElementById('edit_attendance_id').value = id;
                document.getElementById('edit_attendance_date').value = d;
                document.getElementById('edit_attendance_time').value = t;
                // parse reason into select and remarks if possible
                const reasonSelect = document.getElementById('edit_reason');
                const remarksInput = document.getElementById('edit_remarks');
                if(rr){
                  let reasonVal = '';
                  let remarksVal = '';
                  if(rr.indexOf('||')!==-1){ [reasonVal, remarksVal] = rr.split('||').map(s=>s.trim()); }
                  else if(rr.indexOf('|')!==-1){ [reasonVal, remarksVal] = rr.split('|').map(s=>s.trim()); }
                  else reasonVal = rr.trim();
                  if(reasonSelect) reasonSelect.value = reasonVal || '1';
                  if(remarksInput) remarksInput.value = remarksVal || '';
                } else {
                  if(reasonSelect) reasonSelect.value = '1';
                  if(remarksInput) remarksInput.value = '';
                }
                // set employee info in the edit modal
                document.getElementById('edit_emp_name').textContent = empName;
                document.getElementById('edit_emp_designation').textContent = empDesignation || 'Not Assigned';
                document.getElementById('edit_emp_branch').textContent = empBranchName || '';
                const imageElem = document.getElementById('edit_emp_image');
                if(imageElem){
                  let imagePath = empImage || '../../resources/userimg/default-image.jpg';
                  if(empImage && !empImage.startsWith('../') && !empImage.startsWith('http')) imagePath = '../../'+empImage;
                  imageElem.src = imagePath;
                  imageElem.onerror = function(){ this.src='../../resources/userimg/default-image.jpg'; };
                }
                // show modal
                editModal.show();
              });
            });
            body.querySelectorAll('.log-delete').forEach(b=>{
              b.addEventListener('click', e=>{
                const id = b.dataset.id;
                // set delete link and show delete modal
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteAttendanceModal'));
                document.getElementById('confirmDeleteBtn').href = 'delete-attendance.php?id=' + id;
                deleteModal.show();
              });
            });
          }
        })
        .catch(err=>{ body.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading logs</td></tr>'; console.error(err); });
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl); modal.show();
      });
    }
  
  // Check for edit parameter in URL and auto-open edit modal
  const urlParams = new URLSearchParams(window.location.search);
  const editId = urlParams.get('edit');
  const action = urlParams.get('action');
  
  if (editId) {
    // Find the edit button for this attendance record and trigger it
    const editButton = document.querySelector(`[data-id="${editId}"].edit-attendance`);
    if (editButton) {
      // Simulate a click on the edit button to open the modal with data
      editButton.click();
      
      // Clean up the URL by removing the edit parameter
      const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
      window.history.replaceState(null, null, newUrl);
    }
  }
  
  // Auto-open Add Attendance modal on demand (manual upload/request tabs)
  const actionTabTargets = {
    manual: 'manual-single-tab',
    'manual-single': 'manual-single-tab',
    'manual-periodic': 'manual-periodic-tab',
    upload: 'upload-tab'
  };

  if (action && actionTabTargets[action]) {
    const addAttendanceModalEl = document.getElementById('addAttendanceModal');
    if (addAttendanceModalEl) {
      const shouldClearAction = action === 'manual';
      if (shouldClearAction) {
        const newUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
        window.history.replaceState(null, null, newUrl);
      }

      setTimeout(() => {
        const addAttendanceModal = new bootstrap.Modal(addAttendanceModalEl);
        addAttendanceModal.show();
        addAttendanceModal._element.addEventListener('shown.bs.modal', function() {
          const targetTabId = actionTabTargets[action];
          if (!targetTabId) {
            return;
          }
          const targetTabButton = document.getElementById(targetTabId);
          if (targetTabButton) {
            const tabInstance = new bootstrap.Tab(targetTabButton);
            tabInstance.show();
          }
        }, { once: true });
      }, 100);
    }
  }

  // Request Attendance form validation (limited users and admin tab)
  const enhanceRequestAttendanceForms = () => {
    const requestForms = document.querySelectorAll('.request-attendance-form');
    requestForms.forEach((form) => {
      const dateInput = form.querySelector('input[name="request_date"]');
      const timeInput = form.querySelector('input[name="request_time"]');
      if (!dateInput || !timeInput) {
        return;
      }

      const updateDateConstraints = () => {
        const minDateValue = dateInput.getAttribute('min');
        if (minDateValue && dateInput.value && dateInput.value < minDateValue) {
          dateInput.value = minDateValue;
        }

        if (dateInput.value === todayDate) {
          const now = new Date();
          const timeStr = now.toLocaleTimeString('en-GB', { hour12: false });
          timeInput.max = timeStr;
        } else {
          timeInput.removeAttribute('max');
        }
      };

      dateInput.addEventListener('change', updateDateConstraints);
      updateDateConstraints();

      form.addEventListener('submit', function(e) {
        if (!dateInput.value || !timeInput.value) {
          return;
        }

        const minDateValue = dateInput.getAttribute('min');
        if (minDateValue && dateInput.value < minDateValue) {
          e.preventDefault();
          alert('You can only request attendance within the last 30 days.');
          return;
        }

        const selectedDateTime = new Date(`${dateInput.value}T${timeInput.value}`);
        const now = new Date();
        if (selectedDateTime.getTime() > now.getTime()) {
          e.preventDefault();
          alert('You cannot request attendance for a future date or time.');
        }
      });
    });
  };
  enhanceRequestAttendanceForms();

  // Review Request Modal Handler
  const reviewRequestModal = document.getElementById('reviewRequestModal');
  if (reviewRequestModal) {
    reviewRequestModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      
      document.getElementById('review_request_id').value = button.getAttribute('data-id');
      document.getElementById('review_emp_name').textContent = button.getAttribute('data-emp');
      document.getElementById('review_date').textContent = button.getAttribute('data-date');
      document.getElementById('review_time').textContent = button.getAttribute('data-time');
      document.getElementById('review_reason').textContent = button.getAttribute('data-reason');
      document.getElementById('review_user_remarks').textContent = button.getAttribute('data-remarks') || 'None';
      document.getElementById('review_notes').value = ''; // Clear previous notes
    });
  }

  // Expose submitReview function globally so onclick works
  window.submitReview = function(action) {
    if (!canApproveRequests) {
      alert('You do not have permission to approve or reject attendance requests.');
      return;
    }
    document.getElementById('review_action').value = action;
    document.getElementById('reviewRequestForm').submit();
  };

});
  // If this user is limited (non-admin with machine applicable), clamp available date range
  if (isLimitedNonAdmin) {
    const fromInput = document.getElementById('filter_date_from');
    const toInput = document.getElementById('filter_date_to');
    if (fromInput) {
      fromInput.min = limitedMaxDateFrom;
      if (fromInput.value && fromInput.value < limitedMaxDateFrom) {
        fromInput.value = limitedMaxDateFrom;
      }
    }
    if (toInput) {
      toInput.max = todayDate;
      if (toInput.value && toInput.value > todayDate) {
        toInput.value = todayDate;
      }
    }

    // Intercept filter form submit and validate dates
    const filterForm = document.querySelector('form[method="get"]');
    if (filterForm) {
      filterForm.addEventListener('submit', function(e) {
        const f = document.getElementById('filter_date_from').value;
        const t = document.getElementById('filter_date_to').value;
        if (f && f < limitedMaxDateFrom) {
          e.preventDefault();
          alert('Date range limited: "From" cannot be older than ' + limitedMaxDateFrom);
          document.getElementById('filter_date_from').value = limitedMaxDateFrom;
          return false;
        }
        if (t && t > todayDate) {
          e.preventDefault();
          alert('Date range limited: "To" cannot be in the future. Resetting to today.');
          document.getElementById('filter_date_to').value = todayDate;
          return false;
        }
        // Enforce limit cap (if user manipulates URL to choose bigger limit)
        const limitSelect = document.getElementById('filter_limit');
        if (limitSelect) {
          const selectedLimit = parseInt(limitSelect.value, 10) || 0;
          if (selectedLimit > 200) {
            e.preventDefault();
            alert('Result limit limited to 200 for your account. Please choose a smaller limit.');
            limitSelect.value = '200';
            return false;
          }
        }
      });
    }

    // Reduce limit options client-side for clarity
    const limitSelect = document.getElementById('filter_limit');
    if (limitSelect) {
      for (const opt of Array.from(limitSelect.options)) {
        if (parseInt(opt.value, 10) > 200) opt.remove();
      }
    }
  }
</script>
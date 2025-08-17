<?php
// Start output buffering to prevent issues with redirects
ob_start();

$page = 'attendance';

include '../../includes/session_config.php';
include '../../includes/db_connection.php';
include '../../includes/utilities.php';

// Debug: Add error display for development
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Check if we need to open the modal in manual mode
$openManualModal = isset($_GET['action']) && $_GET['action'] === 'manual';

// Fetching attendance data
try {
  // Read filters from query params
  $selectedBranch = isset($_GET['branch']) && $_GET['branch'] !== '' ? (int)$_GET['branch'] : null;
  $dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
  $dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;
  $allowedLimits = [50, 100, 200, 500, 1000];
  $limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowedLimits, true) ? (int)$_GET['limit'] : 200;

  // Build dynamic SQL with filters
  $sql = "SELECT a.*, 
                  e.first_name, e.last_name, e.middle_name, e.branch, e.emp_id, e.user_image, 
                  b.name, 
                  d.title AS designation
           FROM attendance_logs a 
           LEFT JOIN employees e ON a.emp_id = e.emp_id
           LEFT JOIN branches b ON e.branch = b.id 
           LEFT JOIN designations d ON e.designation = d.id";

  $conditions = [];
  $params = [];
  if ($selectedBranch) {
    $conditions[] = 'e.branch = :branch';
    $params[':branch'] = $selectedBranch;
  }
  if ($dateFrom && $dateTo) {
    $conditions[] = 'a.date BETWEEN :date_from AND :date_to';
    $params[':date_from'] = $dateFrom;
    $params[':date_to'] = $dateTo;
  } elseif ($dateFrom) {
    $conditions[] = 'a.date >= :date_from';
    $params[':date_from'] = $dateFrom;
  } elseif ($dateTo) {
    $conditions[] = 'a.date <= :date_to';
    $params[':date_to'] = $dateTo;
  }
  if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
  }
  $sql .= ' ORDER BY a.date DESC, a.time DESC ';
  // LIMIT must be a literal integer (not a bound param) for MySQL
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
?>

<style>
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
    border-left: 4px solid #0dcaf0;
    background-color: #e7f3ff;
}

.nav-tabs .nav-link {
    border-radius: 0.375rem 0.375rem 0 0;
}

.nav-tabs .nav-link.active {
    border-color: #dee2e6 #dee2e6 #fff;
    background-color: #fff;
}

.tab-pane {
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    padding: 1.5rem;
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
          <h1 class="fs-2 fw-bold mb-1">Attendance Records</h1>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAttendanceModal">
          <i class="fas fa-plus me-2"></i> Add Attendance
        </button>
      </div>
      
      <!-- Attendance Filters + Table Card -->
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <!-- Filters -->
          <form method="get" class="row g-3 mb-3 align-items-end">
            <div class="col-md-3">
              <label for="filter_branch" class="form-label">Branch</label>
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
            </div>
            <div class="col-md-3">
              <label for="filter_date_from" class="form-label">From Date</label>
              <input type="date" id="filter_date_from" name="date_from" class="form-control" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
            </div>
            <div class="col-md-3">
              <label for="filter_date_to" class="form-label">To Date</label>
              <input type="date" id="filter_date_to" name="date_to" class="form-control" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
            </div>
            <div class="col-md-2">
              <label for="filter_limit" class="form-label">Limit</label>
              <select id="filter_limit" name="limit" class="form-select">
                <?php 
                  $limits = [50,100,200,500,1000];
                  $selLimit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
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
          <div class="table-responsive">
            <table id="attendance-table" class="table table-hover">
              <thead>
                <tr>
                  <th class="text-center">ID</th>
                  <th>Employee</th>
                  <th class="text-center">Branch</th>
                  <th class="text-center">Date</th>
                  <th class="text-center">Time</th>
                  <th class="text-center">Method</th>
                  <th>Reason</th>
                  <th class="text-center">Actions</th>
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
                        <div class="fw-bold"><?php echo htmlspecialchars(trim(($record['first_name'] ?? '') . ' ' . ($record['middle_name'] ?? '') . ' ' . ($record['last_name'] ?? ''))); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars(($record['designation'] ?? '') !== '' ? $record['designation'] : 'Not Assigned'); ?></small>
                      </div>
                    </div>
                  </td>
                  <td class="text-center align-middle"><?php echo htmlspecialchars($record['name'] ?? ''); ?></td>
                  <td class="text-center align-middle"><?php echo !empty($record['date']) ? date('M d, Y', strtotime($record['date'])) : '-'; ?></td>
                  <td class="text-center align-middle"><?php echo htmlspecialchars($record['time'] ?? ''); ?></td>
                  <td class="text-center align-middle">
                    <?php 
                    $methodText = '<span class="badge bg-secondary">Unknown</span>'; // Default
                    if (isset($record['method'])) {
                        switch ($record['method']) {
                            case 0:
                                $methodText = '<span class="badge bg-primary">Auto</span>';
                                break;
                            case 1:
                                $methodText = '<span class="badge bg-warning" style="color: #000;">Manual</span>';
                                break;
                            case 2:
                                $methodText = '<span class="badge bg-info">Web</span>';
                                break;
                        }
                    }
                    echo $methodText;
                    ?>
                  </td>
                  <td>
                    <?php 
                    // Map reason code to label
                    $reasonMap = [
                      '1' => 'Card Forgot',
                      '2' => 'Card Lost',
                      '3' => 'Forgot to Punch',
                      '4' => 'Office Work Delay',
                      '5' => 'Field Visit',
                    ];

                    if (!empty($record['manual_reason'])) {
                      $raw = (string)$record['manual_reason'];
                      $reasonId = trim($raw);
                      $remarks = '';
                      if (strpos($raw, '||') !== false) {
                        [$reasonId, $remarks] = array_map('trim', explode('||', $raw, 2));
                      } elseif (strpos($raw, '|') !== false) {
                        [$reasonId, $remarks] = array_map('trim', explode('|', $raw, 2));
                      }

                      // If numeric and mapped, use label; otherwise show the raw text (sanitized)
                      if (is_numeric($reasonId) && isset($reasonMap[$reasonId])) {
                        echo htmlspecialchars($reasonMap[$reasonId]);
                      } elseif ($reasonId !== '') {
                        echo htmlspecialchars($reasonId);
                      } else {
                        echo '<span class="text-muted">-</span>';
                      }

                      if ($remarks !== '') {
                        echo "<br><small class='text-muted'>-" . htmlspecialchars($remarks) . "</small>";
                      }
                    } else {
                      echo '<span class="text-muted">-</span>';
                    }
                    ?>
                  </td>
                  <td class="text-center align-middle">
                    <?php if ($record['method'] == 1): ?>
                    <div class="dropdown">
                      <a href="#" class="text-secondary" role="button" id="dropdownMenuButton<?php echo $record['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                      </a>
                      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $record['id']; ?>">
                        <li>
                          <a class="dropdown-item edit-attendance" href="#"
                              data-bs-toggle="modal" 
                              data-bs-target="#editAttendanceModal" 
                              data-id="<?php echo $record['id']; ?>"
                              data-date="<?php echo $record['date']; ?>"
                              data-time="<?php echo $record['time']; ?>"
                              data-reason="<?php echo !empty($record['manual_reason']) ? explode(' || ', $record['manual_reason'])[0] : ''; ?>"
                              data-remarks="<?php echo !empty($record['manual_reason']) && strpos($record['manual_reason'], ' || ') !== false ? explode(' || ', $record['manual_reason'])[1] : ''; ?>"
                              data-emp-name="<?php echo $record['first_name'] . ' ' . $record['last_name']; ?>"
                              data-emp-id="<?php echo $record['emp_id']; ?>"
                              data-emp-image="<?php echo $record['user_image']; ?>"
                              data-designation="<?php echo $record['designation']; ?>"
                              data-branch="<?php echo $record['name']; ?>">
                            <i class="fas fa-edit me-2"></i> Edit
                          </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <a class="dropdown-item text-danger delete-attendance" href="#"
                             data-bs-toggle="modal" 
                             data-bs-target="#deleteAttendanceModal"
                             data-id="<?php echo $record['id']; ?>">
                            <i class="fas fa-trash me-2"></i> Delete
                          </a>
                        </li>
                      </ul>
                    </div>
                    <?php else: ?>
                    <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
        <?php 
          endforeach; 
        }
        // Note: Do not render a colspan row here; DataTables does not support colspan in tbody.
        ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div> <!-- /.container-fluid -->
    
<!-- Modals remain outside the main content flow, before the final footer include -->
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
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-tab-pane" type="button" role="tab" aria-controls="upload-tab-pane" aria-selected="true">Upload</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="manual-single-tab" data-bs-toggle="tab" data-bs-target="#manual-single-tab-pane" type="button" role="tab" aria-controls="manual-single-tab-pane" aria-selected="false">Manual (Single)</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="manual-periodic-tab" data-bs-toggle="tab" data-bs-target="#manual-periodic-tab-pane" type="button" role="tab" aria-controls="manual-periodic-tab-pane" aria-selected="false">Manual (Periodic)</button>
          </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content mt-3" id="attendanceTabsContent">
          <!-- Upload Tab -->
          <div class="tab-pane fade show active" id="upload-tab-pane" role="tabpanel" aria-labelledby="upload-tab" tabindex="0">
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
          
          <!-- Manual (Single) Tab -->
          <div class="tab-pane fade" id="manual-single-tab-pane" role="tabpanel" aria-labelledby="manual-single-tab" tabindex="0">
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
                    <option value="1">Card Forgot</option>
                    <option value="2">Card Lost</option>
                    <option value="3">Forgot to Punch</option>
                    <option value="4">Office Work Delay</option>
                    <option value="5">Field Visit</option>
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
          
          <!-- Manual (Periodic) Tab -->
          <div class="tab-pane fade" id="manual-periodic-tab-pane" role="tabpanel" aria-labelledby="manual-periodic-tab" tabindex="0">
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
                    <option value="1">Card Forgot</option>
                    <option value="2">Card Lost</option>
                    <option value="3">Forgot to Punch</option>
                    <option value="4">Office Work Delay</option>
                    <option value="5">Field Visit</option>
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
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

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
                <option value="1">Card Forgot</option>
                <option value="2">Card Lost</option>
                <option value="3">Forgot to Punch</option>
                <option value="4">Office Work Delay</option>
                <option value="5">Field Visit</option>
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

<!-- Include the main footer (which closes content-wrapper, main-wrapper, etc.) -->
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize DataTable
  const attendanceTable = new DataTable('#attendance-table', {
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[3, 'desc'], [4, 'desc']], // Sort by date and time
    pageLength: 10,
    language: {
  emptyTable: 'No attendance records found.',
      paginate: {
        previous: '<i class="fas fa-chevron-left"></i>',
        next: '<i class="fas fa-chevron-right"></i>'
      }
    }
  });

  // Branch change - fetch employees
  document.getElementById('empBranch').addEventListener('change', function() {
    const branch = this.value;
    if (branch) {
      fetch('../../fetch_users.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'branch=' + branch
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.text();
      })
      .then(data => {
        document.getElementById('emp_id').innerHTML = data;
      })
      .catch(error => {
        console.error('Error fetching employees:', error);
        document.getElementById('emp_id').innerHTML = '<option value="">Error loading employees</option>';
      });
    } else {
      document.getElementById('emp_id').innerHTML = '<option value="">Select Employee</option>';
    }
  });

  // Branch change for periodic attendance - fetch employees
  document.getElementById('periodicEmpBranch').addEventListener('change', function() {
    const branch = this.value;
    if (branch) {
      fetch('../../fetch_users.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'branch=' + branch
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.text();
      })
      .then(data => {
        document.getElementById('periodic_emp_id').innerHTML = data;
      })
      .catch(error => {
        console.error('Error fetching employees:', error);
        document.getElementById('periodic_emp_id').innerHTML = '<option value="">Error loading employees</option>';
      });
    } else {
      document.getElementById('periodic_emp_id').innerHTML = '<option value="">Select Employee</option>';
    }
  });

  // Date validation for periodic attendance
  document.getElementById('periodicStartDate').addEventListener('change', function() {
    const startDate = this.value;
    const endDateInput = document.getElementById('periodicEndDate');
    
    if (startDate) {
      endDateInput.min = startDate;
      if (endDateInput.value && endDateInput.value < startDate) {
        endDateInput.value = startDate;
      }
    }
  });

  document.getElementById('periodicEndDate').addEventListener('change', function() {
    const endDate = this.value;
    const startDateInput = document.getElementById('periodicStartDate');
    
    if (endDate) {
      startDateInput.max = endDate;
      if (startDateInput.value && startDateInput.value > endDate) {
        startDateInput.value = endDate;
      }
    }
  });

  // Select all days checkbox handler
  document.getElementById('selectAllDays').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('input[name="workingDays[]"]');
    checkboxes.forEach(checkbox => {
      checkbox.checked = this.checked;
    });
  });

  // Individual day checkbox handler to update "select all" state
  document.querySelectorAll('input[name="workingDays[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
      const allCheckboxes = document.querySelectorAll('input[name="workingDays[]"]');
      const checkedCheckboxes = document.querySelectorAll('input[name="workingDays[]"]:checked');
      const selectAllCheckbox = document.getElementById('selectAllDays');
      
      selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
      selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
    });
  });

  // Debug: Add form submission handler for periodic attendance
  document.getElementById('manualPeriodicAttendance').addEventListener('submit', function(e) {
    console.log('Periodic attendance form submitted');
    
    // Check if at least one working day is selected
    const checkedDays = document.querySelectorAll('input[name="workingDays[]"]:checked');
    if (checkedDays.length === 0) {
      e.preventDefault();
      alert('Please select at least one working day.');
      return false;
    }
    
    // Log form data for debugging
    const formData = new FormData(this);
    console.log('Form data:');
    for (let [key, value] of formData.entries()) {
      console.log(key, value);
    }
  });

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
  
  // Check for manual parameter and auto-open add attendance modal
  if (action === 'manual') {
    // Clean up the URL immediately to prevent re-opening on refresh
    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
    window.history.replaceState(null, null, newUrl);
    
    // Open the add attendance modal and switch to manual tab after a short delay
    setTimeout(() => {
      const addAttendanceModal = new bootstrap.Modal(document.getElementById('addAttendanceModal'));
      addAttendanceModal.show();
      
      // Switch to manual single tab after modal is shown
      addAttendanceModal._element.addEventListener('shown.bs.modal', function() {
        const manualSingleTab = new bootstrap.Tab(document.getElementById('manual-single-tab'));
        manualSingleTab.show();
      }, { once: true });
    }, 100);
  }
});
</script>
<?php
// Ensure session configuration (custom session name & path) is loaded before any session usage
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
// Temporary debug helper - visit holidays.php?_debug=1 to log request/session info
if (isset($_GET['_debug']) && $_GET['_debug'] == '1') {
    $debug = [];
    $debug['request_uri'] = $_SERVER['REQUEST_URI'] ?? '';
    $debug['script_name'] = $_SERVER['SCRIPT_NAME'] ?? '';
    $debug['php_self'] = $_SERVER['PHP_SELF'] ?? '';
    $debug['query_string'] = $_SERVER['QUERY_STRING'] ?? '';
    $debug['session'] = [];
    // Capture cookies and session information
    $debug['cookies'] = $_COOKIE ?? [];
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $debug['session_id'] = session_id();
    foreach (['user_id','redirect_to'] as $k) { $debug['session'][$k] = $_SESSION[$k] ?? null; }
    // Check for session file existence (session save path configured in session_config.php)
    $sessSavePath = ini_get('session.save_path') ?: (dirname(__DIR__) . '/sessions');
    $sessFile = rtrim($sessSavePath, "\/\\") . DIRECTORY_SEPARATOR . 'sess_' . session_id();
    $debug['session_file'] = ['path' => $sessFile, 'exists' => file_exists($sessFile)];
    $debug['time'] = date('c');
    file_put_contents(__DIR__ . '/holidays_debug.log', print_r($debug, true), FILE_APPEND);
}

// Ensure session is started for flash messages
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Handle add holiday
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_holiday'])) {
    $name = trim($_POST['holiday_name'] ?? '');
    $startDate = $_POST['holiday_start_date'] ?? '';
    $endDate = $_POST['holiday_end_date'] ?? $startDate;
    $type = $_POST['holiday_type'] ?? 'company';
    $branchId = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : null;
    $description = trim($_POST['holiday_description'] ?? '');
    $recurringType = $_POST['recurring_type'] ?? 'none';
    // Ensure recurring day-of-week is stored as integer or NULL (avoid empty string causing SQL error)
    $recurringDow = (isset($_POST['recurring_day_of_week']) && $_POST['recurring_day_of_week'] !== '') ? (int)$_POST['recurring_day_of_week'] : null;
    $isRecurring = ($recurringType !== 'none') ? 1 : 0;

    if ($name === '' || $startDate === '') {
        $_SESSION['error'] = 'Holiday name and date are required.';
        header('Location: holidays.php');
        exit;
    }

    // Insert using extended holidays schema (includes start_date/end_date/branch_id/is_recurring)
    // Allowed types must match the holidays.type ENUM in the database
    $allowedTypes = ['national','religious','company'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'company';
    }
    $isRec = ($recurringType !== 'none') ? 1 : 0;
    // Use 'status' column (enum 'active'/'inactive') and store canonical date in start_date/end_date
    $insertStmt = $pdo->prepare("INSERT INTO holidays (name, start_date, end_date, type, branch_id, description, is_recurring, recurring_type, recurring_day_of_week, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $exec = $insertStmt->execute([
        $name,
        $startDate ?: null,
        $endDate ?: null,
        $type,
        $branchId,
        $description,
        $isRec,
        $recurringType,
        $recurringDow,
        'active'
    ]);

    if ($exec) {
        $_SESSION['success'] = 'Holiday added successfully.';
    } else {
        $_SESSION['error'] = 'Failed to add holiday.';
    }
    header('Location: holidays.php');
    exit;
}

// Handle holiday deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_holiday'], $_POST['holiday_id'])) {
    $deleteId = (int)$_POST['holiday_id'];
    $delStmt = $pdo->prepare("DELETE FROM holidays WHERE id = ?");
    if ($delStmt->execute([$deleteId])) {
        $_SESSION['success'] = 'Holiday deleted successfully.';
    } else {
        $_SESSION['error'] = 'Failed to delete holiday.';
    }
    // Redirect to avoid form resubmission
    header('Location: holidays.php');
    exit;
}

$page = 'Holiday Management';
$home = '../../';
// ...existing code...
$stmt = $pdo->query("SELECT * FROM holidays");
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize year and view filters
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedView = isset($_GET['view']) ? $_GET['view'] : 'all';

// Fetch branches for dropdown
$branchStmt = $pdo->query("SELECT id, name FROM branches ORDER BY name");
$branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

// Include the header
require_once '../../includes/header.php';
?>

<style>
    .accordion-button {
            transition: background-color 0.2s;
        }
        body.dark-mode .accordion-button {
            background-color: var(--bs-gray-900) !important;
            color: var(--bs-gray-100) !important;
            border-color: var(--bs-gray-800) !important;
        }
        body.dark-mode .accordion-item {
            background-color: var(--bs-gray-900) !important;
        }
</style>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Holiday Management</h1>
            <p class="text-muted mb-0">Manage company holidays and special days</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
            <i class="fas fa-plus me-2"></i> Add Holiday
        </button>
    </div>

    <!-- Year Filter and Stats -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h3 class="text-primary mb-1"><?php echo count($holidays); ?></h3>
                                <small class="text-muted">Total Holidays</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h3 class="text-success mb-1"><?php echo count(array_filter($holidays, function($h) { return $h['type'] === 'national'; })); ?></h3>
                                <small class="text-muted">National Holidays</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h3 class="text-info mb-1"><?php echo count(array_filter($holidays, function($h) { return $h['type'] === 'company'; })); ?></h3>
                                <small class="text-muted">Company Holidays</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h3 class="text-warning mb-1"><?php echo count(array_filter($holidays, function($h) { return $h['type'] === 'optional'; })); ?></h3>
                                <small class="text-muted">Optional Holidays</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <label for="yearFilter" class="form-label">Filter by Year</label>
                    <select class="form-select" id="yearFilter" onchange="applyHolidayFilters()">
                        <?php for ($year = date('Y') - 2; $year <= date('Y') + 2; $year++): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($year == $currentYear) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <div class="mt-3">
                        <label for="viewFilter" class="form-label">Show</label>
                        <select class="form-select" id="viewFilter" onchange="applyHolidayFilters()">
                            <option value="all" <?php echo $selectedView === 'all' ? 'selected' : ''; ?>>All holidays for the year</option>
                            <option value="upcoming" <?php echo $selectedView === 'upcoming' ? 'selected' : ''; ?>>Upcoming holidays</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly-wise Holidays Accordion -->
    <div class="accordion" id="holidaysAccordion">
        <?php
        // Group holidays by month
        $months = [];
        foreach ($holidays as $holiday) {
            // Compute display date for recurring holidays in the selected year
            $today = date('Y-m-d');
            $nowYear = (int)date('Y');
            $isRecurring = (int)($holiday['is_recurring'] ?? 0) === 1;
            $rtype = $holiday['recurring_type'] ?? ($isRecurring ? 'annually' : 'none');
    // Prefer start_date when present (range support).
    $baseDate = $holiday['start_date'] ?? null;
            $displayDateYmd = $baseDate;
            if ($isRecurring && !empty($baseDate)) {
                $baseTs = strtotime($baseDate);
                $baseM = (int)date('n', $baseTs);
                $baseD = (int)date('j', $baseTs);
                if ($rtype === 'annually') {
                    $displayDateYmd = sprintf('%04d-%02d-%02d', (int)$currentYear, (int)date('n', $baseTs), $baseD);
                } elseif ($rtype === 'monthly') {
                    $disp = null;
                    $startMonth = ($currentYear == $nowYear) ? (int)date('n') : 1;
                    for ($m = $startMonth; $m <= 12; $m++) {
                        if (checkdate($m, $baseD, (int)$currentYear)) {
                            $cand = sprintf('%04d-%02d-%02d', (int)$currentYear, $m, $baseD);
                            if ($currentYear > $nowYear || $cand >= $today) { $disp = $cand; break; }
                        }
                    }
                    $displayDateYmd = $disp ?: null;
                } elseif ($rtype === 'quarterly') {
                    $disp = null;
                    $startMonth = ($currentYear == $nowYear) ? (int)date('n') : 1;
                    for ($m = $startMonth; $m <= 12; $m++) {
                        if ( (($m - $baseM) % 3) === 0 && checkdate($m, $baseD, (int)$currentYear) ) {
                            $cand = sprintf('%04d-%02d-%02d', (int)$currentYear, $m, $baseD);
                            if ($currentYear > $nowYear || $cand >= $today) { $disp = $cand; break; }
                        }
                    }
                    $displayDateYmd = $disp ?: null;
                } elseif ($rtype === 'weekly') {
                    $dowWanted = (int)($holiday['recurring_day_of_week'] ?? date('N', $baseTs));
                    $startDate = ($currentYear == $nowYear) ? $today : sprintf('%04d-01-01', (int)$currentYear);
                    $startTs = strtotime($startDate);
                    $startDow = (int)date('N', $startTs);
                    $delta = ($dowWanted - $startDow + 7) % 7;
                    $candTs = strtotime("+$delta day", $startTs);
                    $cand = date('Y-m-d', $candTs);
                    if ((int)date('Y', $candTs) === (int)$currentYear) {
                        $displayDateYmd = ($currentYear > $nowYear || $cand >= $today) ? $cand : null;
                    } else {
                        $displayDateYmd = null;
                    }
                }
            }
            // Apply view filter: upcoming vs all
            if ($selectedView === 'upcoming') {
                if ($displayDateYmd === null) { continue; }
                $boundary = ($currentYear < $nowYear)
                    ? '9999-12-31'
                    : (($currentYear > $nowYear)
                        ? sprintf('%04d-01-01', (int)$currentYear)
                        : $today);
                if ($displayDateYmd < $boundary) { continue; }
            }
            if ($displayDateYmd === null) continue;
            $monthNum = (int)date('n', strtotime($displayDateYmd));
            $months[$monthNum][] = array_merge($holiday, ['display_date' => $displayDateYmd]);
        }
        $monthNames = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
        $currentMonth = (int)date('n');
        foreach ($monthNames as $mNum => $mName):
            $monthHolidays = $months[$mNum] ?? [];
            if (empty($monthHolidays)) continue;
            $expanded = ($mNum === $currentMonth) ? 'show' : '';
            $collapsed = ($mNum === $currentMonth) ? '' : 'collapsed';
            $ariaExpanded = ($mNum === $currentMonth) ? 'true' : 'false';
        ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?php echo $mNum; ?>">
                <button class="accordion-button <?php echo $collapsed; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $mNum; ?>" aria-expanded="<?php echo $ariaExpanded; ?>" aria-controls="collapse<?php echo $mNum; ?>">
                    <?php echo $mName; ?> <span class="ms-2 badge bg-primary"><?php echo count($monthHolidays); ?></span>
                </button>
            </h2>
            <div id="collapse<?php echo $mNum; ?>" class="accordion-collapse collapse <?php echo $expanded; ?>" aria-labelledby="heading<?php echo $mNum; ?>" data-bs-parent="#holidaysAccordion">
                <div class="accordion-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Holiday Name</th>
                                    <th class="text-center">Date</th>
                                    <th class="text-center">Day</th>
                                    <th class="text-center">Type</th>
                                    <th>Branch</th>
                                    <th class="text-center">Recurrence</th>
                                    <th>Description</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthHolidays as $holiday): ?>
                                <tr>
                                    <td class="align-middle">
                                        <strong><?php echo htmlspecialchars($holiday['name']); ?></strong>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php
                                            $start_raw = $holiday['start_date'] ?? ($holiday['display_date'] ?? null);
                                            $end_raw = $holiday['end_date'] ?? ($holiday['display_date'] ?? null);
                                            $start = $start_raw ? date('M d, Y', strtotime($start_raw)) : '';
                                            $end = $end_raw ? date('M d, Y', strtotime($end_raw)) : '';
                                            echo ($start === $end) ? $start : ($start . ' - ' . $end);
                                        ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php echo date('l', strtotime($holiday['display_date'])); ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php
                                        $badgeClass = '';
                                        switch ($holiday['type']) {
                                            case 'national':
                                                $badgeClass = 'bg-success';
                                                break;
                                            case 'company':
                                                $badgeClass = 'bg-info';
                                                break;
                                            case 'optional':
                                                $badgeClass = 'bg-warning';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($holiday['type']); ?>
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <?php echo (isset($holiday['branch_name']) && $holiday['branch_name']) ? htmlspecialchars($holiday['branch_name']) : '<span class="text-muted">All Branches</span>'; ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php
                                            $rt = $holiday['recurring_type'] ?? ($holiday['is_recurring'] ? 'annually' : 'none');
                                            $labels = [
                                                'none' => 'One-time',
                                                'weekly' => 'Weekly',
                                                'monthly' => 'Monthly',
                                                'quarterly' => 'Quarterly',
                                                'annually' => 'Annually'
                                            ];
                                            echo '<span class="badge bg-secondary">' . ($labels[$rt] ?? 'One-time') . '</span>';
                                        ?>
                                    </td>
                                    <td class="align-middle">
                                        <?php echo $holiday['description'] ? htmlspecialchars(substr($holiday['description'], 0, 50)) . '...' : '<span class="text-muted">No description</span>'; ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <button class="dropdown-item edit-holiday" 
                                                            data-id="<?php echo $holiday['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($holiday['name']); ?>"
                                                            data-start_date="<?php echo $holiday['start_date'] ?? ($holiday['display_date'] ?? ''); ?>"
                                                            data-end_date="<?php echo $holiday['end_date'] ?? ($holiday['display_date'] ?? ''); ?>"
                                                            data-type="<?php echo $holiday['type']; ?>"
                                                            data-description="<?php echo htmlspecialchars($holiday['description']); ?>"
                                                            data-recurring="<?php echo $holiday['is_recurring']; ?>"
                                                            data-recurring-type="<?php echo htmlspecialchars($holiday['recurring_type'] ?? ($holiday['is_recurring'] ? 'annually' : 'none')); ?>"
                                                            data-recurring-dow="<?php echo htmlspecialchars($holiday['recurring_day_of_week'] ?? ''); ?>"
                                                            data-branch="<?php echo $holiday['branch_id']; ?>"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editHolidayModal">
                                                        <i class="fas fa-edit me-2"></i> Edit
                                                    </button>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button class="dropdown-item text-danger delete-holiday" 
                                                            data-id="<?php echo $holiday['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($holiday['name']); ?>"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteHolidayModal">
                                                        <i class="fas fa-trash me-2"></i> Delete
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Holiday Modal -->
<div class="modal fade" id="addHolidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label for="holiday_name" class="form-label">Holiday Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="holiday_name" name="holiday_name" required>
                        </div>
                        <div class="col-md-5 mb-3 d-flex align-items-end">
                            <div style="width:100%;">
                                <label for="holiday_date_range" class="form-label">Date Range <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="holiday_date_range" name="holiday_date_range" required autocomplete="off">
                                <input type="hidden" id="holiday_start_date" name="holiday_start_date">
                                <input type="hidden" id="holiday_end_date" name="holiday_end_date">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="holiday_type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="holiday_type" name="holiday_type" required>
                                <option value="company">Company Holiday</option>
                                <option value="national">National Holiday</option>
                                <option value="optional">Optional Holiday</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="branch_id" class="form-label">Branch</label>
                            <select class="form-select" id="branch_id" name="branch_id">
                                <option value="">All Branches</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="holiday_description" class="form-label">Description</label>
                        <textarea class="form-control" id="holiday_description" name="holiday_description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="recurring_type" class="form-label">Recurrence</label>
                            <select class="form-select" id="recurring_type" name="recurring_type">
                                <option value="none">One-time</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annually">Annually</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="weekly_dow_container" style="display:none;">
                            <label for="recurring_day_of_week" class="form-label">Day of Week</label>
                            <select class="form-select" id="recurring_day_of_week" name="recurring_day_of_week">
                                <option value="">Select day</option>
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                                <option value="7">Sunday</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_holiday" class="btn btn-primary">Add Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Holiday Modal -->
<div class="modal fade" id="editHolidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit_holiday_id" name="holiday_id">
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label for="edit_holiday_name" class="form-label">Holiday Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_holiday_name" name="holiday_name" required>
                        </div>
                        <div class="col-md-5 mb-3 d-flex align-items-end">
                            <div style="width:100%;">
                                <label for="edit_holiday_date_range" class="form-label">Date Range <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_holiday_date_range" name="edit_holiday_date_range" required autocomplete="off">
                                <input type="hidden" id="edit_holiday_start_date" name="holiday_start_date">
                                <input type="hidden" id="edit_holiday_end_date" name="holiday_end_date">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_holiday_type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_holiday_type" name="holiday_type" required>
                                <option value="company">Company Holiday</option>
                                <option value="national">National Holiday</option>
                                <option value="optional">Optional Holiday</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_branch_id" class="form-label">Branch</label>
                            <select class="form-select" id="edit_branch_id" name="branch_id">
                                <option value="">All Branches</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_holiday_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_holiday_description" name="holiday_description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_recurring_type" class="form-label">Recurrence</label>
                            <select class="form-select" id="edit_recurring_type" name="recurring_type">
                                <option value="none">One-time</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annually">Annually</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="edit_weekly_dow_container" style="display:none;">
                            <label for="edit_recurring_day_of_week" class="form-label">Day of Week</label>
                            <select class="form-select" id="edit_recurring_day_of_week" name="recurring_day_of_week">
                                <option value="">Select day</option>
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                                <option value="7">Sunday</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_holiday" class="btn btn-primary">Update Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Holiday Modal -->
<div class="modal fade" id="deleteHolidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete_holiday_id" name="holiday_id">
                    <p>Are you sure you want to delete the holiday "<strong id="delete_holiday_name"></strong>"?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_holiday" class="btn btn-danger">Delete Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include the main footer -->
<?php require_once '../../includes/footer.php'; ?>

<!-- DataTables CSS & JS (paths fixed relative to $home) -->
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<style>
body.dark-mode .daterangepicker {
    background: #23272b !important;
    color: #f8f9fa !important;
}
body.dark-mode .daterangepicker .calendar-table {
    background: #23272b !important;
    color: #f8f9fa !important;
}
body.dark-mode .daterangepicker .calendar-table th,
body.dark-mode .daterangepicker .calendar-table td {
    color: #f8f9fa !important;
}
body.dark-mode .daterangepicker .ranges li {
    color: #f8f9fa !important;
}
body.dark-mode .daterangepicker .applyBtn,
body.dark-mode .daterangepicker .cancelBtn {
    background: #343a40 !important;
    color: #f8f9fa !important;
    border-color: #23272b !important;
}
</style>
<script src="<?php echo $home; ?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $home; ?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $home; ?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $home; ?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<!-- SweetAlert2 Flash Messages -->
<script>
<?php if (isset($_SESSION['success'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: <?php echo json_encode($_SESSION['success']); ?>,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    <?php unset($_SESSION['success']); ?>
<?php elseif (isset($_SESSION['error'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: <?php echo json_encode($_SESSION['error']); ?>,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true
    });
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>

<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Daterangepicker for Add Holiday
    if (window.$ && $('#holiday_date_range').length) {
        $('#holiday_date_range').daterangepicker({
            opens: 'center',
            autoUpdateInput: false,
            locale: {
                format: 'DD/MM/YYYY',
                cancelLabel: 'Clear'
            },
            ranges: {
                'Tomorrow': [moment().add(1, 'days'), moment().add(1, 'days')],
                'Day After Tomorrow': [moment().add(2, 'days'), moment().add(2, 'days')],
                'Next Week': [moment().add(1, 'weeks').startOf('week'), moment().add(1, 'weeks').endOf('week')],
                'Next Month': [moment().add(1, 'months').startOf('month'), moment().add(1, 'months').endOf('month')]
            }
        });
        $('#holiday_date_range').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
            $('#holiday_start_date').val(picker.startDate.format('YYYY-MM-DD'));
            $('#holiday_end_date').val(picker.endDate.format('YYYY-MM-DD'));
        });
        $('#holiday_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            $('#holiday_start_date').val('');
            $('#holiday_end_date').val('');
        });
    }
    // Daterangepicker for Edit Holiday
    if (window.$ && $('#edit_holiday_date_range').length) {
        $('#edit_holiday_date_range').daterangepicker({
            opens: 'center',
            autoUpdateInput: false,
            locale: {
                format: 'DD/MM/YYYY',
                cancelLabel: 'Clear'
            },
            ranges: {
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Quarter': [moment().startOf('quarter'), moment().endOf('quarter')],
                'Last Quarter': [moment().subtract(1, 'quarter').startOf('quarter'), moment().subtract(1, 'quarter').endOf('quarter')],
                'Custom Range': []
            }
        });
        $('#edit_holiday_date_range').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
            $('#edit_holiday_start_date').val(picker.startDate.format('YYYY-MM-DD'));
            $('#edit_holiday_end_date').val(picker.endDate.format('YYYY-MM-DD'));
        });
        $('#edit_holiday_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            $('#edit_holiday_start_date').val('');
            $('#edit_holiday_end_date').val('');
        });
    }
    // Initialize DataTable (support both vanilla DataTables 2 and jQuery DataTables 1.x)
    try {
        if (typeof DataTable === 'function' && (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable)) {
            new DataTable('#holidays-table', {
                responsive: true,
                lengthChange: true,
                autoWidth: false,
                order: [[1, 'asc']], // Sort by date by default
                pageLength: 10,
                language: {
                    paginate: {
                        previous: '<i class="fas fa-chevron-left"></i>',
                        next: '<i class="fas fa-chevron-right"></i>'
                    }
                }
            });
        } else if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
            jQuery('#holidays-table').DataTable({
                responsive: true,
                lengthChange: true,
                autoWidth: false,
                order: [[1, 'asc']], // Sort by date by default
                pageLength: 10,
                language: {
                    paginate: {
                        previous: '<i class="fas fa-chevron-left"></i>',
                        next: '<i class="fas fa-chevron-right"></i>'
                    }
                }
            });
        }
    } catch (e) {
        console.error('Failed to initialize DataTable:', e);
    }

    // Edit Holiday Modal Handler
    const editHolidayModal = document.getElementById('editHolidayModal');
    if (editHolidayModal) {
        editHolidayModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            document.getElementById('edit_holiday_id').value = button.getAttribute('data-id');
            document.getElementById('edit_holiday_name').value = button.getAttribute('data-name');
            document.getElementById('edit_holiday_start_date').value = button.getAttribute('data-start_date');
            document.getElementById('edit_holiday_end_date').value = button.getAttribute('data-end_date');
            document.getElementById('edit_holiday_type').value = button.getAttribute('data-type');
            document.getElementById('edit_holiday_description').value = button.getAttribute('data-description');
            document.getElementById('edit_branch_id').value = button.getAttribute('data-branch') || '';
            const rtype = button.getAttribute('data-recurring-type') || 'none';
            const rdow = button.getAttribute('data-recurring-dow') || '';
            const editRecType = document.getElementById('edit_recurring_type');
            const editDow = document.getElementById('edit_recurring_day_of_week');
            const editDowContainer = document.getElementById('edit_weekly_dow_container');
            if (editRecType) editRecType.value = rtype;
            if (editDow) editDow.value = rdow;
            if (editDowContainer) editDowContainer.style.display = (rtype === 'weekly') ? '' : 'none';
        });
    }

    // Delete Holiday Modal Handler
    const deleteHolidayModal = document.getElementById('deleteHolidayModal');
    if (deleteHolidayModal) {
        deleteHolidayModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            document.getElementById('delete_holiday_id').value = button.getAttribute('data-id');
            document.getElementById('delete_holiday_name').textContent = button.getAttribute('data-name');
        });
    }
});

// Toggle weekly DOW on add modal
const recurringType = document.getElementById('recurring_type');
const weeklyDowContainer = document.getElementById('weekly_dow_container');
if (recurringType && weeklyDowContainer) {
    recurringType.addEventListener('change', function() {
        weeklyDowContainer.style.display = (this.value === 'weekly') ? '' : 'none';
    });
}

// Toggle weekly DOW on edit modal
const editRecurringType = document.getElementById('edit_recurring_type');
const editWeeklyDowContainer = document.getElementById('edit_weekly_dow_container');
if (editRecurringType && editWeeklyDowContainer) {
    editRecurringType.addEventListener('change', function() {
        editWeeklyDowContainer.style.display = (this.value === 'weekly') ? '' : 'none';
    });
}

function applyHolidayFilters() {
    const year = document.getElementById('yearFilter')?.value || new Date().getFullYear();
    const view = document.getElementById('viewFilter')?.value || 'all';
    const params = new URLSearchParams(window.location.search);
    params.set('year', year);
    params.set('view', view);
    window.location.href = 'holidays.php?' + params.toString();
}
</script>

<style>
.stat-item {
    padding: 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.stat-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Table header styling - light and dark mode compatible */
.table th {
    font-weight: 600;
    border-top: none;
    background-color: var(--bs-gray-100);
    color: var(--bs-gray-800);
}

/* Dark mode table header */
body.dark-mode .table th {
    background-color: var(--bs-gray-800);
    color: var(--bs-gray-100);
    border-color: var(--bs-gray-700);
}

/* Dark mode table styling */
body.dark-mode .table {
    color: var(--bs-gray-100);
}

body.dark-mode .table td {
    border-color: var(--bs-gray-700);
}

body.dark-mode .table-hover tbody tr:hover {
    background-color: var(--bs-gray-700);
}

.accordion-button {
    transition: background-color 0.2s;
}
body.dark-mode .accordion-button {
    background-color: var(--bs-gray-900) !important;
    color: var(--bs-gray-100) !important;
    border-color: var(--bs-gray-800) !important;
}
body.dark-mode .accordion-item {
    background-color: var(--bs-gray-900) !important;
}

.badge {
    font-size: 0.75rem;
}
</style>

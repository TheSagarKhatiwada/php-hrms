<?php
$page = 'Holiday Management';
$home = '../../';

// Include utilities for role check functions
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';

// Check if user has permission to manage holidays (admin or HR)
if (!is_admin() && !has_permission('manage_holidays')) {
    $_SESSION['error'] = "You don't have permission to manage holidays.";
    header('Location: ../../dashboard.php');
    exit();
}

// Include database connection
include '../../includes/db_connection.php';

// Create holidays table if it doesn't exist
try {
    $createTableSQL = "CREATE TABLE IF NOT EXISTS holidays (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        date DATE NOT NULL,
        type ENUM('national', 'company', 'optional') DEFAULT 'company',
        description TEXT,
        is_recurring BOOLEAN DEFAULT FALSE,
        branch_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTableSQL);
} catch (PDOException $e) {
    error_log("Error creating holidays table: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_holiday'])) {
        // Add new holiday
        $name = trim($_POST['holiday_name']);
        $date = $_POST['holiday_date'];
    $type = $_POST['holiday_type'];
        $description = trim($_POST['holiday_description']);
    $recurring_type = $_POST['recurring_type'] ?? 'none';
    $recurring_day_of_week = isset($_POST['recurring_day_of_week']) && $_POST['recurring_day_of_week'] !== '' ? (int)$_POST['recurring_day_of_week'] : null;
    // Back-compat flag
    $is_recurring = ($recurring_type !== 'none') ? 1 : 0;
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : null;

        try {
            $stmt = $pdo->prepare("INSERT INTO holidays (name, date, type, description, is_recurring, branch_id, recurring_type, recurring_day_of_week) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $date, $type, $description, $is_recurring, $branch_id, $recurring_type, $recurring_day_of_week]);
            $_SESSION['success'] = "Holiday added successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding holiday: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit_holiday'])) {
        // Edit holiday
        $id = $_POST['holiday_id'];
        $name = trim($_POST['holiday_name']);
        $date = $_POST['holiday_date'];
        $type = $_POST['holiday_type'];
        $description = trim($_POST['holiday_description']);
    $recurring_type = $_POST['recurring_type'] ?? 'none';
    $recurring_day_of_week = isset($_POST['recurring_day_of_week']) && $_POST['recurring_day_of_week'] !== '' ? (int)$_POST['recurring_day_of_week'] : null;
    $is_recurring = ($recurring_type !== 'none') ? 1 : 0;
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : null;

        try {
            $stmt = $pdo->prepare("UPDATE holidays SET name = ?, date = ?, type = ?, description = ?, is_recurring = ?, branch_id = ?, recurring_type = ?, recurring_day_of_week = ? WHERE id = ?");
            $stmt->execute([$name, $date, $type, $description, $is_recurring, $branch_id, $recurring_type, $recurring_day_of_week, $id]);
            $_SESSION['success'] = "Holiday updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating holiday: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_holiday'])) {
        // Delete holiday
        $id = $_POST['holiday_id'];

        try {
            $stmt = $pdo->prepare("DELETE FROM holidays WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Holiday deleted successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting holiday: " . $e->getMessage();
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: holidays.php");
    exit();
}

// Fetch holidays for the selected year (default to current year)
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
// Basic sanity check to keep year within a reasonable range
if ($currentYear < 2000 || $currentYear > 2100) {
    $currentYear = (int)date('Y');
}
// View filter: 'all' or 'upcoming'
$selectedView = isset($_GET['view']) && $_GET['view'] === 'upcoming' ? 'upcoming' : 'all';
$nowYear = (int)date('Y');
if ($currentYear < $nowYear) {
    // Past year: do NOT include recurring holidays
    $stmt = $pdo->prepare("SELECT h.*, b.name as branch_name 
                           FROM holidays h 
                           LEFT JOIN branches b ON h.branch_id = b.id 
                           WHERE YEAR(h.date) = ?
                           ORDER BY h.date ASC");
    $stmt->execute([$currentYear]);
} elseif ($currentYear === $nowYear) {
    // Current year: include one-time holidays for this year, and only recurring holidays that still have an occurrence ahead in this year
    // Annual recurring considered upcoming if month-day >= today
    $sql = "SELECT h.*, b.name as branch_name
            FROM holidays h
            LEFT JOIN branches b ON h.branch_id = b.id
            WHERE YEAR(h.date) = :yr
               OR (
                    h.is_recurring = 1 AND (
                        h.recurring_type IN ('weekly','monthly','quarterly')
                        OR (
                            (h.recurring_type = 'annually' OR h.recurring_type IS NULL)
                            AND STR_TO_DATE(CONCAT(:yr2, DATE_FORMAT(h.date, '-%m-%d')), '%Y-%m-%d') >= CURDATE()
                        )
                    )
               )
            ORDER BY h.date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':yr' => $currentYear, ':yr2' => (string)$currentYear]);
} else {
    // Future year: include recurring holidays and one-time holidays
    $stmt = $pdo->prepare("SELECT h.*, b.name as branch_name 
                           FROM holidays h 
                           LEFT JOIN branches b ON h.branch_id = b.id 
                           WHERE YEAR(h.date) = ? OR h.is_recurring = 1
                           ORDER BY h.date ASC");
    $stmt->execute([$currentYear]);
}
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch branches for dropdown
$branchStmt = $pdo->query("SELECT id, name FROM branches ORDER BY name");
$branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

// Include the header
require_once '../../includes/header.php';
?>

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

    <!-- Holidays Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="holidays-table" class="table table-hover">
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
                        <?php foreach ($holidays as $holiday): 
                            // Compute display date for recurring holidays in the selected year; hide past occurrences for current year
                            $today = date('Y-m-d');
                            $nowYear = (int)date('Y');
                            $isRecurring = (int)($holiday['is_recurring'] ?? 0) === 1;
                            $rtype = $holiday['recurring_type'] ?? ($isRecurring ? 'annually' : 'none');
                            $baseDate = $holiday['date'];
                            $displayDateYmd = $baseDate;

                            if ($isRecurring && !empty($baseDate)) {
                                $baseTs = strtotime($baseDate);
                                $baseM = (int)date('n', $baseTs);
                                $baseD = (int)date('j', $baseTs);
                                if ($rtype === 'annually') {
                                    $displayDateYmd = sprintf('%04d-%02d-%02d', (int)$currentYear, (int)date('n', $baseTs), $baseD);
                                } elseif ($rtype === 'monthly') {
                                    // Next monthly occurrence in selected year
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
                                    // Months that align every 3 months from base month
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
                                    // Next weekly occurrence (day of week) in selected year
                                    $dowWanted = (int)($holiday['recurring_day_of_week'] ?? date('N', $baseTs)); // 1..7
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
                                // Determine boundary within selected year
                                if ($displayDateYmd === null) { continue; }
                                $boundary = ($currentYear < $nowYear)
                                    ? '9999-12-31'                            // ensures skip all for past years
                                    : (($currentYear > $nowYear)
                                        ? sprintf('%04d-01-01', (int)$currentYear) // all in future year are upcoming within that year
                                        : $today);                               // current year: from today
                                if ($displayDateYmd < $boundary) { continue; }
                            }
                        ?>
                        <tr>
                            <td class="align-middle">
                                <strong><?php echo htmlspecialchars($holiday['name']); ?></strong>
                            </td>
                            <td class="text-center align-middle">
                                <?php echo date('M d, Y', strtotime($displayDateYmd)); ?>
                            </td>
                            <td class="text-center align-middle">
                                <?php echo date('l', strtotime($displayDateYmd)); ?>
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
                                <?php echo $holiday['branch_name'] ? htmlspecialchars($holiday['branch_name']) : '<span class="text-muted">All Branches</span>'; ?>
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
                                                    data-date="<?php echo $holiday['date']; ?>"
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
                        <div class="col-md-6 mb-3">
                            <label for="holiday_name" class="form-label">Holiday Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="holiday_name" name="holiday_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="holiday_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="holiday_date" name="holiday_date" required>
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
                        <div class="col-md-6 mb-3">
                            <label for="edit_holiday_name" class="form-label">Holiday Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_holiday_name" name="holiday_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_holiday_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_holiday_date" name="holiday_date" required>
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
<script src="<?php echo $home; ?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $home; ?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $home; ?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $home; ?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

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
            document.getElementById('edit_holiday_date').value = button.getAttribute('data-date');
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

.badge {
    font-size: 0.75rem;
}
</style>

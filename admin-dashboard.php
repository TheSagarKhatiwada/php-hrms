<?php 
// Page meta
require_once 'includes' . DIRECTORY_SEPARATOR . 'settings.php';
require_once 'includes' . DIRECTORY_SEPARATOR . 'utilities.php';
require_once 'includes' . DIRECTORY_SEPARATOR . 'reason_helpers.php';
require_once 'includes' . DIRECTORY_SEPARATOR . 'schedule_helpers.php';

$page = 'Admin Dashboard';

// Shared manual attendance reasons
$manualAttendanceReasons = function_exists('hrms_reason_label_map')
    ? hrms_reason_label_map()
    : [
        '1' => 'Card Forgot',
        '2' => 'Card Lost',
        '3' => 'Forgot to Punch',
        '4' => 'Office Work Delay',
        '5' => 'Field Visit'
    ];

// Get current date and time values based on the set timezone
$today = date('Y-m-d');
$currentMonth = date('m');
$currentDay = date('d');
$currentYear = date('Y');
$currentTime = date('H:i:s');

// Include the main header (loads CSS/JS and opens layout wrappers)
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';

echo '<style>body.dark-mode .badge.bg-warning{color:#000 !important;}</style>';

// Open page container
echo '<div class="container-fluid">';

// Recompute timezone offset after header (ensures DB settings are applied)
try {
    $tzId = date_default_timezone_get();
    $date = new DateTime('now', new DateTimeZone($tzId ?: 'UTC'));
    $timezoneOffset = $date->format('P');
} catch (Throwable $e) {
    $timezoneOffset = '+00:00';
}

// Prepare greeting for the logged-in user (use only first name)
$userDisplayName = 'Admin';
try {
    if (isset($_SESSION['user_id'])) {
        $stmtUser = $pdo->prepare("SELECT first_name, middle_name, last_name FROM employees WHERE emp_id = ? LIMIT 1");
        $stmtUser->execute([$_SESSION['user_id']]);
        if ($row = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
            $first = trim((string)($row['first_name'] ?? ''));
            // If first name contains spaces, take the first token
            if ($first !== '') {
                $tokens = preg_split('/\s+/', $first);
                $first = $tokens && isset($tokens[0]) ? $tokens[0] : $first;
            }
            // Fallback to last name's first token if first name is missing
            if ($first === '') {
                $last = trim((string)($row['last_name'] ?? ''));
                if ($last !== '') {
                    $tokens = preg_split('/\s+/', $last);
                    $first = $tokens && isset($tokens[0]) ? $tokens[0] : $last;
                }
            }
            if ($first !== '') { $userDisplayName = $first; }
        }
    }
} catch (Throwable $e) { /* ignore, use default name */ }

$hourNow = (int)date('G');
if ($hourNow < 12) {
    $greetingWord = 'Good Morning';
} elseif ($hourNow < 17) {
    $greetingWord = 'Good Afternoon';
} elseif ($hourNow < 21) {
    $greetingWord = 'Good Evening';
} else {
    $greetingWord = 'Good Night';
}
$greetingText = $greetingWord . ', ' . $userDisplayName;

$canManageContacts = false;
if(function_exists('is_admin') && is_admin()) {
    $canManageContacts = true;
} elseif(function_exists('has_permission')) {
    $canManageContacts = has_permission('manage_contacts');
}

// Get attendance statistics
$totalEmployees = $totalBranches = $totalDepartments = $totalDesignations = $presentToday = $absentToday = $newHiresThisMonth = $birthdaysThisMonth = 0;
$recentAttendance = [];
$scheduleOverrides = [];
$upcomingCelebrations = [];
$attendanceDateFilter = isset($_GET['attendance_date']) && $_GET['attendance_date'] !== ''
    ? $_GET['attendance_date']
    : $today;

try {
    // Total employees
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM employees WHERE exit_date IS NULL");
    if ($result) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $totalEmployees = $row['cnt'] ?? 0;
    }
} catch (Throwable $e) {
    error_log("Dashboard error (total employees): " . $e->getMessage());
}

try {
    // Total branches
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM branches");
    if ($result) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $totalBranches = $row['cnt'] ?? 0;
    }
} catch (Throwable $e) {
    error_log("Dashboard error (total branches): " . $e->getMessage());
}

try {
    // Total departments
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM departments");
    if ($result) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $totalDepartments = $row['cnt'] ?? 0;
    }
} catch (Throwable $e) {
    error_log("Dashboard error (total departments): " . $e->getMessage());
}

try {
    // Total designations
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM designations");
    // Absent today (total employees - present)
    $absentToday = $totalEmployees - $presentToday;
    if ($absentToday < 0) {
        $absentToday = 0;
    }
} catch (Throwable $e) {
    error_log("Dashboard error (attendance today): " . $e->getMessage());
}

try {
    // New hires this month
    $monthStart = date('Y-m') . '-01';
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM employees WHERE exit_date IS NULL AND join_date >= :monthStart");
    if ($stmt && $stmt->execute([':monthStart' => $monthStart])) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $newHiresThisMonth = $row['cnt'] ?? 0;
    }
} catch (Throwable $e) {
    error_log("Dashboard error (new hires): " . $e->getMessage());
}

try {
    // Birthdays this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM employees WHERE exit_date IS NULL AND date_of_birth IS NOT NULL AND MONTH(date_of_birth) = :currentMonth");
    if ($stmt && $stmt->execute([':currentMonth' => (int)$currentMonth])) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $birthdaysThisMonth = $row['cnt'] ?? 0;
    }
} catch (Throwable $e) {
    error_log("Dashboard error (birthdays): " . $e->getMessage());
}

try {
    // Get upcoming celebrations (birthdays and anniversaries combined)
    $upcomingCelebrations = get_upcoming_celebrations(30, 8, null);
} catch (Throwable $e) {
    error_log("Dashboard error (upcoming celebrations): " . $e->getMessage());
    $upcomingCelebrations = [];
}

try {
    // Attendance by selected date for all employees
    $stmt = $pdo->prepare("\n        SELECT e.emp_id, e.first_name, e.last_name, e.middle_name, e.user_image,\n               e.work_start_time, e.work_end_time,\n               d.title as designation_name, b.name as branch_name,\n               ag.date, ag.in_time, ag.out_time, ag.cnt,\n               (SELECT manual_reason FROM attendance_logs l WHERE l.emp_id = e.emp_id AND l.date = :att_date AND l.time = ag.in_time LIMIT 1) AS in_reason,\n               (SELECT method FROM attendance_logs l WHERE l.emp_id = e.emp_id AND l.date = :att_date AND l.time = ag.in_time LIMIT 1) AS in_method,\n               (SELECT manual_reason FROM attendance_logs l WHERE l.emp_id = e.emp_id AND l.date = :att_date AND l.time = ag.out_time LIMIT 1) AS out_reason,\n               (SELECT method FROM attendance_logs l WHERE l.emp_id = e.emp_id AND l.date = :att_date AND l.time = ag.out_time LIMIT 1) AS out_method\n        FROM employees e\n        LEFT JOIN (\n            SELECT a.emp_id, a.date,\n                   MIN(a.time) AS in_time,\n                   CASE WHEN COUNT(*)>1 THEN MAX(a.time) ELSE NULL END AS out_time,\n                   COUNT(*) AS cnt\n            FROM attendance_logs a\n            WHERE a.date = :att_date\n            GROUP BY a.emp_id, a.date\n        ) ag ON e.emp_id = ag.emp_id\n        LEFT JOIN branches b ON e.branch = b.id\n        LEFT JOIN designations d ON e.designation_id = d.id\n        WHERE e.exit_date IS NULL\n          AND (e.join_date IS NULL OR e.join_date <= :att_date)\n          AND (e.mach_id_not_applicable IS NULL OR e.mach_id_not_applicable = 0)\n        ORDER BY e.first_name, e.last_name\n    ");
    $stmt->execute([':att_date' => $attendanceDateFilter]);
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $attendanceEmpIds = array_values(array_unique(array_filter(array_map(function($row) {
        return $row['emp_id'] ?? null;
    }, $recentAttendance))));
    $attendanceStart = $attendanceDateFilter;
    $attendanceEnd = $attendanceDateFilter;
    $scheduleOverrides = !empty($attendanceEmpIds)
        ? prefetch_schedule_overrides($pdo, $attendanceEmpIds, $attendanceStart, $attendanceEnd)
        : [];
} catch (Throwable $e) {
    error_log("Dashboard error (recent attendance): " . $e->getMessage());
}

?>

    <!-- Greeting Row -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="fs-4 fw-semibold"><?php echo htmlspecialchars($greetingText); ?></div>
                <div class="d-flex align-items-center">
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Quick settings">
                            Quick Settings
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <!-- Manage -->
                            <?php if ($canManageContacts): ?>
                            <li>
                                <a class="dropdown-item" href="modules/employees/employees.php">
                                    <i class="fas fa-users me-2"></i>Employees
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="permissions.php">
                                    <i class="fas fa-key me-2"></i>Permissions
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="branches.php">
                                    <i class="fas fa-code-branch me-2"></i>Branches
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="departments.php">
                                    <i class="fas fa-sitemap me-2"></i>Departments
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="designations.php">
                                    <i class="fas fa-id-badge me-2"></i>Designations
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- Communication -->
                            <li>
                                <a class="dropdown-item" href="notifications.php">
                                    <i class="fas fa-bell me-2"></i>Notifications
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="scheduled_notifications.php">
                                    <i class="far fa-calendar-alt me-2"></i>Scheduled Notifications
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- System -->
                            <li>
                                <a class="dropdown-item" href="system-settings.php">
                                    <i class="fas fa-cog me-2"></i>System Settings
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="maintenance.php">
                                    <i class="fas fa-tools me-2"></i>Maintenance Mode
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="backup-management.php">
                                    <i class="fas fa-database me-2"></i>Backup Management
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="clear-cache.php">
                                    <i class="fas fa-broom me-2"></i>Clear Cache
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="system-validation.php">
                                    <i class="fas fa-stethoscope me-2"></i>System Validation
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Time Card -->
    <div class="card mb-4 text-white shadow rounded-3 datetime-card" style="background: linear-gradient(135deg, var(--primary-color) 0%, #4e73df 100%);">
        <div class="card-body py-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="fs-3 mb-0"><?php echo hrms_format_preferred_date(date('Y-m-d'), 'l, F j, Y'); ?></h2>
                    <h4 class="opacity-85" id="live-time">Loading time...</h4>
                </div>
                <div class="col-md-6">
                    <div class="row align-items-center justify-content-end text-end">
                        <div class="col-auto">
                            <div class="d-flex flex-column">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="connection-dot online me-1"></div>
                                    <span class="small">System Online</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="connection-dot online me-1"></div>
                                    <span class="small">Database Connected</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
      
    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <!-- Total Employees -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3 stat-icon">
                            <i class="fas fa-users text-light fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">Total Employees</h6>
                            <h3 class="mb-0"><?php echo (int)$totalEmployees; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Present Today -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 stat-icon">
                            <i class="fas fa-user-check text-light fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">Present Today</h6>
                            <h3 class="mb-0"><?php echo (int)$presentToday; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Absent Today -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-danger bg-opacity-10 p-3 rounded-3 stat-icon">
                            <i class="fas fa-user-xmark text-light fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">Absent Today</h6>
                            <h3 class="mb-0"><?php echo (int)$absentToday; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- New Hires (This Month) -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-warning bg-opacity-10 p-3 rounded-3 stat-icon">
                            <i class="fas fa-user-plus text-light fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">New Hires (<?php echo date('M'); ?>)</h6>
                            <h3 class="mb-0"><?php echo (int)$newHiresThisMonth; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
      
    <div class="row g-2">
        <!-- Recent Attendance Table -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-transparent border-0 d-flex align-items-center py-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">Attendance <span class="text-muted" id="attendanceDateLabel"></span></h5>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <form method="get" class="d-flex align-items-center">
                            <button type="button" class="btn btn-sm btn-outline-primary" title="Previous day" aria-label="Previous day" onclick="shiftAttendanceDate(-1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <input type="date" class="form-control form-control-sm visually-hidden" name="attendance_date" value="<?php echo htmlspecialchars($attendanceDateFilter); ?>" max="<?php echo htmlspecialchars($today); ?>" onchange="handleAttendanceDateChange(this.value)">
                            <button type="button" class="btn btn-sm btn-outline-primary" title="Select date" aria-label="Select date" onclick="const input=this.previousElementSibling; if(input){ if(input.showPicker){ input.showPicker(); } else { input.focus(); input.click(); } }">
                                <i class="far fa-calendar-alt"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" title="Next day" aria-label="Next day" onclick="shiftAttendanceDate(1)" id="attendanceNextBtn">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </form>
                        <a href="modules/attendance/attendance.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-list me-1"></i> View All
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle" id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Branch</th>
                                    <th>In</th>
                                    <th>Out</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceTableBody">
                                <?php
                                if (empty($recentAttendance)) {
                                    echo "<tr><td colspan='5' class='text-center'>No attendance records found</td></tr>";
                                } else {
                                    foreach ($recentAttendance as $record) {
                                        // Format date and time
                                        $displayDate = $record['date'] ?? $attendanceDateFilter;
                                        $inTime = !empty($record['in_time']) ? date('h:i A', strtotime($record['in_time'])) : '-';
                                        $outTime = !empty($record['out_time']) ? date('h:i A', strtotime($record['out_time'])) : '-';
                                        
                                        // Get employee image or default
                                        $employeeImage = $record['user_image'] ?: 'resources/images/default-user.png';

                                        $renderMeta = function($method, $reason) use ($manualAttendanceReasons) {
                                            $parts = [];
                                            if ($method !== null) {
                                                switch ((int)$method) {
                                                    case 0: $parts[] = 'Auto'; break;
                                                    case 1: $parts[] = 'Manual'; break;
                                                    case 2: $parts[] = 'Web'; break;
                                                }
                                            }
                                            if (!empty($reason)) {
                                                if (strpos($reason, '||') !== false) {
                                                    [$rId, $rRem] = array_map('trim', explode('||', $reason, 2));
                                                } elseif (strpos($reason, '|') !== false) {
                                                    [$rId, $rRem] = array_map('trim', explode('|', $reason, 2));
                                                } else {
                                                    $rId = trim($reason);
                                                    $rRem = '';
                                                }
                                                $reasonLabel = (is_numeric($rId) && isset($manualAttendanceReasons[$rId])) ? $manualAttendanceReasons[$rId] : $rId;
                                                if ($reasonLabel !== '') {
                                                    $parts[] = $reasonLabel;
                                                }
                                                if (!empty($rRem)) {
                                                    $parts[] = $rRem;
                                                }
                                            }
                                            return implode(' | ', array_filter($parts));
                                        };

                                        $inMeta = $renderMeta($record['in_method'] ?? null, $record['in_reason'] ?? '');
                                        $outMeta = $renderMeta($record['out_method'] ?? null, $record['out_reason'] ?? '');
                                        $inMetaHtml = $inMeta !== '' ? "<small class='text-muted'>" . htmlspecialchars($inMeta) . "</small>" : '';
                                        $outMetaHtml = $outMeta !== '' ? "<small class='text-muted'>" . htmlspecialchars($outMeta) . "</small>" : '';

                                        $timeToSeconds = function($time) {
                                            if (empty($time) || strpos($time, ':') === false) {
                                                return null;
                                            }
                                            $parts = array_pad(explode(':', $time), 3, 0);
                                            return ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
                                        };

                                        $empId = $record['emp_id'] ?? null;
                                        $empRow = [
                                            'emp_id' => $empId,
                                            'work_start_time' => $record['work_start_time'] ?? null,
                                            'work_end_time' => $record['work_end_time'] ?? null
                                        ];
                                        $overridesForEmp = (!empty($empId) && isset($scheduleOverrides[$empId])) ? $scheduleOverrides[$empId] : [];
                                        $schedule = resolve_schedule_for_emp_date($empRow, $record['date'], $overridesForEmp, '09:00', '18:00');
                                        $startSec = $timeToSeconds($schedule['start'] ?? null);
                                        $endSec = $timeToSeconds($schedule['end'] ?? null);
                                        $inSec = $timeToSeconds($record['in_time'] ?? null);
                                        $outSec = $timeToSeconds($record['out_time'] ?? null);

                                        $formatDuration = function($seconds) {
                                            $seconds = max(0, (int)$seconds);
                                            $hours = intdiv($seconds, 3600);
                                            $minutes = intdiv($seconds % 3600, 60);
                                            if ($hours > 0) {
                                                return $hours . 'h ' . $minutes . 'm';
                                            }
                                            return $minutes . 'm';
                                        };

                                        $remarkParts = [];
                                        if ($inSec !== null && $startSec !== null && $inSec !== $startSec) {
                                            $diff = $inSec - $startSec;
                                            if ($diff > 0) {
                                                $remarkParts[] = 'Late In (' . $formatDuration($diff) . ')';
                                            } else {
                                                $remarkParts[] = 'Early In (' . $formatDuration(abs($diff)) . ')';
                                            }
                                        }
                                        if ($outSec !== null && $endSec !== null && $outSec !== $endSec) {
                                            $diff = $outSec - $endSec;
                                            if ($diff > 0) {
                                                $remarkParts[] = 'Late Out (' . $formatDuration($diff) . ')';
                                            } else {
                                                $remarkParts[] = 'Early Out (' . $formatDuration(abs($diff)) . ')';
                                            }
                                        }
                                        $remarkHtml = '';
                                        if (!empty($remarkParts)) {
                                            $remarkLines = array_map(function($item) {
                                                $label = $item;
                                                $class = '';
                                                $isLateIn = stripos($item, 'Late In') === 0;
                                                $isEarlyOut = stripos($item, 'Early Out') === 0;
                                                $isEarlyIn = stripos($item, 'Early In') === 0;
                                                $isLateOut = stripos($item, 'Late Out') === 0;
                                                if ($isLateIn || $isEarlyOut) {
                                                    $class = 'bg-warning text-dark';
                                                    if (preg_match('/\((\d+)h\s*(\d+)m\)/', $item, $m)) {
                                                        $hours = (int)$m[1];
                                                        if ($hours >= 1) {
                                                            $class = 'bg-danger';
                                                        }
                                                    }
                                                } elseif (stripos($item, 'Early In') === 0 || stripos($item, 'Late Out') === 0) {
                                                    $class = 'bg-success';
                                                }
                                                $safe = htmlspecialchars($label);
                                                return $class ? "<span class=\"badge {$class}\">{$safe}</span>" : $safe;
                                            }, $remarkParts);
                                            $remarkHtml = implode('<br>', $remarkLines);
                                        }
                                        
                                        echo "<tr>
                                                <td>
                                                    <div class='d-flex align-items-center'>
                                                        <img src='{$employeeImage}' class='rounded-circle me-2' style='width: 32px; height: 32px; object-fit: cover;'>
                                                        <div>
                                                            <div class='fw-medium'>{$record['first_name']} {$record['last_name']}</div>
                                                            <small class='text-muted'>{$record['designation_name']}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{$record['branch_name']}</td>
                                                <td>
                                                    <div class='fw-medium'>{$inTime}</div>
                                                    {$inMetaHtml}
                                                </td>
                                                <td>
                                                    <div class='fw-medium'>{$outTime}</div>
                                                    {$outMetaHtml}
                                                </td>
                                                <td>
                                                    <div class='small text-muted'>{$remarkHtml}</div>
                                                </td>
                                            </tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Celebrations (same width as attendance table) -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-transparent border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Upcoming Celebrations</h5>
                    <span class="badge rounded-pill bg-primary"><?php echo count($upcomingCelebrations ?? []); ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($upcomingCelebrations)): ?>
                        <div class="text-center py-4">
                            <i class="far fa-calendar-alt fa-3x text-muted mb-3"></i>
                            <p class="mb-0 text-muted">No upcoming celebrations in the next 30 days.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($upcomingCelebrations as $c): ?>
                                <?php 
                                    $isBirthday = ($c['celebration_type'] === 'birthday');
                                    $icon = $isBirthday ? 'fa-birthday-cake' : 'fa-award';
                                    $badge = $isBirthday ? 'bg-primary' : 'bg-info';
                                    $timeMsg = $c['days_until'] === 0 ? ($isBirthday ? 'Today 🎂' : 'Today 🎊') : ($c['days_until'] === 1 ? 'Tomorrow' : ($c['days_until'] . ' days'));
                                    if (!$isBirthday && isset($c['years_completed']) && $c['days_until'] === 0) { $timeMsg .= " ({$c['years_completed']} years)"; }
                                    $img = !empty($c['user_image']) ? $c['user_image'] : 'resources/images/default-user.png';
                                ?>
                                <div class="col-12 col-sm-6">
                                    <div class="border rounded-3 p-3 h-100 d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($img); ?>" class="rounded-circle me-3 flex-shrink-0" alt="<?php echo htmlspecialchars($c['first_name']); ?>" width="44" height="44" style="object-fit: cover;">
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars(trim($c['first_name'] . ' ' . ($c['middle_name'] ?? '') . ' ' . $c['last_name'])); ?></strong></br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($c['designation_name'] ?? ''); ?></small>
                                                </div>
                                                <span class="badge <?php echo $badge; ?>"> <i class="fas <?php echo $icon; ?> me-1"></i><?php echo $isBirthday ? 'Birthday' : 'Anniversary'; ?></span>
                                            </div>
                                            <small class="text-muted"><i class="far fa-calendar-alt me-1"></i><?php echo htmlspecialchars($c['display_date']); ?> • <?php echo $timeMsg; ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Side Column -->
        <div class="col-lg-4">
            <!-- Attendance Chart -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="card-title mb-0">Attendance Overview</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="attendanceChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="modules/employees/add-employee.php" class="btn btn-primary w-100 d-flex flex-column align-items-center p-3 h-100 dashboard-action-btn">
                                <i class="fas fa-user-plus fs-4 mb-2"></i>
                                <span>Add Employee</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="modules/attendance/attendance.php?action=manual" class="btn btn-success w-100 d-flex flex-column align-items-center p-3 h-100 dashboard-action-btn">
                                <i class="fas fa-clipboard-check fs-4 mb-2"></i>
                                <span>Record Attendance</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="modules/assets/manage_assets.php" class="btn btn-indigo w-100 d-flex flex-column align-items-center p-3 h-100 dashboard-action-btn text-white" style="background-color: #6610f2;">
                                <i class="fas fa-boxes fs-4 mb-2"></i>
                                <span>Manage Assets</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="modules/reports/attendance-reports.php" class="btn btn-warning w-100 d-flex flex-column align-items-center p-3 h-100 dashboard-action-btn text-dark">
                                <i class="fas fa-chart-bar fs-4 mb-2"></i>
                                <span>Reports</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-info w-100 d-flex flex-column align-items-center p-3 h-100 dashboard-action-btn text-white" data-bs-toggle="modal" data-bs-target="#sendSMSModal">
                                <i class="fas fa-sms fs-4 mb-2"></i>
                                <span>Send SMS</span>
                            </button>
                        </div>
                        <div class="col-6">
                            <a href="modules/sms/sms-dashboard.php" class="btn btn-secondary w-100 d-flex flex-column align-items-center p-3 h-100 dashboard-action-btn text-white">
                                <i class="fas fa-cog fs-4 mb-2"></i>
                                <span>SMS Dashboard</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> <!-- /.container-fluid -->

<!-- Include SMS Modal -->
<?php include 'includes' . DIRECTORY_SEPARATOR . 'sms-modal.php'; ?>

<!-- Page Scripts -->
<script>
(function() {
  // Clock runs immediately - no wait for load
  const serverTimezoneOffset = "<?php echo $timezoneOffset; ?>";
  const initialBsMode = <?php echo hrms_should_use_bs_dates() ? 'true' : 'false'; ?>;
  const nepaliDigitMap = { '0': '०', '1': '१', '2': '२', '3': '३', '4': '४', '5': '५', '6': '६', '7': '७', '8': '८', '9': '९' };

  function getCookieValue(name) {
    var cookies = document.cookie.split('; ');
    for (var i = 0; i < cookies.length; i++) {
      var parts = cookies[i].split('=');
      if (parts[0] === name) {
        return decodeURIComponent(parts[1] || '');
      }
    }
    return null;
  }

  function isBsModeEnabled() {
    const cookieMode = getCookieValue('date_display_mode');
    return cookieMode === 'bs' || initialBsMode;
  }

  function toNepaliDigits(value) {
    return String(value).replace(/[0-9]/g, function(digit) { return nepaliDigitMap[digit] || digit; });
  }

  function updateClock() {
    const now = new Date();
    let timeString = '';
    if (serverTimezoneOffset) {
      const offsetMatch = serverTimezoneOffset.match(/([+-])(\d{2}):(\d{2})/);
      if (offsetMatch) {
        const offsetSign = offsetMatch[1] === '+' ? 1 : -1;
        const offsetHours = parseInt(offsetMatch[2], 10);
        const offsetMinutes = parseInt(offsetMatch[3], 10);
        const totalOffsetMinutes = offsetSign * (offsetHours * 60 + offsetMinutes);
        const utcTime = now.getTime();
        const serverTime = new Date(utcTime + (totalOffsetMinutes * 60 * 1000));
        var hours = serverTime.getUTCHours();
        var ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        var minutes = serverTime.getUTCMinutes().toString().padStart(2, '0');
        var seconds = serverTime.getUTCSeconds().toString().padStart(2, '0');
        timeString = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
      } else {
        timeString = formatLocalTimeString(now);
      }
    } else {
      timeString = formatLocalTimeString(now);
    }
    var displayTime = isBsModeEnabled() ? toNepaliDigits(timeString) : timeString;
    var el = document.getElementById('live-time');
    if (el) el.textContent = displayTime;
  }

  function formatLocalTimeString(dateObj) {
    var hours = dateObj.getHours();
    var ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    var minutes = dateObj.getMinutes().toString().padStart(2, '0');
    var seconds = dateObj.getSeconds().toString().padStart(2, '0');
    return hours + ':' + minutes + ':' + seconds + ' ' + ampm;
  }

    function escapeHtml(str){
        return (str||'').toString().replace(/[&<>"']/g, function(m){
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
        });
    }

    const attendanceToday = <?php echo json_encode($today); ?>;

    function formatLocalDate(d){
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function updateAttendanceNavState(dateVal){
        const nextBtn = document.getElementById('attendanceNextBtn');
        if(!nextBtn) return;
        const todayStr = attendanceToday || formatLocalDate(new Date());
        nextBtn.disabled = !dateVal || dateVal >= todayStr;
    }

    window.shiftAttendanceDate = function(offsetDays){
        const dateInput = document.querySelector('input[name="attendance_date"]');
        if(!dateInput) return;
        const base = dateInput.value || attendanceToday || formatLocalDate(new Date());
        const d = new Date(base + 'T00:00:00');
        if(Number.isNaN(d.getTime())) return;
        d.setDate(d.getDate() + offsetDays);
        const maxDate = attendanceToday || formatLocalDate(new Date());
        const newVal = formatLocalDate(d);
        if (newVal > maxDate) return;
        dateInput.value = newVal;
        handleAttendanceDateChange(newVal);
    };

    function updateAttendanceDateLabel(dateVal){
        const label = document.getElementById('attendanceDateLabel');
        if(!label) return;
        const todayStr = attendanceToday || formatLocalDate(new Date());
        if(!dateVal){
            label.textContent = '';
            return;
        }
        const disp = new Date(dateVal + 'T00:00:00');
        const formatted = disp.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
        label.textContent = '— ' + formatted;
    }

    function formatDisplayDate(value){
        if(!value) return '';
        const d = new Date(value + 'T00:00:00');
        if(Number.isNaN(d.getTime())) return value;
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' });
    }

    function formatDisplayTime(value){
        if(!value) return '-';
        const d = new Date('1970-01-01T' + value);
        if(Number.isNaN(d.getTime())) return value;
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function buildAttendanceRow(row, dateFallback){
        const fullName = [row.first_name, row.middle_name, row.last_name].filter(Boolean).join(' ');
        const img = row.user_image ? row.user_image : 'resources/images/default-user.png';
        const inTime = formatDisplayTime(row.in_time);
        const outTime = formatDisplayTime(row.out_time);
        const inMeta = row.in_meta ? `<small class='text-muted'>${escapeHtml(row.in_meta)}</small>` : '';
        const outMeta = row.out_meta ? `<small class='text-muted'>${escapeHtml(row.out_meta)}</small>` : '';
        const remarks = (row.remarks || []).map(r => {
            const text = escapeHtml(r);
            const isLateIn = r.startsWith('Late In');
            const isEarlyOut = r.startsWith('Early Out');
            const isEarlyIn = r.startsWith('Early In');
            const isLateOut = r.startsWith('Late Out');
            if (isLateIn || isEarlyOut) {
                let cls = 'bg-warning text-dark';
                const match = r.match(/\((\d+)h\s*(\d+)m\)/);
                if (match && parseInt(match[1], 10) >= 1) {
                    cls = 'bg-danger';
                }
                return `<span class='badge ${cls}'>${text}</span>`;
            }
            if (isEarlyIn || isLateOut) {
                return `<span class='badge bg-success'>${text}</span>`;
            }
            return text;
        }).join('<br>');

        return [
            `<div class='d-flex align-items-center'>
                <img src='${escapeHtml(img)}' class='rounded-circle me-2' style='width: 32px; height: 32px; object-fit: cover;'>
                <div>
                    <div class='fw-medium'>${escapeHtml(fullName)}</div>
                    <small class='text-muted'>${escapeHtml(row.designation_name || '')}</small>
                </div>
            </div>`,
            escapeHtml(row.branch_name || ''),
            `<div class='fw-medium'>${escapeHtml(inTime)}</div>${inMeta}`,
            `<div class='fw-medium'>${escapeHtml(outTime)}</div>${outMeta}`,
            `<div class='small text-muted'>${remarks}</div>`
        ];
    }

    let attendanceAdjustTimer = null;
    function adjustAttendanceTable(){
        if (!window.jQuery || !$.fn || !$.fn.DataTable || !$.fn.DataTable.isDataTable('#attendanceTable')) return;
        if (attendanceAdjustTimer) {
            clearTimeout(attendanceAdjustTimer);
        }
        attendanceAdjustTimer = setTimeout(() => {
            const tableEl = document.getElementById('attendanceTable');
            if (tableEl) {
                tableEl.style.width = '100%';
            }
            const table = $('#attendanceTable').DataTable();
            table.columns.adjust().responsive?.recalc?.();
        }, 0);
    }

    window.handleAttendanceDateChange = function(dateVal){
        if(!dateVal) return;
        updateAttendanceDateLabel(dateVal);
        updateAttendanceNavState(dateVal);
        const tbody = document.getElementById('attendanceTableBody');
        if(!tbody) return;
        const hasDataTable = window.jQuery && $.fn && $.fn.DataTable && $.fn.DataTable.isDataTable('#attendanceTable');
        if(!hasDataTable){
            tbody.innerHTML = "<tr><td colspan='6' class='text-center text-muted'>Loading...</td></tr>";
        }
        fetch('api/attendance-daily.php?date=' + encodeURIComponent(dateVal), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if(!res || res.status !== 'ok'){
                    if(hasDataTable){
                        const table = $('#attendanceTable').DataTable();
                        table.clear().draw();
                    } else {
                        tbody.innerHTML = "<tr><td colspan='6' class='text-center text-danger'>Failed to load attendance</td></tr>";
                    }
                    return;
                }
                const rows = res.data || [];
                if(hasDataTable){
                    const table = $('#attendanceTable').DataTable();
                    table.clear();
                    if(rows.length){
                        const dataRows = rows.map(row => buildAttendanceRow(row, dateVal));
                        table.rows.add(dataRows);
                    }
                      table.draw(false);
                      adjustAttendanceTable();
                } else {
                    if(!rows.length){
                        tbody.innerHTML = "<tr><td colspan='5' class='text-center'>No attendance records found</td></tr>";
                        return;
                    }
                    tbody.innerHTML = rows.map(row => `<tr>${buildAttendanceRow(row, dateVal).map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('');
                }
            })
            .catch(() => {
                if(hasDataTable){
                    const table = $('#attendanceTable').DataTable();
                    table.clear().draw();
                } else {
                    tbody.innerHTML = "<tr><td colspan='6' class='text-center text-danger'>Failed to load attendance</td></tr>";
                }
            });
    };

    document.addEventListener('DOMContentLoaded', function(){
        const dateInput = document.querySelector('input[name="attendance_date"]');
        if(dateInput && dateInput.value){
            updateAttendanceDateLabel(dateInput.value);
            updateAttendanceNavState(dateInput.value);
        } else {
            updateAttendanceNavState(attendanceToday || formatLocalDate(new Date()));
        }
    });

  // Run clock immediately and every second
  updateClock();
  setInterval(updateClock, 1000);
})();

// Defer DataTable/Chart init until all assets load
window.addEventListener('load', function() {
  var loadingOverlay = document.getElementById('loadingOverlay');
  if (loadingOverlay) {
    loadingOverlay.style.display = 'flex';
  }

  // Initialize DataTable
  if (window.jQuery && $.fn && $.fn.DataTable && $('#attendanceTable').length) {
                if (!$.fn.DataTable.isDataTable('#attendanceTable') && !$('#attendanceTable tbody tr td[colspan="5"]').length) {
      $('#attendanceTable').DataTable({
        responsive: true,
        lengthChange: true,
        pageLength: 7,
        lengthMenu: [[7, 15, 20], [7, 15, 20]],
                order: [[2, 'desc'], [3, 'desc']],
        columnDefs: [
                                        { orderable: false, targets: [0, 1, 4] }
        ],
        searching: true,
        info: true,
        pagingType: 'full_numbers',
        language: {
          paginate: {
            previous: '<i class="fas fa-chevron-left"></i>',
            next: '<i class="fas fa-chevron-right"></i>',
            first: false,
            last: false
          },
          search: '',
          searchPlaceholder: "Search...",
          emptyTable: "No attendance records found"
        }
      });
    }
  }

  // Initialize Chart
  var chartCanvas = document.getElementById('attendanceChart');
    if (chartCanvas && window.Chart) {
    try {
      // Initialize Charts with Bootstrap 5 colors and animations
      const ctx = chartCanvas.getContext('2d');
      const attendanceChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: ['Present', 'Absent'],
          datasets: [{
            data: [<?php echo $presentToday; ?>, <?php echo $absentToday; ?>],
            backgroundColor: ['#198754', '#dc3545'], // Bootstrap 5 success and danger colors
            borderWidth: 0,
            hoverOffset: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '70%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 20,
                font: {
                  family: "'Poppins', sans-serif"
                }
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.raw || 0;
                  const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                  const percentage = Math.round((value / total) * 100);
                  return `${label}: ${value} (${percentage}%)`;
                }
              },
              padding: 12,
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              titleFont: {
                family: "'Poppins', sans-serif",
                size: 14
              },
              bodyFont: {
                family: "'Poppins', sans-serif",
                size: 13
              }
            }
          },
          animation: {
            animateScale: true,
            animateRotate: true,
            duration: 1000,
            easing: 'easeOutQuart'
          }
        }
      });
        } catch (error) {
      console.error('Error initializing attendance chart:', error);
    }
  } else {
        console.warn('Attendance chart canvas element not found or Chart.js not loaded');
  }

  // Hide loading overlay when page is fully loaded
    if (loadingOverlay) {
        loadingOverlay.style.opacity = '0';
        setTimeout(() => {
            loadingOverlay.style.display = 'none';
        }, 300);
    }

  // Handle celebrations card collapse/expand with chevron rotation
  const celebrationsCollapse = document.getElementById('celebrationsCollapse');
  const celebrationsChevron = document.getElementById('celebrationsChevron');
  
  if (celebrationsCollapse && celebrationsChevron) {
    celebrationsCollapse.addEventListener('show.bs.collapse', function() {
      celebrationsChevron.style.transform = 'rotate(0deg)';
    });
    
    celebrationsCollapse.addEventListener('hide.bs.collapse', function() {
      celebrationsChevron.style.transform = 'rotate(-90deg)';
    });
  }
});
</script>

<!-- Include the main footer (closes layout and loads libraries) -->
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
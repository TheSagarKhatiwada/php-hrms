<?php 
// Page meta
require_once 'includes' . DIRECTORY_SEPARATOR . 'settings.php';
require_once 'includes' . DIRECTORY_SEPARATOR . 'utilities.php';

$page = 'Admin Dashboard';

// Get current date and time values based on the set timezone
$today = date('Y-m-d');
$currentMonth = date('m');
$currentDay = date('d');
$currentYear = date('Y');
$currentTime = date('H:i:s');

// Include the main header (loads CSS/JS and opens layout wrappers)
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';

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

// Get attendance statistics
try {
    // Total employees
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE exit_date IS NULL");
    $totalEmployees = $stmt->fetchColumn();
    
    // Total branches
    $stmt = $pdo->query("SELECT COUNT(*) FROM branches");
    $totalBranches = $stmt->fetchColumn();
    
    // Total departments
    $stmt = $pdo->query("SELECT COUNT(*) FROM departments");
    $totalDepartments = $stmt->fetchColumn();
    
    // Total designations
    $stmt = $pdo->query("SELECT COUNT(*) FROM designations");
    $totalDesignations = $stmt->fetchColumn();
    
    // Present today
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT emp_Id) FROM attendance_logs WHERE date = ?");
    $stmt->execute([$today]);
    $presentToday = $stmt->fetchColumn();
    
    // Absent today (total employees - present)
    $absentToday = $totalEmployees - $presentToday;
    // If no employees and absent is negative, set to 0
    if ($absentToday < 0) {
        $absentToday = 0;
    }
    
    // New hires this month
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE exit_date IS NULL AND join_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $stmt->execute();
    $newHiresThisMonth = $stmt->fetchColumn();
    
    // Birthdays this month
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE exit_date IS NULL AND date_of_birth IS NOT NULL AND MONTH(date_of_birth) = ?");
    $stmt->execute([ (int)$currentMonth ]);
    $birthdaysThisMonth = $stmt->fetchColumn();
    
    // Get upcoming celebrations (birthdays and anniversaries combined)
    try {
        $upcomingCelebrations = [];
        
        // Get birthdays in the next 30 days
    // Upcoming celebrations via shared utility (30-day window, max 8)
    try {
        $upcomingCelebrations = get_upcoming_celebrations(30, 8, null);
    } catch (Throwable $e) {
        $upcomingCelebrations = [];
    }
    } catch (Throwable $e) {
        $upcomingCelebrations = [];
    }

    // Get recent attendance records
    $stmt = $pdo->query("\n        SELECT a.*, e.first_name, e.last_name, e.middle_name, e.user_image, \n               d.title as designation_name, b.name as branch_name \n        FROM attendance_logs a\n        JOIN employees e ON a.emp_Id = e.emp_id\n        LEFT JOIN branches b ON e.branch = b.id\n        LEFT JOIN designations d ON e.designation = d.id\n        ORDER BY a.date DESC, a.time DESC\n        LIMIT 20\n    ");
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $totalEmployees = $totalBranches = $totalDepartments = $totalDesignations = $presentToday = $absentToday = $newHiresThisMonth = $birthdaysThisMonth = 0;
    $recentAttendance = [];
    $upcomingCelebrations = [];
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
                            <li>
                                <a class="dropdown-item" href="contacts.php">
                                    <i class="fas fa-users me-2"></i>Employees
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="roles.php">
                                    <i class="fas fa-user-shield me-2"></i>Roles
                                </a>
                            </li>
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
                    <h2 class="fs-3 mb-0"><?php echo date('l, F j, Y'); ?></h2>
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
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 stat-icon">
                            <i class="fas fa-users text-success fs-4"></i>
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
                            <i class="fas fa-user-check text-success fs-4"></i>
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
                            <i class="fas fa-user-xmark text-danger fs-4"></i>
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
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 stat-icon">
                            <i class="fas fa-user-plus text-success fs-4"></i>
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
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0">Recent Attendance</h5>
                    <a href="modules/attendance/attendance.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-list me-1"></i> View All
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Branch</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (empty($recentAttendance)) {
                                    echo "<tr><td colspan='5' class='text-center'>No attendance records found</td></tr>";
                                } else {
                                    foreach ($recentAttendance as $record) {
                                        // Format date and time
                                        $date = date('M d, Y', strtotime($record['date']));
                                        $time = date('h:i A', strtotime($record['time']));
                                        
                                        // Get employee image or default
                                        $employeeImage = $record['user_image'] ?: 'resources/images/default-user.png';
                                        
                                        // Determine method badge color and text based on method value
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
                                                <td>{$date}</td>
                                                <td>{$time}</td>
                                                <td>{$methodText}</td>
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

<!-- Page Scripts (defer init until after libraries load) -->
<script>
// Defer page initialization until all assets (from footer) are loaded
window.addEventListener('load', function() {
  // Show loading overlay when page starts loading
  const loadingOverlay = document.getElementById('loadingOverlay');
  if (loadingOverlay) {
    loadingOverlay.style.display = 'flex';
  }
  
  // Pass PHP timezone information to JavaScript
  const serverTimezoneOffset = "<?php echo $timezoneOffset; ?>"; // Format: +HH:MM or -HH:MM
  
    // Initialize DataTable with Bootstrap 5 styling if available
    if (window.jQuery && $.fn && $.fn.DataTable && $('#attendanceTable').length) {
        // Prevent reinitialization
        if (!$.fn.DataTable.isDataTable('#attendanceTable') && !$('#attendanceTable tbody tr td[colspan="5"]').length) {
            $('#attendanceTable').DataTable({
        responsive: true,
        lengthChange: true,
        pageLength: 7,
                lengthMenu: [[7, 15, 20], [7, 15, 20]],
        order: [[2, 'desc'], [3, 'desc']],
        columnDefs: [
          { orderable: false, targets: [0, 1, 4] } // Disable sorting on specific columns
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
          search : '',
          searchPlaceholder: "Search...",
          emptyTable: "No attendance records found"
        }
      });
        } else {
            console.log('DataTable already initialized or no data to initialize');
        }
  }
  
  // Live clock update with timezone support
  function updateClock() {
    // Get current UTC time
    const now = new Date();
    
    // Apply server timezone offset
    if (serverTimezoneOffset) {
      // Extract hours and minutes from the offset string (format: +HH:MM or -HH:MM)
      const offsetMatch = serverTimezoneOffset.match(/([+-])(\d{2}):(\d{2})/);
      if (offsetMatch) {
        const offsetSign = offsetMatch[1] === '+' ? 1 : -1;
        const offsetHours = parseInt(offsetMatch[2], 10);
        const offsetMinutes = parseInt(offsetMatch[3], 10);
        
        // Calculate total offset in minutes
        const totalOffsetMinutes = offsetSign * (offsetHours * 60 + offsetMinutes);
        
        // Get current UTC time in minutes
        const utcTime = now.getTime();
        
        // Apply the offset
        const serverTime = new Date(utcTime + (totalOffsetMinutes * 60 * 1000));
        
        // Format the server time
        let hours = serverTime.getUTCHours();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        const minutes = serverTime.getUTCMinutes().toString().padStart(2, '0');
        const seconds = serverTime.getUTCSeconds().toString().padStart(2, '0');
        const timeString = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        
        document.getElementById('live-time').textContent = timeString;
      } else {
        // Fallback to local time if offset format is invalid
        formatLocalTime(now);
      }
    } else {
      // Fallback to local time if no offset provided
      formatLocalTime(now);
    }
  }
  
  // Helper function to format local time
  function formatLocalTime(dateObj) {
    let hours = dateObj.getHours();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // the hour '0' should be '12'
    const minutes = dateObj.getMinutes().toString().padStart(2, '0');
    const seconds = dateObj.getSeconds().toString().padStart(2, '0');
    const timeString = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
    
    document.getElementById('live-time').textContent = timeString;
  }
  
  // Update clock every second
  updateClock();
  setInterval(updateClock, 1000);
  
  // Make sure we have a valid canvas element before initializing the chart
  const chartCanvas = document.getElementById('attendanceChart');
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
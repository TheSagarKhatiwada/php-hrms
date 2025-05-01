<?php 
// Include session configuration - ensure it's included first
require_once 'includes/session_config.php';

// Include database connection before other includes that might use it
require_once 'includes/db_connection.php';

require_once 'includes/utilities.php';
require_once 'includes/settings.php';

$page = 'Admin Dashboard';

// Get timezone from settings using the get_setting function for consistency
$timezone = get_setting('timezone', 'GMT');
// Set the timezone from settings
date_default_timezone_set($timezone);

// Get current date and time values based on the set timezone
$today = date('Y-m-d');
$currentMonth = date('m');
$currentDay = date('d');
$currentYear = date('Y');
$currentTime = date('H:i:s');

// Store timezone offset for JavaScript
$date = new DateTime('now', new DateTimeZone($timezone));
$timezoneOffset = $date->format('P'); // Format as +00:00

// Display the timezone in the console for debugging
echo "<!-- Using timezone: $timezone, Current time: $currentTime, Offset: $timezoneOffset -->";

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
    
    // Present today
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT emp_Id) FROM attendance_logs WHERE date = ?");
    $stmt->execute([$today]);
    $presentToday = $stmt->fetchColumn();
    
    // Absent today (total employees - present)
    $absentToday = $totalEmployees - $presentToday;
    
    // Get upcoming birthdays (within the next 30 days)
    try {
        $upcomingBirthdays = [];
        
        // Query for employees with birthdays in the next 30 days
        $stmt = $pdo->prepare("
            SELECT emp_id, first_name, middle_name, last_name, designation, user_image, dob, 
                   DAYOFMONTH(dob) as birth_day, 
                   MONTH(dob) as birth_month,
                   YEAR(CURDATE()) as current_year
            FROM employees 
            WHERE (MONTH(dob) = ? AND DAYOFMONTH(dob) >= ?) 
               OR (MONTH(dob) = ? AND DAYOFMONTH(dob) <= ?)
               AND exit_date IS NULL
            ORDER BY MONTH(dob), DAYOFMONTH(dob)
            LIMIT 5
        ");
        
        // For current month, get upcoming birthdays
        // For next month, get birthdays in the first days to complete 30 days window
        $nextMonth = $currentMonth == 12 ? 1 : $currentMonth + 1;
        $daysInCurrentMonth = date('t'); // Days in current month
        $daysInNextMonth = min(30 - ($daysInCurrentMonth - $currentDay), 31); // Days to look in next month
        
        $stmt->execute([$currentMonth, $currentDay, $nextMonth, $daysInNextMonth]);
        $upcomingBirthdays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get work anniversaries (employees who joined this month)
        $upcomingAnniversaries = [];
        
        // Query for employees with work anniversaries in the current month
        $stmt = $pdo->prepare("
            SELECT emp_id, first_name, middle_name, last_name, designation, user_image, 
                   join_date, 
                   DAYOFMONTH(join_date) as join_day, 
                   MONTH(join_date) as join_month,
                   YEAR(join_date) as join_year,
                   YEAR(CURDATE())-YEAR(join_date) as years_completed
            FROM employees 
            WHERE MONTH(join_date) = ? 
               AND DAYOFMONTH(join_date) >= ?
               AND YEAR(join_date) < ?
               AND exit_date IS NULL
            ORDER BY MONTH(join_date), DAYOFMONTH(join_date)
            LIMIT 5
        ");
        
        $stmt->execute([$currentMonth, $currentDay, $currentYear]);
        $upcomingAnniversaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // If the columns don't exist, we'll just show empty sections
        $upcomingBirthdays = [];
        $upcomingAnniversaries = [];
    }
    
    // Get recent attendance records
    $stmt = $pdo->query("
        SELECT a.*, e.first_name, e.last_name, e.middle_name, e.user_image, e.designation, b.name as branch_name 
        FROM attendance_logs a
        JOIN employees e ON a.emp_Id = e.emp_id
        LEFT JOIN branches b ON e.branch = b.id
        ORDER BY a.date DESC, a.time DESC
        LIMIT 15
    ");
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $totalEmployees = $totalBranches = $totalDepartments = $presentToday = $absentToday = 0;
    $recentAttendance = [];
    $upcomingBirthdays = [];
    $upcomingAnniversaries = [];
}

// Include header (which includes new bootstrap 5 structure and topbar)
require_once __DIR__ . '/includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <?php 
            // Get user information
            $firstName = 'User'; // Default value
            $isFirstLogin = false; // Default to returning user
            
            if (isset($_SESSION['user_id'])) {
                try {
                    // Get user's first name
                    $userStmt = $pdo->prepare("SELECT first_name, last_login FROM employees WHERE id = ?");
                    $userStmt->execute([$_SESSION['user_id']]);
                    if ($userData = $userStmt->fetch(PDO::FETCH_ASSOC)) {
                        $firstName = $userData['first_name'];
                        
                        // Check if this is first login (if last_login is NULL or empty)
                        if (empty($userData['last_login'])) {
                            $isFirstLogin = true;
                        }
                        
                        // Update last_login timestamp
                        if (!isset($_SESSION['login_recorded'])) {
                            $updateStmt = $pdo->prepare("UPDATE employees SET last_login = NOW() WHERE id = ?");
                            $updateStmt->execute([$_SESSION['user_id']]);
                            $_SESSION['login_recorded'] = true;
                        }
                    }
                } catch (PDOException $e) {
                    // Silently fail and use default values
                }
            }
            
            // Display appropriate welcome message
            if ($isFirstLogin) {
                echo "<h1 class='mb-1'>Welcome, {$firstName}!</h1>";
            } else {
                echo "<h1 class='mb-1'>Welcome back, {$firstName}!</h1>";
            }
          ?>
        </div>
        <div class="d-flex align-items-center">
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" id="dashboardActions" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-1"></i> Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dashboardActions">
                    <li><a class="dropdown-item" href="add-employee.php"><i class="fas fa-user-plus me-2"></i>Add Employee</a></li>
                    <li><a class="dropdown-item" href="record_manual_attendance.php"><i class="fas fa-clipboard-check me-2"></i>Record Attendance</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="system-settings.php"><i class="fas fa-cogs me-2"></i>System Settings</a></li>
                </ul>
            </div>
        </div>
    </div>
      
    <!-- Date Time Card -->
    <div class="card mb-4 bg-primary text-white shadow-sm rounded-3">
        <div class="card-body py-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="fs-3 mb-0"><?php echo date('l, F j, Y'); ?></h2>
                    <h3 class="opacity-85" id="live-time">Loading time...</h3>
                </div>
                <div class="col-md-6">
                    <div class="row align-items-center justify-content-end text-end">
                        <div class="col-auto">
                            <div class="d-flex flex-column">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-success rounded-circle me-2" style="width: 8px; height: 8px; outline: 1px solid #fff;"></div>
                                    <span class="small">System Online</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="bg-success rounded-circle me-2" style="width: 8px; height: 8px; outline: 1px solid #fff;"></div>
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
    <div class="row g-4 mb-4">
        <!-- Total Employees -->
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3">
                            <i class="fas fa-users text-primary fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">Total Employees</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $totalEmployees; ?></h2>
                        </div>
                    </div>
                    <div class="d-flex align-items-center text-success">
                        <i class="fas fa-arrow-up me-1 small"></i>
                        <span class="small">Active Workforce</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total Branches -->
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-info bg-opacity-10 p-3 rounded-3">
                            <i class="fas fa-building text-info fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">Total Branches</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $totalBranches; ?></h2>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="small text-muted">Company Network</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Present Today -->
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3">
                            <i class="fas fa-user-check text-success fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">Present Today</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $presentToday; ?></h2>
                        </div>
                    </div>
                    <div class="progress mb-2" style="height: 6px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $totalEmployees > 0 ? ($presentToday/$totalEmployees) * 100 : 0; ?>%" aria-valuenow="<?php echo $totalEmployees > 0 ? ($presentToday/$totalEmployees) * 100 : 0; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="small text-muted"><?php echo $totalEmployees > 0 ? round(($presentToday/$totalEmployees) * 100, 1) : 0; ?>% of workforce</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Absent Today -->
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-danger bg-opacity-10 p-3 rounded-3">
                            <i class="fas fa-user-times text-danger fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-1 text-muted">Absent Today</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $absentToday; ?></h2>
                        </div>
                    </div>
                    <div class="progress mb-2" style="height: 6px;">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $totalEmployees > 0 ? ($absentToday/$totalEmployees) * 100 : 0; ?>%" aria-valuenow="<?php echo $totalEmployees > 0 ? ($absentToday/$totalEmployees) * 100 : 0; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="small text-muted"><?php echo $totalEmployees > 0 ? round(($absentToday/$totalEmployees) * 100, 1) : 0; ?>% of workforce</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Celebrations Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
            <h5 class="card-title mb-0">Upcoming Celebrations</h5>
            <div class="nav nav-pills" id="celebrations-tab" role="tablist">
                <button class="nav-link active" id="birthdays-tab" data-bs-toggle="pill" data-bs-target="#birthdays" type="button" role="tab" aria-controls="birthdays" aria-selected="true">
                    <i class="fas fa-birthday-cake me-1"></i> Birthdays
                </button>
                <button class="nav-link" id="anniversaries-tab" data-bs-toggle="pill" data-bs-target="#anniversaries" type="button" role="tab" aria-controls="anniversaries" aria-selected="false">
                    <i class="fas fa-glass-cheers me-1"></i> Work Anniversaries
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="tab-content" id="celebrations-tab-content">
                <!-- Birthdays Tab -->
                <div class="tab-pane fade show active p-3" id="birthdays" role="tabpanel" aria-labelledby="birthdays-tab">
                    <?php if (empty($upcomingBirthdays)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-birthday-cake fa-4x text-muted opacity-25 mb-3"></i>
                            <p class="text-muted mb-0">No upcoming birthdays in the next 30 days</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($upcomingBirthdays as $employee): ?>
                                <?php 
                                    // Format display date
                                    $birthMonth = $employee['birth_month'];
                                    $birthDay = $employee['birth_day'];
                                    $birthDate = date('F j', mktime(0, 0, 0, $birthMonth, $birthDay, date('Y')));
                                    
                                    // Calculate days until birthday - Calculate more precisely
                                    $today = new DateTime(date('Y-m-d')); // Use current date without time
                                    $birthdayThisYear = new DateTime(date('Y') . '-' . sprintf('%02d', $birthMonth) . '-' . sprintf('%02d', $birthDay));
                                    
                                    // If birthday has passed this year, look at next year's birthday
                                    if ($birthdayThisYear < $today) {
                                        $birthdayThisYear->modify('+1 year');
                                    }
                                    
                                    $diff = $today->diff($birthdayThisYear);
                                    $daysUntil = $diff->days;
                                    
                                    // Get employee image or default
                                    $employeeImage = $employee['user_image'] ?: 'resources/images/default-user.png';
                                ?>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-2 rounded border">
                                        <div class="position-relative">
                                            <img src="<?php echo $employeeImage; ?>" class="rounded-circle" alt="<?php echo $employee['first_name']; ?>" width="50" height="50" style="object-fit: cover;">
                                            <div class="position-absolute top-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; font-size: 10px; transform: translate(25%, -25%);">
                                                <i class="fas fa-gift"></i>
                                            </div>
                                        </div>
                                        <div class="ms-3">
                                            <h6 class="mb-0"><?php echo $employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name']; ?></h6>
                                            <div class="d-flex align-items-center">
                                                <span class="text-muted small me-2"><?php echo $employee['designation']; ?></span>
                                                <span class="badge bg-primary rounded-pill"><?php echo $birthDate; ?></span>
                                            </div>
                                            <div class="small text-success mt-1">
                                                <?php if ($daysUntil == 0): ?>
                                                    <strong>Today!</strong>
                                                <?php elseif ($daysUntil == 1): ?>
                                                    <strong>Tomorrow!</strong>
                                                <?php else: ?>
                                                    In <?php echo $daysUntil; ?> days
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Work Anniversaries Tab -->
                <div class="tab-pane fade p-3" id="anniversaries" role="tabpanel" aria-labelledby="anniversaries-tab">
                    <?php if (empty($upcomingAnniversaries)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-award fa-4x text-muted opacity-25 mb-3"></i>
                            <p class="text-muted mb-0">No upcoming work anniversaries this month</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($upcomingAnniversaries as $employee): ?>
                                <?php 
                                    // Format display date
                                    $joinMonth = $employee['join_month'];
                                    $joinDay = $employee['join_day'];
                                    $joinDate = date('F j', mktime(0, 0, 0, $joinMonth, $joinDay, date('Y')));
                                    $yearsCompleted = $employee['years_completed'];
                                    
                                    // Calculate days until anniversary
                                    $today = new DateTime();
                                    $anniversaryThisYear = new DateTime(date('Y') . '-' . $joinMonth . '-' . $joinDay);
                                    
                                    // If anniversary has passed this year, look at next year's anniversary
                                    if ($anniversaryThisYear < $today) {
                                        $anniversaryThisYear->modify('+1 year');
                                    }
                                    
                                    $diff = $today->diff($anniversaryThisYear);
                                    $daysUntil = $diff->days;
                                    
                                    // Get employee image or default
                                    $employeeImage = $employee['user_image'] ?: 'resources/images/default-user.png';
                                ?>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-2 rounded border">
                                        <div class="position-relative">
                                            <img src="<?php echo $employeeImage; ?>" class="rounded-circle" alt="<?php echo $employee['first_name']; ?>" width="50" height="50" style="object-fit: cover;">
                                            <div class="position-absolute top-0 end-0 bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; font-size: 10px; transform: translate(25%, -25%);">
                                                <i class="fas fa-award"></i>
                                            </div>
                                        </div>
                                        <div class="ms-3">
                                            <h6 class="mb-0"><?php echo $employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name']; ?></h6>
                                            <div class="d-flex align-items-center">
                                                <span class="text-muted small me-2"><?php echo $employee['designation']; ?></span>
                                                <span class="badge bg-info rounded-pill"><?php echo $joinDate; ?></span>
                                            </div>
                                            <div class="small text-success mt-1">
                                                <?php if ($daysUntil == 0): ?>
                                                    <strong>Today! (<?php echo $yearsCompleted; ?> years)</strong>
                                                <?php elseif ($daysUntil == 1): ?>
                                                    <strong>Tomorrow! (<?php echo $yearsCompleted; ?> years)</strong>
                                                <?php else: ?>
                                                    In <?php echo $daysUntil; ?> days (<?php echo $yearsCompleted; ?> years)
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
      
    <div class="row g-4">
        <!-- Recent Attendance Table -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0">Recent Attendance</h5>
                    <a href="attendance.php" class="btn btn-sm btn-primary">
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
                                        
                                        // Determine method badge color
                                        $methodClass = $record['method'] == 1 ? 'bg-warning' : 'bg-success';
                                        $methodText = $record['method'] == 1 ? 'Manual' : 'Auto';
                                        
                                        echo "<tr>
                                                <td>
                                                    <div class='d-flex align-items-center'>
                                                        <img src='{$employeeImage}' class='rounded-circle me-2' style='width: 32px; height: 32px; object-fit: cover;'>
                                                        <div>
                                                            <div class='fw-medium'>{$record['first_name']} {$record['last_name']}</div>
                                                            <small class='text-muted'>{$record['designation']}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{$record['branch_name']}</td>
                                                <td>{$date}</td>
                                                <td>{$time}</td>
                                                <td><span class='badge {$methodClass} rounded-pill'>{$methodText}</span></td>
                                            </tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
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
                    <canvas id="attendanceChart" height="250"></canvas>
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
                            <a href="add-employee.php" class="btn btn-primary w-100 d-flex flex-column align-items-center p-3 h-100">
                                <i class="fas fa-user-plus fs-4 mb-2"></i>
                                <span>Add Employee</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="record_manual_attendance.php" class="btn btn-success w-100 d-flex flex-column align-items-center p-3 h-100">
                                <i class="fas fa-clipboard-check fs-4 mb-2"></i>
                                <span>Record Attendance</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="manage_assets.php" class="btn btn-info w-100 d-flex flex-column align-items-center p-3 text-white h-100">
                                <i class="fas fa-boxes fs-4 mb-2"></i>
                                <span>Manage Assets</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="monthly-report.php" class="btn btn-warning w-100 d-flex flex-column align-items-center p-3 h-100">
                                <i class="fas fa-chart-bar fs-4 mb-2"></i>
                                <span>Reports</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> <!-- /.container-fluid -->

<!-- Include the main footer -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Charts.js Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Pass PHP timezone information to JavaScript
  const serverTimezoneOffset = "<?php echo $timezoneOffset; ?>"; // Format: +HH:MM or -HH:MM
  
  // Initialize DataTable with Bootstrap 5 styling
  if ($.fn.DataTable) {
    $('#attendanceTable').DataTable({
      responsive: true,
      lengthChange: false,
      pageLength: 5,
      searching: false,
      info: false,
      language: {
        paginate: {
          previous: '<i class="fas fa-chevron-left"></i>',
          next: '<i class="fas fa-chevron-right"></i>'
        }
      }
    });
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
  if (chartCanvas) {
    try {
      // Initialize Charts with Bootstrap 5 colors
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
          }
        }
      });
      console.log('Attendance chart initialized successfully');
    } catch (error) {
      console.error('Error initializing attendance chart:', error);
    }
  } else {
    console.warn('Attendance chart canvas element not found');
  }

  // Hide loading overlay when page is fully loaded
  const loadingOverlay = document.getElementById('loadingOverlay');
  if (loadingOverlay) {
    loadingOverlay.style.opacity = '0';
    setTimeout(() => {
      loadingOverlay.style.display = 'none';
    }, 300);
  }
});
</script>
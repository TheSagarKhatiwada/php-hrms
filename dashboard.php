<?php
// Set page title
$page = "Dashboard";

// Include required files
require_once 'includes/header.php';
require_once 'includes/db_connection.php';
require_once 'includes/settings.php'; // Include settings to get timezone

// Ensure database is connected before proceeding
requireDatabaseConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User not logged in, redirect to login page
    header('Location: index.php');
    exit();
}

// Get timezone from settings
$timezone = get_setting('timezone', 'UTC');
date_default_timezone_set($timezone);

// Get current date
$today = date('Y-m-d');

// Get current user data
$userId = $_SESSION['user_id'];

try {
    // Get user data with designation title
    $stmt = $pdo->prepare("
        SELECT e.*, d.title as designation_title 
        FROM employees e
        LEFT JOIN designations d ON e.designation = d.id
        WHERE e.id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current user's attendance for today
    $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE emp_Id = ? AND date = ? ORDER BY time DESC LIMIT 1");
    $stmt->execute([$userData['emp_id'], $today]);
    $todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get attendance for last 7 days
    $stmt = $pdo->prepare("
        SELECT date, 
           MIN(time) AS clock_in, 
           MAX(time) AS clock_out, 
           GROUP_CONCAT(method ORDER BY time LIMIT 1) AS method
        FROM attendance_logs 
        WHERE emp_Id = ? 
        AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
        GROUP BY date
        ORDER BY date DESC
    ");
    $stmt->execute([$userData['emp_id']]);
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage() ."\n");
    $_SESSION['error'] = "Error retrieving user data" . $e->getMessage();
    $userData = [];
    $todayAttendance = [];
    $recentAttendance = [];
}
?>

<!-- Dashboard Styles -->
<style>
    /* Welcome section with gradient */
    .welcome-section {
        background: linear-gradient(120deg, var(--primary-color), var(--primary-hover));
        color: #ffffff;
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        transition: all 0.3s ease;
    }
    
    body.dark-mode .welcome-section {
        background: linear-gradient(120deg, #343a40, #212529);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.25);
    }
    
    /* Info boxes styling - Enhanced */
    .modern-info-box {
        border-radius: 0.75rem;
        transition: transform 0.3s, box-shadow 0.3s;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        padding: 0.75rem;
        background-color: #fff;
    }
    
    .modern-info-box:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-shadow-hover);
    }
    
    body.dark-mode .modern-info-box {
        background-color: #343a40;
    }
    
    /* Icon container styling */
    .info-box-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        font-size: 1.5rem;
        color: #fff;
    }
    
    /* Gradient backgrounds with enhanced colors */
    .bg-gradient-info {
        background: linear-gradient(135deg, #17a2b8, #0097a7) !important;
    }
    
    .bg-gradient-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)) !important;
    }
    
    .bg-gradient-success {
        background: linear-gradient(135deg, #28a745, #2E7D32) !important;
    }
    
    .bg-gradient-warning {
        background: linear-gradient(135deg, #ffc107, #fb8c00) !important;
    }
    
    /* Info box content styling */
    .info-box-content {
        flex: 1;
    }
    
    .info-box-text {
        display: block;
        font-size: 0.9rem;
        font-weight: 500;
        color: #6c757d;
    }
    
    body.dark-mode .info-box-text {
        color: #adb5bd;
    }
    
    .info-box-number {
        display: block;
        font-weight: 600;
        font-size: 1.25rem;
    }
    
    body.dark-mode .info-box-number {
        color: #f8f9fa;
    }
    
    /* Clock icon styling */
    .clock-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.75rem auto;
        box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.2);
        font-size: 1.75rem;
        color: var(--primary-color);
        background-color: rgba(var(--primary-rgb), 0.05);
        transition: all 0.3s ease;
    }
    
    .clock-card:hover .clock-icon {
        transform: rotate(15deg);
    }
    
    /* Cards styling */
    .modern-card {
        border-radius: 0.75rem;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        transition: transform 0.3s, box-shadow 0.3s;
        margin-bottom: 1.5rem;
        border: none;
    }
    
    .modern-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-shadow-hover);
    }
    
    body.dark-mode .modern-card {
        background-color: #343a40;
        color: #f8f9fa;
    }
    
    body.dark-mode .modern-card .card-header {
        background-color: #2c3136;
        border-color: #495057;
    }
    
    /* Clock styling */
    .clock-card {
        text-align: center;
        padding: 1.25rem;
    }
    
    .clock-display {
        font-size: 2.5rem;
        font-weight: 600;
        margin: 0.75rem 0;
        font-family: 'Poppins', sans-serif;
    }
    
    body.dark-mode .clock-display {
        color: #f8f9fa;
    }
    
    /* Attendance status badges */
    .attendance-status {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        font-weight: 500;
        margin-top: 0.625rem;
    }
    
    .status-present {
        background-color: rgba(40, 167, 69, 0.15);
        color: #28a745;
    }
    
    body.dark-mode .status-present {
        background-color: rgba(40, 167, 69, 0.25);
        color: #68d289;
    }
    
    .status-absent {
        background-color: rgba(220, 53, 69, 0.15);
        color: #dc3545;
    }
    
    body.dark-mode .status-absent {
        background-color: rgba(220, 53, 69, 0.25);
        color: #e35d6a;
    }
    
    /* Animation for time display */
    .time-refresh {
        animation: fadeInOut 2s infinite;
    }
    
    @keyframes fadeInOut {
        0% { opacity: 0.7; }
        50% { opacity: 1; }
        100% { opacity: 0.7; }
    }
    
    /* Profile image */
    .profile-user-img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border: 4px solid rgba(var(--primary-rgb), 0.2);
    }
    
    /* Quick links */
    .quick-link {
        transition: all 0.2s ease;
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        text-decoration: none !important;
    }
    
    .quick-link:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        transform: translateX(5px);
    }
    
    body.dark-mode .quick-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    /* List group updates */
    body.dark-mode .list-group-item {
        background-color: #343a40;
        border-color: #495057;
    }
    
    /* Table styling */
    body.dark-mode .table {
        color: #f8f9fa;
    }
    
    body.dark-mode .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(255, 255, 255, 0.05);
    }
    
    body.dark-mode .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }
    
    /* Button styling */
    .btn-attendance {
        border-radius: 2rem;
        padding: 0.5rem 1.5rem;
        font-weight: 500;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
    }
    
    .btn-attendance:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    /* Quick Links Grid Modernization */
    .quick-links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1rem;
        padding: 1.25rem;
    }
    .quick-link-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #fff;
        border-radius: 0.75rem;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        padding: 1.25rem 0.75rem;
        text-decoration: none !important;
        transition: transform 0.18s, box-shadow 0.18s, background 0.18s;
        min-height: 140px;
        border: none;
        position: relative;
    }
    .quick-link-card:hover {
        background: rgba(var(--primary-rgb), 0.07);
        transform: translateY(-4px) scale(1.03);
        box-shadow: 0 6px 24px rgba(0,0,0,0.12);
        z-index: 2;
    }
    .quick-link-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 0.75rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    }
    .quick-link-text h6 {
        font-weight: 600;
        font-size: 1.05rem;
        margin-bottom: 0.15rem;
        color: #212529;
    }
    .quick-link-text small {
        color: #6c757d;
    }
    body.dark-mode .quick-link-card {
        background: #23272b;
        box-shadow: 0 2px 12px rgba(0,0,0,0.22);
    }
    body.dark-mode .quick-link-card:hover {
        background: rgba(255,255,255,0.04);
    }
    body.dark-mode .quick-link-text h6 {
        color: #f8f9fa;
    }
    body.dark-mode .quick-link-text small {
        color: #adb5bd;
    }
</style>

<!-- Main Content Container -->
<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <?php 
                // Get user information
                $firstName = $userData['first_name'] ?? 'User'; // Default value
                
                echo "<h1 class='mb-1'>Welcome, {$firstName}!</h1>";
            ?>
        </div>
    </div>
    
    <!-- Date Time Card -->
    <div class="card mb-4 text-white shadow rounded-3 datetime-card" style="background: linear-gradient(135deg, var(--primary-color) 0%, #4e73df 100%);">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class=" mb-0"><?php echo date('l'); ?></h2>
                    <h4 class="opacity-85" id="date"><?php echo date('F j, Y'); ?></h4>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="d-flex align-items-center justify-content-md-end">
                        <div class="attendance-status <?php echo !empty($todayAttendance) ? 'status-present' : 'status-absent'; ?> mb-0">
                            <i class="fas <?php echo !empty($todayAttendance) ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                            <?php echo !empty($todayAttendance) ? 'Present Today' : 'Not Checked In'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Dashboard Content -->
    <div class="row">
        <!-- Left Column (8 cols) -->
        <div class="col-lg-8">
            <!-- User Profile Card (Redesigned with Bootstrap 5 classes) -->
            <div class="card shadow mb-4 rounded-3 overflow-hidden">
                <div class="row g-0 h-100">
                    <!-- Left side with profile image and background - now full height -->
                    <div class="col-md-4 text-center text-white position-relative p-0">
                        <div class="d-flex flex-column justify-content-center align-items-center h-100 p-4"
                             style="background: linear-gradient(135deg, var(--primary-color), #4e73df);"> 
                            <div class="position-relative mx-auto mb-4">
                                <img src="<?php echo $userData['user_image'] ?? 'resources/images/default-user.png'; ?>" 
                                     class="rounded-circle img-thumbnail border-4 shadow"
                                     style="width: 130px; height: 130px; object-fit: cover;">
                                <div class="position-absolute bottom-0 end-0 translate-middle-y">
                                    <div class="rounded-circle border-3 border-white d-flex align-items-center justify-content-center bg-<?php echo !empty($userData['exit_date']) ? 'danger' : 'success'; ?>"
                                         style="width: 32px; height: 32px;">
                                        <i class="fas fa-<?php echo !empty($userData['exit_date']) ? 'times' : 'check'; ?> fa-sm text-white"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="fw-bold mb-1">
                                <?php echo htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']); ?>
                            </h5>
                            <p class="opacity-75 mb-4"><?php echo htmlspecialchars($userData['designation_title'] ?? 'Not Assigned'); ?></p>
                            <a href="profile.php" class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-medium">
                                <i class="fas fa-user me-1"></i> View Profile
                            </a>
                        </div>
                    </div>
                    
                    <!-- Right side with user details -->
                    <div class="col-md-8">
                        <div class="p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">Profile Information</h5>
                                <span class="badge bg-<?php echo !empty($userData['exit_date']) ? 'danger' : 'success'; ?> rounded-pill py-1 px-3">
                                    <?php echo !empty($userData['exit_date']) ? 'Inactive' : 'Active'; ?>
                                </span>
                            </div>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <div class="border-start border-primary ps-3 py-1">
                                        <small class="text-muted d-block">Email Address</small>
                                        <span class="fw-medium"><?php echo htmlspecialchars($userData['email']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="border-start border-success ps-3 py-1">
                                        <small class="text-muted d-block">Employee ID</small>
                                        <span class="fw-medium"><?php echo htmlspecialchars($userData['emp_id']); ?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="border-start border-info ps-3 py-1">
                                        <small class="text-muted d-block">Department</small>
                                        <span class="fw-medium">
                                            <?php 
                                                try {
                                                    $stmt = $pdo->prepare("SELECT departments.dept_name FROM departments 
                                                                        JOIN employees ON departments.id = employees.department 
                                                                        WHERE employees.id = ?");
                                                    $stmt->execute([$userId]);
                                                    $dept = $stmt->fetchColumn();
                                                    echo htmlspecialchars($dept ?? 'Not assigned');
                                                } catch (PDOException $e) {
                                                    echo 'Not available';
                                                }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="border-start border-warning ps-3 py-1">
                                        <small class="text-muted d-block">Position</small>
                                        <span class="fw-medium"><?php echo htmlspecialchars($userData['designation_title'] ?? 'Not Assigned'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- Quick Stats -->
                            <div class="row g-2 text-center">
                                <div class="col-4">
                                    <div class="bg-body-tertiary p-3 rounded-3 shadow-sm border-0">
                                        <h3 class="mb-0 fs-3 text-primary"><?php echo count($recentAttendance); ?></h3>
                                        <small class="text-muted">Days Present</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-body-tertiary p-3 rounded-3 shadow-sm border-0">
                                        <h3 class="mb-0 fs-3 text-success">
                                            <?php 
                                                // Get leave count if available
                                                $leaveCount = 0;
                                                try {
                                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leaves WHERE emp_id = ? AND status = 'approved'");
                                                    if ($stmt) {
                                                        $stmt->execute([$userData['emp_id']]);
                                                        $leaveCount = $stmt->fetchColumn() ?: 0;
                                                    }
                                                } catch (PDOException $e) {
                                                    // Table might not exist, just show 0
                                                }
                                                echo $leaveCount;
                                            ?>
                                        </h3>
                                        <small class="text-muted">Leaves</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-body-tertiary p-3 rounded-3 shadow-sm border-0">
                                        <h3 class="mb-0 fs-3 text-info">
                                            <?php 
                                                // Calculate tenure if join date is available
                                                $tenure = 'N/A';
                                                if (!empty($userData['join_date'])) {
                                                    $joinDate = new DateTime($userData['join_date']);
                                                    $now = new DateTime();
                                                    $interval = $joinDate->diff($now);
                                                    $tenure = $interval->y;
                                                }
                                                echo $tenure;
                                            ?>
                                        </h3>
                                        <small class="text-muted">Years</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Attendance Card -->
            <div class="card modern-card">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="card-title mb-0">Your Recent Attendance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                    <th>Duration</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($recentAttendance)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No attendance records found for the last 7 days</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($recentAttendance as $record): ?>
                                        <tr>
                                            <td><?php echo date('D, M d', strtotime($record['date'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($record['clock_in'])); ?></td>
                                            <td>
                                                <?php 
                                                    if($record['clock_out'] && $record['clock_out'] != $record['clock_in']) {
                                                        echo date('h:i A', strtotime($record['clock_out']));
                                                    } else {
                                                        echo '<span class="badge bg-warning">No checkout</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    if($record['clock_out'] && $record['clock_out'] != $record['clock_in']) {
                                                        $in = strtotime($record['clock_in']);
                                                        $out = strtotime($record['clock_out']);
                                                        $hours = round(($out - $in) / 3600, 2);
                                                        echo $hours . ' hrs';
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    switch ($record['method']) {
                                                        case 0:
                                                            echo '<span class="badge bg-primary">Auto</span>';
                                                            break;
                                                        case 1:
                                                            echo '<span class="badge bg-warning" style="color: #000;">Manual</span>';
                                                            break;
                                                        case 2:
                                                            echo '<span class="badge bg-info">Web</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge bg-secondary">Unknown</span>';
                                                            break;
                                                    }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-3">
                        <a href="attendance.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-calendar-alt me-1"></i> View Full Attendance
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column (4 cols) -->
        <div class="col-lg-4">
            <!-- Today's Attendance Card (Moved from left column) -->
            <div class="card modern-card">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="card-title mb-0">Today's Attendance</h5>
                </div>
                <div class="card-body">
                    <div class="clock-card mb-3">
                        <div class="clock-icon">
                            <i class="far fa-clock"></i>
                        </div>
                        <h6 class="text-muted">Current Time</h6>
                        <div class="clock-display" id="digital-clock">00:00:00</div>
                        <div class="mt-3">
                            <?php if (empty($todayAttendance)): ?>
                                <button id="clockInBtn" class="btn btn-success btn-attendance">
                                    <i class="fas fa-sign-in-alt me-2"></i> Clock In
                                </button>
                            <?php else: ?>
                                <div class="text-success mb-3">
                                    <i class="fas fa-check-circle me-1"></i>
                                    You clocked in at <?php echo !empty($todayAttendance['time']) ? date('h:i A', strtotime($todayAttendance['time'])) : 'N/A'; ?>
                                </div>
                                <button id="clockOutBtn" class="btn btn-primary btn-attendance">
                                    <i class="fas fa-sign-out-alt me-2"></i> Clock Out
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-box modern-info-box">
                        <span class="info-box-icon bg-gradient-info"><i class="far fa-calendar-check"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Last 7 Days Attendance</span>
                            <span class="info-box-number"><?php echo count($recentAttendance); ?> days
                                <small class="text-muted">(<?php echo round((count($recentAttendance)/7) * 100); ?>%)</small>
                            </span>
                            <div class="progress" style="height: 5px; margin-top: 5px;">
                                <div class="progress-bar bg-info" style="width: <?php echo (count($recentAttendance)/7) * 100; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Links Card - Redesigned -->
            <div class="card modern-card">
                <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Quick Links</h5>
                    <span class="badge rounded-pill bg-primary">5 links</span>
                </div>
                <div class="card-body p-0">
                    <div class="quick-links-grid">
                        <a href="attendance.php" class="quick-link-card">
                            <div class="quick-link-icon bg-primary-light">
                                <i class="fas fa-calendar-alt text-primary"></i>
                            </div>
                            <div class="quick-link-text">
                                <h6 class="mb-0">Attendance</h6>
                                <small class="text-muted">View your records</small>
                            </div>
                        </a>
                        
                        <a href="profile.php" class="quick-link-card">
                            <div class="quick-link-icon bg-success-light">
                                <i class="fas fa-user text-success"></i>
                            </div>
                            <div class="quick-link-text">
                                <h6 class="mb-0">Profile</h6>
                                <small class="text-muted">View details</small>
                            </div>
                        </a>
                        
                        <a href="#" class="quick-link-card" data-bs-toggle="modal" data-bs-target="#leaveRequestModal">
                            <div class="quick-link-icon bg-danger-light">
                                <i class="fas fa-calendar-minus text-danger"></i>
                            </div>
                            <div class="quick-link-text">
                                <h6 class="mb-0">Leave</h6>
                                <small class="text-muted">Request time off</small>
                            </div>
                        </a>
                        
                        <a href="notifications.php" class="quick-link-card">
                            <div class="quick-link-icon bg-warning-light">
                                <i class="fas fa-bell text-warning"></i>
                            </div>
                            <div class="quick-link-text">
                                <h6 class="mb-0">Alerts</h6>
                                <small class="text-muted">View notifications</small>
                            </div>
                        </a>
                        
                        <a href="#" class="quick-link-card">
                            <div class="quick-link-icon bg-info-light">
                                <i class="fas fa-file-alt text-info"></i>
                            </div>
                            <div class="quick-link-text">
                                <h6 class="mb-0">Pay Slip</h6>
                                <small class="text-muted">Download documents</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Events Card -->
            <div class="card modern-card">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="card-title mb-0">Upcoming Events</h5>
                </div>
                <div class="card-body">
                    <div class="text-center py-4">
                        <i class="far fa-calendar-alt fa-3x text-muted mb-3"></i>
                        <p class="mb-0">No upcoming events at this time.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leave Request Modal -->
<div class="modal fade" id="leaveRequestModal" tabindex="-1" aria-labelledby="leaveRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leaveRequestModalLabel">Request Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="leaveRequestForm">
                    <div class="mb-3">
                        <label for="leaveType" class="form-label">Leave Type</label>
                        <select class="form-select" id="leaveType" required>
                            <option value="">Select leave type</option>
                            <option value="sick">Sick Leave</option>
                            <option value="vacation">Vacation</option>
                            <option value="personal">Personal Leave</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="startDate" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="startDate" required>
                    </div>
                    <div class="mb-3">
                        <label for="endDate" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="endDate" required>
                    </div>
                    <div class="mb-3">
                        <label for="leaveReason" class="form-label">Reason</label>
                        <textarea class="form-control" id="leaveReason" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitLeaveRequest">Submit Request</button>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Digital clock function
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const timeString = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        
        // Update digital clock in Today's Attendance card
        const digitalClock = document.getElementById('digital-clock');
        if (digitalClock) {
            digitalClock.textContent = timeString;
        }
        
        // Update live time in top date card if it exists
        const liveTime = document.getElementById('live-time');
        if (liveTime) {
            liveTime.textContent = timeString;
        }
    }
    
    // Initial call and then update every second
    updateClock();
    setInterval(updateClock, 1000);
    
    // Handle attendance button clicks (both clock in and clock out)
    const clockInBtn = document.getElementById('clockInBtn');
    const clockOutBtn = document.getElementById('clockOutBtn');
    
    function recordAttendance() {
        // Determine if this is a clock-in or clock-out button press
        const isClockIn = document.getElementById('clockInBtn') !== null;
        
        // Show loading state
        Swal.fire({
            title: 'Processing...',
            text: isClockIn ? 'Recording clock in...' : 'Recording clock out...',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Make an AJAX request to record attendance
        fetch('record_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=record_attendance&emp_id=<?php echo $userData['emp_id']; ?>',
            // Add timeout to prevent hanging requests
            timeout: 10000
        })
        .then(response => {
            // Check if the response is ok (status in the range 200-299)
            if (!response.ok) {
                throw new Error('Server returned ' + response.status + ': ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                // No need to save timestamp separately - just show success message
                Swal.fire({
                    icon: 'success',
                    title: data.action === 'CI' ? 'Clocked In!' : 'Clocked Out!',
                    text: data.message || 'Your attendance has been recorded.',
                    showConfirmButton: false,
                    timer: 2000
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to record attendance. Please try again.',
                    showConfirmButton: true
                });
            }
        })
        .catch(error => {
            console.error('Attendance error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                html: 'A network error occurred while recording attendance.<br><br>' +
                      '<strong>Possible causes:</strong><br>' +
                      '• Database connection issue<br>' +
                      '• Server is not responding<br>' +
                      '• Network connectivity problem',
                showConfirmButton: true,
                confirmButtonText: 'Try Again',
                showCancelButton: true,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // If user clicks "Try Again", call this function again
                    recordAttendance();
                }
            });
        });
    }
    
    // Add click handlers to both buttons
    if (clockInBtn) {
        clockInBtn.addEventListener('click', recordAttendance);
    }
    
    if (clockOutBtn) {
        clockOutBtn.addEventListener('click', recordAttendance);
    }
    
    // Handle leave request submission
    const submitLeaveRequest = document.getElementById('submitLeaveRequest');
    if (submitLeaveRequest) {
        submitLeaveRequest.addEventListener('click', function() {
            const form = document.getElementById('leaveRequestForm');
            if (form.checkValidity()) {
                // AJAX call would go here in a production environment
                Swal.fire({
                    icon: 'success',
                    title: 'Request Submitted!',
                    text: 'Your leave request has been submitted for approval.',
                    showConfirmButton: true
                }).then(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('leaveRequestModal'));
                    modal.hide();
                });
            } else {
                form.reportValidity();
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
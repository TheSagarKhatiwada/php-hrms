<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin_user = is_admin();

// Get current month and year
$current_month = $_GET['month'] ?? date('n');
$current_year = $_GET['year'] ?? date('Y');

// Navigation for previous/next month
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get leave requests for the current month
$start_date = "$current_year-$current_month-01";
$end_date = date('Y-m-t', strtotime($start_date)); // Last day of the month

$sql = "SELECT lr.*, 
               e.first_name, e.last_name, e.emp_id,
               lt.name as leave_type_name, lt.color
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.emp_id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.status = 'approved' 
        AND ((lr.start_date <= ? AND lr.end_date >= ?) 
             OR (lr.start_date >= ? AND lr.start_date <= ?))";

// Add permission filter - employees see only their own
if (!$is_admin_user) {
    $sql .= " AND lr.employee_id = ?";
}

$sql .= " ORDER BY lr.start_date";

if (!$is_admin_user) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$end_date, $start_date, $start_date, $end_date, $user_id]);
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$end_date, $start_date, $start_date, $end_date]);
}

$leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get holidays for the current month
$holidays_data = get_holidays_for_month($current_month, $current_year);
$holidays = [];

// Convert holidays to the expected format (date => name)
foreach ($holidays_data as $holiday) {
    $holiday_date = $holiday['date'];
    
    // Handle recurring holidays - adjust year to current year
    if ($holiday['is_recurring']) {
        $holiday_date = $current_year . '-' . date('m-d', strtotime($holiday['date']));
    }
    
    // Only include holidays that fall within the current month
    if (date('Y-m', strtotime($holiday_date)) === sprintf('%04d-%02d', $current_year, $current_month)) {
        $holidays[$holiday_date] = $holiday['name'];
    }
}

// Build calendar data
$days_in_month = date('t', strtotime($start_date));
$first_day_of_month = date('w', strtotime($start_date)); // 0 = Sunday, 6 = Saturday

$calendar_data = [];
for ($day = 1; $day <= $days_in_month; $day++) {
    $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
    $calendar_data[$date] = [
        'day' => $day,
        'date' => $date,
        'leaves' => [],
        'is_holiday' => isset($holidays[$date]),
        'holiday_name' => $holidays[$date] ?? null,
        'is_weekend' => in_array(date('w', strtotime($date)), [0, 6]) // Sunday = 0, Saturday = 6
    ];
}

// Add leave requests to calendar data
foreach ($leave_requests as $leave) {
    $start = strtotime($leave['start_date']);
    $end = strtotime($leave['end_date']);
    
    for ($date = $start; $date <= $end; $date = strtotime('+1 day', $date)) {
        $date_str = date('Y-m-d', $date);
        if (isset($calendar_data[$date_str])) {
            $calendar_data[$date_str]['leaves'][] = $leave;
        }
    }
}

// Get leave statistics for the current month
$stats_sql = "SELECT 
    COUNT(DISTINCT lr.employee_id) as employees_on_leave,
    COUNT(lr.id) as total_requests,
    SUM(lr.days_requested) as total_days
    FROM leave_requests lr
    WHERE lr.status = 'approved'
    AND ((lr.start_date <= ? AND lr.end_date >= ?) 
         OR (lr.start_date >= ? AND lr.start_date <= ?))";

if (!$is_admin_user) {
    $stats_sql .= " AND lr.employee_id = ?";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute([$end_date, $start_date, $start_date, $end_date, $user_id]);
} else {
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute([$end_date, $start_date, $start_date, $end_date]);
}

$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-calendar me-2"></i>Leave Calendar</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-success">
                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
            </a>
            <a href="requests.php" class="btn btn-outline-info">
                <i class="fas fa-list me-1"></i>All Requests
            </a>
            <?php if (!$is_admin_user): ?>
                <a href="request.php" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i>Apply for Leave
                </a>
            <?php endif; ?>
        </div>
    </div>    <!-- Calendar Stats -->
    <div class="row g-4 mb-4">
        <!-- Employees on Leave -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3 stat-icon me-3">
                            <i class="fas fa-users text-light fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted">Employees on Leave</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['employees_on_leave'] ?? 0; ?></h2>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user-times text-primary me-1"></i>
                        <small class="text-primary">This month's leave count</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Requests -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 stat-icon me-3">
                            <i class="fas fa-calendar-check text-success fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted">Total Requests</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['total_requests'] ?? 0; ?></h2>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-1"></i>
                        <small class="text-success">Approved requests this month</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Days -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm rounded-3 dashboard-stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-warning bg-opacity-10 p-3 rounded-3 stat-icon me-3">
                            <i class="fas fa-calendar-times text-warning fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted">Total Days</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['total_days'] ?? 0; ?></h2>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-clock text-warning me-1"></i>
                        <small class="text-warning">Total leave days taken</small>
                    </div>
                </div>
            </div>
        </div>
    </div>    <!-- Calendar -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?>
                </h6>
            </div>
            <div class="d-flex gap-2">
                <div class="btn-group" role="group">
                    <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" 
                       class="btn btn-sm btn-primary">
                        Today
                    </a>
                    <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" 
                       class="btn btn-sm btn-outline-primary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php if (!$is_admin_user): ?>
                    <a href="request.php" class="btn btn-sm btn-success">
                        <i class="fas fa-plus me-1"></i>Apply for Leave
                    </a>
                <?php endif; ?>
            </div>
        </div><div class="card-body p-0">
                    <!-- Calendar Grid -->
                    <div class="calendar-container">
                        <table class="table table-bordered calendar-table mb-0" width="100%" cellspacing="0">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="text-center">Sunday</th>
                                    <th class="text-center">Monday</th>
                                    <th class="text-center">Tuesday</th>
                                    <th class="text-center">Wednesday</th>
                                    <th class="text-center">Thursday</th>
                                    <th class="text-center">Friday</th>
                                    <th class="text-center">Saturday</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $week = 0;
                                $day_count = 1;
                                
                                // Start calendar
                                while ($day_count <= $days_in_month) {
                                    echo "<tr>";
                                    
                                    for ($day_of_week = 0; $day_of_week < 7; $day_of_week++) {
                                        if ($week == 0 && $day_of_week < $first_day_of_month) {
                                            // Empty cells before first day
                                            echo "<td class='calendar-day empty'></td>";
                                        } elseif ($day_count > $days_in_month) {
                                            // Empty cells after last day
                                            echo "<td class='calendar-day empty'></td>";
                                        } else {
                                            // Actual calendar day
                                            $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day_count);
                                            $day_data = $calendar_data[$date];
                                            
                                            $classes = ['calendar-day'];
                                            if ($day_data['is_weekend']) $classes[] = 'weekend';
                                            if ($day_data['is_holiday']) $classes[] = 'holiday';
                                            if ($date == date('Y-m-d')) $classes[] = 'today';
                                            if (!empty($day_data['leaves'])) $classes[] = 'has-leaves';
                                            
                                            echo "<td class='" . implode(' ', $classes) . "' data-date='$date'>";
                                            echo "<div class='day-number'>" . $day_count . "</div>";
                                              // Show holiday name
                                            if ($day_data['is_holiday'] && !empty($day_data['holiday_name'])) {
                                                echo "<div class='holiday-name'>" . htmlspecialchars($day_data['holiday_name']) . "</div>";
                                            }
                                            
                                            // Show leave requests
                                            foreach ($day_data['leaves'] as $leave) {
                                                $tooltip = htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name'] . ' - ' . $leave['leave_type_name']);
                                                if ($leave['is_half_day']) {
                                                    $tooltip .= ' (Half Day)';
                                                }
                                                
                                                echo "<div class='leave-item' 
                                                          style='background-color: " . $leave['color'] . "' 
                                                          title='$tooltip'>";
                                                  if ($is_admin_user) {
                                                    echo "<small>" . htmlspecialchars($leave['first_name']) . "</small>";
                                                } else {
                                                    echo "<small>" . htmlspecialchars($leave['leave_type_name']) . "</small>";
                                                }
                                                
                                                if ($leave['is_half_day']) {
                                                    echo "<br><small>(Half)</small>";
                                                }
                                                
                                                echo "</div>";
                                            }
                                            
                                            echo "</td>";
                                            $day_count++;
                                        }
                                    }
                                    
                                    echo "</tr>";
                                    $week++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>            
            </div>            

            <!-- Legends -->
            <div class="row g-4">
                <div class="col-md-12">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Legend</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="legend-item">
                                        <span class="legend-color today"></span>
                                        <span>Today</span>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color weekend"></span>
                                        <span>Weekend</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="legend-item">
                                        <span class="legend-color holiday"></span>
                                        <span>Holiday</span>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color has-leaves"></span>
                                        <span>Has Leave</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<style>
.calendar-container {
    overflow-x: auto;
}

.calendar-table {
    min-width: 800px;
}

.calendar-day {
    height: 120px;
    width: 14.28%;
    vertical-align: top;
    padding: 5px;
    position: relative;
    border: 1px solid #dee2e6;
}

.calendar-day.empty {
    background-color: #ddd;
}

.calendar-day.weekend {
    background-color: transparent;
    color: #dc3545;
    font-weight: bold;
}

.calendar-day.holiday {
    background-color: #fff3cd;
}

.calendar-day.today {
    background-color: #3b3569;
    border: 2px solid #3b3569;
    color: white;
}

.calendar-day.has-leaves {
    border-left: 4px solid #28a745;
}

.day-number {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 5px;
}

.holiday-name {
    font-size: 10px;
    color: #856404;
    font-weight: bold;
    margin-bottom: 5px;
}

.leave-item {
    font-size: 10px;
    padding: 2px 4px;
    margin: 1px 0;
    border-radius: 3px;
    color: white;
    cursor: pointer;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.legend-color {
    width: 20px;
    height: 20px;
    margin-right: 10px;
    border: 1px solid #ccc;
    display: inline-block;
}

.legend-color.today {
    background-color: #3b3569;
    border: 2px solid #3b3569;
}

.legend-color.weekend {
    background-color: transparent;
    color: #dc3545;
    font-weight: bold;
    border: 1px solid #ccc;
}

.legend-color.holiday {
    background-color: #fff3cd;
}

.legend-color.has-leaves {
    border-left: 4px solid #28a745;
    background-color: white;
}

/* Dark mode support */
[data-bs-theme="dark"] .calendar-table {
    background-color: var(--bs-dark);
    color: var(--bs-body-color);
}

[data-bs-theme="dark"] .calendar-table th {
    background-color: var(--bs-primary) !important;
    color: white !important;
}

[data-bs-theme="dark"] .calendar-table td {
    border-color: var(--bs-border-color);
    background-color: var(--bs-dark);
}

[data-bs-theme="dark"] .calendar-day:hover {
    background-color: rgba(255,255,255,0.1) !important;
}

[data-bs-theme="dark"] .calendar-day.weekend {
    background-color: transparent;
    color: #ff6b6b;
    font-weight: bold;
}

[data-bs-theme="dark"] .calendar-day.has-leave {
    background-color: rgba(40, 167, 69, 0.1);
}

[data-bs-theme="dark"] .calendar-day.today {
    background-color: #3b3569;
    border-color: #3b3569;
    color: white;
}

[data-bs-theme="dark"] .legend-color {
    background-color: rgba(255,255,255,0.05);
    border-color: var(--bs-border-color);
}

[data-bs-theme="dark"] .legend-color.weekend {
    background-color: transparent;
    color: #ff6b6b;
    font-weight: bold;
    border-color: var(--bs-border-color);
}

[data-bs-theme="dark"] .legend-color.has-leaves {
    border-left-color: #28a745;
    background-color: rgba(255,255,255,0.05);
}

/* Dashboard stat card styling */
.dashboard-stat-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.dashboard-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1) !important;
}

.stat-icon {
    transition: transform 0.2s ease-in-out;
}

.dashboard-stat-card:hover .stat-icon {
    transform: scale(1.1);
}

/* Card header theme support */
[data-bs-theme="dark"] .card {
    background-color: var(--bs-dark);
    border-color: var(--bs-border-color);
}

[data-bs-theme="dark"] .card-header {
    background-color: rgba(255,255,255,0.05) !important;
    border-bottom-color: var(--bs-border-color) !important;
}

@media (max-width: 768px) {
    .calendar-day {
        height: 80px;
        font-size: 12px;
    }
    
    .day-number {
        font-size: 14px;
    }
    
    .leave-item {
        font-size: 9px;
        padding: 1px 2px;
    }
    
    .dashboard-stat-card {
        margin-bottom: 1rem;
    }
}
</style>

<script>
$(document).ready(function() {
    // Add click event to calendar days
    $('.calendar-day').click(function() {
        var date = $(this).data('date');
        if (date) {
            // You can add functionality here, like opening a modal to show day details
            console.log('Clicked on date:', date);
        }
    });

    // Initialize tooltips
    $('[title]').tooltip();
    
    // Make leave items clickable
    $('.leave-item').click(function(e) {
        e.stopPropagation();
        // Add functionality to view leave details
        console.log('Clicked on leave item');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>

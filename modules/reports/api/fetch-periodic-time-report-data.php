<?php
// Apply cache control to prevent showing old versions of the page
require_once '../../../includes/cache_control.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once '../../../includes/session_config.php';
require_once '../../../includes/utilities.php';
include("includes/db_connection.php");

// Check if user has permission
// if (!has_permission('view_daily_report') || !is_admin()) {
//     $_SESSION['error'] = "You don't have permission to access Reports.";
//     header('Location: index.php');
//     exit();
// }

// Function to convert time to seconds for comparison
function timeToSeconds($timeStr) {
    if (empty($timeStr) || $timeStr == '-') return 0;
    $parts = explode(':', $timeStr);
    return isset($parts[1]) ? ($parts[0] * 3600) + ($parts[1] * 60) : 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['reportDateRange']) || empty($_POST['reportDateRange'])) {
        echo json_encode(["error" => "Date range not received."]);
        exit;
    }

    $daterange = $_POST['reportDateRange'];
    list($startDate, $endDate) = explode(' - ', $daterange);

    // Convert to proper format (YYYY-MM-DD)
    $startDateObj = DateTime::createFromFormat('d/m/Y', trim($startDate));
    $endDateObj = DateTime::createFromFormat('d/m/Y', trim($endDate));

    if (!$startDateObj || !$endDateObj) {
        echo json_encode(["error" => "Invalid date range format.", "received" => $_POST['reportDateRange']]);
        exit;
    }

    $startDate = $startDateObj->format('Y-m-d'); // Convert to YYYY-MM-DD
    $endDate = $endDateObj->format('Y-m-d');     // Convert to YYYY-MM-DD

    // Fetch the branch
    $empBranch = $_POST['empBranch'] ?? '';    // Validate the date inputs
    if (empty($startDate) || empty($endDate)) {
        echo json_encode(["error" => "Invalid date range (empty values)."]);
        exit;
    }

    // Branch is now required
    if (empty($empBranch)) {
        echo json_encode(["error" => "Please select a branch."]);
        exit;
    }    // Validate branch filter - using separate queries for better SQL compatibility
    $branch_check = $pdo->prepare("SELECT COUNT(*) as count FROM branches WHERE id = :empBranch");
    $branch_check->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
    $branch_check->execute();
    $count_data = $branch_check->fetch(PDO::FETCH_ASSOC);
    
    if ($count_data['count'] == 0) {
        echo json_encode(["error" => "Invalid branch."]);
        exit;
    }
    
    // Get branch name in a separate query
    $branch_name_query = $pdo->prepare("SELECT name FROM branches WHERE id = :empBranch");
    $branch_name_query->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
    $branch_name_query->execute();
    $branch_data = $branch_name_query->fetch(PDO::FETCH_ASSOC);
    
    // Store branch name for header display
    $branchName = $branch_data['name'];

    // Fetch system settings for work hours
    $settingsQuery = "SELECT * FROM settings WHERE 1";
    $settingsStmt = $pdo->query($settingsQuery);
    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Default working hours setup if settings not available
    $scheduled_in_time = isset($settings['work_start_time']) ? $settings['work_start_time'] : '09:30';
    $scheduled_out_time = isset($settings['work_end_time']) ? $settings['work_end_time'] : '18:00';

    // Create DateTime objects for easier time calculations
    $scheduled_in = new DateTime($scheduled_in_time);
    $scheduled_out = new DateTime($scheduled_out_time);    // Fetch employees
    $sqlEmployees = "SELECT 
                        e.emp_id, 
                        CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) AS employee_name, 
                        e.exit_date
                    FROM employees e
                    WHERE e.branch = :empBranch
                    AND (e.exit_date IS NULL OR e.exit_date >= :startDate)";    $stmtEmployees = $pdo->prepare($sqlEmployees);
    $stmtEmployees->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
    $stmtEmployees->bindParam(':startDate', $startDate);
    $stmtEmployees->execute();
    $employees = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);    // Fetch attendance data within the selected date range
    $sqlAttendance = "SELECT
                        a.emp_Id,
                        a.date,
                        MIN(a.time) AS in_time,
                        GROUP_CONCAT(a.method ORDER BY a.time ASC SEPARATOR ', ') AS methods_used
                    FROM attendance_logs a
                    WHERE a.date BETWEEN :startDate AND :endDate
                    AND emp_Id IN (SELECT emp_id FROM employees WHERE branch = :empBranch)
                    GROUP BY a.emp_Id, a.date";

    $stmtAttendance = $pdo->prepare($sqlAttendance);
    $stmtAttendance->bindParam(':startDate', $startDate);
    $stmtAttendance->bindParam(':endDate', $endDate);
    $stmtAttendance->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
    $stmtAttendance->execute();
    $attendanceData = $stmtAttendance->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map for quick lookup of attendance data by employee ID and date
    $attendanceMap = [];
    foreach ($attendanceData as $att) {
        $attendanceMap[$att['emp_Id']][$att['date']] = $att;
    }

    // Generate date range for the report
    $period = new DatePeriod(
        new DateTime($startDate),
        new DateInterval('P1D'),
        (new DateTime($endDate))->modify('+1 day') // Include end date
    );

    // Process data for each employee
    $processedData = [];
    $totalWorkingDays = 0;
    $totalPresent = 0;
    $totalAbsent = 0;
    $totalLeave = 0;
    $totalHolidays = 0;    foreach ($employees as $employee) {
        $empId = $employee['emp_id'];
        $employeeName = $employee['employee_name'];
        $exitDate = $employee['exit_date'];
          $employeeData = [
            'emp_id' => $empId,
            'employee_name' => $employeeName,
            'branch' => $branchName, // Store branch name for header display
            'dates' => [],
            'summary' => [
                'working_days' => 0,
                'present' => 0,
                'absent' => 0,
                'leave' => 0,
                'holidays' => 0,
                'holiday_count' => 0  // Added explicit holiday count for employee summary
            ]
        ];

        // Process each date in the period
        foreach ($period as $dateObj) {
            $date = $dateObj->format('Y-m-d');
            $dayOfWeek = (int)$dateObj->format('N'); // 1 (Monday) to 7 (Sunday)
            
            // Check if employee was exited by this date
            $isExited = (!empty($exitDate) && $date > $exitDate);            // Determine if it's a weekend/holiday (Saturday in this case)
            $isSaturday = ($dayOfWeek == 6);
            
            // Check if the date is a holiday
            $holiday = is_holiday($date, $employee['branch_id'] ?? null);
            $isHoliday = $holiday !== false;
            
            // Default status is 'L' for Leave/Absent
            $status = 'L';
            
            if ($isExited) {
                // If employee has exited, mark as '-' (not applicable)
                $status = '-';
            } elseif ($isSaturday || $isHoliday) {
                // If it's Saturday or a holiday, mark as Holiday 'H'
                $status = 'H';
                $employeeData['summary']['holidays']++;
                $employeeData['summary']['holiday_count']++; // Increment holiday count for this employee
            } else {
                // For other days, check attendance
                if (isset($attendanceMap[$empId][$date])) {
                    // Employee has an attendance record for this date
                    $attendance = $attendanceMap[$empId][$date];
                    $inTime = $attendance['in_time'];
                    
                    // Convert to DateTime for comparison
                    $inTimeObj = new DateTime($inTime);
                    
                    // Compare entry time with the scheduled start time
                    if ($inTimeObj <= $scheduled_in) {
                        // On time or early - Present
                        $status = 'P';
                        $employeeData['summary']['present']++;
                    } else {
                        // Late entry - Absent
                        $status = 'A';
                        $employeeData['summary']['absent']++;
                    }
                } else {
                    // No attendance record - Leave
                    $status = 'L';
                    $employeeData['summary']['leave']++;
                }
                
                // Count as working day
                $employeeData['summary']['working_days']++;
            }
            
            // Add date data to employee record
            $employeeData['dates'][$date] = [
                'date' => $date,
                'status' => $status
            ];
              // Update totals for the first employee only (to avoid duplicating the count)
            if (count($processedData) === 0) {
                if (!$isExited && !$isSaturday && !$isHoliday) {
                    $totalWorkingDays++;
                    
                    if ($status === 'P') $totalPresent++;
                    else if ($status === 'A') $totalAbsent++;
                    else if ($status === 'L') $totalLeave++;
                } elseif ($isSaturday || $isHoliday) {
                    $totalHolidays++;
                }
            }
        }
        
        // Update first employee's summary with totals
        if (count($processedData) === 0) {
            $employeeData['summary']['working_days'] = $totalWorkingDays;
            $employeeData['summary']['present'] = $totalPresent;
            $employeeData['summary']['absent'] = $totalAbsent;
            $employeeData['summary']['leave'] = $totalLeave;
            $employeeData['summary']['holidays'] = $totalHolidays;
        }
          // Only add employees who have at least one entry in the selected date range
        $hasData = false;
        foreach ($employeeData['dates'] as $dateData) {
            if ($dateData['status'] === 'P' || $dateData['status'] === 'A') {
                $hasData = true;
                break;
            }
        }
        
        if ($hasData) {
            $processedData[] = $employeeData;
        }
    }
    
    // Convert the data to JSON and pass it to the form
    $dataJson = json_encode($processedData, JSON_UNESCAPED_UNICODE);
?>

<form id="jsonForm" action="periodic-time-report.php" method="post">
    <input type="hidden" name="jsonData" value='<?php echo htmlspecialchars($dataJson, ENT_QUOTES, "UTF-8"); ?>'>
    <input type="hidden" name="reportDateRange" value="<?php echo htmlspecialchars($daterange, ENT_QUOTES, "UTF-8"); ?>">
    <input type="hidden" name="empBranch" value="<?php echo htmlspecialchars($empBranch, ENT_QUOTES, "UTF-8"); ?>">
</form>

<script>
    document.getElementById("jsonForm").submit();
</script>

<?php
}
?>

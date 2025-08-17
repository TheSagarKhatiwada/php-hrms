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
require_once '../../../includes/reason_helpers.php';
include("../../../includes/db_connection.php");
require_once '../../../includes/report-templates/monthly-attendance.php';

// Check if user has permission to access periodic reports
if (!has_permission('view_monthly_report') && !is_admin()) {
    $_SESSION['error'] = "You don't have permission to access Periodic Reports.";
    header('Location: ../../../dashboard.php');
    exit();
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
    $empBranch = $_POST['empBranch'] ?? '';

    // Validate the date inputs
    if (empty($startDate) || empty($endDate)) {
        echo json_encode(["error" => "Invalid date range (empty values)."]);
        exit;
    }

    // Validate branch filter
    if (!empty($empBranch)) {
        $branch_check = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE id = :empBranch");
        $branch_check->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
        $branch_check->execute();
        if ($branch_check->fetchColumn() == 0) {
            echo json_encode(["error" => "Invalid branch filter."]);
            exit;
        }
    }

    // Get the branch name for display if selected
    $branchName = 'All Branches';
    if (!empty($empBranch)) {
        $branchQuery = $pdo->prepare("SELECT name FROM branches WHERE id = :empBranch");
        $branchQuery->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
        $branchQuery->execute();
        $branchRow = $branchQuery->fetch();
        if ($branchRow) {
            $branchName = $branchRow['name'];
        }
    }    // Fetch employees
    $sqlEmployees = "SELECT 
                        e.emp_id, 
                        CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) AS employee_name, 
                        e.designation, 
                        b.name AS branch,
                        e.exit_date
                    FROM employees e
                    LEFT JOIN branches b ON e.branch = b.id";

    if (!empty($empBranch)) {
        $sqlEmployees .= " WHERE e.branch = :empBranch";
    }
    
    // Add condition to include exited employees who were active during the report period
    if (!empty($sqlEmployees)) {
        $sqlEmployees .= " AND (e.exit_date IS NULL OR e.exit_date >= :startDate)";
    } else {
        $sqlEmployees .= " WHERE (e.exit_date IS NULL OR e.exit_date >= :startDate)";
    }    $stmtEmployees = $pdo->prepare($sqlEmployees);
    if (!empty($empBranch)) {
        $stmtEmployees->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
    }
    // Bind the start date to filter exited employees
    $stmtEmployees->bindParam(':startDate', $startDate);
    
    $stmtEmployees->execute();
    $employees = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);    // Fetch attendance data within the selected date range
    $sqlAttendance = "SELECT
                        a.emp_Id,
                        a.date,
                        MIN(a.time) AS in_time,
                        MAX(a.time) AS out_time,
                        GROUP_CONCAT(a.method ORDER BY a.time ASC SEPARATOR ', ') AS methods_used,
                        GROUP_CONCAT(a.manual_reason ORDER BY a.time ASC SEPARATOR '; ') AS manual_reasons,
                        COUNT(a.id) AS punch_count
                    FROM attendance_logs a
                    WHERE a.date BETWEEN :startDate AND :endDate
                    GROUP BY a.emp_Id, a.date";

    if (!empty($empBranch)) {
        $sqlAttendance .= " HAVING emp_Id IN (SELECT emp_id FROM employees WHERE branch = :empBranch)";
    }

    $stmtAttendance = $pdo->prepare($sqlAttendance);
    $stmtAttendance->bindParam(':startDate', $startDate);
    $stmtAttendance->bindParam(':endDate', $endDate);
    if (!empty($empBranch)) {
        $stmtAttendance->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
    }

    if (!$stmtAttendance->execute()) {
        echo json_encode(["error" => "Attendance query failed."]);
        exit;
    }
    
    $attendanceData = $stmtAttendance->fetchAll(PDO::FETCH_ASSOC);
    $attendanceMap = [];

    // Convert attendance data into a map for quick lookup
    foreach ($attendanceData as $att) {
        $attendanceMap[$att['emp_Id']][$att['date']] = $att;
    }

    // Structure to hold our pre-processed data
    $processedData = [
        'employees' => [],
        'report_meta' => [
            'date_range' => $daterange,
            'filter_branch' => $branchName,
            'generated_at' => date('Y-m-d H:i:s')
        ]
    ];

    // Default working hours setup
    $scheduled_in = new DateTime('09:30');
    $scheduled_out = new DateTime('18:00');
    $working_hours = new DateInterval('PT8H30M');
    $formatted_working_hours = sprintf('%02d:%02d:%02d', $working_hours->h, $working_hours->i, 0);

    // Process each employee
    foreach ($employees as $employee) {
        $empid = $employee['emp_id'];
        $employeeName = $employee['employee_name'];
        $designation = $employee['designation'];
        $employeeBranch = $employee['branch'];
          // Initialize employee structure with summary
        $employeeData = [
            'id' => $empid,
            'name' => $employeeName,
            'designation' => $designation,
            'branch' => $employeeBranch,
            'exit_date' => $employee['exit_date'] ?? null,
            'daily_records' => [],
            'summary' => [
                'present' => 0,
                'absent' => 0,
                'weekend' => 0,
                'holiday' => 0,
                'paid_leave' => 0,
                'unpaid_leave' => 0,
                'missed' => 0,
                'manual' => 0,
                'misc' => 0,
                'total_worked_seconds' => 0,
                'total_overtime_seconds' => 0,
                'total_scheduled_seconds' => 0,
                'formatted_worked_time' => '00:00:00',
                'formatted_overtime' => '00:00:00',
                'formatted_scheduled_time' => '00:00:00'
            ]
        ];

        // Generate dates within range
        $period = new DatePeriod(
            new DateTime($startDate),
            new DateInterval('P1D'),
            (new DateTime($endDate))->modify('+1 day') // Ensure it includes the last day
        );        foreach ($period as $dateObj) {
            $date = $dateObj->format('Y-m-d');
            $dayOfWeek = date('N', strtotime($date));
            
            // Check if the date is after employee exit date
            $isExited = false;
            if (!empty($employee['exit_date']) && $date > $employee['exit_date']) {
                $isExited = true;
            }
            
            $record = [
                'date' => $date,
                'scheduled_in' => $scheduled_in->format('H:i'),
                'scheduled_out' => $scheduled_out->format('H:i'),
                'working_hour' => $formatted_working_hours,
                'in_time' => '',
                'out_time' => '',
                'worked_duration' => '',
                'over_time' => '',
                'late_in' => '',
                'early_out' => '',
                'early_in' => '',
                'late_out' => '',
                'marked_as' => $isExited ? 'Exited' : (($dayOfWeek >= 6) ? 'Weekend' : 'Absent'), // 6,7 for Saturday and Sunday
                'methods' => '',
                'remarks' => $isExited ? 'Employee exited on ' . $employee['exit_date'] : ''
            ];

            // Add scheduled time to total (except weekends)
            if ($dayOfWeek < 6) {
                $employeeData['summary']['total_scheduled_seconds'] += timeToSeconds($formatted_working_hours);
            }            // If attendance record exists for this day and the employee wasn't exited yet
            if (isset($attendanceMap[$empid][$date]) && !$isExited) {
                $attendance = $attendanceMap[$empid][$date];
                $in_time = new DateTime($attendance['in_time']);
                $out_time = new DateTime($attendance['out_time']);

                $record['in_time'] = $in_time->format('H:i');
                $record['out_time'] = ($out_time != $in_time) ? $out_time->format('H:i') : '';

                // Calculate worked duration
                $worked_duration = $in_time->diff($out_time);
                if ($out_time != $in_time) {
                    $worked_duration_str = sprintf('%02d:%02d:%02d', $worked_duration->h, $worked_duration->i, $worked_duration->s);
                    $record['worked_duration'] = $worked_duration_str;
                    
                    // Add to total worked time
                    $worked_seconds = timeToSeconds($worked_duration_str);
                    $employeeData['summary']['total_worked_seconds'] += $worked_seconds;
                }

                // Calculate overtime (only if not weekend)
                if ($dayOfWeek < 6) {
                    $total_minutes_worked = ($worked_duration->h * 60) + $worked_duration->i;
                    $scheduled_minutes = ($working_hours->h * 60) + $working_hours->i;
                    $overtime_minutes = max(0, $total_minutes_worked - $scheduled_minutes);
                    
                    if ($overtime_minutes > 0) {
                        $overtime_str = sprintf('%02d:%02d:00', floor($overtime_minutes / 60), $overtime_minutes % 60);
                        $record['over_time'] = $overtime_str;
                        $employeeData['summary']['total_overtime_seconds'] += timeToSeconds($overtime_str);
                    }
                }

                // Calculate late in
                if ($in_time > $scheduled_in) {
                    $late_in = $scheduled_in->diff($in_time);
                    $record['late_in'] = sprintf('%02d:%02d', $late_in->h, $late_in->i);
                }

                // Calculate early out
                if ($out_time != $in_time && $out_time < $scheduled_out) {
                    $early_out = $out_time->diff($scheduled_out);
                    $record['early_out'] = sprintf('%02d:%02d', $early_out->h, $early_out->i);
                }

                // Calculate early in
                if ($in_time < $scheduled_in) {
                    $early_in = $scheduled_in->diff($in_time);
                    $record['early_in'] = sprintf('%02d:%02d', $early_in->h, $early_in->i);
                }

                // Calculate late out
                if ($out_time > $scheduled_out) {
                    $late_out = $scheduled_out->diff($out_time);
                    $record['late_out'] = sprintf('%02d:%02d', $late_out->h, $late_out->i);
                }                // Determine attendance status
                $record['marked_as'] = 'Present';                // Include only in time and out time methods/reasons
                $methodsArray = explode(', ', $attendance['methods_used'] ?? '');
                $reasonsArray = explode('; ', $attendance['manual_reasons'] ?? '');
                $punchCount = $attendance['punch_count'] ?? 1;
                                
                // First record is always check-in
                $inMethod = $methodsArray[0] ?? '';
                $inReasonRaw = $reasonsArray[0] ?? '';
                $inReason = hrms_format_reason_for_report($inReasonRaw);
                
                // Last record is always check-out (if there's more than one record)
                $outMethod = ($punchCount > 1) ? end($methodsArray) : '';
                $outReasonRaw = ($punchCount > 1) ? end($reasonsArray) : '';
                $outReason = hrms_format_reason_for_report($outReasonRaw);
                
                // Only show in time and out time data
                $record['methods'] = "In: " . $inMethod . ($outMethod ? ", Out: " . $outMethod : "");
                $record['remarks'] = $inReason . ($outReason ? " | " . $outReason : "");
                
                // Check for manual entries
                if (strpos($inMethod, '1') !== false || strpos($outMethod, '1') !== false) {
                    $employeeData['summary']['manual']++;
                }
            }            // Update summary based on marked_as
            switch ($record['marked_as']) {
                case 'Present': $employeeData['summary']['present']++; break;
                case 'Absent': $employeeData['summary']['absent']++; break;
                case 'Weekend': $employeeData['summary']['weekend']++; break;
                case 'Holiday': $employeeData['summary']['holiday']++; break;
                case 'Paid Leave': $employeeData['summary']['paid_leave']++; break;
                case 'Unpaid Leave': $employeeData['summary']['unpaid_leave']++; break;
                case 'Missed': $employeeData['summary']['missed']++; break;
                case 'Exited': $employeeData['summary']['misc']++; break; // Count exited days as misc
                default: $employeeData['summary']['misc']++; break;
            }

            // Add record to employee's daily records
            $employeeData['daily_records'][] = $record;
        }

        // Format summary times
        $employeeData['summary']['formatted_scheduled_time'] = secondsToTime($employeeData['summary']['total_scheduled_seconds']);
        $employeeData['summary']['formatted_worked_time'] = secondsToTime($employeeData['summary']['total_worked_seconds']);
        $employeeData['summary']['formatted_overtime'] = secondsToTime($employeeData['summary']['total_overtime_seconds']);

        // Add employee data to processed data
        $processedData['employees'][] = $employeeData;
    }

    // If this is an AJAX request, just return the JSON
    if (isset($_POST['isAjax']) && $_POST['isAjax'] === true) {
        header('Content-Type: application/json');
        echo json_encode($processedData);
        exit;
    }
    
    // For non-AJAX requests, store the data in session and redirect
    $_SESSION['monthly_report_data'] = $processedData;
    
    // Redirect back to the periodic report page with the date range and branch    $redirectUrl = 'periodic-report.php?hasData=1';
    
    if (!empty($_POST['reportDateRange'])) {
        $redirectUrl .= '&dateRange=' . urlencode($_POST['reportDateRange']);
    }
    
    if (isset($_POST['empBranch'])) {
        $redirectUrl .= '&branch=' . urlencode($_POST['empBranch']);
    }
    
    header('Location: ' . $redirectUrl);
    exit;
}
?>

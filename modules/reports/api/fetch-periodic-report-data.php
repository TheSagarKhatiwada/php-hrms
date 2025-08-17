<?php
// Security check and session validation
require_once '../../../includes/session_config.php';
require_once '../../../includes/utilities.php';
require_once '../../../includes/reason_helpers.php';

// (Removed verbose debug logging previously writing to debug_log.txt)

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "User not logged in."]);
    exit;
}

// Check permissions - allow admin or users with view_periodic_report permission
$hasPermission = false;
if (is_admin()) {
    $hasPermission = true;
} else {
    // For now, allow all logged-in users to access reports (TODO: refine permissions)
    $hasPermission = true;
}

if (!$hasPermission) {
    http_response_code(403);
    echo json_encode(["error" => "Access denied. Insufficient permissions."]);
    exit;
}

include("../../../includes/db_connection.php");

// Only allow POST requests for security
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Only POST requests are accepted."]);
    exit;
}

// Validate required parameters
if (!isset($_POST['reportDateRange']) || empty($_POST['reportDateRange'])) {
    echo json_encode(["error" => "Date range not received."]);
    exit;
}

$daterange = $_POST['reportDateRange'];

// Split the date range
if (strpos($daterange, ' - ') === false) {
    echo json_encode(["error" => "Invalid date range format. Expected: 'DD/MM/YYYY - DD/MM/YYYY'"]);
    exit;
}

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

    // Fetch employees
    $sqlEmployees = "SELECT 
                        e.emp_id, 
                        CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) AS employee_name, 
                        d.title as designation, 
                        b.name AS branch,
                        e.exit_date
                    FROM employees e
                    LEFT JOIN branches b ON e.branch = b.id
                    LEFT JOIN designations d ON e.designation = d.id";

    if (!empty($empBranch)) {
        $sqlEmployees .= " WHERE e.branch = :empBranch";
    }
    
    // Add condition to include exited employees who were active during the report period
    if (strpos($sqlEmployees, "WHERE") !== false) {
        $sqlEmployees .= " AND (e.exit_date IS NULL OR e.exit_date >= :startDate)";
    } else {
        $sqlEmployees .= " WHERE (e.exit_date IS NULL OR e.exit_date >= :startDate)";
    }

    $stmtEmployees = $pdo->prepare($sqlEmployees);
    if (!empty($empBranch)) {
        $stmtEmployees->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
    }
    // Bind start date for filtering exited employees
    $stmtEmployees->bindParam(':startDate', $startDate);
    $stmtEmployees->execute();
    $employees = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);

    // Fetch attendance data within the selected date range
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

    if ($stmtAttendance->execute()) {
        $attendanceData = $stmtAttendance->fetchAll(PDO::FETCH_ASSOC);
    } else {
        echo json_encode(["error" => "Attendance query failed."]);
        exit;
    }

    $attendanceMap = [];

    // Convert attendance data into a map for quick lookup
    foreach ($attendanceData as $att) {
        $attendanceMap[$att['emp_Id']][$att['date']] = $att;
    }

    $data = [];

    // Default working hours setup
    $scheduled_in = new DateTime('09:30');
    $scheduled_out = new DateTime('18:00');
    $working_hours = new DateInterval('PT8H30M');
    $formatted_working_hours = sprintf('%02d:%02d', $working_hours->h, $working_hours->i);

    // Process each employee
    foreach ($employees as $employee) {
        $empid = $employee['emp_id'];
        $employeeName = $employee['employee_name'];
        $designation = $employee['designation'];
        $employeeBranch = $employee['branch'];

        // Generate dates within range
        $period = new DatePeriod(
            new DateTime($startDate),
            new DateInterval('P1D'),
            (new DateTime($endDate))->modify('+1 day') // Ensure it includes the last day
        );
        
        foreach ($period as $dateObj) {
            $date = $dateObj->format('Y-m-d');
            // Check if the date is a weekend (Saturday = 6)
            $isWeekend = (date('N', strtotime($date)) == 6);
            
            // Check if the date is a holiday
            $holiday = is_holiday($date, $employee['branch_id'] ?? null);
            $isHoliday = $holiday !== false;
            
            // Check if the date is after employee exit date
            $isExited = false;
            if (!empty($employee['exit_date']) && $date > $employee['exit_date']) {
                $isExited = true;
            }
            
            $row = [
                'emp_id' => $empid,
                'employee_name' => $employeeName,
                'designation' => $designation,
                'branch' => $employeeBranch,
                'date' => $date,
                'date_range' => $daterange,
                'scheduled_in' => ($isWeekend || $isHoliday) ? '-' : $scheduled_in->format('H:i'),
                'scheduled_out' => ($isWeekend || $isHoliday) ? '-' : $scheduled_out->format('H:i'),
                'working_hour' => ($isWeekend || $isHoliday) ? '-' : $formatted_working_hours,
                'in_time' => ($isWeekend || $isHoliday) ? '-' : '',
                'out_time' => ($isWeekend || $isHoliday) ? '-' : '',
                'worked_duration' => ($isWeekend || $isHoliday) ? '-' : '',
                'over_time' => ($isWeekend || $isHoliday) ? '-' : '',
                'late_in' => ($isWeekend || $isHoliday) ? '-' : '',
                'early_out' => ($isWeekend || $isHoliday) ? '-' : '',
                'early_in' => ($isWeekend || $isHoliday) ? '-' : '',
                'late_out' => ($isWeekend || $isHoliday) ? '-' : '',
                'marked_as' => $isExited ? 'Exited' : ($isHoliday ? 'Holiday' : ($isWeekend ? 'Weekend' : 'Absent')),
                'methods' => '',
                'remarks' => $isExited ? 'Employee exited on ' . $employee['exit_date'] : ($isHoliday ? $holiday['name'] : '')
            ];

            if (isset($attendanceMap[$empid][$date]) && !$isExited) {
                // Employee was present on this date
                $attendance = $attendanceMap[$empid][$date];
                // Get the in and out times
                $in_time = new DateTime($attendance['in_time']);
                $out_time = new DateTime($attendance['out_time']);

                $row['in_time'] = $in_time->format('H:i');
                $row['out_time'] = ($out_time != $in_time) ? $out_time->format('H:i') : '';

                // Calculate worked duration
                $worked_duration = $in_time->diff($out_time);
                if ($out_time != $in_time) {
                    $row['worked_duration'] = $worked_duration->format('%H:%I');
                } else {
                    $row['worked_duration'] = '';
                }

                // Calculate overtime
                $total_minutes_worked = ($worked_duration->h * 60) + $worked_duration->i;
                $scheduled_minutes = ($working_hours->h * 60) + $working_hours->i;
                
                if ($isHoliday || $isWeekend) {
                    // On holidays and weekends, all worked time is considered overtime
                    $overtime_minutes = $total_minutes_worked;
                } else {
                    // On regular days, only time beyond scheduled hours is overtime
                    $overtime_minutes = max(0, $total_minutes_worked - $scheduled_minutes);
                }
                
                $row['over_time'] = $overtime_minutes > 0 ? sprintf('%02d:%02d', floor($overtime_minutes / 60), $overtime_minutes % 60) : '';

                // Calculate time differences only for regular working days
                if (!$isWeekend && !$isHoliday) {
                    // Calculate late in
                    $late_in = $scheduled_in->diff($in_time);
                    $row['late_in'] = ($in_time > $scheduled_in) ? $late_in->format('%H:%I') : '';

                    // Calculate early out
                    $early_out = $out_time->diff($scheduled_out);
                    if ($out_time != $in_time) {
                        $row['early_out'] = ($out_time < $scheduled_out) ? $early_out->format('%H:%I') : '';
                    } else {
                        $row['early_out'] = '';
                    }

                    // Calculate early in
                    $early_in = $scheduled_in->diff($in_time);
                    $row['early_in'] = ($in_time < $scheduled_in) ? $early_in->format('%H:%I') : '';

                    // Calculate late out
                    $late_out = $scheduled_out->diff($out_time);
                    $row['late_out'] = ($out_time > $scheduled_out) ? $late_out->format('%H:%I') : '';
                } else {
                    // For weekends and holidays, set time differences to empty
                    $row['late_in'] = '';
                    $row['early_out'] = '';
                    $row['early_in'] = '';
                    $row['late_out'] = '';
                }

                // Determine attendance status and remarks
                if ($isHoliday) {
                    $row['marked_as'] = 'Present (Holiday)';
                    $row['remarks'] = $holiday['name'] . ' - Worked as OT';
                } elseif ($isWeekend) {
                    $row['marked_as'] = 'Present (Weekend)';
                    $row['remarks'] = 'Weekend - Worked as OT';
                } else {
                    $row['marked_as'] = 'Present';
                }

                // Include only in time and out time methods/reasons
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
                
                // Convert method codes to letters: 0=A (Auto), 1=M (Manual), 2=W (Web)
                $methodMap = ['0' => 'A', '1' => 'M', '2' => 'W'];
                $inMethodLetter = isset($methodMap[$inMethod]) ? $methodMap[$inMethod] : $inMethod;
                $outMethodLetter = isset($methodMap[$outMethod]) ? $methodMap[$outMethod] : $outMethod;
                
                // Only show in time and out time data in format "W | W"
                $row['methods'] = $inMethodLetter . ($outMethod ? " | " . $outMethodLetter : "");
                $row['remarks'] = $inReason . ($outReason ? " | " . $outReason : "");
            } else if ($isHoliday && !$isExited) {
                // Employee didn't work on holiday - show dashes for time fields
                $row['in_time'] = '-';
                $row['out_time'] = '-';
                $row['worked_duration'] = '-';
                $row['over_time'] = '-';
                $row['late_in'] = '-';
                $row['early_out'] = '-';
                $row['early_in'] = '-';
                $row['late_out'] = '-';
                $row['methods'] = '-';
                // Keep the original holiday remarks
            }

            // Add the row to the data array
            $data[] = $row;
        }
    }

    // Encode data as JSON
    $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);
?>

<form id="jsonForm" action="../periodic-report.php" method="post">
    <input type="hidden" name="jsonData" value='<?php echo htmlspecialchars($dataJson, ENT_QUOTES, "UTF-8"); ?>'>
    <input type="hidden" name="startdate" value="<?php echo $startDate; ?>">
    <input type="hidden" name="enddate" value="<?php echo $endDate; ?>">
</form>

<script>
    document.getElementById("jsonForm").submit();
</script>
<?php

include("includes/db_connection.php");

// Fetch report date and branch filter from the request
$reportdate = $_POST['reportdate'];
$empBranch = $_POST['empBranch'];

// Validate the report date format
if (empty($reportdate) || !DateTime::createFromFormat('Y-m-d', $reportdate)) {
    echo json_encode(["error" => "Invalid report date format."]);
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
                    b.name AS branch,
                    e.exit_date
                FROM employees e
                LEFT JOIN branches b ON e.branch = b.id";

if (!empty($empBranch)) {
    $sqlEmployees .= " WHERE e.branch = :empBranch";
}

// Add condition to include exited employees who were active on the report date
if (strpos($sqlEmployees, "WHERE") !== false) {
    $sqlEmployees .= " AND (e.exit_date IS NULL OR e.exit_date >= :reportdate)";
} else {
    $sqlEmployees .= " WHERE (e.exit_date IS NULL OR e.exit_date >= :reportdate)";
}

$stmtEmployees = $pdo->prepare($sqlEmployees);
if (!empty($empBranch)) {
    $stmtEmployees->bindParam(':empBranch', $empBranch);
}
// Bind report date for filtering exited employees
$stmtEmployees->bindParam(':reportdate', $reportdate);
$stmtEmployees->execute();
$employees = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);

// Fetch attendance data
$sqlAttendance = "SELECT
                    a.emp_Id,
                    MIN(a.time) AS in_time,
                    MAX(a.time) AS out_time,
                    GROUP_CONCAT(a.method ORDER BY a.time ASC SEPARATOR ', ') AS methods_used,
                    GROUP_CONCAT(a.manual_reason ORDER BY a.time ASC SEPARATOR '; ') AS manual_reasons,
                    COUNT(a.id) AS punch_count
                FROM attendance_logs a
                WHERE a.date = :reportdate
                GROUP BY a.emp_Id";

if (!empty($empBranch)) {
    $sqlAttendance .= " HAVING emp_Id IN (SELECT emp_id FROM employees WHERE branch = :empBranch)";
}

$stmtAttendance = $pdo->prepare($sqlAttendance);
$stmtAttendance->bindParam(':reportdate', $reportdate);
if (!empty($empBranch)) {
    $stmtAttendance->bindParam(':empBranch', $empBranch);
}
$stmtAttendance->execute();
$attendanceData = $stmtAttendance->fetchAll(PDO::FETCH_ASSOC);
$attendanceMap = [];

// Convert attendance data into a map for quick lookup
foreach ($attendanceData as $att) {
    $attendanceMap[$att['emp_Id']] = $att;
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
    $employeeBranch = $employee['branch'];

    // Check if the employee was exited on or before this report date
    $isExited = false;
    if (!empty($employee['exit_date']) && $reportdate > $employee['exit_date']) {
        $isExited = true;
    }
    
    $row = [
        'emp_id' => $empid,
        'employee_name' => $employeeName,
        'branch' => $employeeBranch,
        'report_date' => $reportdate,
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
        'marked_as' => $isExited ? 'Exited' : 'Absent',
        'methods' => '',
        'remarks' => $isExited ? 'Employee exited on ' . $employee['exit_date'] : ''
    ];


    if (isset($attendanceMap[$empid]) && !$isExited) {
        $attendance = $attendanceMap[$empid];

        // Convert times to DateTime objects
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
        $overtime_minutes = max(0, $total_minutes_worked - $scheduled_minutes);
        $row['over_time'] = $overtime_minutes > 0 ? sprintf('%02d:%02d', floor($overtime_minutes / 60), $overtime_minutes % 60) : '';

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

        // Determine attendance status
        $row['marked_as'] = 'Present';

        // Include only in time and out time methods/reasons
        $methodsArray = explode(', ', $attendance['methods_used'] ?? '');
        $reasonsArray = explode('; ', $attendance['manual_reasons'] ?? '');
        $punchCount = $attendance['punch_count'] ?? 1;
                                
        // First record is always check-in
        $inMethod = $methodsArray[0] ?? '';
        $inReason = $reasonsArray[0] ?? '';
        
        // Last record is always check-out (if there's more than one record)
        $outMethod = ($punchCount > 1) ? end($methodsArray) : '';
        $outReason = ($punchCount > 1) ? end($reasonsArray) : '';
        
        // Only show in time and out time data
        $row['methods'] = "In: " . $inMethod . ($outMethod ? ", Out: " . $outMethod : "");
        $row['remarks'] = $inReason . ($outReason ? " | " . $outReason : "");
    }

    $data[] = $row;
}

// Encode data as JSON
$dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);

?>

<form id="jsonForm" action="daily-report.php" method="post">
    <input type="hidden" name="jsonData" value='<?php echo htmlspecialchars($dataJson, ENT_QUOTES, "UTF-8"); ?>'>
    <input type="hidden" name="reportdate" value="<?php echo $reportdate;?>">
</form>

<script>
    document.getElementById("jsonForm").submit();
</script>
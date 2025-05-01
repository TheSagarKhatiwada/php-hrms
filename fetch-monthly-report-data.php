<?php
include("includes/db_connection.php");

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

    // Fetch employees
    $sqlEmployees = "SELECT 
                        e.emp_id, 
                        CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) AS employee_name, 
                        e.designation, 
                        b.name AS branch
                    FROM employees e
                    LEFT JOIN branches b ON e.branch = b.id";

    if (!empty($empBranch)) {
        $sqlEmployees .= " WHERE e.branch = :empBranch";
    }

    $stmtEmployees = $pdo->prepare($sqlEmployees);
    if (!empty($empBranch)) {
        $stmtEmployees->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
    }
    $stmtEmployees->execute();
    $employees = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);

    // Fetch attendance data within the selected date range
    $sqlAttendance = "SELECT
                        a.emp_Id,
                        a.date,
                        MIN(a.time) AS in_time,
                        MAX(a.time) AS out_time,
                        GROUP_CONCAT(a.method SEPARATOR ', ') AS methods_used,
                        GROUP_CONCAT(a.manual_reason SEPARATOR '; ') AS manual_reasons
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
            $row = [
                'emp_id' => $empid,
                'employee_name' => $employeeName,
                'designation' => $designation,
                'branch' => $employeeBranch,
                'date' => $date,
                'date_range' => $daterange,
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
                'marked_as' => (date('N', strtotime($date)) == 6) ? 'Weekend' : 'Absent',
                'methods' => '',
                'remarks' => ''
            ];

            if (isset($attendanceMap[$empid][$date])) {
                $attendance = $attendanceMap[$empid][$date];
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

                // Include methods used and manual reasons
                $row['methods'] = $attendance['methods_used'] ?? '';
                $row['remarks'] = $attendance['manual_reasons'] ?? '';
            }

            // Add the row to the data array
            $data[] = $row;
        }
    }

    // Encode data as JSON
    $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);
}
?>



<form id="jsonForm" action="monthly-report.php" method="post">
    <input type="hidden" name="jsonData" value='<?php echo htmlspecialchars($dataJson, ENT_QUOTES, "UTF-8"); ?>'>
    <input type="hidden" name="startdate" value="<?php echo $startDate; ?>">
    <input type="hidden" name="enddate" value="<?php echo $endDate; ?>">
</form>

<script>
    document.getElementById("jsonForm").submit();
</script>
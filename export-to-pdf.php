<?php
include("includes/db_connection.php");
require_once("plugins/tcpdf/tcpdf.php"); // Include TCPDF library from plugins folder

// Suppress warnings and errors to prevent output before PDF generation
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Debugging: Log received POST data
    file_put_contents('debug_log.txt', print_r($_POST, true), FILE_APPEND);

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

    $startDate = $startDateObj->format('Y-m-d');
    $endDate = $endDateObj->format('Y-m-d');

    // Fetch the branch
    $empBranch = $_POST['empBranch'] ?? '';

    // Validate the date inputs
    if (empty($startDate) || empty($endDate)) {
        echo json_encode(["error" => "Invalid date range (empty values)."]);
        exit;
    }

    // Validate branch filter
    if (!isset($_POST['empBranch']) || empty($_POST['empBranch'])) {
        echo json_encode(["error" => "Branch value not received."]);
        exit;
    }

    if (!empty($empBranch)) {
        $branch_check = $pdo->prepare("SELECT 1 FROM branches WHERE id = :empBranch LIMIT 1");
        $branch_check->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
        $branch_check->execute();
        if ($branch_check->fetchColumn() === false) {
            echo json_encode(["error" => "Invalid branch filter."]);
            exit;
        }
    }

    // Handle case where empBranch is empty (All Branches)
    if (empty($empBranch)) {
        $sqlEmployees = "SELECT 
                            e.emp_id, 
                            CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) AS employee_name, 
                            e.designation, 
                            b.name AS branch
                        FROM employees e
                        LEFT JOIN branches b ON e.branch = b.id";
    } else {
        $sqlEmployees = "SELECT 
                            e.emp_id, 
                            CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) AS employee_name, 
                            e.designation, 
                            b.name AS branch
                        FROM employees e
                        LEFT JOIN branches b ON e.branch = b.id
                        WHERE e.branch = :empBranch";
    }

    $stmtEmployees = $pdo->prepare($sqlEmployees);
    if (!empty($empBranch)) {
        $stmtEmployees->bindParam(':empBranch', $empBranch, PDO::PARAM_INT);
    }
    $stmtEmployees->execute();
    $employees = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);

    if (empty($employees)) {
        // Handle case where no employees are found
        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Write(0, 'No employee data found for the selected criteria.', '', 0, 'C', true, 0, false, false, 0);
        $pdf->Output('attendance_report.pdf', 'I');
        exit;
    }

    // Update attendance query to handle all branches
    $sqlAttendance = "SELECT
                        a.emp_Id,
                        a.date,
                        MIN(a.time) AS in_time,
                        MAX(a.time) AS out_time,
                        GROUP_CONCAT(a.method SEPARATOR ', ') AS methods_used,
                        GROUP_CONCAT(a.manual_reason SEPARATOR '; ') AS manual_reasons
                    FROM attendance_logs a
                    WHERE a.date BETWEEN :startDate AND :endDate";

    if (!empty($empBranch)) {
        $sqlAttendance .= " AND a.emp_Id IN (SELECT emp_id FROM employees WHERE branch = :empBranch)";
    }

    $sqlAttendance .= " GROUP BY a.emp_Id, a.date";

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
    foreach ($attendanceData as $att) {
        $attendanceMap[$att['emp_Id']][$att['date']] = $att;
    }

    $data = [];
    $scheduled_in = new DateTime('09:30');
    $scheduled_out = new DateTime('18:00');
    $working_hours = new DateInterval('PT8H30M');
    $formatted_working_hours = sprintf('%02d:%02d', $working_hours->h, $working_hours->i);

    foreach ($employees as $employee) {
        $empid = $employee['emp_id'];
        $employeeName = $employee['employee_name'];
        $designation = $employee['designation'];
        $employeeBranch = $employee['branch'];

        $period = new DatePeriod(
            new DateTime($startDate),
            new DateInterval('P1D'),
            (new DateTime($endDate))->modify('+1 day')
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
                $worked_duration = $in_time->diff($out_time);
                $row['worked_duration'] = ($out_time != $in_time) ? $worked_duration->format('%H:%I') : '';
                $total_minutes_worked = ($worked_duration->h * 60) + $worked_duration->i;
                $scheduled_minutes = ($working_hours->h * 60) + $working_hours->i;
                $overtime_minutes = max(0, $total_minutes_worked - $scheduled_minutes);
                $row['over_time'] = $overtime_minutes > 0 ? sprintf('%02d:%02d', floor($overtime_minutes / 60), $overtime_minutes % 60) : '';
                $late_in = $scheduled_in->diff($in_time);
                $row['late_in'] = ($in_time > $scheduled_in) ? $late_in->format('%H:%I') : '';
                $early_out = $out_time->diff($scheduled_out);
                $row['early_out'] = ($out_time < $scheduled_out) ? $early_out->format('%H:%I') : '';
                $early_in = $scheduled_in->diff($in_time);
                $row['early_in'] = ($in_time < $scheduled_in) ? $early_in->format('%H:%I') : '';
                $late_out = $scheduled_out->diff($out_time);
                $row['late_out'] = ($out_time > $scheduled_out) ? $late_out->format('%H:%I') : '';
                $row['marked_as'] = 'Present';
                $row['methods'] = $attendance['methods_used'] ?? '';
                $row['remarks'] = $attendance['manual_reasons'] ?? '';
            }

            $data[] = $row;
        }
    }

    // Set the PDF orientation to landscape and reduce the font size
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('HRMS');
    $pdf->SetTitle('Peridoic Attendance Report');
    // $pdf->SetHeaderData('', 0, 'Prime Express Courier & Cargo Pvt Ltd',''");
    $pdf->setHeaderFont(['helvetica', '', 8]); // Reduced header font size
    $pdf->setFooterFont(['helvetica', '', 8]); // Reduced footer font size
    $pdf->SetMargins(10, 10, 10); // Adjusted margins for landscape
    $pdf->SetAutoPageBreak(TRUE, 10); // 
    $pdf->SetFont('helvetica', '', 8); // Reduced overall font size

    // Reset SN for each employee
    foreach ($employees as $employee) {
        $empid = $employee['emp_id'];
        $employeeName = $employee['employee_name'];
        $designation = $employee['designation'];
        $employeeBranch = $employee['branch'];

        // Initialize the SN variable before the loop
        $sn = 1; // Start serial number from 1 for each employee

        $pdf->AddPage(); // Start a new page for each user

        // Replace Bootstrap classes with inline styles for TCPDF compatibility
        $html .= '<table border="1" cellpadding="2" style="width: 100%; font-size: 8px;">';
        $html .= '<thead>
                    <tr>
                        <th style="text-align: center; font-size: 12px;" colspan="10"><strong>Prime Express Courier & Cargo Pvt Ltd</strong></th>
                    </tr>
                    <tr>
                        <th style="text-align: center;" colspan="10"><strong>Monthly Attendance Report</strong></th>
                    </tr>
                    <tr>
                        <th style="text-align: center; width: 8%;"><strong>Emp. ID: ' . htmlspecialchars($empid) . '</strong></th>
                        <th style="text-align: left; width: 17%;"><strong>Name: ' . htmlspecialchars($employeeName) . '</strong></th>
                        <th style="text-align: center; width: 20%;"><strong>Designation: ' . htmlspecialchars($designation) . '</strong></th>
                        <th style="text-align: center; width: 20%;"><strong>Report Date: ' . htmlspecialchars($daterange) . '</strong></th>
                        <th style="text-align: left; width: 35%;"><strong>Branch: ' . htmlspecialchars($employeeBranch) . '</strong></th>
                    </tr>
                    <tr>
                        <th style="text-align: center; width: 18%;"></th>
                        <th style="text-align: center; width: 10%;">Planned Time</th>
                        <th style="text-align: center; width: 10%;">Worked Time</th>
                        <th style="text-align: center; width: 62%;"></th>
                    </tr>
                    <tr>
                        <th style="text-align: center; width: 3%;">SN</th>
                        <th style="text-align: center; width: 15%;">Date</th>
                        <th class="text-align: center; width: 5%;">In</th>
                        <th class="text-align: center; width: 5%;">Out</th>
                        <th class="text-align: center; width: 5%;">Work hrs</th>
                        <th style="text-align: center; width: 5%;">In</th>
                        <th style="text-align: center; width: 5%;">Out</th>
                        <th style="text-align: center; width: 5%;">Work hrs</th>
                        <th style="text-align: center; width: 5%;">Overtime</th>
                        <th style="text-align: center; width: 5%;">Late In</th>
                        <th style="text-align: center; width: 5%;">Early Out</th>
                        <th style="text-align: center; width: 10%;">Marked As</th>
                        <th style="text-align: center; width: 5%;">Methods</th>
                        <th style="text-align: center; width: 10%;">Remarks</th></strong>
                    </tr>
                </thead>
                <tbody>';

        // Fix the filtering logic for userData to ensure it only includes data for the current employee
        $userData = array_filter($data, function($row) use ($empid) {
            return $row['emp_id'] === $empid; // Ensure strict comparison is used for accurate filtering
        });

        // Populate planned in, out, and total hours data
        foreach ($userData as &$row) {
            $row['planned_in'] = $scheduled_in->format('H:i');
            $row['planned_out'] = $scheduled_out->format('H:i');
            $row['planned_hours'] = $formatted_working_hours;
        }

        // Calculate footer summary data for each employee
        $present = $absent = $weekend = $holiday = $paidLeave = $unpaidLeave = $missed = $manual = 0;

        foreach ($userData as $row) {
            switch ($row['marked_as']) {
                case 'Present':
                    $present++;
                    break;
                case 'Absent':
                    $absent++;
                    break;
                case 'Weekend':
                    $weekend++;
                    break;
                case 'Holiday':
                    $holiday++;
                    break;
                case 'Paid Leave':
                    $paidLeave++;
                    break;
                case 'Unpaid Leave':
                    $unpaidLeave++;
                    break;
                case 'Missed':
                    $missed++;
                    break;
                case 'Manual':
                    $manual++;
                    break;
            }
        }

        // Ensure the table body rows match the colspan structure of the thead and tfoot
        // $html .= '<tr>
        //             <td style="text-align: center;" colspan="16">No data available for this employee.</td>
        //           </tr>';

        // Update the existing rows to match the colspan structure
        foreach ($userData as $row) {
            $html .= '<tr>
                        <td style="text-align: center; width: 3%;" colspan="1">' . $sn++ . '</td>
                        <td style="text-align: left; width: 15%;" colspan="1">' . htmlspecialchars(date("Y-m-d, l", strtotime($row['date']))) . '</td>
                        <td class="text-align: center; width: 5%;" colspan="1">' . htmlspecialchars($row['scheduled_in']) . '</td>
                        <td class="text-align: center; width: 5%;" colspan="1">' . htmlspecialchars($row['scheduled_out']) . '</td>
                        <td class="text-align: center; width: 5%;" colspan="1">' . htmlspecialchars($row['working_hour']) . '</td>
                        <td style="text-align: center; width: 5%;" colspan="1">' . htmlspecialchars($row['in_time']) . '</td>
                        <td style="text-align: center; width: 5%;" colspan="1">' . htmlspecialchars($row['out_time']) . '</td>
                        <td style="text-align: center; width: 5%;" colspan="1">' . htmlspecialchars($row['worked_duration']) . '</td>
                        <td style="text-align: center; width: 5%;" colspan="1">' . htmlspecialchars($row['over_time']) . '</td>
                        <td style="text-align: center; width: 5%;" colspan="1">' . htmlspecialchars($row['late_in']) . '</td>
                        <td style="text-align: center; width: 5%;" colspan="1">' . htmlspecialchars($row['early_out']) . '</td>
                        <td style="text-align: center; width: 10%;" colspan="1">' . htmlspecialchars($row['marked_as']) . '</td>
                        <td style="text-align: center; width: 5%;" colspan="1">' . htmlspecialchars($row['methods']) . '</td>
                        <td style="text-align: center; width: 10%" colspan="1">' . htmlspecialchars($row['remarks']) . '</td>
                      </tr>';
        }

        // Add footer to the table for summary
        $html .= '</tbody>
        <tfoot>
        <tr>
           <th style="text-align: right;" colspan="1"></th>
           <th style="text-align: right;" colspan="1">Summary</th>
           <th style="text-align: center;" colspan="1">Present: ' . htmlspecialchars($present) . '</th>
           <th style="text-align: center;" colspan="1">Absent: ' . htmlspecialchars($absent) . '</th>
           <th style="text-align: center;" colspan="1">Weekend: ' . htmlspecialchars($weekend) . '</th>
           <th style="text-align: center;" colspan="1">Holiday: ' . htmlspecialchars($holiday) . '</th>
           <th style="text-align: center;" colspan="1">Paid Leave: ' . htmlspecialchars($paidLeave) . '</th>
           <th style="text-align: center;" colspan="1">Unpaid Leave: ' . htmlspecialchars($unpaidLeave) . '</th>
           <th style="text-align: center;" colspan="1">Missed: ' . htmlspecialchars($missed) . '</th>
           <th style="text-align: center;" colspan="1">Manual: ' . htmlspecialchars($manual) . '</th>
        </tr>
        </tfoot>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');
    }

    $pdf->Output('attendance_report.pdf', 'I');
}
?>
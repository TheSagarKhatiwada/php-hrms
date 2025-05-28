<?php
/**
 * Periodic Attendance Report Template
 * Reusable component for consistent display between web and exports
 */

// Helper functions used by the template
function timeToSeconds($time) {
    if (empty($time) || !str_contains($time, ':')) return 0;
    list($h, $m, $s) = explode(':', $time);
    return ($h * 3600) + ($m * 60) + $s;
}

function secondsToTime($seconds) {
    if ($seconds <= 0) return '00:00:00';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

/**
 * Renders an employee's periodic attendance table
 * 
 * @param array $employee Employee data with pre-calculated summaries
 * @param string $dateRange Formatted date range string
 * @param bool $forPDF Whether this is being rendered for PDF export
 * @return string HTML content of the table
 */
function renderMonthlyAttendanceTable($employee, $dateRange, $forPDF = false) {
    $tableClasses = $forPDF ? 'report-table' : 'table table-sm table-bordered table-striped periodic-report-table display nowrap';
    $tableStyles = 'width:100%';
    
    ob_start();
    ?>
    <table class="<?= $tableClasses ?>" style="<?= $tableStyles ?>" id="emp-<?= $employee['id'] ?>">
        <thead class="<?= $forPDF ? '' : 'table-light' ?>">
            <tr>
                <th class="text-center <?= $forPDF ? '' : 'fs-5' ?>" colspan="16">Prime Express Courier & Cargo Pvt Ltd</th>
            </tr>
            <tr>
                <th class="text-center <?= $forPDF ? '' : 'fs-6' ?>" colspan="16">Periodic Attendance Report</th>
            </tr>
            <tr>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>" colspan="2">Emp. ID: <?= htmlspecialchars($employee['id']) ?></th>
                <th class="text-start <?= $forPDF ? '' : 'small' ?>" colspan="3">Name: <?= htmlspecialchars($employee['name']) ?></th>
                <th class="text-start <?= $forPDF ? '' : 'small' ?>" colspan="4">Designation: <?= htmlspecialchars($employee['designation']) ?></th>
                <th class="text-start <?= $forPDF ? '' : 'small' ?>" colspan="4">Report Period: <?= htmlspecialchars($dateRange) ?></th>
                <th class="text-start <?= $forPDF ? '' : 'small' ?>" colspan="3">Branch: <?= htmlspecialchars($employee['branch']) ?></th>
            </tr>
            <tr>
                <th class="text-center <?= $forPDF ? '' : 'small align-middle' ?>" rowspan="2">SN</th>
                <th class="text-center <?= $forPDF ? '' : 'small align-middle' ?>" rowspan="2">Date</th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>" colspan="3">Planned Time</th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>" colspan="3">Worked Time</th>
                <th class="text-center <?= $forPDF ? '' : 'small align-middle' ?>" rowspan="2">Overtime</th>
                <th class="text-center <?= $forPDF ? '' : 'small align-middle' ?>" rowspan="2">Late In</th>
                <th class="text-center <?= $forPDF ? '' : 'small align-middle' ?>" rowspan="2">Early Out</th>
                <th class="text-center <?= $forPDF ? '' : 'small align-middle' ?>" rowspan="2">Early In</th>
                <th class="text-center <?= $forPDF ? '' : 'small align-middle' ?>" rowspan="2">Late Out</th>
                <th class="text-center <?= $forPDF ? '' : 'small align-middle' ?>" rowspan="2">Marked As</th>
                <th class="text-center <?= $forPDF ? '' : 'small align-middle' ?>" rowspan="2">Methods</th>
                <th class="text-center <?= $forPDF ? '' : 'small align-middle' ?>" rowspan="2">Remarks</th>
            </tr>
            <tr>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">In</th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">Out</th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">Work Hrs</th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">In</th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">Out</th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">Actual</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employee['daily_records'] as $index => $record): ?>
                <tr>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= $index + 1 ?></td>
                    <td class="text-start <?= $forPDF ? '' : 'small' ?>"><?= date("d-M-Y, D", strtotime($record['date'])) ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['scheduled_in']) ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['scheduled_out']) ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['working_hour']) ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['in_time'] ?: '--') ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['out_time'] ?: '--') ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['worked_duration'] ?: '--') ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['over_time'] ?: '--') ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['late_in'] ?: '--') ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['early_out'] ?: '--') ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['early_in'] ?: '--') ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['late_out'] ?: '--') ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['marked_as']) ?></td>
                    <td class="text-center <?= $forPDF ? '' : 'small' ?>">
                    <?php
                        if (!empty($record['methods'])) {
                            $methods = explode(',', $record['methods']);
                            $output = [];
                            foreach ($methods as $method) {
                                $output[] = ($method == '1') ? 'M' : 'A'; // Manual vs Auto
                            }
                            echo implode(' | ', $output);
                        }
                    ?>
                    </td>
                    <td class="text-start <?= $forPDF ? '' : 'small' ?>"><?= htmlspecialchars($record['remarks'] ?: '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="<?= $forPDF ? '' : 'table-light' ?>">
            <tr>
                <th class="text-end <?= $forPDF ? '' : 'small' ?>" colspan="2">Summary:</th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>" colspan="2">Scheduled: <?= $employee['summary']['formatted_scheduled_time'] ?></th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>" colspan="2">Worked: <?= $employee['summary']['formatted_worked_time'] ?></th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>" colspan="2">Overtime: <?= $employee['summary']['formatted_overtime'] ?></th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">Present: <?= $employee['summary']['present'] ?></th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">Absent: <?= $employee['summary']['absent'] ?></th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">Weekend: <?= $employee['summary']['weekend'] ?></th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">Holiday: <?= $employee['summary']['holiday'] ?></th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">P.Leave: <?= $employee['summary']['paid_leave'] ?></th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">U.Leave: <?= $employee['summary']['unpaid_leave'] ?></th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">Missed: <?= $employee['summary']['missed'] ?></th>
                <th class="text-center <?= $forPDF ? '' : 'small' ?>">Manual: <?= $employee['summary']['manual'] ?></th>
            </tr>
        </tfoot>
    </table>
    <?php
    return ob_get_clean();
}

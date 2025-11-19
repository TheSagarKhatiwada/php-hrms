<?php
// Test script to verify schedule overrides in report generation
define('INCLUDE_CHECK', true);
require_once 'includes/config.php';
require_once 'includes/db_connection.php';

// Create a test schedule override for employee 101 
$stmt = $pdo->prepare("INSERT INTO employee_schedule_overrides 
  (emp_id, start_date, end_date, work_start_time, work_end_time, reason, created_by)
  VALUES (?, ?, ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE 
  work_start_time = VALUES(work_start_time),
  work_end_time = VALUES(work_end_time),
  reason = VALUES(reason)");

// Set an override for 2025-10-29
$stmt->execute([
  101,
  '2025-10-29',
  '2025-10-29',
  '09:30:00',
  '18:00:00',
  'Test override schedule',
  'Test Script'
]); 

// Setup simulated API environment 
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SESSION['user_id'] = 1;
$_SESSION['fullName'] = 'Test User';

// Set up test data
$data = [
  'report_type' => 'daily',
  'date' => '2025-10-29',
  'branch' => '',  // all branches
  'employees' => '101'  // specific employee
];

// Store POST data in superglobal
$_POST = $data;

// Run report generation directly
require_once __DIR__.'/includes/session_config.php';
require_once __DIR__.'/includes/db_connection.php'; 
require_once __DIR__.'/includes/utilities.php';
require_once __DIR__.'/includes/settings.php';
require_once __DIR__.'/includes/schedule_helpers.php';
require_once __DIR__.'/plugins/TCPDF/tcpdf.php';

function ar_debug($msg){
  static $logFile = null; if($logFile===null){ $root=realpath(__DIR__); $logFile=$root?($root.'/logs/attendance_reports_debug.log'):__DIR__.'/logs/attendance_reports_debug.log'; }
  $line = date('Y-m-d H:i:s')."\t".($msg)."\n"; @file_put_contents($logFile,$line,FILE_APPEND|LOCK_EX);
}


// Generate report
$raw = json_encode($data);
$data = json_decode($raw, true);

$reportType = $data['report_type'] ?? 'daily';
$branch = trim((string)($data['branch'] ?? ''));
$employees = $data['employees'] ?? '*';

$dateLabel = '';
$startDate = $endDate = null;
if($reportType === 'daily') {
  $d = $data['date'] ?? '';
  if(preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $d, $m)) {
    $startDate = $endDate = $d;
    $dateLabel = $m[3].'/'.$m[2].'/'.$m[1];
  } elseif(preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $d, $m2)) {
    $startDate = $endDate = $m2[3].'-'.$m2[2].'-'.$m2[1];
    $dateLabel = $d;
  } else {
    die("Invalid date");
  }
}

// Normalize employees list
$employeeIds = [];
if($employees !== '*' && $employees !== '') {
  $employeeIds = array_filter(array_map('trim', explode(',', $employees)));
}

$branchLabel = 'All Branches';
if($branch !== '') {
  try {
    $stmt = $pdo->prepare('SELECT name FROM branches WHERE id = ?');
    $stmt->execute([$branch]);
    if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $branchLabel = $row['name'];
    }
  } catch(Exception $e) {}
}

// Determine report type label
$typeLabelMap = [
  'daily' => 'Daily Attendance',
  'periodic' => 'Periodic Attendance',
  'timesheet' => 'Time Sheet'
];
$typeLabel = $typeLabelMap[$reportType] ?? ucfirst($reportType);

// Build employees label
$employeesLabel = 'All';
if(!empty($employeeIds)) {
  $in = str_repeat('?,', count($employeeIds)-1) . '?';
  try {
    $stmt = $pdo->prepare("SELECT emp_id, first_name FROM employees WHERE emp_id IN ($in)");
    $stmt->execute($employeeIds);
    $nameMap = [];
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $nameMap[$r['emp_id']] = trim($r['first_name']);
    }
    $names = [];
    foreach($employeeIds as $id){
      $names[] = $nameMap[$id] ?? $id;
    }
    if(count($names) > 15) {
      $shown = array_slice($names,0,15);
      $employeesLabel = implode(', ', $shown).' +'.(count($names)-15).' more';
    } else {
      $employeesLabel = implode(', ', $names);
    }
  } catch(Exception $e) {
    $employeesLabel = implode(', ', $employeeIds);
  }
}

// Generate PDF using existing daily-report logic for daily only (placeholder for others)
if($reportType === 'daily') {
  try {
    // 1. Build dataset
    require_once __DIR__.'/includes/reason_helpers.php';
    // Use existing variables: $startDate (Y-m-d), $branch
    $reportdate = $startDate; // single day
    
    // Fetch employees (include exited employees active that day) honoring selections
    $empConds = [];
    $empParams = [];
    if($branch !== '') {
      $empConds[] = 'e.branch = ?';
      $empParams[] = $branch;
    }
    $empConds[] = '(e.exit_date IS NULL OR e.exit_date >= ?)';
    $empParams[] = $reportdate;
    if(!empty($employeeIds)) {
      $empConds[] = 'e.emp_id IN ('.implode(',', array_fill(0,count($employeeIds),'?')).')';
      $empParams = array_merge($empParams, $employeeIds);
    }
    $sqlEmployees = "SELECT e.emp_id, CONCAT(e.first_name,' ', e.middle_name,' ', e.last_name) AS employee_name, b.name AS branch, e.exit_date, e.branch AS branch_id, e.work_start_time, e.work_end_time FROM employees e LEFT JOIN branches b ON e.branch = b.id";
    if($empConds) {
      $sqlEmployees .= ' WHERE '.implode(' AND ', $empConds);
    }
    $stmtEmp = $pdo->prepare($sqlEmployees);
    $stmtEmp->execute($empParams);
    $employeesData = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

    // Prefetch overrides for the employees in this dataset for the report date
    $empIdsForPrefetch = array_map(function($r){ return $r['emp_id']; }, $employeesData);
    $overridesMap = prefetch_schedule_overrides($pdo, $empIdsForPrefetch, $reportdate, $reportdate);

    // Attendance logs map
    $sqlAtt = "SELECT a.emp_Id, MIN(a.time) AS in_time, MAX(a.time) AS out_time, GROUP_CONCAT(a.method ORDER BY a.time ASC SEPARATOR ', ') AS methods_used, GROUP_CONCAT(a.manual_reason ORDER BY a.time ASC SEPARATOR '; ') AS manual_reasons, COUNT(a.id) AS punch_count FROM attendance_logs a WHERE a.date = ?";
    $attParams = [$reportdate];
    if(!empty($employeeIds)) {
      $sqlAtt .= ' AND a.emp_Id IN ('.implode(',', array_fill(0,count($employeeIds),'?')).')';
      $attParams = array_merge($attParams, $employeeIds);
    }
    if($branch !== '') {
      $sqlAtt .= ' AND a.emp_Id IN (SELECT emp_id FROM employees WHERE branch = ?)';
      $attParams[] = $branch;
    }
    $sqlAtt .= ' GROUP BY a.emp_Id';
    $stmtAtt = $pdo->prepare($sqlAtt);
    $stmtAtt->execute($attParams);
    $attendanceMap = [];
    foreach($stmtAtt->fetchAll(PDO::FETCH_ASSOC) as $att){
      $attendanceMap[$att['emp_Id']] = $att;
    }

    // Defaults (fallback when employee-specific times are not set)
    $default_work_start = get_setting('work_start_time', '09:00');
    $default_work_end = get_setting('work_end_time', '18:00');
    // Working hours default interval will be computed per-employee from start/end when available
    $formatted_working_hours = sprintf('%02d:%02d', 8, 30); // fallback display
    
    $dataRows = [];
    foreach($employeesData as $emp){
      $empid = $emp['emp_id'];
      $isExited = (!empty($emp['exit_date']) && $reportdate > $emp['exit_date']);
      $holiday = is_holiday($reportdate, $emp['branch_id'] ?? null);
      $isHoliday = $holiday !== false;
      $isWeekend = (date('N', strtotime($reportdate)) == 6);

      // Determine scheduled times per employee
      $emp_work_start = !empty($emp['work_start_time']) ? $emp['work_start_time'] : $default_work_start;
      $emp_work_end = !empty($emp['work_end_time']) ? $emp['work_end_time'] : $default_work_end;
      try {
        $scheduled_in = new DateTime($emp_work_start);
        $scheduled_out = new DateTime($emp_work_end);
        $working_interval = $scheduled_in->diff($scheduled_out);
        $formatted_working_hours = sprintf('%02d:%02d', $working_interval->h, $working_interval->i);
      } catch (Exception $e) {
        // fallback to default times
        $scheduled_in = new DateTime($default_work_start);
        $scheduled_out = new DateTime($default_work_end);
        $working_interval = $scheduled_in->diff($scheduled_out);
        $formatted_working_hours = sprintf('%02d:%02d', $working_interval->h, $working_interval->i);
      }

      // Check for schedule override
      $empOverrides = $overridesMap[$empid] ?? [];
      $sched = resolve_schedule_for_emp_date($emp, $reportdate, $empOverrides, $default_work_start, $default_work_end);
      try {
        $scheduled_in = new DateTime($sched['start']);
        $scheduled_out = new DateTime($sched['end']); 
        $working_interval = $scheduled_in->diff($scheduled_out);
        $formatted_working_hours = sprintf('%02d:%02d', $working_interval->h, $working_interval->i);
      } catch(Exception $e){
        // Keep existing scheduled times on error
      }

      $row = [
        'emp_id'=>$empid,
        'employee_name'=>$emp['employee_name'],
        'branch'=>$emp['branch'],
        'report_date'=>$reportdate,
        'scheduled_in'=>($isWeekend||$isHoliday)?'-':$scheduled_in->format('H:i'),
        'scheduled_out'=>($isWeekend||$isHoliday)?'-':$scheduled_out->format('H:i'),
        'working_hour'=>($isWeekend||$isHoliday)?'-':$formatted_working_hours,
        'in_time'=>'','out_time'=>'','worked_duration'=>'','over_time'=>'','late_in'=>'','early_out'=>'','early_in'=>'','late_out'=>'',
        'marked_as'=>$isExited?'Exited':($isHoliday?'Holiday':($isWeekend?'Weekend':'Absent')),
        'methods'=>'','remarks'=>$isExited?('Employee exited on '.$emp['exit_date']):($isHoliday?$holiday['name']:'')
      ];

      if(isset($attendanceMap[$empid]) && !$isExited){
        $att = $attendanceMap[$empid];
        $in_time = new DateTime($att['in_time']);
        $out_time = new DateTime($att['out_time']);
        $row['in_time'] = $in_time->format('H:i');
        $row['out_time'] = ($out_time != $in_time) ? $out_time->format('H:i') : '';
        $worked = $in_time->diff($out_time);
        if($out_time != $in_time) {
          $row['worked_duration'] = $worked->format('%H:%I');
        }
        $total_minutes = ($worked->h*60)+$worked->i;
        $scheduled_minutes = ($working_interval->h*60)+$working_interval->i;
        $overtime_minutes = ($isHoliday||$isWeekend)?$total_minutes:max(0,$total_minutes-$scheduled_minutes);
        $row['over_time'] = $overtime_minutes>0 ? sprintf('%02d:%02d', floor($overtime_minutes/60), $overtime_minutes%60):'';
        if(!$isWeekend && !$isHoliday){
          $row['late_in'] = ($in_time > $scheduled_in)?$scheduled_in->diff($in_time)->format('%H:%I'):'';
          if($out_time != $in_time){
            $row['early_out'] = ($out_time < $scheduled_out)?$out_time->diff($scheduled_out)->format('%H:%I'):'';
          }
          $row['early_in'] = ($in_time < $scheduled_in)?$in_time->diff($scheduled_in)->format('%H:%I'):'';
          $row['late_out'] = ($out_time > $scheduled_out)?$scheduled_out->diff($out_time)->format('%H:%I'):'';
        }
        $methodsArray = explode(', ', $att['methods_used'] ?? '');
        $reasonsArray = explode('; ', $att['manual_reasons'] ?? '');
        $punchCount = $att['punch_count'] ?? 1;
        $methodMap=['0'=>'A','1'=>'M','2'=>'W'];
        $firstMethod = $methodsArray[0] ?? '';
        $inMethodLetter = isset($methodMap[$firstMethod]) ? $methodMap[$firstMethod] : $firstMethod;
        $outMethodLetter='';
        if($punchCount>1){
          $lastMethod=end($methodsArray);
          $outMethodLetter = isset($methodMap[$lastMethod])?$methodMap[$lastMethod]:$lastMethod;
        }
        $inReasonRaw=$reasonsArray[0]??'';
        $outReasonRaw='';
        if($punchCount>1){
          $outReasonRaw=end($reasonsArray);
        }
        if(function_exists('hrms_format_reason_for_report')){
          $inReason=hrms_format_reason_for_report($inReasonRaw);
          $outReason=hrms_format_reason_for_report($outReasonRaw);
        } else {
          $inReason=$inReasonRaw;
          $outReason=$outReasonRaw;
        }
        $row['methods']=$inMethodLetter.($outMethodLetter?" | ".$outMethodLetter:'');
        // Build remarks only for the entries that actually have text and avoid duplicates
        $remarkParts = array_filter([trim($inReason), trim($outReason)], function($v){ return $v !== null && $v !== ''; });
        $remarkParts = array_values(array_unique($remarkParts));
        $row['remarks'] = implode(' | ', $remarkParts);
        if($isHoliday){
          $row['marked_as']='Present (Holiday)';
          $row['remarks']=($holiday['name']??'Holiday').' - Worked as OT';
        } elseif($isWeekend){
          $row['marked_as']='Present (Weekend)';
          $row['remarks']='Weekend - Worked as OT';
        } else {
          $row['marked_as']='Present';
        }
      } elseif($isHoliday && !$isExited) {
        $row['in_time']='-';$row['out_time']='-';$row['worked_duration']='-';$row['over_time']='-';
        $row['late_in']='-';$row['early_out']='-';$row['early_in']='-';$row['late_out']='-';$row['methods']='-';
      }
      $dataRows[]=$row;
    }

    // Show the first row so we can verify overrides are working
    print_r($dataRows[0]);

  } catch(Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
  }
}
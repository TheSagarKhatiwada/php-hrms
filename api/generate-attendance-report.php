<?php
// API to generate attendance reports (daily / periodic) and store metadata in session
// Use unified session configuration to ensure same session name (HRMS_SESSION)
require_once __DIR__.'/../includes/session_config.php';
header('Content-Type: application/json');
require_once __DIR__.'/../includes/db_connection.php';
require_once __DIR__.'/../includes/utilities.php';
// Settings helper (defines get_setting(), save_setting(), etc.)
require_once __DIR__.'/../includes/settings.php';
// Schedule helpers: prefetch overrides & resolve schedule per employee/date
require_once __DIR__.'/../includes/schedule_helpers.php';

// Basic debug logging helper (silent if unwritable)
function ar_debug($msg){
  static $logFile = null; if($logFile===null){ $root=realpath(__DIR__.'/..'); $logFile=$root?($root.'/logs/attendance_reports_debug.log'):__DIR__.'/attendance_reports_debug.log'; }
  $line = date('Y-m-d H:i:s')."\t".($msg)."\n"; @file_put_contents($logFile,$line,FILE_APPEND|LOCK_EX);
}

// Increase limits for potentially large periodic PDFs
@ini_set('memory_limit','512M');
@set_time_limit(120);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if(!$data){ echo json_encode(['status'=>'error','message'=>'Invalid JSON']); exit; }

$reportType = $data['report_type'] ?? 'daily';
$branch = trim((string)($data['branch'] ?? ''));
$employees = $data['employees'] ?? '*';

// Authentication: standard session key is user_id (userName not set elsewhere)
if(!isset($_SESSION['user_id'])){ echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit; }

$isAdmin = function_exists('is_admin') ? is_admin() : false;
$canGenerateReports = $isAdmin || has_permission('generate_attendance_reports');
$canViewDailyReports = $isAdmin || has_permission('view_attendance_reports_daily');
$canViewPeriodicReports = $isAdmin || has_permission('view_attendance_reports_periodic');
$canViewTimesheetReports = $isAdmin || has_permission('view_attendance_reports_timesheet');
$canViewSensitiveAttendance = $isAdmin || has_permission('view_sensitive_attendance_metrics');
$allowedReportTypes = [];
if($canViewDailyReports){ $allowedReportTypes[] = 'daily'; }
if($canViewPeriodicReports){ $allowedReportTypes[] = 'periodic'; }
if($canViewTimesheetReports){ $allowedReportTypes[] = 'timesheet'; }

if(!$canGenerateReports || empty($allowedReportTypes)){
  http_response_code(403);
  echo json_encode(['status'=>'error','message'=>'You are not allowed to generate attendance reports.']);
  exit;
}

if(!in_array($reportType, $allowedReportTypes, true)){
  http_response_code(403);
  echo json_encode(['status'=>'error','message'=>'You are not allowed to use this report type.']);
  exit;
}

if($branch === '*' || strtolower($branch) === 'all'){ $branch = ''; }
if($branch !== '' && !ctype_digit($branch)){
  http_response_code(400);
  echo json_encode(['status'=>'error','message'=>'Invalid branch identifier.']);
  exit;
}

$canViewAllBranchAttendance = $isAdmin || has_permission('view_all_branch_attendance');
if(!$canViewAllBranchAttendance){
  $viewerBranch = hrms_get_user_branch_context($pdo, $_SESSION['user_id']);
  $assignedBranch = null;
  if($viewerBranch['legacy'] !== null){
    $assignedBranch = (string)$viewerBranch['legacy'];
  } elseif($viewerBranch['numeric'] !== null){
    $assignedBranch = (string)$viewerBranch['numeric'];
  }
  if($assignedBranch === null){
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Branch access is not configured for your account.']);
    exit;
  }
  if($branch === ''){
    $branch = $assignedBranch;
  } elseif((string)$branch !== (string)$assignedBranch){
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'You are not allowed to access this branch.']);
    exit;
  }
}

// Normalize employees list
$employeeIds = [];
if($employees !== '*' && $employees !== '') {
  $employeeIds = array_filter(array_map('trim', explode(',', $employees))); 
}

// Date handling
$dateLabel = '';
$startDate = $endDate = null;
if($reportType === 'daily') {
  $d = $data['date'] ?? '';
  if(preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $d, $m)) { // Y-m-d native input
    $startDate = $endDate = $d;
    $dateLabel = $m[3].'/'.$m[2].'/'.$m[1]; // display as DD/MM/YYYY
  } elseif(preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $d, $m2)) { // fallback DD/MM/YYYY
    $startDate = $endDate = $m2[3].'-'.$m2[2].'-'.$m2[1];
    $dateLabel = $d;
  } else { echo json_encode(['status'=>'error','message'=>'Invalid date']); exit; }
} else {
  $range = $data['range'] ?? '';
  if(!preg_match('~^(\d{2}/\d{2}/\d{4})\s*-\s*(\d{2}/\d{2}/\d{4})$~', $range, $m)) { echo json_encode(['status'=>'error','message'=>'Invalid date range']); exit; }
  $parts1 = explode('/', $m[1]);
  $parts2 = explode('/', $m[2]);
  $startDate = $parts1[2].'-'.$parts1[1].'-'.$parts1[0];
  $endDate = $parts2[2].'-'.$parts2[1].'-'.$parts2[0];
  $dateLabel = $m[1].' - '.$m[2];
}

// Fetch branch label
$branchLabel = 'All Branches';
if($branch !== '') {
  try {
    $stmt = $pdo->prepare('SELECT name FROM branches WHERE id = ?');
    $stmt->execute([$branch]);
    if($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $branchLabel = $row['name']; }
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
$employeesHidden = '';
if(!empty($employeeIds)) {
  $in  = str_repeat('?,', count($employeeIds)-1) . '?';
  try {
    // Fetch first names for provided emp_ids
    $stmt = $pdo->prepare("SELECT emp_id, first_name FROM employees WHERE emp_id IN ($in)");
    $stmt->execute($employeeIds);
    $nameMap = [];
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $nameMap[$r['emp_id']] = trim($r['first_name']); }
    $names = [];
    foreach($employeeIds as $id){ $names[] = $nameMap[$id] ?? $id; } // fallback to id if missing
    // If too many, compress
    if(count($names) > 15) {
      $shown = array_slice($names,0,15);
      $hiddenChunk = array_slice($names,15);
      $employeesHidden = implode(', ', $hiddenChunk);
      $employeesLabel = implode(', ', $shown).' +'.(count($names)-15).' more';
    } else {
      $employeesLabel = implode(', ', $names);
      $employeesHidden = '';
    }
  } catch(Exception $e) { $employeesLabel = implode(', ', $employeeIds); }
}

// Generate PDF using existing daily-report logic for daily only (placeholder for others)
// We'll call existing endpoint via include if daily; for periodic/time sheet future extension can be added.
if($reportType === 'daily') {
  // Integrated full daily dataset + PDF (mirrors modules/reports/api/fetch-daily-report-data.php & daily-report-pdf.php)
  try {
    // 1. Build dataset
    require_once __DIR__.'/../includes/reason_helpers.php';
    // Use existing variables: $startDate (Y-m-d), $branch
    $reportdate = $startDate; // single day
  // Fetch employees (include exited employees active that day) honoring selections
  $empConds = [];
  $empParams = [];
  if($branch !== '') { $empConds[] = 'e.branch = ?'; $empParams[] = $branch; }
  $empConds[] = '(e.mach_id_not_applicable IS NULL OR e.mach_id_not_applicable = 0)';
  $empConds[] = '(e.exit_date IS NULL OR e.exit_date >= ?)'; $empParams[] = $reportdate;
  if(!empty($employeeIds)) { $empConds[] = 'e.emp_id IN ('.implode(',', array_fill(0,count($employeeIds),'?')).')'; $empParams = array_merge($empParams, $employeeIds); }
  $sqlEmployees = "SELECT e.emp_id, CONCAT(e.first_name,' ', e.middle_name,' ', e.last_name) AS employee_name, b.name AS branch, e.exit_date, e.branch AS branch_id, e.work_start_time, e.work_end_time FROM employees e LEFT JOIN branches b ON e.branch = b.id";
  if($empConds) { $sqlEmployees .= ' WHERE '.implode(' AND ', $empConds); }
  $stmtEmp = $pdo->prepare($sqlEmployees);
  $stmtEmp->execute($empParams);
  $employeesData = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

  // Prefetch overrides for the employees in this dataset for the report date
  $empIdsForPrefetch = array_map(function($r){ return $r['emp_id']; }, $employeesData);
  $overridesMap = prefetch_schedule_overrides($pdo, $empIdsForPrefetch, $reportdate, $reportdate);

    // Attendance logs map
  $sqlAtt = "SELECT a.emp_Id, MIN(a.time) AS in_time, MAX(a.time) AS out_time, GROUP_CONCAT(a.method ORDER BY a.time ASC SEPARATOR ', ') AS methods_used, GROUP_CONCAT(a.manual_reason ORDER BY a.time ASC SEPARATOR '; ') AS manual_reasons, COUNT(a.id) AS punch_count FROM attendance_logs a WHERE a.date = ?";
  $attParams = [$reportdate];
  if(!empty($employeeIds)) { $sqlAtt .= ' AND a.emp_Id IN ('.implode(',', array_fill(0,count($employeeIds),'?')).')'; $attParams = array_merge($attParams, $employeeIds); }
  if($branch !== '') { $sqlAtt .= ' AND a.emp_Id IN (SELECT emp_id FROM employees WHERE branch = ? AND (mach_id_not_applicable IS NULL OR mach_id_not_applicable = 0))'; $attParams[] = $branch; }
  $sqlAtt .= ' GROUP BY a.emp_Id';
  $stmtAtt = $pdo->prepare($sqlAtt);
  $stmtAtt->execute($attParams);
    $attendanceMap = [];
    foreach($stmtAtt->fetchAll(PDO::FETCH_ASSOC) as $att){ $attendanceMap[$att['emp_Id']] = $att; }

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
      
      // Resolve schedule (check overrides first)
      $empOverrides = $overridesMap[$empid] ?? [];
      $sched = resolve_schedule_for_emp_date($emp, $reportdate, $empOverrides, $default_work_start, $default_work_end);
      
      try {
        $scheduled_in = new DateTime($sched['start']);
        $scheduled_out = new DateTime($sched['end']);
        $working_interval = $scheduled_in->diff($scheduled_out);
        $formatted_working_hours = sprintf('%02d:%02d', $working_interval->h, $working_interval->i);
      } catch (Exception $e) {
        // fallback to default times
        $scheduled_in = new DateTime($default_work_start);
        $scheduled_out = new DateTime($default_work_end);
        $working_interval = $scheduled_in->diff($scheduled_out);
        $formatted_working_hours = sprintf('%02d:%02d', $working_interval->h, $working_interval->i);
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
        'marked_as'=>$isExited?'Exited':($isHoliday?'Holiday':($isWeekend?'Weekend':'Absent')), 'methods'=>'','remarks'=>$isExited?('Employee exited on '.$emp['exit_date']):($isHoliday?$holiday['name']:'')
      ];
      if(isset($attendanceMap[$empid]) && !$isExited){
        $att = $attendanceMap[$empid];
        $in_time = new DateTime($att['in_time']);
        $out_time = new DateTime($att['out_time']);
        
        $row['in_time'] = $in_time->format('H:i');
        $row['out_time'] = ($out_time != $in_time) ? $out_time->format('H:i') : '';
        $worked = $in_time->diff($out_time);
        if($out_time != $in_time) { $row['worked_duration'] = $worked->format('%H:%I'); }
  $total_minutes = ($worked->h*60)+$worked->i; $scheduled_minutes = ($working_interval->h*60)+$working_interval->i;
        $overtime_minutes = ($isHoliday||$isWeekend)?$total_minutes:max(0,$total_minutes-$scheduled_minutes);
        $row['over_time'] = $overtime_minutes>0 ? sprintf('%02d:%02d', floor($overtime_minutes/60), $overtime_minutes%60):'';
        if(!$isWeekend && !$isHoliday){
          $row['late_in'] = ($in_time > $scheduled_in)?$scheduled_in->diff($in_time)->format('%H:%I'):'';
          if($out_time != $in_time){ $row['early_out'] = ($out_time < $scheduled_out)?$out_time->diff($scheduled_out)->format('%H:%I'):''; }
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
    if($punchCount>1){ $lastMethod=end($methodsArray); $outMethodLetter = isset($methodMap[$lastMethod])?$methodMap[$lastMethod]:$lastMethod; }
    $inReasonRaw=$reasonsArray[0]??''; $outReasonRaw=''; if($punchCount>1){ $outReasonRaw=end($reasonsArray); }
    if(function_exists('hrms_format_reason_for_report')){ $inReason=hrms_format_reason_for_report($inReasonRaw); $outReason=hrms_format_reason_for_report($outReasonRaw); } else { $inReason=$inReasonRaw; $outReason=$outReasonRaw; }
    $row['methods']=$inMethodLetter.($outMethodLetter?" | ".$outMethodLetter:'');
    // Build remarks only for the entries that actually have text and avoid duplicates
    $remarkParts = array_filter([trim($inReason), trim($outReason)], function($v){ return $v !== null && $v !== ''; });
    $remarkParts = array_values(array_unique($remarkParts));
    $row['remarks'] = implode(' | ', $remarkParts);
        if($isHoliday){ $row['marked_as']='Present (Holiday)'; $row['remarks']=($holiday['name']??'Holiday').' - Worked as OT'; }
        elseif($isWeekend){ $row['marked_as']='Present (Weekend)'; $row['remarks']='Weekend - Worked as OT'; }
        else { $row['marked_as']='Present'; }
      } elseif($isHoliday && !$isExited) {
        $row['in_time']='-';$row['out_time']='-';$row['worked_duration']='-';$row['over_time']='-';$row['late_in']='-';$row['early_out']='-';$row['early_in']='-';$row['late_out']='-';$row['methods']='-';
      }
      $dataRows[]=$row;
    }

    // 2. Generate PDF using same layout logic as existing daily-report-pdf.php
    require_once __DIR__.'/../plugins/TCPDF/tcpdf.php';
    class GenDailyPDF extends TCPDF {}
    $pdf = new GenDailyPDF('L','mm','A4');
    $pdf->SetCreator('HRMS');
    $pdf->SetAuthor($_SESSION['fullName'] ?? 'HRMS');
    $pdf->SetTitle('Daily Attendance Report');
    $pdf->SetMargins(8,20,8); $pdf->SetAutoPageBreak(true,12); $pdf->AddPage();
    // Attempt to place logo (same style as legacy daily PDF). Adjust X/width if needed.
    $logoPath = realpath(__DIR__.'/../resources/logo.png');
    if($logoPath && file_exists($logoPath)) {
      // Compute a width and right-align the logo so its right edge matches table/content boundary.
      $logoWidth = 50; // mm (adjust if needed)
      // If you want to auto-scale by height, uncomment below and adjust $targetHeight
      // list($imgWpx,$imgHpx) = @getimagesize($logoPath) ?: [0,0];
      // if($imgWpx && $imgHpx){ $targetHeight = 18; $logoWidth = ($imgWpx/$imgHpx) * $targetHeight; }
      $pageWidth = $pdf->getPageWidth();
      $rightMargin = $pdf->getMargins()['right'];
      $x = $pageWidth - $rightMargin - $logoWidth; // right-aligned inside printable area
      $pdf->Image($logoPath, $x, 6, $logoWidth);
    }
    $pdf->SetFont('helvetica','B',14); $pdf->Cell(0,8,'Daily Attendance Report',0,1,'C');
    $pdf->SetFont('helvetica','',10); $pdf->Cell(0,6,'Date: '.$dateLabel.'   Branch: '.$branchLabel,0,1,'C'); $pdf->Ln(2);
    $headers = ['#','Employee','In','Out','Total','In','Out','Worked','OT','LateIn','EarlyOut','EarlyIn','LateOut','Status','Method','Remarks'];
    $rel=[7,55,14,14,14,14,14,16,14,14,16,14,14,24,20,34];
    $pageWidth = $pdf->getPageWidth()-$pdf->getMargins()['left']-$pdf->getMargins()['right']; $relSum=array_sum($rel); $widths=[]; foreach($rel as $r){ $widths[]=round($pageWidth*($r/$relSum),2);}    
    // Group header
    $pdf->SetFont('helvetica','B',9);
    $pdf->Cell($widths[0],7,'',1,0,'C');
    $pdf->Cell($widths[1],7,'',1,0,'C');
    $pdf->Cell($widths[2]+$widths[3]+$widths[4],7,'Planned Schedule',1,0,'C');
    $pdf->Cell($widths[5]+$widths[6]+$widths[7]+$widths[8],7,'Actual Attendance',1,0,'C');
    $pdf->Cell($widths[9]+$widths[10]+$widths[11]+$widths[12],7,'Deviations',1,0,'C');
    $pdf->Cell($widths[13]+$widths[14]+$widths[15],7,'Status Summary',1,0,'C'); $pdf->Ln();
    foreach($headers as $i=>$h){ $pdf->Cell($widths[$i],6,$h,1,0,'C'); } $pdf->Ln();
    $pdf->SetFont('helvetica','',9); $sn=1;
    foreach($dataRows as $r){
      $line=[ $sn++, $r['emp_id'].' - '.$r['employee_name'], $r['scheduled_in'],$r['scheduled_out'],$r['working_hour'],$r['in_time'],$r['out_time'],$r['worked_duration'],$r['over_time'],$r['late_in'],$r['early_out'],$r['early_in'],$r['late_out'],$r['marked_as'], strip_tags($r['methods']), strip_tags($r['remarks']) ];
      if(!$canViewSensitiveAttendance){
        $line[7]=$line[8]=$line[9]=$line[10]=$line[11]=$line[12]='--';
        $line[14] = trim((string)$line[14]) !== '' ? 'Restricted' : '--';
        $line[15] = trim((string)$line[15]) !== '' ? 'Restricted' : '--';
      }
      $prepared=[];$heights=[]; foreach($line as $i=>$val){ $text=(string)$val; if($i==1){ $text=wordwrap($text,50,"\n",true);} $prepared[$i]=$text; $heights[$i]=$pdf->getStringHeight($widths[$i],$text); }
      $rowHeight=max($heights); if($rowHeight<5.2)$rowHeight=5.2; $y=$pdf->GetY(); $x=$pdf->GetX();
      foreach($prepared as $i=>$text){ $align=($i==1)?'L':'C'; $pdf->MultiCell($widths[$i],$rowHeight,$text,1,$align,false,0); $x+=$widths[$i]; $pdf->SetXY($x,$y);} $pdf->SetY($y+$rowHeight);
    }
    // Summary counts
    $present=$absent=$leave=0; foreach($dataRows as $r){ if(strpos($r['marked_as'],'Present')!==false)$present++; elseif($r['marked_as']=='Absent')$absent++; elseif($r['marked_as']=='Leave')$leave++; }
    $pdf->SetFont('helvetica','B',10); $footerHeight=6; $pdf->Cell($widths[0]+$widths[1],$footerHeight,'Summary',1,0,'R');
    $pdf->Cell($widths[2]+$widths[3]+$widths[4],$footerHeight,'Total Employees: '.count($dataRows),1,0,'C');
    $pdf->Cell($widths[5]+$widths[6]+$widths[7]+$widths[8],$footerHeight,'Total Present: '.$present,1,0,'C');
    $pdf->Cell($widths[9]+$widths[10],$footerHeight,'Absent: '.$absent,1,0,'C');
    $pdf->Cell($widths[11]+$widths[12],$footerHeight,'On Leave: '.$leave,1,0,'C');
    $pdf->Cell($widths[13]+$widths[14]+$widths[15],$footerHeight,'',1,0,'C'); $pdf->Ln();
    $pdf->Ln(1); $pdf->SetFont('helvetica','',8);
    $timezone='UTC'; $tzStmt=$pdo->prepare("SELECT value FROM settings WHERE setting_key = 'timezone'"); if($tzStmt && $tzStmt->execute() && ($res=$tzStmt->fetch(PDO::FETCH_ASSOC))){ $timezone=$res['value']; }
    date_default_timezone_set($timezone); $generatedAt=date('Y-m-d h:i A'); $pdf->Cell(0,5,'Generated on: '.$generatedAt,0,1,'R');

    // Persist file
    $rootPath = realpath(__DIR__.'/../');
    $outDir = $rootPath.'/uploads/reports/daily-temp'; if(!is_dir($outDir)) { @mkdir($outDir,0755,true);} if(!is_writable($outDir)) { $outDir = sys_get_temp_dir(); }
    // cleanup old
  // Cleanup: remove daily report PDFs older than 30 days (was 6 hours)
  if(is_dir($outDir)) { $nowTs=time(); $ttl=2592000; foreach(glob($outDir.'/daily_report_*.pdf') as $old){ if(is_file($old) && ($nowTs-filemtime($old))>$ttl){ @unlink($old); } } }
    $safeBranch=preg_replace('/[^A-Za-z0-9_-]+/','_', $branchLabel);
    $fileName='daily_report_'.$reportdate.'_'.$safeBranch.'_'.uniqid().'.pdf';
    $filePath=rtrim($outDir,'/').'/'.$fileName; $pdf->Output($filePath,'F');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $projectRoot = realpath(__DIR__.'/../');
    $relativeUrlPath = null; if(strpos($filePath,$projectRoot)===0){ $relativeUrlPath = str_replace('\\','/', substr($filePath, strlen($projectRoot))); if(strlen($relativeUrlPath) && $relativeUrlPath[0] !== '/') { $relativeUrlPath='/'.$relativeUrlPath; } }
    $fileUrl = $scheme.'://'.$host.$relativeUrlPath;
  } catch(Exception $e) {
    echo json_encode(['status'=>'error','message'=>'Daily PDF failed: '.$e->getMessage()]); exit;
  }
} elseif($reportType === 'periodic') {
  // Build periodic dataset (mirrors modules/reports/api/fetch-periodic-report-data.php but inline for API/PDF)
  try {
  ar_debug('Periodic start payload='.json_encode(['start'=>$startDate,'end'=>$endDate,'branch'=>$branch,'employees'=>$employees]));
  if(!$startDate || !$endDate){ echo json_encode(['status'=>'error','message'=>'Missing start or end date']); exit; }
  if($endDate < $startDate){ echo json_encode(['status'=>'error','message'=>'End date before start date']); exit; }
    require_once __DIR__.'/../includes/reason_helpers.php';
    // Dates: $startDate, $endDate already set (Y-m-d)
    // Fetch employees (include exited employees active during range) honoring branch & selected employees
    $empParams = [];
    $empWhere = [];
    if($branch !== '') { $empWhere[] = 'e.branch = ?'; $empParams[] = $branch; }
    $empWhere[] = '(e.mach_id_not_applicable IS NULL OR e.mach_id_not_applicable = 0)';
    if(!empty($employeeIds)) { $empWhere[] = 'e.emp_id IN ('.implode(',', array_fill(0,count($employeeIds),'?')).')'; $empParams = array_merge($empParams, $employeeIds); }
    // Include active employees whose exit_date is null or after start
    $empWhere[] = '(e.exit_date IS NULL OR e.exit_date >= ?)'; $empParams[] = $startDate;
    $sqlEmployees = "SELECT e.emp_id, CONCAT(e.first_name,' ',e.middle_name,' ',e.last_name) AS employee_name, d.title AS designation, b.name AS branch, e.exit_date, e.branch AS branch_id, dept.name AS department, e.work_start_time, e.work_end_time
      FROM employees e
      LEFT JOIN branches b ON e.branch = b.id
      LEFT JOIN designations d ON e.designation = d.id
      LEFT JOIN departments dept ON e.department_id = dept.id".
      ($empWhere?(' WHERE '.implode(' AND ',$empWhere)):'').' ORDER BY e.emp_id';
    $stmtEmp = $pdo->prepare($sqlEmployees); $stmtEmp->execute($empParams); $employeesData = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
  ar_debug('Periodic employees count='.count($employeesData));
  // Prefetch overrides/assignments for date range and these employees
  $empIdsForPrefetch = array_map(function($r){ return $r['emp_id']; }, $employeesData);
  $overridesMap = prefetch_schedule_overrides($pdo, $empIdsForPrefetch, $startDate, $endDate);

    // Attendance logs grouped by emp & date in range
    $attParams = [$startDate,$endDate];
    $attWhere = 'a.date BETWEEN ? AND ?';
    if(!empty($employeeIds)) { $attWhere .= ' AND a.emp_Id IN ('.implode(',', array_fill(0,count($employeeIds),'?')).')'; $attParams = array_merge($attParams, $employeeIds); }
    if($branch !== '') { $attWhere .= ' AND a.emp_Id IN (SELECT emp_id FROM employees WHERE branch = ? AND (mach_id_not_applicable IS NULL OR mach_id_not_applicable = 0))'; $attParams[] = $branch; }
    $sqlAtt = "SELECT a.emp_Id, a.date, MIN(a.time) AS in_time, MAX(a.time) AS out_time, GROUP_CONCAT(a.method ORDER BY a.time ASC SEPARATOR ', ') AS methods_used, GROUP_CONCAT(a.manual_reason ORDER BY a.time ASC SEPARATOR '; ') AS manual_reasons, COUNT(a.id) AS punch_count FROM attendance_logs a WHERE $attWhere GROUP BY a.emp_Id, a.date";
    $stmtAtt = $pdo->prepare($sqlAtt); $stmtAtt->execute($attParams); $attendanceMap = [];
    foreach($stmtAtt->fetchAll(PDO::FETCH_ASSOC) as $att) { $attendanceMap[$att['emp_Id']][$att['date']] = $att; }
  ar_debug('Periodic attendance rows='.array_reduce($attendanceMap,function($c,$v){return $c+count($v);},0));

    // Prefetch holidays for the date range per branch to avoid per-employee/per-day DB calls
    $holidaysByBranch = []; // branch_id|null => [ 'YYYY-MM-DD' => holidayRow ]
    try {
      // collect unique branch ids from employeesData
      $branchIds = [null];
      foreach($employeesData as $ed){ $bid = $ed['branch_id'] ?? null; if(!in_array($bid,$branchIds,true)) $branchIds[] = $bid; }
      foreach($branchIds as $bId){
        $list = get_holidays_in_range($startDate, $endDate, $bId===null?null:$bId);
        $map = [];
        foreach($list as $h){ if(!empty($h['effective_date'])){ $map[$h['effective_date']] = $h; } }
        $bKey = is_null($bId) ? '__global' : (string)$bId;
        $holidaysByBranch[$bKey] = $map;
      }
    } catch(Throwable $e) { ar_debug('Holiday prefetch failed: '.$e->getMessage()); }

    // Load defaults from settings and compute working interval
    try {
      $defaultStart = function_exists('get_setting') ? get_setting('work_start_time','09:30') : '09:30';
      $defaultEnd = function_exists('get_setting') ? get_setting('work_end_time','18:00') : '18:00';
      $scheduled_in = new DateTime($defaultStart);
      $scheduled_out = new DateTime($defaultEnd);
      $working_hours = $scheduled_in->diff($scheduled_out);
      $formatted_working_hours = sprintf('%02d:%02d', $working_hours->h, $working_hours->i);
    } catch(Exception $e) {
      // Fallback to safe defaults in case of malformed settings
      $scheduled_in = new DateTime('09:30');
      $scheduled_out = new DateTime('18:00');
      $working_hours = new DateInterval('PT8H30M');
      $formatted_working_hours = sprintf('%02d:%02d',$working_hours->h,$working_hours->i);
    }
    // Build list of dates in range
    try {
      $period = new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), (new DateTime($endDate))->modify('+1 day'));
    } catch(Exception $ePeriod) {
      echo json_encode(['status'=>'error','message'=>'Invalid date period']); exit; }

    // Group rows per employee
    $employeeRows = []; // emp_id => rows
    foreach($employeesData as $emp){
      $rows = [];
      foreach($period as $dt){ // We can't reuse iterator after first loop; rebuild inside
      }
    }
    // Rebuild period array (DatePeriod is single-pass)
    $dates = [];
    try {
      foreach(new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), (new DateTime($endDate))->modify('+1 day')) as $dt){ $dates[] = $dt->format('Y-m-d'); }
    } catch(Exception $eDP) { echo json_encode(['status'=>'error','message'=>'Failed building dates']); exit; }
  ar_debug('Periodic date span days='.count($dates));

    foreach($employeesData as $emp){
      $empid = $emp['emp_id'];
      $rows = [];
      foreach($dates as $d){
      }
      // Per-employee scheduled times: resolve via overrides then employee then defaults
      try {
        $empOverrides = $overridesMap[$empid] ?? [];
        $rs = resolve_schedule_for_emp_date($emp, $d, $empOverrides, $scheduled_in->format('H:i'), $scheduled_out->format('H:i'));
        $empSchedIn = new DateTime($rs['start']);
      } catch(Exception $e) { $empSchedIn = clone $scheduled_in; }
      try {
        $empOverrides = $overridesMap[$empid] ?? [];
        $rs2 = isset($rs) ? $rs : resolve_schedule_for_emp_date($emp, $d, $empOverrides, $scheduled_in->format('H:i'), $scheduled_out->format('H:i'));
        $empSchedOut = new DateTime($rs2['end']);
      } catch(Exception $e) { $empSchedOut = clone $scheduled_out; }
      // compute per-employee working hours interval and minutes
      $empWorkingInterval = $empSchedIn->diff($empSchedOut);
      $empScheduledMinutes = ($empWorkingInterval->h * 60) + $empWorkingInterval->i;
      $empFormattedWorkingHours = sprintf('%02d:%02d', $empWorkingInterval->h, $empWorkingInterval->i);
      foreach($dates as $d){
        $isWeekend = (date('N', strtotime($d)) == 6);
        
        // Re-resolve schedule for each specific date in the loop to handle daily overrides correctly
        try {
            $empOverrides = $overridesMap[$empid] ?? [];
            $rs = resolve_schedule_for_emp_date($emp, $d, $empOverrides, $scheduled_in->format('H:i'), $scheduled_out->format('H:i'));
            $empSchedIn = new DateTime($rs['start']);
            $empSchedOut = new DateTime($rs['end']);
            
            $empWorkingInterval = $empSchedIn->diff($empSchedOut);
            $empScheduledMinutes = ($empWorkingInterval->h * 60) + $empWorkingInterval->i;
            $empFormattedWorkingHours = sprintf('%02d:%02d', $empWorkingInterval->h, $empWorkingInterval->i);
        } catch(Exception $e) { 
            // Keep previous values if resolution fails
        }
  $branchKey = $emp['branch_id'] ?? null;
  $bKey = is_null($branchKey) ? '__global' : (string)$branchKey;
  $holiday = ($holidaysByBranch[$bKey][$d] ?? ($holidaysByBranch['__global'][$d] ?? false));
        $isHoliday = $holiday !== false;
        $isExited = (!empty($emp['exit_date']) && $d > $emp['exit_date']);
        $row = [
          'date'=>$d,
          'scheduled_in'=>($isWeekend||$isHoliday)?'-':$empSchedIn->format('H:i'),
          'scheduled_out'=>($isWeekend||$isHoliday)?'-':$empSchedOut->format('H:i'),
          'working_hour'=>($isWeekend||$isHoliday)?'-':$empFormattedWorkingHours,
          'in_time'=>($isWeekend||$isHoliday)?'-':'',
          'out_time'=>($isWeekend||$isHoliday)?'-':'',
          'worked_duration'=>($isWeekend||$isHoliday)?'-':'',
          'over_time'=>($isWeekend||$isHoliday)?'-':'',
          'late_in'=>($isWeekend||$isHoliday)?'-':'',
          'early_out'=>($isWeekend||$isHoliday)?'-':'',
          'early_in'=>($isWeekend||$isHoliday)?'-':'',
          'late_out'=>($isWeekend||$isHoliday)?'-':'',
          'marked_as'=>$isExited?'Exited':($isHoliday?'Holiday':($isWeekend?'Weekend':'Absent')),
          'in_method'=>'', 'out_method'=>'',
          'remarks'=>$isExited?('Employee exited on '.$emp['exit_date']):($isHoliday?($holiday['name']??'Holiday'):'')
        ];
        if(isset($attendanceMap[$empid][$d]) && !$isExited){
          $att = $attendanceMap[$empid][$d];
          $in_time = new DateTime($att['in_time']);
          $out_time = new DateTime($att['out_time']);
          $row['in_time'] = $in_time->format('H:i');
          $row['out_time'] = ($out_time != $in_time)?$out_time->format('H:i'):'';
          $worked = $in_time->diff($out_time); if($out_time != $in_time){ $row['worked_duration']=$worked->format('%H:%I'); }
          $total_minutes = ($worked->h*60)+$worked->i; $scheduled_minutes = $empScheduledMinutes;
          $overtime_minutes = ($isHoliday||$isWeekend)?$total_minutes:max(0,$total_minutes-$scheduled_minutes);
          $row['over_time'] = $overtime_minutes>0?sprintf('%02d:%02d', floor($overtime_minutes/60), $overtime_minutes%60):'';
          if(!$isWeekend && !$isHoliday){
            $row['late_in']=($in_time>$empSchedIn)?$empSchedIn->diff($in_time)->format('%H:%I'):'';
            if($out_time != $in_time){ $row['early_out']=($out_time<$empSchedOut)?$out_time->diff($empSchedOut)->format('%H:%I'):''; }
            $row['early_in']=($in_time<$empSchedIn)?$in_time->diff($empSchedIn)->format('%H:%I'):'';
            $row['late_out']=($out_time>$empSchedOut)?$empSchedOut->diff($out_time)->format('%H:%I'):'';
          }
          $methodsArray = explode(', ', $att['methods_used'] ?? '');
          $reasonsArray = explode('; ', $att['manual_reasons'] ?? '');
          $punchCount = $att['punch_count'] ?? 1;
          $methodMap=['0'=>'A','1'=>'M','2'=>'W'];
          $inMethod = $methodsArray[0] ?? ''; $outMethod = ($punchCount>1)?end($methodsArray):'';
          $inReasonRaw=$reasonsArray[0]??''; $outReasonRaw=($punchCount>1)?end($reasonsArray):'';
          if(function_exists('hrms_format_reason_for_report')){ $inReason=hrms_format_reason_for_report($inReasonRaw); $outReason=hrms_format_reason_for_report($outReasonRaw);} else { $inReason=$inReasonRaw; $outReason=$outReasonRaw; }
          $row['in_method'] = (isset($methodMap[$inMethod])?$methodMap[$inMethod]:$inMethod);
          // Use strict check against empty string because '0' is a valid method code but is falsy in PHP
          $row['out_method'] = ($outMethod !== '' ? (isset($methodMap[$outMethod])?$methodMap[$outMethod]:$outMethod) : '');
          // Build remarks only for the entries that actually have text and avoid duplicates
          $remarkParts = array_filter([trim($inReason), trim($outReason)], function($v){ return $v !== null && $v !== ''; });
          $remarkParts = array_values(array_unique($remarkParts));
          $row['remarks'] = implode(' | ', $remarkParts);
          if($isHoliday){ $row['marked_as']='Present (Holiday)'; $row['remarks']=($holiday['name']??'Holiday').' - Worked as OT'; }
          elseif($isWeekend){ $row['marked_as']='Present (Weekend)'; $row['remarks']='Weekend - Worked as OT'; }
          else { $row['marked_as']='Present'; }
        } elseif($isHoliday && !$isExited) {
          // ensure dash style for time fields already set above
        }
        $rows[] = $row;
      }
      $employeeRows[$empid] = [ 'meta'=>$emp, 'rows'=>$rows ];
    }

    // Generate PDF
    require_once __DIR__.'/../plugins/TCPDF/tcpdf.php';
    class GenPeriodicPDF extends TCPDF {}
    $pdf = new GenPeriodicPDF('L','mm','A4');
    $pdf->SetCreator('HRMS');
    $pdf->SetAuthor($_SESSION['fullName'] ?? 'HRMS');
    $pdf->SetTitle('Periodic Attendance Report');
  // Tighter margins to reduce bottom whitespace
  $pdf->SetMargins(6,10,6); // left, top, right
  $pdf->SetAutoPageBreak(true,6); // reserve only 6mm at bottom
  $pdf->AddPage();
    // Fetch company logo from settings for watermark & corner logo
    $companyLogoSetting=null; $stmtLogo=$pdo->prepare("SELECT value FROM settings WHERE setting_key = 'company_logo' LIMIT 1");
    if($stmtLogo && $stmtLogo->execute() && ($lr=$stmtLogo->fetch(PDO::FETCH_ASSOC))){ $companyLogoSetting=$lr['value']; }
    $logoCandidates=[];
    if($companyLogoSetting){ $logoCandidates[]=$companyLogoSetting; }
    $logoCandidates[]='resources/logo.png'; // fallback
    $resolvedLogo=null;
    foreach($logoCandidates as $cand){
      if(!$cand) continue; // skip empty
      // If URL leave as-is for later (watermark skip for remote to avoid latency)
      if(preg_match('~^https?://~i',$cand)) { $resolvedLogo=$cand; break; }
      // Normalize relative path
      $full=realpath(__DIR__.'/../'.ltrim($cand,'/'));
      if($full && file_exists($full)) { $resolvedLogo=$full; break; }
    }
    // Safety: if resolved logo is PNG with alpha but no GD/Imagick installed, skip to prevent TCPDF error
    $canProcessPngAlpha = (extension_loaded('gd') || extension_loaded('imagick'));
    $skipLogo=false; $fallbackTried=false;
    if($resolvedLogo && !$canProcessPngAlpha && !preg_match('~^https?://~i',$resolvedLogo)){
      $ext = strtolower(pathinfo($resolvedLogo, PATHINFO_EXTENSION));
      if($ext === 'png'){
        // Detect alpha quickly: read IHDR & tRNS chunks
        $fh = @fopen($resolvedLogo,'rb');
        if($fh){
          $header = fread($fh, 64); fclose($fh);
          // If file contains tRNS or color type 6 (RGBA) or 4 (GA) assume alpha
          $hasTRNS = (strpos($header,'tRNS')!==false);
          $colorType = ord(substr($header,25,1)); // IHDR color type byte
          if($hasTRNS || in_array($colorType,[4,6])){ $skipLogo=true; }
        } else { $skipLogo=true; }
        if($skipLogo){
          // Try fallback to resources/logo.png (may be non-alpha)
          $fallback = realpath(__DIR__.'/../resources/logo.png');
          if($fallback && file_exists($fallback)){
            $resolvedLogo=$fallback; $skipLogo=false; $fallbackTried=true;
          }
        }
      }
    }
    if($skipLogo){ ar_debug('Periodic: skipping logo due to PNG alpha without GD/Imagick: '.$resolvedLogo); }
  // Prepare watermark image placement function (applied to every page after generation)
  $watermarkLogo = ($resolvedLogo && !$skipLogo && !preg_match('~^https?://~i',$resolvedLogo) && file_exists($resolvedLogo)) ? $resolvedLogo : null;
  $applyWatermark = function($pdfInstance,$logoPath){
    if(!$logoPath) return; 
    try {
      $wmWidth = 160; // mm
      $wmHeight = 0;  // auto height
      if(method_exists($pdfInstance,'SetAlpha')) { $pdfInstance->SetAlpha(0.06); }
      $centerX = ($pdfInstance->getPageWidth()-$wmWidth)/2;
      $centerY = ($pdfInstance->getPageHeight()-$wmWidth*0.4)/2;
      $pdfInstance->Image($logoPath,$centerX,$centerY,$wmWidth,$wmHeight,'','', '', false, 300, '', false, false, 0);
      if(method_exists($pdfInstance,'SetAlpha')) { $pdfInstance->SetAlpha(1); }
    } catch(Exception $e) { /* ignore */ }
  };
    // Corner logo & global title removed per request
  $pdf->SetFont('helvetica','',6); // minimal spacing before first employee table

  // Column layout (16 columns) similar to UI - Methods combined into single column (In | Out)
  $headers1 = ['SN','Date','In','Out','Work','In','Out','Actual','OT','LateIn','EarlyOut','EarlyIn','LateOut','Marked As','Methods','Remarks'];
  // relative widths tuned for periodic (Date narrower than Employee in daily)
  // Note: Methods column consolidated (20)
  $rel=[7,30,14,14,14,14,14,16,14,14,16,14,14,24,20,34];
    $usableWidth = $pdf->getPageWidth()-$pdf->getMargins()['left']-$pdf->getMargins()['right'];
    $relSum = array_sum($rel); $widths=[]; foreach($rel as $r){ $widths[]=round($usableWidth*($r/$relSum),2);}    

  // Track deepest Y for each page so we can place footer immediately after content
  $pageContentBottoms = [];
  $trackBottom = function($pdf,&$pageContentBottoms){ $p=$pdf->getPage(); $y=$pdf->GetY(); if(!isset($pageContentBottoms[$p]) || $y > $pageContentBottoms[$p]) { $pageContentBottoms[$p]=$y; } };

  $firstEmployee = true; // ensure each employee starts on a fresh page (except the first)
  foreach($employeeRows as $empid=>$bundle){
      $meta = $bundle['meta']; $rows = $bundle['rows'];
      if(!$firstEmployee){
        $pdf->AddPage();
      }
      $firstEmployee = false;
      // (Removed dynamic page break check: now every employee begins on a new page by request)
  // Insert title row at start of each employee table (centered with light shade)
  $pdf->SetFont('helvetica','B',9.5);
  $fullWidth = array_sum($widths);
  $pdf->SetFillColor(240,240,240); // light gray background
  $pdf->Cell($fullWidth,6,'Periodic Attendance Report: '.$dateLabel,1,1,'C',true); // centered, filled
  // Removed per-employee title row containing report label & date range
  // Employee meta row (mimic colspans from UI)
  // Remove top horizontal line at very top of each page by omitting top border when Y is near the top margin
  $pdf->SetFont('helvetica','B',8); // meta row bold as previously
  $atTop = ($pdf->GetY() <= ($pdf->getMargins()['top'] + 5)); // broaden threshold to eliminate stray top line
  $metaBorder = $atTop ? 'LRB' : 1; // no top border if first row on a new page
  $pdf->Cell($widths[0]+$widths[1],6,'Emp. ID: '.$empid,$metaBorder,0,'L'); // colspan 2
  $pdf->Cell($widths[2]+$widths[3]+$widths[4],6,'Name: '.$meta['employee_name'],$metaBorder,0,'L'); // colspan 3
  $pdf->Cell($widths[5]+$widths[6]+$widths[7]+$widths[8],6,'Designation: '.($meta['designation']??''),$metaBorder,0,'L'); // colspan 4
  $pdf->Cell($widths[9]+$widths[10]+$widths[11]+$widths[12],6,'Branch: '.($meta['branch']??''),$metaBorder,0,'L'); // colspan 4
  $pdf->Cell($widths[13]+$widths[14]+$widths[15],6,'Department: '.($meta['department']??''),$metaBorder,1,'L'); // colspan 3
      // Group header row
  $pdf->SetFont('helvetica','B',8.5);
  $pdf->Cell($widths[0],6,'',1,0,'C');
  $pdf->Cell($widths[1],6,'',1,0,'C');
  $pdf->Cell($widths[2]+$widths[3]+$widths[4],6,'Planned Time',1,0,'C');
  $pdf->Cell($widths[5]+$widths[6]+$widths[7],6,'Worked Time',1,0,'C');
  $pdf->Cell($widths[8],6,'OT',1,0,'C');
  $pdf->Cell($widths[9]+$widths[10]+$widths[11]+$widths[12],6,'Deviations',1,0,'C');
  $pdf->Cell($widths[13]+$widths[14]+$widths[15],6,'Status / Notes',1,1,'C');
  // Second header row (sub columns) enhanced: bold, shaded, alignment tweaks
  $pdf->SetFont('helvetica','B',7.2); // slightly larger for clarity
  $pdf->SetFillColor(245,245,245); // light gray shade
  $pdf->Cell($widths[0],6,'#',1,0,'C',true);
  $pdf->Cell($widths[1],6,'Date',1,0,'L',true);
  $pdf->Cell($widths[2],6,'In',1,0,'C',true);
  $pdf->Cell($widths[3],6,'Out',1,0,'C',true);
  $pdf->Cell($widths[4],6,'Work',1,0,'C',true);
  $pdf->Cell($widths[5],6,'In',1,0,'C',true);
  $pdf->Cell($widths[6],6,'Out',1,0,'C',true);
  $pdf->Cell($widths[7],6,'Actual',1,0,'C',true);
  $pdf->Cell($widths[8],6,'OT',1,0,'C',true);
  $pdf->Cell($widths[9],6,'LateIn',1,0,'C',true);
  $pdf->Cell($widths[10],6,'EarlyOut',1,0,'C',true);
  $pdf->Cell($widths[11],6,'EarlyIn',1,0,'C',true);
  $pdf->Cell($widths[12],6,'LateOut',1,0,'C',true);
  $pdf->Cell($widths[13],6,'Marked',1,0,'C',true); // left align text headers for textual columns
  $pdf->Cell($widths[14],6,'Methods',1,0,'C',true);
  $pdf->Cell($widths[15],6,'Remarks',1,1,'C',true);

      $pdf->SetFont('helvetica','',7.4);
      $sn=1; $present=$absent=$weekend=$holiday=$paid=$unpaid=$missed=$manual=0; // summary counters
      foreach($rows as $r){
        // Count statuses
        if(strpos($r['marked_as'],'Present')!==false) $present++; else {
          switch($r['marked_as']){
            case 'Absent': $absent++; break; case 'Weekend': $weekend++; break; case 'Holiday': $holiday++; break; case 'Paid Leave': $paid++; break; case 'Unpaid Leave': $unpaid++; break; case 'Missed': $missed++; break; case 'Manual': $manual++; break; default: break; }
        }
  // Combine in/out methods into single Methods column as 'A | M' when both present
  $methodsCombined = trim((string)($r['in_method'] ?? ''));
  if(!empty($r['out_method'])){ $methodsCombined = ($methodsCombined !== '' ? $methodsCombined.' | ' : '').trim((string)$r['out_method']); }
      $line=[ $sn++, date('Y-m-d, l', strtotime($r['date'])), $r['scheduled_in'],$r['scheduled_out'],$r['working_hour'],$r['in_time'],$r['out_time'],$r['worked_duration'],$r['over_time'],$r['late_in'],$r['early_out'],$r['early_in'],$r['late_out'],$r['marked_as'], strip_tags($methodsCombined), strip_tags($r['remarks']) ];
      if(!$canViewSensitiveAttendance){
        $line[7]=$line[8]=$line[9]=$line[10]=$line[11]=$line[12]='--';
        $line[14] = trim((string)$line[14]) !== '' ? 'Restricted' : '--';
        $line[15] = trim((string)$line[15]) !== '' ? 'Restricted' : '--';
      }
        $prepared=[]; $heights=[];
        foreach($line as $i=>$val){ $text=(string)$val; if($i==15){ $text=wordwrap($text,60,"\n",true);} $prepared[$i]=$text; $heights[$i]=$pdf->getStringHeight($widths[$i],$text); }
        $rowHeight=max($heights); if($rowHeight<5)$rowHeight=5; $y=$pdf->GetY(); $x=$pdf->GetX();
        foreach($prepared as $i=>$text){
          // Default center alignment; only Date column (index 1) left-aligned now.
          $align = ($i==1) ? 'L' : 'C';
          $pdf->MultiCell($widths[$i],$rowHeight,$text,1,$align,false,0);
          $x+=$widths[$i]; $pdf->SetXY($x,$y);
        }
    $pdf->SetY($y+$rowHeight);
    $trackBottom($pdf,$pageContentBottoms);
  if($pdf->GetY() > ($pdf->getPageHeight()-18)) { $pdf->AddPage(); }
      }
      // Summary footer row (colspans replicating UI)
      $pdf->SetFont('helvetica','B',7.2);
      // Build cells matching colspan distribution: 1,2,2,2,2,2,2,1,2 totals 16
      $pdf->Cell($widths[0]+$widths[1],6,'Summary:',1,0,'R');
      $pdf->Cell($widths[2]+$widths[3],6,'Present: '.$present,1,0,'C');
      $pdf->Cell($widths[4]+$widths[5],6,'Absent: '.$absent,1,0,'C');
      $pdf->Cell($widths[6]+$widths[7],6,'Weekend: '.$weekend,1,0,'C');
      $pdf->Cell($widths[8]+$widths[9],6,'Holiday: '.$holiday,1,0,'C');
      $pdf->Cell($widths[10]+$widths[11],6,'Paid Leave: '.$paid,1,0,'C');
      $pdf->Cell($widths[12]+$widths[13],6,'Unpaid Leave: '.$unpaid,1,0,'C');
  $pdf->Cell($widths[14],6,'Missed: '.$missed,1,0,'C');
  $pdf->Cell($widths[15],6,'Manual: '.$manual,1,1,'C');
  $trackBottom($pdf,$pageContentBottoms);
  $pdf->Ln(1);
  $trackBottom($pdf,$pageContentBottoms);
    }

    // Apply watermark on every page now (after all pages created)
    if($watermarkLogo){ $totalPages=$pdf->getNumPages(); for($wp=1;$wp<=$totalPages;$wp++){ $pdf->setPage($wp); $applyWatermark($pdf,$watermarkLogo); } }
    // Unified footer text (same timestamp for all pages) placed close to table if large whitespace exists
  // Footer font: make last page bold per request
  $pdf->SetFont('helvetica','',7);
    $timezone='UTC'; $tzStmt=$pdo->prepare("SELECT value FROM settings WHERE setting_key = 'timezone'"); if($tzStmt && $tzStmt->execute() && ($res=$tzStmt->fetch(PDO::FETCH_ASSOC))){ $timezone=$res['value']; }
    date_default_timezone_set($timezone); $generatedAt=date('Y-m-d h:i A');
    $pages = $pdf->getNumPages();
    for($p=1;$p<=$pages;$p++){
      $pdf->setPage($p);
      // All pages bold (previous change limited bold to last page)
      $pdf->SetFont('helvetica','B',7);
      $baseY = isset($pageContentBottoms[$p]) ? $pageContentBottoms[$p] + 1 : ($pdf->getMargins()['top']); // 1mm below content
      $maxY = $pdf->getPageHeight() - $pdf->getMargins()['bottom'] - 2; // keep above bottom margin
      if($baseY > $maxY) $baseY = $maxY;
      $leftX = $pdf->getMargins()['left'];
      $rightMargin = $pdf->getMargins()['right'];
      $pageText = 'Page '.$p.' of '.$pages;
      $genText = 'Generated on: '.$generatedAt;
      $pdf->SetXY($leftX,$baseY);
      $pdf->Cell(0,4,$pageText,0,0,'L');
      $pdf->SetXY($leftX,$baseY);
      $pdf->Cell($pdf->getPageWidth()-$pdf->getMargins()['left']-$rightMargin,4,$genText,0,0,'R');
    }

    // Persist file
    $rootPath = realpath(__DIR__.'/../');
    $outDir = $rootPath.'/uploads/reports/periodic-temp'; if(!is_dir($outDir)) { @mkdir($outDir,0755,true);} if(!is_writable($outDir)) { $outDir = sys_get_temp_dir(); }
    // Cleanup old (30 days)
    if(is_dir($outDir)) { $nowTs=time(); $ttl=2592000; foreach(glob($outDir.'/periodic_report_*.pdf') as $old){ if(is_file($old) && ($nowTs-filemtime($old))>$ttl){ @unlink($old); } } }
    $safeBranch=preg_replace('/[^A-Za-z0-9_-]+/','_', $branchLabel);
    $fileName='periodic_report_'.$startDate.'_'.$endDate.'_'.$safeBranch.'_'.uniqid().'.pdf';
    $filePath=rtrim($outDir,'/').'/'.$fileName; $pdf->Output($filePath,'F');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $projectRoot = realpath(__DIR__.'/../');
    $relativeUrlPath = null; if(strpos($filePath,$projectRoot)===0){ $relativeUrlPath = str_replace('\\','/', substr($filePath, strlen($projectRoot))); if(strlen($relativeUrlPath) && $relativeUrlPath[0] !== '/') { $relativeUrlPath='/'.$relativeUrlPath; } }
    $fileUrl = $scheme.'://'.$host.$relativeUrlPath;
  ar_debug('Periodic PDF generated file='.$filePath.' size='.(is_file($filePath)?filesize($filePath):0));
  } catch(Exception $e) {
  ar_debug('Periodic ERROR '.$e->getMessage().' trace='.$e->getTraceAsString());
  http_response_code(500);
  // Clean any prior buffered output to keep JSON valid
  if(ob_get_length()) { ob_clean(); }
  echo json_encode(['status'=>'error','message'=>'Periodic PDF failed: '.$e->getMessage()]); exit;
  }
} elseif($reportType === 'timesheet') {
  // True Time Sheet: replicate periodic-time-report logic (P/A/L/H with per-day columns and summaries)
  try {
    if(!$startDate || !$endDate){ echo json_encode(['status'=>'error','message'=>'Missing start or end date']); exit; }
    if($endDate < $startDate){ echo json_encode(['status'=>'error','message'=>'End date before start date']); exit; }
  // Enforce maximum 32 day inclusive range for timesheet report (auto-truncate silently and note in header)
  $d1=new DateTime($startDate); $d2=new DateTime($endDate); $diffDays=$d1->diff($d2)->days + 1; // inclusive
    $truncated=false; if($diffDays>32){ $endDate=(clone $d1)->modify('+31 days')->format('Y-m-d'); $truncated=true; }
    if($truncated){
      // Recompute displayed date label to reflect truncated end date
      $startLabel=(new DateTime($startDate))->format('d/m/Y');
      $endLabel=(new DateTime($endDate))->format('d/m/Y');
      $dateLabel=$startLabel.' - '.$endLabel;
    }
  // Load settings for work start time (fallback to 09:30)
  $workStart='09:30';
  try { $st=$pdo->query("SELECT value FROM settings WHERE setting_key='work_start_time' LIMIT 1"); if($st && ($r=$st->fetch(PDO::FETCH_ASSOC))){ $workStart=$r['value']; } } catch(Exception $e){}
    $scheduled_in = new DateTime($workStart);
    // Grace time: 5 minutes for all employees
    $graceMinutes = 5;
    // Employees limited to branch (timesheet UI requires branch); if none given treat as all
    $empParams=[]; $where=[];
    if($branch!==''){ $where[]='e.branch = ?'; $empParams[]=$branch; }
    $where[]='(e.mach_id_not_applicable IS NULL OR e.mach_id_not_applicable = 0)';
    if(!empty($employeeIds)){ $where[]='e.emp_id IN ('.implode(',',array_fill(0,count($employeeIds),'?')).')'; $empParams=array_merge($empParams,$employeeIds);}    
    $where[]='(e.join_date IS NULL OR e.join_date < ?)'; $empParams[]=$startDate;
    $where[]='(e.exit_date IS NULL OR e.exit_date > ?)'; $empParams[]=$endDate;
  $sqlEmp="SELECT e.emp_id, CONCAT(e.first_name,' ',e.middle_name,' ',e.last_name) AS employee_name, e.join_date, e.exit_date, e.branch AS branch_id, b.name AS branch, e.work_start_time, e.work_end_time FROM employees e LEFT JOIN branches b ON e.branch=b.id".($where?(' WHERE '.implode(' AND ',$where)):'').' ORDER BY e.emp_id';
  $stEmp=$pdo->prepare($sqlEmp); $stEmp->execute($empParams); $employees=$stEmp->fetchAll(PDO::FETCH_ASSOC);
  // Prefetch overrides for timesheet employees across the date range
  $empIdsForPrefetch = array_map(function($r){ return $r['emp_id']; }, $employees);
  $overridesMap = prefetch_schedule_overrides($pdo, $empIdsForPrefetch, $startDate, $endDate);
  // Attendance earliest in_time per day
    $attParams=[$startDate,$endDate]; $attWhere='a.date BETWEEN ? AND ?';
    if(!empty($employeeIds)){ $attWhere.=' AND a.emp_id IN ('.implode(',',array_fill(0,count($employeeIds),'?')).')'; $attParams=array_merge($attParams,$employeeIds);}    
    if($branch!==''){ $attWhere.=' AND a.emp_id IN (SELECT emp_id FROM employees WHERE branch = ? AND (mach_id_not_applicable IS NULL OR mach_id_not_applicable = 0))'; $attParams[]=$branch; }
    $sqlAtt="SELECT a.emp_id, a.date, MIN(a.time) AS in_time FROM attendance_logs a WHERE $attWhere GROUP BY a.emp_id,a.date";
    $stAtt=$pdo->prepare($sqlAtt); $stAtt->execute($attParams); $attendance=[]; foreach($stAtt->fetchAll(PDO::FETCH_ASSOC) as $r){ $attendance[$r['emp_id']][$r['date']]=$r; }
    // Build date list
    $dates=[]; foreach(new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), (new DateTime($endDate))->modify('+1 day')) as $d){ $dates[]=$d->format('Y-m-d'); }
    // Prefetch holidays for the date range per branch to avoid per-employee/per-day DB calls (timesheet)
    $holidaysByBranch = [];
    try {
      $branchIds = [null];
      foreach($employees as $ed){ $bid = $ed['branch'] ?? null; if(!in_array($bid,$branchIds,true)) $branchIds[] = $bid; }
      foreach($branchIds as $bId){
        $list = get_holidays_in_range($startDate, $endDate, $bId===null?null:$bId);
        $map = [];
        foreach($list as $h){ if(!empty($h['effective_date'])){ $map[$h['effective_date']] = $h; } }
        $bKey = is_null($bId) ? '__global' : (string)$bId;
        $holidaysByBranch[$bKey] = $map;
      }
    } catch(Throwable $e) { ar_debug('Timesheet holiday prefetch failed: '.$e->getMessage()); }

    // Process employees
    $employeesOut=[]; foreach($employees as $emp){
      $empData=[ 'meta'=>$emp, 'dates'=>[], 'summary'=>['working_days'=>0,'present'=>0,'absent'=>0,'late'=>0,'holidays'=>0] ];
      foreach($dates as $date){
        $dow=(int)date('N',strtotime($date)); $isSaturday=($dow==6);
        // Use pre-fetched holidays map if available (will be populated below)
  $branchKey = $emp['branch_id'] ?? null;
  $bKey = is_null($branchKey) ? '__global' : (string)$branchKey;
  $holiday = ($holidaysByBranch[$bKey][$date] ?? ($holidaysByBranch['__global'][$date] ?? false));
        $isHoliday = $holiday!==false;
        $status='A'; // default absent on working days
        $exited=(!empty($emp['exit_date']) && $date > $emp['exit_date']);
        if($exited){ $status='-'; }
        elseif($isSaturday || $isHoliday){ $status='H'; $empData['summary']['holidays']++; }
        else {
          if(isset($attendance[$emp['emp_id']][$date])){
            $inTimeRaw = $attendance[$emp['emp_id']][$date]['in_time'];
            // Normalize both times to H:i (ignore seconds)
            $inTime = date('H:i', strtotime($inTimeRaw));
            // Prefer per-employee override/assignment first, else employee field, else configured work start
            $empOverrides = $overridesMap[$emp['emp_id']] ?? [];
            $resolved = resolve_schedule_for_emp_date($emp, $date, $empOverrides, $workStart, $workStart);
            $workStartNorm = date('H:i', strtotime($resolved['start']));
            $inDT = DateTime::createFromFormat('H:i', $inTime);
            $scheduled_in_norm = DateTime::createFromFormat('H:i', $workStartNorm);
            // Apply grace time: add grace minutes to scheduled time
            $scheduledWithGrace = clone $scheduled_in_norm;
            if($graceMinutes > 0) {
              $scheduledWithGrace->modify('+' . $graceMinutes . ' minutes');
            }
            if($inDT <= $scheduledWithGrace){ $status='P'; $empData['summary']['present']++; }
            else { $status='L'; $empData['summary']['late']++; }
          } else {
            // No attendance -> Absent
            $empData['summary']['absent']++;
          }
          $empData['summary']['working_days']++;
        }
        $empData['dates'][$date]=['status'=>$status];
      }
      // Always include employee (even if no P/A days) per new requirement
      $employeesOut[]=$empData;
    }
    // Prepass: compute amount & selection for each employee (so we can size columns dynamically)
    $grandAmount=0; $selectedCount=0; $amountStrings=[]; $hasNotSelected=false;
    foreach($employeesOut as &$empObj){
      $present=$empObj['summary']['present']; $absent=$empObj['summary']['absent']; $late=$empObj['summary']['late'];
      $amount = ($late <= 3)?0:(($late-3)*50); // only late beyond 3
      $workingDays=$empObj['summary']['working_days'];
      // Lucky draw selection logic per rules:
      // 1. New employees (joined during/after period) are not eligible
      $isNew = false;
      if (!empty($empObj['meta']['join_date'])) {
        $joinDate = $empObj['meta']['join_date'];
        if ($joinDate >= $startDate) $isNew = true;
      }
      // 2. Must be present at least 50% of working days
      $threshold = ceil($workingDays * 0.5); // 50% (rounded up)
      // 3. Absence for up to 2 days is allowed
  $isSelected = (!$isNew && $present >= $threshold && $late <= 2);
      $empObj['calc_amount']=$amount; $empObj['calc_selected']=$isSelected;
      $amountStrings[] = number_format($amount,0); // no decimals currently shown in body rows
      if($isSelected){ $grandAmount += $amount; $selectedCount++; } else { $hasNotSelected=true; }
    }
    unset($empObj);
    // Prepare summary per date across employees
  $dateTotals=[]; foreach($dates as $date){ $dateTotals[$date]=['P'=>0,'A'=>0,'L'=>0,'H'=>0]; }
    foreach($employeesOut as $e){ foreach($dates as $date){ $s=$e['dates'][$date]['status']??'L'; if(isset($dateTotals[$date][$s])) $dateTotals[$date][$s]++; }}
    // PDF build
    require_once __DIR__.'/../plugins/TCPDF/tcpdf.php'; class GenTimeSheetPDF extends TCPDF {}
  $pdf=new GenTimeSheetPDF('L','mm','A4'); $pdf->SetCreator('HRMS'); $pdf->SetAuthor($_SESSION['fullName'] ?? 'HRMS'); $pdf->SetTitle('Periodic Entry Time Report');
  // Ensure font subsetting for Unicode (needed for Nepali text)
  if(method_exists($pdf,'setFontSubsetting')) { $pdf->setFontSubsetting(true); }
    $pdf->SetMargins(6,10,6); $pdf->SetAutoPageBreak(true,8); $pdf->AddPage();
    // Logo skip logic (reuse periodic approach)
    $logoCandidates=['resources/logo.png']; $resolvedLogo=null; foreach($logoCandidates as $cand){ $full=realpath(__DIR__.'/../'.ltrim($cand,'/')); if($full && file_exists($full)){ $resolvedLogo=$full; break; } }
    $canAlpha=(extension_loaded('gd')||extension_loaded('imagick')); if($resolvedLogo && !$canAlpha && strtolower(pathinfo($resolvedLogo,PATHINFO_EXTENSION))==='png'){ $resolvedLogo=null; }
    $watermarkLogo=$resolvedLogo; $applyWM=function($pdfInstance,$logo){ if(!$logo) return; try{ if(method_exists($pdfInstance,'SetAlpha'))$pdfInstance->SetAlpha(0.06); $w=160; $pdfInstance->Image($logo,($pdfInstance->getPageWidth()-$w)/2,($pdfInstance->getPageHeight()-$w*0.4)/2,$w,0); if(method_exists($pdfInstance,'SetAlpha'))$pdfInstance->SetAlpha(1);}catch(Exception $e){} };
    // Dynamic column widths: SN + Employee + N dates + 5 summary columns (Present, Absent, Leave, Amount, Status)
    $dateCount = count($dates);
    $baseWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
    // Fixed widths for non-date columns
    $snW = 6;
    $empW = 38;
    $schInW = 10;
    $pdf->SetFont('helvetica','B',8);
    $amountLabel = 'Amount';
    $statusLabel = 'Status';
    $maxAmountStr = $amountLabel;
    foreach ($amountStrings as $s) { if (strlen($s) > strlen($maxAmountStr)) $maxAmountStr = $s; }
    $maxStatusStr = $hasNotSelected ? 'Not Selected' : 'Selected';
    if (strlen($statusLabel) > strlen($maxStatusStr)) $maxStatusStr = $statusLabel;
    // Measure string widths (add small padding)
    $amountWidth = ceil($pdf->GetStringWidth($maxAmountStr) + 4); if ($amountWidth < 12) $amountWidth = 12; if ($amountWidth > 28) $amountWidth = 28;
    $statusWidth = ceil($pdf->GetStringWidth($maxStatusStr) + 4); if ($statusWidth < 16) $statusWidth = 16; if ($statusWidth > 34) $statusWidth = 34;
    // Present, Absent, Late Entry keep fixed minimal widths
    $summaryW = [12, 12, 16, $amountWidth, $statusWidth];
    $summaryTotal = array_sum($summaryW);
    // Calculate remaining width for date columns
    $fixedTotal = $snW + $empW + $schInW + $summaryTotal;
    $remaining = $baseWidth - $fixedTotal;
    // Remove minimum width: force per-date column to fit exactly
    $perDate = $dateCount ? ($remaining / $dateCount) : 0;
    // If still over (should not happen), shrink empW as last resort
    $totalWidth = $fixedTotal + ($perDate * $dateCount);
    if ($totalWidth > $baseWidth) {
      $empW = max(10, $empW - ($totalWidth - $baseWidth));
      $remaining = $baseWidth - ($snW + $empW + $schInW + $summaryTotal);
      $perDate = $dateCount ? ($remaining / $dateCount) : 0;
    }
    // Header rows
  $pdf->SetFont('helvetica','B',12); $pdf->SetFillColor(240,240,240);
  $headerTitle='Periodic Entry Time Report: '.$dateLabel; if(isset($truncated) && $truncated){ $headerTitle.=' (Truncated to 32 days)'; }
  $pdf->Cell($baseWidth,9,$headerTitle,1,1,'C',true);
  $pdf->SetFont('helvetica','',9); $pdf->Cell($baseWidth,7,'Branch: '.$branchLabel,1,1,'C');
    // Column header line 1 (labels)
  $pdf->SetFont('helvetica','B',8); $pdf->SetFillColor(245,245,245);
  $pdf->Cell($snW,7,'SN',1,0,'C',true); $pdf->Cell($empW,7,'Employee',1,0,'L',true);
  $pdf->Cell(10,7,'Sch. In',1,0,'C',true);
  foreach($dates as $d){ $dow=(int)date('N',strtotime($d)); $isSat=$dow==6; if($isSat){ $pdf->SetFillColor(255,235,150);} else { $pdf->SetFillColor(245,245,245);} $pdf->Cell($perDate,7,date('d',strtotime($d)),1,0,'C',true); }
    // Reset fill for summary columns
    $pdf->SetFillColor(245,245,245);
  $summaryLabels=['Present','Absent','Late Entry','Amount','Status']; foreach($summaryLabels as $i=>$lab){ $pdf->Cell($summaryW[$i],7,$lab,1,0,'C',true);} $pdf->Ln();
    $pageBottoms=[]; $track=function($pdf,&$pageBottoms){ $p=$pdf->getPage(); $y=$pdf->GetY(); if(!isset($pageBottoms[$p])||$y>$pageBottoms[$p]) $pageBottoms[$p]=$y; };
    // Rows
    // Fetch configurable absence color once
    static $absColor = null; if($absColor===null){ $absColor='#FFD2D2'; try{ $cs=$pdo->prepare("SELECT value FROM settings WHERE setting_key='timesheet_absent_color' LIMIT 1"); if($cs && $cs->execute() && ($cr=$cs->fetch(PDO::FETCH_ASSOC))){ $val=trim($cr['value']); if(preg_match('/^#?[0-9A-Fa-f]{6}$/',$val)){ if($val[0]!=='#') $val='#'.$val; $absColor=$val; } } }catch(Exception $e){} }
    $hexToRGB=function($hex){ $hex=ltrim($hex,'#'); return [hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2))]; };
    [$absR,$absG,$absB]=$hexToRGB($absColor);
  // Reduced row height (previously 6mm) per request
  $pdf->SetFont('helvetica','',7.4); // slight font reduction for tighter rows
  $rowH = 5; // unified body row height
  $sn=1; $rowIdx=0; foreach($employeesOut as $emp) {
    $rowStartY = $pdf->GetY();
    $alt = ($rowIdx % 2) == 1;
    if ($alt) { $pdf->SetFillColor(250,250,250); } else { $pdf->SetFillColor(255,255,255); }
    $pdf->Cell($snW, $rowH, $sn++, 1, 0, 'C', true);
    if ($alt) { $pdf->SetFillColor(250,250,250); } else { $pdf->SetFillColor(255,255,255); }
    // Employee cell bold
    $pdf->SetFont('helvetica','B',7.4);
    $pdf->Cell($empW, $rowH, $emp['meta']['emp_id'].' - '.$emp['meta']['employee_name'], 1, 0, 'L', true);
    // Scheduled In time (always present)
    $pdf->SetFont('helvetica','',7.4);
    $pdf->Cell(10, $rowH, date("g:i", strtotime($emp['meta']['work_start_time'])), 1, 0, 'C', true);
    // Date columns
    foreach ($dates as $d) {
      $status = $emp['dates'][$d]['status'];
      $dow = (int)date('N', strtotime($d));
      $isSat = $dow == 6;
      $fill = false;
      if ($status === 'H') { $pdf->SetFillColor(255,235,150); $fill = true; }
      elseif ($status === 'P') { $pdf->SetFillColor(200,230,200); $fill = true; }
      elseif ($status === 'L') { $pdf->SetFillColor(255,170,170); $fill = true; }
      elseif ($status === 'A') { $pdf->SetFillColor(224,224,224); $fill = true; }
      else { $pdf->SetFillColor(245,245,245); }
      $display = $status;
      $pdf->Cell($perDate, $rowH, $display, 1, 0, 'C', $fill);
    }
    // Summary columns (Present, Absent, Late, Amount, Status)
    $present = $emp['summary']['present'];
    $absent = $emp['summary']['absent'];
    $late = $emp['summary']['late'];
    $amount = $emp['calc_amount'];
    $isSelected = $emp['calc_selected'];
    if ($alt) { $pdf->SetFillColor(250,250,250); } else { $pdf->SetFillColor(255,255,255); }
    $pdf->Cell($summaryW[0], $rowH, $present, 1, 0, 'C', true);
    if ($alt) { $pdf->SetFillColor(250,250,250); } else { $pdf->SetFillColor(255,255,255); }
    $pdf->Cell($summaryW[1], $rowH, $absent, 1, 0, 'C', true);
    if ($alt) { $pdf->SetFillColor(250,250,250); } else { $pdf->SetFillColor(255,255,255); }
    $pdf->Cell($summaryW[2], $rowH, $late, 1, 0, 'C', true);
    if ($alt) { $pdf->SetFillColor(250,250,250); } else { $pdf->SetFillColor(255,255,255); }
    $pdf->Cell($summaryW[3], $rowH, number_format($amount,0), 1, 0, 'C', true);
    if ($alt) { $pdf->SetFillColor(250,250,250); } else { $pdf->SetFillColor(255,255,255); }
    $pdf->Cell($summaryW[4], $rowH, ($isSelected ? 'Selected' : 'Not Selected'), 1, 1, 'C', true);
    $track($pdf, $pageBottoms);
    if ($pdf->GetY() > $pdf->getPageHeight() - 20) {
      $pdf->AddPage();
      // reprint headers on new page (including Sch. In)
      $pdf->SetFont('helvetica','B',8); $pdf->SetFillColor(245,245,245);
      $pdf->Cell($snW,7,'SN',1,0,'C',true);
      $pdf->Cell($empW,7,'Employee',1,0,'L',true);
      $pdf->Cell(10,7,'Sch. In',1,0,'C',true);
      foreach($dates as $d){ $dow=(int)date('N',strtotime($d)); $isSat=$dow==6; if($isSat){ $pdf->SetFillColor(255,235,150);} else { $pdf->SetFillColor(245,245,245);} $pdf->Cell($perDate,7,date('d',strtotime($d)),1,0,'C',true); }
      $pdf->SetFillColor(245,245,245);
      foreach($summaryLabels as $i=>$lab){ $pdf->Cell($summaryW[$i],7,$lab,1,0,'C',true); }
      $pdf->Ln(); $pdf->SetFont('helvetica','',7.4);
    }
    $rowIdx++;
  }
  // Footer summary row per date (may need page break)
  if($pdf->GetY()>$pdf->getPageHeight()-24){ $pdf->AddPage(); $pdf->SetFont('helvetica','B',8); $pdf->SetFillColor(245,245,245); $pdf->Cell($snW,7,'SN',1,0,'C',true); $pdf->Cell($empW,7,'Employee',1,0,'L',true); foreach($dates as $d){ $dow=(int)date('N',strtotime($d)); $isSat=$dow==6; if($isSat){ $pdf->SetFillColor(255,235,150);} else { $pdf->SetFillColor(245,245,245);} $pdf->Cell($perDate,7,date('d',strtotime($d)),1,0,'C',true);} $pdf->SetFillColor(245,245,245); foreach($summaryLabels as $i=>$lab){ $pdf->Cell($summaryW[$i],7,$lab,1,0,'C',true);} $pdf->Ln(); }
  // Summary row (match body row height & avoid double border by removing top border)
  // Match summary row height to body row height
  $sumH=$rowH; $pdf->SetFont('helvetica','B',7.0);
  $centerCell=function($w,$h,$txt,$border='LRB',$align='C') use ($pdf){
    $x=$pdf->GetX(); $y=$pdf->GetY();
    $pdf->Cell($w,$h,'',$border,0,$align,false); // draw border
    $fontSize=$pdf->getFontSizePt(); $textHeight=$fontSize*0.3528; // convert pt to mm
    $offsetY=$y + ($h-$textHeight)/2 - 0.1; // slight tweak
    $pdf->SetXY($x,$offsetY); $pdf->Cell($w,$textHeight,$txt,0,0,$align,false);
    $pdf->SetXY($x+$w,$y); // move cursor to original baseline right edge
  };
  // Summary row content (match header: SN, Employee, Sch. In, [dates], Present, Absent, Late, Amount, Status)
  $centerCell($snW, $sumH, '');
  $centerCell($empW, $sumH, 'Summary');
  $centerCell(10, $sumH, ''); // Sch. In column
  foreach($dates as $d){
    $presentOnly = $dateTotals[$d]['P'] ?? 0;
    $centerCell($perDate, $sumH, (string)$presentOnly);
  }
  // Empty / aggregate summary columns
  $centerCell($summaryW[0], $sumH, '');
  $centerCell($summaryW[1], $sumH, '');
  $centerCell($summaryW[2], $sumH, '');
  // Calculate total sum of all employees' amounts for summary row
  $totalAmountAll = 0;
  foreach($employeesOut as $empObj){
    $totalAmountAll += $empObj['calc_amount'] ?? 0;
  }
  $centerCell($summaryW[3], $sumH, number_format($totalAmountAll,0));
  $centerCell($summaryW[4], $sumH, (string)$selectedCount);
  $pdf->Ln(); $track($pdf,$pageBottoms);
  // Add breathing space before total amount summary and legend & rules
  if($pdf->GetY() < ($pdf->getPageHeight()-65)) { $pdf->Ln(2); }

  // Legend + Rules (moved after entire table as requested)
  $legendText='Legend:  P = Timely Present    ||    L = Late    ||    A = Absent    ||    H = Holiday/Weekend';
    // English rules (Nepali removed due to font rendering issues)
    $rulesList = [
      '1. New employees will not be allowed to participate in the lottery for the first time.',
      '2. To be eligible for the lottery, employees must be present for at least 50% of the total working days in the month.',
      '3. Absence for up to 2 days will still be considered eligible for the lottery.',
      '4. Absence for up to 3 days will not incur any penalty.',
  '5. If absent for 4 or more days, penalties will be calculated starting from the 4th day of absence.',
      '6. The lottery will be conducted on the upcoming Sunday after the name list is published.',
      '7. If the published name list needs to be reviewed, or if there are any complaints or suggestions, please contact Mr. Sagar Khatiwada.'
    ];
    // Use standard font only (simplified, no Unicode font probing required now)
    $pdf->SetFont('helvetica','I',7);
  // Ensure space; if near bottom create new page
  if($pdf->GetY()>$pdf->getPageHeight()-50){ $pdf->AddPage(); }
  // Larger legend font
  $pdf->SetFont('helvetica','B',8.5);
  $pdf->MultiCell($baseWidth,7,$legendText,0,'L');
  $pdf->Ln(1);
    // Larger Nepali rules text
  // Rules heading bold + underline, list normal
  $pdf->SetFont('helvetica','BU',9);
  $pdf->Cell($baseWidth,6,'Rules:',0,1,'L');
  $pdf->SetFont('helvetica','',8.3);
  foreach($rulesList as $rule){
    $pdf->MultiCell($baseWidth,5,$rule,0,'L');
  }
  $track($pdf,$pageBottoms);
    // Watermark
    if($watermarkLogo){ $tp=$pdf->getNumPages(); for($i=1;$i<=$tp;$i++){ $pdf->setPage($i); $applyWM($pdf,$watermarkLogo); } }
    // Footer page numbers
  $timezone='UTC'; $tzStmt=$pdo->prepare("SELECT value FROM settings WHERE setting_key = 'timezone'"); if($tzStmt && $tzStmt->execute() && ($res=$tzStmt->fetch(PDO::FETCH_ASSOC))){ $timezone=$res['value']; }
  date_default_timezone_set($timezone); $generatedAt=date('Y-m-d h:i A'); $pages=$pdf->getNumPages();
  for($p=1;$p<=$pages;$p++){
    $pdf->setPage($p);
    $pdf->SetFont('helvetica','B',7);
    $left=$pdf->getMargins()['left'];
    $right=$pdf->getMargins()['right'];
    $bottomMargin=$pdf->getMargins()['bottom'];
    // Position 5mm above absolute bottom (respect bottom margin if larger)
    $y = $pdf->getPageHeight() - $bottomMargin - 5;
    if($y < 0) $y = 0;
    $pdf->SetXY($left,$y);
    $pdf->Cell(0,4,'Page '.$p.' of '.$pages,0,0,'L');
    $pdf->SetXY($left,$y);
    $pdf->Cell($pdf->getPageWidth()-$pdf->getMargins()['left']-$right,4,'Generated on: '.$generatedAt,0,0,'R');
  }
    // Persist
    $rootPath=realpath(__DIR__.'/../'); $outDir=$rootPath.'/uploads/reports/timesheet-temp'; if(!is_dir($outDir)) @mkdir($outDir,0755,true); if(!is_writable($outDir)) $outDir=sys_get_temp_dir(); if(is_dir($outDir)){ $now=time(); $ttl=2592000; foreach(glob($outDir.'/timesheet_report_*.pdf') as $old){ if(is_file($old) && ($now-filemtime($old))>$ttl) @unlink($old); } } $safeBranch=preg_replace('/[^A-Za-z0-9_-]+/','_', $branchLabel); $fileName='timesheet_report_'.$startDate.'_'.$endDate.'_'.$safeBranch.'_'.uniqid().'.pdf'; $filePath=$outDir.'/'.$fileName; $pdf->Output($filePath,'F'); $scheme=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http'; $host=$_SERVER['HTTP_HOST']??'localhost'; $projectRoot=realpath(__DIR__.'/../'); $relativeUrlPath=null; if(strpos($filePath,$projectRoot)===0){ $relativeUrlPath=str_replace('\\','/', substr($filePath, strlen($projectRoot))); if(strlen($relativeUrlPath)&&$relativeUrlPath[0] !== '/') $relativeUrlPath='/'.$relativeUrlPath; } $fileUrl=$scheme.'://'.$host.$relativeUrlPath;
    // Persist metadata (this branch previously exited early without saving record)
    if(!isset($_SESSION['generated_reports'])) { $_SESSION['generated_reports'] = []; }
    $tsRecord = [
      'type' => $reportType,
      'type_label' => $typeLabel,
      'date_label' => $dateLabel,
      'branch' => $branch,
      'branch_label' => $branchLabel,
      'employees_label' => $employeesLabel,
      'employees_hidden' => $employeesHidden,
      'employees_raw' => empty($employeeIds)?'*':implode(',', $employeeIds),
      'generated_by' => (string)($_SESSION['fullName'] ?? ($_SESSION['user_id'] ?? 'Unknown')),
      'generated_at' => date('Y-m-d H:i:s'),
      'file_url' => $fileUrl
    ];
    $_SESSION['generated_reports'][] = $tsRecord;
    if(count($_SESSION['generated_reports'])>50){ $_SESSION['generated_reports'] = array_slice($_SESSION['generated_reports'], -50); }
    try {
      if(isset($pdo)) {
        $stmt = $pdo->prepare("INSERT INTO generated_reports (user_id, generated_by, report_type, type_label, date_label, branch_id, branch_label, employees_label, employees_hidden, file_url, generated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
          isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null,
          $tsRecord['generated_by'],
          $reportType,
          $typeLabel,
          $dateLabel,
          $branch !== '' ? $branch : null,
          $branchLabel,
          $employeesLabel,
          $employeesHidden,
          $fileUrl,
          $tsRecord['generated_at']
        ]);
      }
    } catch(Throwable $e) { /* swallow silently */ }
    if(ob_get_length()) ob_clean();
    echo json_encode([
      'status'=>'ok',
      'report_type'=>'timesheet',
      'file_url'=>$fileUrl,
      'file_name'=>$fileName,
      'start_date'=>$startDate,
      'end_date'=>$endDate,
      'date_label'=>$dateLabel,
      'truncated'=>$truncated,
      'sensitive_masked'=>!$canViewSensitiveAttendance,
      'message'=>$truncated?('Range truncated to 32 days ending '.$endDate):'Timesheet generated successfully'
    ]);
    exit;
  } catch(Exception $e){ http_response_code(500); if(ob_get_length()) ob_clean(); echo json_encode(['status'=>'error','message'=>'Timesheet PDF failed: '.$e->getMessage()]); exit; }
} else {
  // Unknown type fallback generic placeholder
  $dir = __DIR__.'/../uploads/reports/daily-temp'; if(!is_dir($dir)) { @mkdir($dir, 0775, true); }
  $fileName = 'attendance_'.preg_replace('~[^0-9a-zA-Z_-]~','', $reportType).'_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.pdf';
  $filePath = $dir.'/'.$fileName; file_put_contents($filePath, "%PDF-1.4\n% Placeholder PDF generation pending implementation\n");
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $host = $_SERVER['HTTP_HOST'] ?? 'localhost'; $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/api'); $fileUrl = $scheme.'://'.$host.$basePath.'/uploads/reports/daily-temp/'.$fileName;
}

// Record metadata in session (short-term client state) and persist to DB (long-term)
if(!isset($_SESSION['generated_reports'])) { $_SESSION['generated_reports'] = []; }
$record = [
  'type' => $reportType,
  'type_label' => $typeLabel,
  'date_label' => $dateLabel,
  'branch' => $branch,
  'branch_label' => $branchLabel,
  'employees_label' => $employeesLabel,
  'employees_hidden' => $employeesHidden,
  'employees_raw' => empty($employeeIds)?'*':implode(',', $employeeIds),
  'generated_by' => (string)($_SESSION['fullName'] ?? ($_SESSION['user_id'] ?? 'Unknown')),
  'generated_at' => date('Y-m-d H:i:s'),
  'file_url' => $fileUrl
];
$_SESSION['generated_reports'][] = $record;
if(count($_SESSION['generated_reports'])>50){ $_SESSION['generated_reports'] = array_slice($_SESSION['generated_reports'], -50); }

// DB persistence (ignore errors silently if table not migrated yet)
try {
  if(isset($pdo)) {
    $stmt = $pdo->prepare("INSERT INTO generated_reports (user_id, generated_by, report_type, type_label, date_label, branch_id, branch_label, employees_label, employees_hidden, file_url, generated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
  isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null,
      $record['generated_by'],
      $reportType,
      $typeLabel,
      $dateLabel,
      $branch !== '' ? $branch : null,
      $branchLabel,
      $employeesLabel,
      $employeesHidden,
      $fileUrl,
      $record['generated_at']
    ]);
  }
} catch(Throwable $e) { /* optional: log error */ }

echo json_encode([
  'status'=>'ok',
  'file_url'=>$fileUrl,
  'sensitive_masked'=>!$canViewSensitiveAttendance
]);

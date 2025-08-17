<?php
require_once '../../../includes/session_config.php';
require_once '../../../includes/utilities.php';
include '../../../includes/db_connection.php';

// Token-based access (to avoid Unauthorized when session cookie not sent by browser for PDF window)
$incomingToken = $_POST['pdf_token'] ?? '';
if (!isset($_SESSION['user_id'])) {
  if (!isset($_SESSION['report_pdf_token']) || !$incomingToken || !hash_equals($_SESSION['report_pdf_token'], $incomingToken)) {
    http_response_code(403); exit('Unauthorized');
  }
}

$jsonRaw = $_POST['jsonData'] ?? '';
$reportDate = $_POST['reportdate'] ?? date('Y-m-d');
$empBranch = $_POST['empBranch'] ?? '';
if (!$jsonRaw) { exit('No data'); }
$rows = json_decode($jsonRaw, true);
if (!is_array($rows)) { exit('Bad JSON'); }

// Restore branch display in header
$branchName = 'All Branches';
if ($empBranch !== '') {
  $stmt = $pdo->prepare('SELECT name FROM branches WHERE id = ?');
  $stmt->execute([$empBranch]);
  $branchName = $stmt->fetchColumn() ?: $branchName;
}

require_once '../../../plugins/TCPDF/tcpdf.php';

// Custom PDF class for footer page numbers
class DailyPDF extends TCPDF {}
 $pdf = new DailyPDF('L', 'mm', 'A4');
$pdf->SetCreator('HRMS');
$pdf->SetAuthor('HRMS');
$pdf->SetTitle('Daily Attendance Report');
$pdf->SetMargins(8, 20, 8);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

$logoPath = realpath(__DIR__ . '/../../../resources/logo.png');
if ($logoPath && file_exists($logoPath)) {
  $pdf->Image($logoPath, 240, 6, 50);
}
$pdf->SetFont('helvetica','B',14);
$pdf->Cell(0,8,'Daily Attendance Report',0,1,'C');
$pdf->SetFont('helvetica','',10);
$pdf->Cell(0,6,'Date: '.$reportDate.'   Branch: '.$branchName,0,1,'C');
$pdf->Ln(2);

// Table headers

$pdf->SetFont('helvetica','B',9); // larger header font
$headers = ['SN','Employee','In','Out','Total','In','Out','Worked','OT','LateIn','EarlyOut','EarlyIn','LateOut','Status','Method','Remarks'];
// Relative widths rebalanced after removing Branch column
$rel = [7,55,14,14,14,14,14,16,14,14,16,14,14,24,20,34];
$pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
$relSum = array_sum($rel);
$widths = [];
foreach($rel as $r){ $widths[] = round($pageWidth * ($r / $relSum),2); }

// Grouped header row
$pdf->SetFont('helvetica','B',9);
$pdf->Cell($widths[0], 7, '', 1, 0, 'C');
$pdf->Cell($widths[1], 7, 'Employee', 1, 0, 'C');
$pdf->Cell($widths[2]+$widths[3]+$widths[4], 7, 'Planned Schedule', 1, 0, 'C');
$pdf->Cell($widths[5]+$widths[6]+$widths[7]+$widths[8], 7, 'Actual Attendance', 1, 0, 'C');
$pdf->Cell($widths[9]+$widths[10]+$widths[11]+$widths[12], 7, 'Deviations', 1, 0, 'C');
$pdf->Cell($widths[13]+$widths[14]+$widths[15], 7, 'Status Summary', 1, 0, 'C');
$pdf->Ln();

// Detail header row
foreach($headers as $i=>$h){ $pdf->Cell($widths[$i],6,$h,1,0,'C'); }
$pdf->Ln();

$bodyFontSize = 9;
$pdf->SetFont('helvetica','', $bodyFontSize);
$sn=1;
foreach($rows as $r){
  $line = [
    $sn++,
    $r['emp_id'].' - '.$r['employee_name'],
    $r['scheduled_in'],
    $r['scheduled_out'],
    $r['working_hour'],
    $r['in_time'],
    $r['out_time'],
    $r['worked_duration'],
    $r['over_time'],
    $r['late_in'],
    $r['early_out'],
    $r['early_in'],
    $r['late_out'],
    $r['marked_as'],
    strip_tags($r['methods']),
    strip_tags($r['remarks'])
  ];

  // Row printing with rudimentary wrapping for long Employee / Remarks
  $rowHeights = [];
  $prepared = [];
  foreach($line as $i=>$val){
    $text = (string)$val;
    if($i==1 || $i==16){ // Employee or Remarks
      $text = wordwrap($text, 50, "\n", true);
    }
    $prepared[$i] = $text;
    $rowHeights[$i] = $pdf->getStringHeight($widths[$i], $text);
  }
  // Calculate required height for wrapped content using getStringHeight
  $heights = [];
  foreach($prepared as $i=>$text){
    $heights[$i] = $pdf->getStringHeight($widths[$i], $text);
  }
  $rowHeight = max($heights);
  if ($rowHeight < 5.2) { $rowHeight = 5.2; }

  $yStart = $pdf->GetY();
  $xStart = $pdf->GetX();
  foreach($prepared as $i=>$text){
    // Employee column left, all others (including Remarks) centered
    $align = ($i==1) ? 'L' : 'C';
    $pdf->MultiCell($widths[$i], $rowHeight, $text, 1, $align, false, 0);
    // Move cursor to right for next cell (MultiCell moves to next line by default when ln=1 or 0?)
    $xStart += $widths[$i];
    $pdf->SetXY($xStart, $yStart);
  }
  // Set Y to end of row
  $pdf->SetY($yStart + $rowHeight);
}

// Compute summary counts
$present=$absent=$leave=0;foreach($rows as $r){ if(strpos($r['marked_as'],'Present')!==false)$present++; elseif($r['marked_as']=='Absent')$absent++; elseif($r['marked_as']=='Leave')$leave++; }

// Footer summary row inside table structure (without generated timestamp)
$pdf->SetFont('helvetica','B',10);
$footerHeight = 6;
// Summary label (SN + Employee)
$pdf->Cell($widths[0]+$widths[1], $footerHeight, 'Summary', 1, 0, 'R');
// Planned schedule block: total employees
$pdf->Cell($widths[2]+$widths[3]+$widths[4], $footerHeight, 'Total Employees: '.count($rows), 1, 0, 'C');
// Actual attendance block: present
$pdf->Cell($widths[5]+$widths[6]+$widths[7]+$widths[8], $footerHeight, 'Total Present: '.$present, 1, 0, 'C');
// Deviations block split: Absent + Leave (reuse widths[9]-[12])
$pdf->Cell($widths[9]+$widths[10], $footerHeight, 'Absent: '.$absent, 1, 0, 'C');
$pdf->Cell($widths[11]+$widths[12], $footerHeight, 'On Leave: '.$leave, 1, 0, 'C');
// Remaining cells (Status + Method + Remarks) merged empty (spacer)
$pdf->Cell($widths[13]+$widths[14]+$widths[15], $footerHeight, '', 1, 0, 'C');
$pdf->Ln();

// Generated timestamp below table, right-aligned
$pdf->Ln(1);
$pdf->SetFont('helvetica','',8);
// Get timezone from settings if available, otherwise use default
$timezone = 'UTC'; // Default fallback
$stmt = $pdo->prepare("SELECT value FROM settings WHERE setting_key = 'timezone'");
if ($stmt && $stmt->execute() && $result = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $timezone = $result['value'];
}
date_default_timezone_set($timezone);
$generatedAt = date('Y-m-d h:i A');
$pdf->Cell(0, 5, 'Generated on: ' . $generatedAt, 0, 1, 'R');

// Persist PDF to a temporary public location so reloading does not regenerate.
$rootPath = realpath(__DIR__ . '/../../../');
$tempDir = $rootPath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'daily-temp';
if (!is_dir($tempDir)) {
  @mkdir($tempDir, 0755, true);
}
if (!is_writable($tempDir)) {
  // Fallback to system temp if not writable
  $tempDir = sys_get_temp_dir();
}

// Cleanup: keep PDFs for 30 days (2592000 seconds)
if (is_dir($tempDir)) {
  $nowTs = time();
  $ttl = 2592000; // 30 days
  foreach (glob($tempDir . DIRECTORY_SEPARATOR . 'daily_report_*.pdf') as $oldFile) {
    if (is_file($oldFile) && ($nowTs - filemtime($oldFile)) > $ttl) {
      @unlink($oldFile);
    }
  }
}

$safeBranch = preg_replace('/[^A-Za-z0-9_-]+/', '_', $branchName);
$filename = 'daily_report_' . $reportDate . '_' . $safeBranch . '_' . uniqid() . '.pdf';
$filePath = rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

// Save file
$pdf->Output($filePath, 'F');

// Build absolute URL for redirect
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Determine web-accessible relative path if within project root
$relativeUrlPath = null;
if (strpos($filePath, $rootPath) === 0) {
  $relativeUrlPath = str_replace('\\', '/', substr($filePath, strlen($rootPath))); // remove root
  if (strlen($relativeUrlPath) && $relativeUrlPath[0] !== '/') { $relativeUrlPath = '/' . $relativeUrlPath; }
}
if (!$relativeUrlPath) {
  // Fallback: no relative mapping; output direct message
  header('Content-Type: application/json');
  echo json_encode(['file'=> $filePath, 'generated'=>$generatedAt]);
  exit;
}

$absoluteUrl = $scheme . '://' . $host . $relativeUrlPath;

if ((isset($_POST['return']) && $_POST['return'] === 'json') || (isset($_GET['return']) && $_GET['return']==='json')) {
  header('Content-Type: application/json');
  echo json_encode([
    'status' => 'ok',
    'report_type' => 'daily',
    'date' => $reportDate,
    'branch' => $branchName,
    'file_url' => $absoluteUrl,
    'file_path' => $filePath,
    'generated_at' => $generatedAt,
    'present' => $present,
    'absent' => $absent,
    'leave' => $leave
  ]);
  exit;
}

header('Location: ' . $absoluteUrl);
exit;

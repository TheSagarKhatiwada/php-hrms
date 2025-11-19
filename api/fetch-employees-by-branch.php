<?php
header('Content-Type: application/json');
require_once '../includes/session_config.php';
require_once '../includes/db_connection.php';
require_once '../includes/csrf_protection.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'CSRF token missing']);
    exit;
  }
  verify_csrf_token($_POST['csrf_token']);
}

// NOTE: The employees table in the rest of the codebase uses columns `emp_id` (employee code/id)
// and `branch` (foreign key to branches.id).

$branch = $_POST['branch'] ?? '';
$reportType = $_POST['report_type'] ?? '';
$dateStr = $_POST['date'] ?? '';
$rangeStr = $_POST['range'] ?? '';

// Helper: accept DD/MM/YYYY or YYYY-MM-DD and normalize to YYYY-MM-DD
function parse_dmy($s){
  if(!$s) return null;
  if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
  $parts = explode('/', $s);
  if(count($parts)===3){
    [$d,$m,$y] = $parts;
    if(ctype_digit($d) && ctype_digit($m) && ctype_digit($y) && checkdate((int)$m,(int)$d,(int)$y)){
      return sprintf('%04d-%02d-%02d',$y,$m,$d);
    }
  }
  return null;
}

// Determine cutoff for exit filtering
$asOfDate = null;        // daily as-of (YYYY-MM-DD)
$rangeStart = null;      // periodic/timesheet start (YYYY-MM-DD)
$rangeEnd = null;        // periodic/timesheet end (YYYY-MM-DD)
if($reportType === 'daily'){
  $asOfDate = parse_dmy($dateStr) ?: null;
} elseif($reportType === 'periodic' || $reportType === 'timesheet'){
  if($rangeStr && strpos($rangeStr,'-')!==false){
    $parts = array_map('trim', explode('-', $rangeStr));
    if(count($parts)===2){
  $rangeStart = parse_dmy($parts[0]) ?: null;
  $rangeEnd = parse_dmy($parts[1]) ?: null;
    }
  }
}
// If both start and end exist and are inverted, swap to be safe
if($rangeStart && $rangeEnd && strcmp($rangeEnd, $rangeStart) < 0){
  $tmp = $rangeStart; $rangeStart = $rangeEnd; $rangeEnd = $tmp;
}

try {
  $sql = "SELECT emp_id, CONCAT(first_name,' ',last_name) AS name FROM employees";
  $where = [];
  $params = [];
  if($branch !== ''){ $where[] = 'branch = :branch'; $params[':branch'] = $branch; }
  $where[] = '(mach_id_not_applicable IS NULL OR mach_id_not_applicable = 0)';
  // Exclude employees whose exit_date is before the selected date/range; include same-day exits
  // Also exclude employees who joined after the selected date (daily) or after the range end (periodic/timesheet)
  if($asOfDate){
    // Build start/end-of-day bounds for DATETIME-safe comparisons
    $asOfStart = $asOfDate . ' 00:00:00';
    $asOfEnd   = $asOfDate . ' 23:59:59';
    // join_date must be on/before the end of the day
    $where[] = '(
      join_date IS NULL OR join_date = \'\' OR join_date = \'0000-00-00\' OR join_date = \'0000-00-00 00:00:00\' OR join_date <= :asOfEnd
    )';
    // exit_date must be on/after the start of the day (same-day exits included)
    $where[] = '(
      exit_date IS NULL OR exit_date = \'\' OR exit_date = \'0000-00-00\' OR exit_date = \'0000-00-00 00:00:00\' OR exit_date >= :asOfStart
    )';
    $params[':asOfStart'] = $asOfStart;
    $params[':asOfEnd']   = $asOfEnd;
  } elseif($rangeStart){
    // Build start/end-of-day bounds for the range
    $rangeStartStart = $rangeStart . ' 00:00:00';
    $where[] = '(
      exit_date IS NULL OR exit_date = \'\' OR exit_date = \'0000-00-00\' OR exit_date = \'0000-00-00 00:00:00\' OR exit_date >= :rangeStartStart
    )';
    $params[':rangeStartStart'] = $rangeStartStart;
    if($rangeEnd){
      $rangeEndEnd = $rangeEnd . ' 23:59:59';
      $where[] = '(
        join_date IS NULL OR join_date = \'\' OR join_date = \'0000-00-00\' OR join_date = \'0000-00-00 00:00:00\' OR join_date <= :rangeEndEnd
      )';
      $params[':rangeEndEnd'] = $rangeEndEnd;
    }
  }
  if($where){ $sql .= ' WHERE '.implode(' AND ', $where); }
  $sql .= ' ORDER BY first_name, last_name LIMIT 500';

  $stmt = $pdo->prepare($sql);
  foreach($params as $k=>$v){
    if($k === ':branch'){
      $stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
    } else {
      $stmt->bindValue($k, $v);
    }
  }
  $stmt->execute();

  $out = [];
  while($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $out[] = $r; }
  echo json_encode($out);
} catch(Exception $e) {
  // On error return an empty list (frontend handles gracefully)
  echo json_encode([]);
}

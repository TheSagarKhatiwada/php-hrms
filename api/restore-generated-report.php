<?php
// Restore a soft-deleted generated report
require_once __DIR__.'/../includes/session_config.php';
require_once __DIR__.'/../includes/db_connection.php';
require_once __DIR__.'/../includes/utilities.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit; }

$currentUserId = (string)$_SESSION['user_id'];
$isAdmin = function_exists('is_admin') ? is_admin() : false;
$canManageArtifacts = $isAdmin || has_permission('manage_attendance_report_artifacts');
$canViewAllBranchAttendance = $isAdmin || has_permission('view_all_branch_attendance');
$branchLimitValue = null;
if($canManageArtifacts && !$canViewAllBranchAttendance){
  $branchContext = hrms_get_user_branch_context($pdo, $currentUserId);
  if($branchContext['legacy'] !== null){ $branchLimitValue = (string)$branchContext['legacy']; }
  elseif($branchContext['numeric'] !== null){ $branchLimitValue = (string)$branchContext['numeric']; }
  if($branchLimitValue === null){ http_response_code(403); echo json_encode(['status'=>'error','message'=>'Branch access is not configured for your account.']); exit; }
}

$id = (int)($_POST['id'] ?? 0);
if($id<=0){ http_response_code(400); echo json_encode(['status'=>'error','message'=>'Invalid id']); exit; }

try {
  // Ensure soft delete columns exist (auto-migrate)
  try {
    $chk = $pdo->query("SHOW COLUMNS FROM generated_reports LIKE 'deleted_at'");
    if(!$chk || !$chk->fetch()){
      $pdo->exec("ALTER TABLE generated_reports ADD COLUMN deleted_at DATETIME NULL AFTER generated_at, ADD COLUMN purge_at DATETIME NULL AFTER deleted_at, ADD INDEX idx_deleted_at (deleted_at)");
    }
  } catch(Throwable $e) { /* ignore */ }

  $stmt = $pdo->prepare('SELECT user_id, deleted_at, branch_id FROM generated_reports WHERE id=?');
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if(!$row){ http_response_code(404); echo json_encode(['status'=>'error','message'=>'Not found']); exit; }
  if(!$canManageArtifacts){ http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }
  if(!$canViewAllBranchAttendance){
    $rowBranch = isset($row['branch_id']) ? (string)$row['branch_id'] : null;
    if($branchLimitValue === null || $rowBranch === null || $rowBranch !== $branchLimitValue){ http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }
  }
  if(empty($row['deleted_at'])){ echo json_encode(['status'=>'ok','message'=>'Not deleted']); exit; }
  try { $chk2=$pdo->query("SHOW COLUMNS FROM generated_reports LIKE 'deleted_by'"); if(!$chk2||!$chk2->fetch()){ $pdo->exec("ALTER TABLE generated_reports ADD COLUMN deleted_by VARCHAR(50) NULL AFTER deleted_at"); } } catch(Throwable $e) { }
  try { $chk3=$pdo->query("SHOW COLUMNS FROM generated_reports LIKE 'deleted_by_name'"); if(!$chk3||!$chk3->fetch()){ $pdo->exec("ALTER TABLE generated_reports ADD COLUMN deleted_by_name VARCHAR(150) NULL AFTER deleted_by"); } } catch(Throwable $e) { }
  $pdo->prepare('UPDATE generated_reports SET deleted_at=NULL, deleted_by=NULL, deleted_by_name=NULL, purge_at=NULL WHERE id=?')->execute([$id]);
  if($canManageArtifacts){
    $logFile = realpath(__DIR__.'/..').'/logs/attendance_reports_purge.log';
    $line = sprintf("%s\tRESTORE\tID:%d\tUSER:%s\tROLE:%s\n", date('Y-m-d H:i:s'), $id, ($_SESSION['user_id'] ?? 'unknown'), $isAdmin ? 'admin' : 'manager');
    @file_put_contents($logFile, $line, FILE_APPEND|LOCK_EX);
  }
  echo json_encode(['status'=>'ok','restored'=>true]);
} catch(Throwable $e){
  http_response_code(500); echo json_encode(['status'=>'error','message'=>'Restore failed']);
}

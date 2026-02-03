<?php
// Delete a generated report (metadata + optionally file)
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
  $stmt = $pdo->prepare('SELECT user_id, file_url, deleted_at, branch_id FROM generated_reports WHERE id=?');
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if(!$row){ http_response_code(404); echo json_encode(['status'=>'error','message'=>'Not found']); exit; }

  // Authorization: allow if admin or owner
  $ownerId = isset($row['user_id']) ? (string)$row['user_id'] : '';
  $rowBranch = isset($row['branch_id']) ? (string)$row['branch_id'] : null;
  $isOwner = ($ownerId !== '' && $ownerId === $currentUserId);
  if(!$isOwner){
    if(!$canManageArtifacts){ http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }
    if(!$canViewAllBranchAttendance){
      if($branchLimitValue === null || $rowBranch === null || $rowBranch !== $branchLimitValue){
      http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }
    }
  }

  // Attempt to map URL to local path
  $filePath = null;
  $urlPath = parse_url($row['file_url'], PHP_URL_PATH);
  if($urlPath){
    $root = realpath(__DIR__.'/..');
    $candidate = realpath($root.$urlPath);
    if($candidate && strpos($candidate,$root)===0 && is_file($candidate)) { $filePath = $candidate; }
  }

  // Detect if soft delete columns exist; fallback to hard delete if not migrated yet
  $hasSoft = false;
  try {
    $chk = $pdo->query("SHOW COLUMNS FROM generated_reports LIKE 'deleted_at'");
    if($chk && $chk->fetch()) $hasSoft = true; else $hasSoft = false;
  } catch(Throwable $e){ $hasSoft = false; }

  if(!$hasSoft){
    // Attempt to add soft delete columns automatically
    try {
      $pdo->exec("ALTER TABLE generated_reports ADD COLUMN deleted_at DATETIME NULL AFTER generated_at, ADD COLUMN purge_at DATETIME NULL AFTER deleted_at, ADD COLUMN deleted_by VARCHAR(50) NULL AFTER deleted_at, ADD INDEX idx_deleted_at (deleted_at)");
      $hasSoft = true;
    } catch(Throwable $e) { /* ignore */ }
  }
  if($hasSoft){
    if(!empty($row['deleted_at'])){
      echo json_encode(['status'=>'ok','message'=>'Already deleted']);
      exit;
    }
    $pdo->beginTransaction();
  // Ensure deleted_by column exists
  try {
    $chk2=$pdo->query("SHOW COLUMNS FROM generated_reports LIKE 'deleted_by'"); $needName=false;
    if(!$chk2||!$chk2->fetch()){
      $pdo->exec("ALTER TABLE generated_reports ADD COLUMN deleted_by VARCHAR(50) NULL AFTER deleted_at");
      $needName=true;
    }
    $chk3=$pdo->query("SHOW COLUMNS FROM generated_reports LIKE 'deleted_by_name'"); if(!$chk3||!$chk3->fetch()){
      $pdo->exec("ALTER TABLE generated_reports ADD COLUMN deleted_by_name VARCHAR(150) NULL AFTER deleted_by");
    }
  } catch(Throwable $e) { }
  $deletedBy = ($_SESSION['user_id'] ?? null);
  $deletedByName = ($_SESSION['fullName'] ?? $deletedBy);
  $pdo->prepare('UPDATE generated_reports SET deleted_at=NOW(), deleted_by=?, deleted_by_name=?, purge_at=DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id=?')->execute([$deletedBy, $deletedByName, $id]);
    $pdo->commit();
    if($canManageArtifacts){
      $logFile = realpath(__DIR__.'/..').'/logs/attendance_reports_purge.log';
      $line = sprintf("%s\tSOFT_DELETE\tID:%d\tUSER:%s\tROLE:%s\tPURGE_AT:+30d\n", date('Y-m-d H:i:s'), $id, ($_SESSION['user_id'] ?? 'unknown'), $isAdmin ? 'admin' : 'manager');
      @file_put_contents($logFile, $line, FILE_APPEND|LOCK_EX);
    }
    echo json_encode(['status'=>'ok','soft_deleted'=>true,'purge_in_days'=>30]);
  } else {
    // Hard delete fallback
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM generated_reports WHERE id=?')->execute([$id]);
    $pdo->commit();
    if($filePath) { @unlink($filePath); }
    echo json_encode(['status'=>'ok','deleted'=>true,'soft_delete_supported'=>false]);
  }
} catch(Throwable $e) {
  try { if($pdo->inTransaction()) $pdo->rollBack(); } catch(Throwable $e2) {}
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'Delete failed']);
}

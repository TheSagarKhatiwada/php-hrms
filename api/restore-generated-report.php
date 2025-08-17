<?php
// Restore a soft-deleted generated report
require_once __DIR__.'/../includes/session_config.php';
require_once __DIR__.'/../includes/db_connection.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Not authenticated']); exit; }

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

  $stmt = $pdo->prepare('SELECT user_id, deleted_at FROM generated_reports WHERE id=?');
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if(!$row){ http_response_code(404); echo json_encode(['status'=>'error','message'=>'Not found']); exit; }
  $isAdmin = (($_SESSION['user_role'] ?? null)==1) || (($_SESSION['user_role_id'] ?? null)==1);
  if(!$isAdmin){ http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }
  if(empty($row['deleted_at'])){ echo json_encode(['status'=>'ok','message'=>'Not deleted']); exit; }
  try { $chk2=$pdo->query("SHOW COLUMNS FROM generated_reports LIKE 'deleted_by'"); if(!$chk2||!$chk2->fetch()){ $pdo->exec("ALTER TABLE generated_reports ADD COLUMN deleted_by VARCHAR(50) NULL AFTER deleted_at"); } } catch(Throwable $e) { }
  try { $chk3=$pdo->query("SHOW COLUMNS FROM generated_reports LIKE 'deleted_by_name'"); if(!$chk3||!$chk3->fetch()){ $pdo->exec("ALTER TABLE generated_reports ADD COLUMN deleted_by_name VARCHAR(150) NULL AFTER deleted_by"); } } catch(Throwable $e) { }
  $pdo->prepare('UPDATE generated_reports SET deleted_at=NULL, deleted_by=NULL, deleted_by_name=NULL, purge_at=NULL WHERE id=?')->execute([$id]);
  if($isAdmin){
    $logFile = realpath(__DIR__.'/..').'/logs/attendance_reports_purge.log';
    $line = sprintf("%s\tRESTORE\tID:%d\tUSER:%s\tROLE:admin\n", date('Y-m-d H:i:s'), $id, ($_SESSION['user_id'] ?? 'unknown'));
    @file_put_contents($logFile, $line, FILE_APPEND|LOCK_EX);
  }
  echo json_encode(['status'=>'ok','restored'=>true]);
} catch(Throwable $e){
  http_response_code(500); echo json_encode(['status'=>'error','message'=>'Restore failed']);
}

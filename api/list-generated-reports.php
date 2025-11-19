<?php
// Returns paginated list of generated reports (session + DB) with optional filters
// Query params: page, per_page, type, search, date_from, date_to, show_deleted
header('Content-Type: application/json');
// Use unified session configuration to ensure we read the same session (was raw session_start causing missing user context)
require_once __DIR__.'/../includes/session_config.php';
require_once __DIR__.'/../includes/db_connection.php';
require_once __DIR__.'/../includes/utilities.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 10); if($perPage<5) $perPage=5; if($perPage>100) $perPage=100;
$type = trim($_GET['type'] ?? '');
$search = trim($_GET['search'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$showDeleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';

$isAdmin = function_exists('is_admin') ? is_admin() : false;
if(!$isAdmin){ $showDeleted=false; }

// Build DB query
$where = [];$params=[];
if($type!==''){ $where[]='report_type = ?'; $params[]=$type; }
if($search!==''){ $like='%'.$search.'%'; $where[]='(type_label LIKE ? OR date_label LIKE ? OR branch_label LIKE ? OR employees_label LIKE ? OR generated_by LIKE ?)'; $params=array_merge($params,[$like,$like,$like,$like,$like]); }
if($dateFrom!==''){ $where[]='DATE(generated_at) >= ?'; $params[]=$dateFrom; }
if($dateTo!==''){ $where[]='DATE(generated_at) <= ?'; $params[]=$dateTo; }
if(!$showDeleted){ $where[]='deleted_at IS NULL'; }
$whereSql = $where?('WHERE '.implode(' AND ',$where)) : '';

// Count
$total=0; try { $stmt=$pdo->prepare("SELECT COUNT(*) FROM generated_reports $whereSql"); $stmt->execute($params); $total=(int)$stmt->fetchColumn(); } catch(Throwable $e){ /* table may not exist */ }

$pages = $total? (int)ceil($total/$perPage) : 1; if($page>$pages) $page=$pages;
$offset = ($page-1)*$perPage;
$rows=[];
if($total){
	try {
		$stmt=$pdo->prepare("SELECT * FROM generated_reports $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset");
		$stmt->execute($params);
		$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
	} catch(Throwable $e){ $rows=[]; }
}

// Enrich avatars (join employees table if available)
if($rows){
	$userIds = [];$deletedByIds=[];
	foreach($rows as $r){ if(!empty($r['user_id'])) $userIds[]=(int)$r['user_id']; if(!empty($r['deleted_by'])) $deletedByIds[]=(int)$r['deleted_by']; }
	$userIds = array_values(array_unique($userIds));
	$deletedByIds = array_values(array_unique($deletedByIds));
	$avatars=[]; $names=[];
	if($userIds){
		try { $in=implode(',',array_fill(0,count($userIds),'?')); $st=$pdo->prepare("SELECT emp_id, employee_name, avatar FROM employees WHERE emp_id IN ($in)"); $st->execute($userIds); while($row=$st->fetch(PDO::FETCH_ASSOC)){ $avatars[$row['emp_id']]=$row['avatar']; $names[$row['emp_id']]=$row['employee_name']; } } catch(Throwable $e){}
	}
	$delAvatars=[]; $delNames=[];
	if($deletedByIds){
		try { $in=implode(',',array_fill(0,count($deletedByIds),'?')); $st=$pdo->prepare("SELECT emp_id, employee_name, avatar FROM employees WHERE emp_id IN ($in)"); $st->execute($deletedByIds); while($row=$st->fetch(PDO::FETCH_ASSOC)){ $delAvatars[$row['emp_id']]=$row['avatar']; $delNames[$row['emp_id']]=$row['employee_name']; } } catch(Throwable $e){}
	}
	foreach($rows as &$r){
		$uid = (int)($r['user_id']??0);
		if($uid && isset($avatars[$uid])){ $r['generated_by_avatar'] = resolve_avatar_path($avatars[$uid]); }
		if($uid && isset($names[$uid]) && empty($r['generated_by'])){ $r['generated_by']=$names[$uid]; }
		if(!empty($r['deleted_by'])){
			$dbi=(int)$r['deleted_by'];
			if(isset($delAvatars[$dbi])) $r['deleted_by_avatar']=resolve_avatar_path($delAvatars[$dbi]);
			if(isset($delNames[$dbi]) && empty($r['deleted_by_name'])) $r['deleted_by_name']=$delNames[$dbi];
		}
	}
	unset($r);
}

// Add authorization flags per row
foreach($rows as &$rr){
	// user_id column is VARCHAR (emp id style). Prior logic cast to int which broke ownership checks for non-numeric IDs.
	$owner = isset($rr['user_id']) ? (string)$rr['user_id'] : '';
	$currentUser = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : '';
	$rr['can_delete'] = $isAdmin || ($owner !== '' && $owner === $currentUser);
	$rr['can_restore'] = $isAdmin && !empty($rr['deleted_at']);
	$rr['can_purge'] = $isAdmin && !empty($rr['deleted_at']);
}
unset($rr);
echo json_encode([
	'status'=>'ok',
	'data'=>$rows,
	'pagination'=>[
		'page'=>$page,
		'per_page'=>$perPage,
		'total'=>$total,
		'pages'=>$pages
	],
	'show_deleted'=>$showDeleted,
	'is_admin'=>$isAdmin ? true : false
]);
exit;

function resolve_avatar_path($path){
	if(!$path) return '';
	if(preg_match('~^https?://~i',$path)) return $path;
	$rel = ltrim($path,'/');
	return '../../'.$rel; // relative for front-end table (from modules/reports/)
}
?>

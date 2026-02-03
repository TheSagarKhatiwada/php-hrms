<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/utilities.php';

$emp = isset($_POST['emp_id']) ? trim($_POST['emp_id']) : '';
$date = isset($_POST['date']) ? trim($_POST['date']) : '';

if (!$emp || !$date) {
    echo json_encode(['status'=>'error','message'=>'Missing parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM attendance_logs WHERE emp_id = ? AND date = ? ORDER BY time ASC');
    $stmt->execute([$emp, $date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status'=>'ok','data'=>$rows]);
} catch (PDOException $e) {
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}

?>

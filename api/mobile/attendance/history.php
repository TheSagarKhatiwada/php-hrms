<?php
require_once __DIR__ . '/../../../includes/mobile_api.php';

header('Content-Type: application/json');

$auth = mobile_require_auth($pdo);
$empId = $auth['employee_id'];

$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;

if ($start && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
    $start = null;
}
if ($end && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    $end = null;
}

if (!$start || !$end) {
    $end = date('Y-m-d');
    $start = date('Y-m-d', strtotime('-' . max(1, $days) . ' days'));
}

$stmt = $pdo->prepare("SELECT date,
        MIN(time) AS in_time,
        CASE WHEN COUNT(*) > 1 THEN MAX(time) ELSE NULL END AS out_time,
        COUNT(*) AS punch_count
    FROM attendance_logs
    WHERE emp_id = :emp AND date BETWEEN :start AND :end
    GROUP BY date
    ORDER BY date DESC");
$stmt->execute([':emp' => $empId, ':start' => $start, ':end' => $end]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode([
    'success' => true,
    'start' => $start,
    'end' => $end,
    'records' => $rows
]);

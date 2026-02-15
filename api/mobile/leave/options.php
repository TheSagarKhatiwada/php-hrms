<?php
require_once __DIR__ . '/../../../includes/mobile_api.php';

header('Content-Type: application/json');

$auth = mobile_require_auth($pdo);
$empId = $auth['employee_id'];
$year = (int)date('Y');

$types = [];
$balances = [];

try {
    $stmt = $pdo->query("SELECT id, name, code FROM leave_types WHERE is_active = 1 ORDER BY name");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $types = [];
}

try {
    $stmt = $pdo->prepare("SELECT lb.leave_type_id, lb.allocated_days, lb.used_days, lb.remaining_days, lt.name
        FROM leave_balances lb
        INNER JOIN leave_types lt ON lb.leave_type_id = lt.id
        WHERE lb.employee_id = :emp AND lb.year = :year");
    $stmt->execute([':emp' => $empId, ':year' => $year]);
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $balances = [];
}

echo json_encode([
    'success' => true,
    'types' => $types,
    'balances' => $balances,
]);

<?php
require_once __DIR__ . '/../../../includes/mobile_api.php';
require_once __DIR__ . '/../../../includes/reason_helpers.php';

header('Content-Type: application/json');

$auth = mobile_require_auth($pdo);
$empId = $auth['employee_id'];

$isAdmin = mobile_is_admin($pdo, $empId);
$canRequest = $isAdmin || mobile_has_permission($pdo, $empId, 'request_attendance');
$canRequestForOthers = $isAdmin || mobile_has_permission($pdo, $empId, 'request_attendance_for_others');
$canRequestMultiBranch = $isAdmin || mobile_has_permission($pdo, $empId, 'request_attendance_multi_branch');

$branchId = $_GET['branch_id'] ?? null;
$branchId = $branchId !== null && $branchId !== '' ? (int)$branchId : null;

$branches = [];
$employees = [];

$ownBranchId = null;
try {
    $stmt = $pdo->prepare('SELECT branch_id, branch FROM employees WHERE emp_id = :emp LIMIT 1');
    $stmt->execute([':emp' => $empId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $ownBranchId = !empty($row['branch_id']) ? (int)$row['branch_id'] : (int)($row['branch'] ?? 0);
    }
} catch (Throwable $e) {
    $ownBranchId = null;
}

if ($canRequestMultiBranch) {
    try {
        $stmt = $pdo->query('SELECT id, name FROM branches ORDER BY name');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $branches[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
            ];
        }
    } catch (Throwable $e) {
        $branches = [];
    }
} elseif ($ownBranchId) {
    $branches[] = ['id' => $ownBranchId, 'name' => 'My Branch'];
    $branchId = $ownBranchId;
}

$resolvedBranchId = $branchId;
if (!$canRequestMultiBranch) {
    $resolvedBranchId = $ownBranchId;
}

$branchCode = null;
if ($resolvedBranchId) {
    try {
        $codeStmt = $pdo->prepare('SELECT code FROM branches WHERE id = :id LIMIT 1');
        $codeStmt->execute([':id' => $resolvedBranchId]);
        $branchCode = $codeStmt->fetchColumn() ?: null;
    } catch (Throwable $e) {
        $branchCode = null;
    }
}

try {
    $sql = 'SELECT emp_id, CONCAT(first_name, " ", last_name) AS name FROM employees WHERE status = :status AND login_access = 1';
    $params = [':status' => 'active'];
    if ($resolvedBranchId) {
        $sql .= ' AND (branch_id = :branchId OR branch = :branchId';
        $params[':branchId'] = $resolvedBranchId;
        if (!empty($branchCode)) {
            $sql .= ' OR branch = :branchCode';
            $params[':branchCode'] = $branchCode;
        }
        $sql .= ')';
    }
    $sql .= ' ORDER BY first_name, last_name LIMIT 300';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $employees[] = [
            'emp_id' => $row['emp_id'],
            'name' => trim($row['name']),
        ];
    }
} catch (Throwable $e) {
    $employees = [];
}

if (!$canRequestForOthers) {
    $employees = array_values(array_filter($employees, function ($row) use ($empId) {
        return (string)$row['emp_id'] === (string)$empId;
    }));
}

$reasonMap = function_exists('hrms_reason_label_map') ? hrms_reason_label_map() : [];
$reasons = [];
foreach ($reasonMap as $code => $label) {
    $reasons[] = [
        'code' => (string)$code,
        'label' => (string)$label,
    ];
}

echo json_encode([
    'success' => true,
    'can_request' => $canRequest,
    'can_request_for_others' => $canRequestForOthers,
    'can_request_multi_branch' => $canRequestMultiBranch,
    'branch_id' => $resolvedBranchId,
    'branches' => $branches,
    'employees' => $employees,
    'reasons' => $reasons,
]);

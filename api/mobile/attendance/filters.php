<?php
require_once __DIR__ . '/../../../includes/mobile_api.php';

header('Content-Type: application/json');

$auth = mobile_require_auth($pdo);
$empId = $auth['employee_id'];

$isAdmin = mobile_is_admin($pdo, $empId);
$canViewAllBranches = $isAdmin || mobile_has_permission($pdo, $empId, 'view_all_branch_attendance');
$canViewEmployees = $isAdmin || mobile_has_permission($pdo, $empId, 'view_employees');

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

if ($canViewAllBranches) {
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
}

$resolvedBranchId = $branchId;
if (!$canViewAllBranches) {
    $resolvedBranchId = $ownBranchId;
}

if ($canViewEmployees) {
    try {
        $sql = 'SELECT emp_id, CONCAT(first_name, " ", last_name) AS name FROM employees';
        $params = [];
        if ($resolvedBranchId) {
            $sql .= ' WHERE branch = :branch OR branch_id = :branch';
            $params[':branch'] = $resolvedBranchId;
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
}

echo json_encode([
    'success' => true,
    'can_view_all_branches' => $canViewAllBranches,
    'can_view_employees' => $canViewEmployees,
    'branch_id' => $resolvedBranchId,
    'branches' => $branches,
    'employees' => $employees,
]);

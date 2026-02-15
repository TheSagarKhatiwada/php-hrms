<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/utilities.php';
require_once __DIR__ . '/../includes/configuration.php';
require_once __DIR__ . '/../includes/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$canManageEmployees = is_admin() || has_permission('manage_employees');
$hasAllBranchAccessPermission = $canManageEmployees || has_permission('access_all_branch_employee');
$canViewEmployees = $hasAllBranchAccessPermission || has_permission('view_employees');

if (!$canViewEmployees) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$empId = $_GET['empId'] ?? '';
if ($empId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing employee id']);
    exit;
}

$locationDate = $_GET['loc_date'] ?? date('Y-m-d');
$locationDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $locationDate) ? $locationDate : date('Y-m-d');
$todayDate = date('Y-m-d');
if ($locationDate > $todayDate) {
    $locationDate = $todayDate;
}

$limitToUserBranch = !$hasAllBranchAccessPermission;
if ($limitToUserBranch) {
    $viewerBranchContext = ['legacy' => null, 'numeric' => null];
    try {
        $branchLookup = $pdo->prepare("SELECT branch, branch_id FROM employees WHERE emp_id = :emp_id LIMIT 1");
        $branchLookup->execute([':emp_id' => $_SESSION['user_id']]);
        $branchRow = $branchLookup->fetch(PDO::FETCH_ASSOC);
        if ($branchRow) {
            $viewerBranchContext = hrms_resolve_branch_assignment($branchRow['branch'] ?? null, $branchRow['branch_id'] ?? null);
        }
    } catch (PDOException $e) {
        // Leave context empty
    }

    try {
        $empBranchStmt = $pdo->prepare("SELECT branch, branch_id FROM employees WHERE emp_id = :emp_id LIMIT 1");
        $empBranchStmt->execute([':emp_id' => $empId]);
        $empBranchRow = $empBranchStmt->fetch(PDO::FETCH_ASSOC);
        if (!$empBranchRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit;
        }
        $employeeBranchContext = hrms_resolve_branch_assignment($empBranchRow['branch'] ?? null, $empBranchRow['branch_id'] ?? null);
        $isViewingSelf = isset($_SESSION['user_id']) && $_SESSION['user_id'] === $empId;
        if (!$isViewingSelf && !hrms_employee_matches_branch($viewerBranchContext, $employeeBranchContext)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to verify access']);
        exit;
    }
}

$location_logs = [];
try {
    $locStmt = $pdo->prepare("SELECT latitude, longitude, accuracy_meters, created_at
                FROM location_logs
                WHERE employee_id = :emp
                AND DATE(created_at) = :log_date
                ORDER BY created_at ASC
                LIMIT 500");
    $locStmt->execute([
        ':emp' => $empId,
        ':log_date' => $locationDate
    ]);
    $location_logs = $locStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $location_logs = [];
}

$latestPoint = null;
try {
    $latestStmt = $pdo->prepare("SELECT latitude, longitude, accuracy_meters, created_at
                FROM location_logs
                WHERE employee_id = :emp
                ORDER BY created_at DESC
                LIMIT 1");
    $latestStmt->execute([':emp' => $empId]);
    $latestRow = $latestStmt->fetch(PDO::FETCH_ASSOC);
    if ($latestRow) {
        $latestPoint = [
            'lat' => (float)($latestRow['latitude'] ?? 0),
            'lon' => (float)($latestRow['longitude'] ?? 0),
            'time' => $latestRow['created_at'] ?? null,
            'accuracy' => $latestRow['accuracy_meters'] ?? null
        ];
    }
} catch (PDOException $e) {
    $latestPoint = null;
}

$points = array_map(function ($row) {
    return [
        'lat' => (float)($row['latitude'] ?? 0),
        'lon' => (float)($row['longitude'] ?? 0),
        'time' => $row['created_at'] ?? null,
        'accuracy' => $row['accuracy_meters'] ?? null
    ];
}, $location_logs);

$dateLabel = date('jS M, Y', strtotime($locationDate));

echo json_encode([
    'success' => true,
    'date' => $locationDate,
    'dateLabel' => $dateLabel,
    'points' => $points,
    'latestPoint' => $latestPoint,
    'hasLogs' => !empty($points)
]);

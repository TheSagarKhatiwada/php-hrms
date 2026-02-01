<?php
header('Content-Type: application/json');

require_once '../includes/session_config.php';
require_once '../includes/utilities.php';
require_once '../includes/db_connection.php';
require_once '../includes/csrf_protection.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

$canManageBranchAssignments = is_admin() || has_permission('manage_branch_assignments');
if (!$canManageBranchAssignments) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Missing CSRF token.']);
    exit;
}

verify_csrf_token($_POST['csrf_token']);

function respond($message, $extra = [], $status = 'error', $code = 400) {
    if ($code !== 200) {
        http_response_code($code);
    }
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

function normalize_time($time) {
    if ($time === null || $time === '') {
        return null;
    }

    $time = trim($time);
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time . ':00';
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return $time;
    }

    return null;
}

$empId = trim($_POST['emp_id'] ?? '');
$newBranchId = isset($_POST['new_branch_id']) ? (int)$_POST['new_branch_id'] : 0;
$effectiveDate = trim($_POST['effective_date'] ?? '');
$lastDayCurrent = trim($_POST['last_day_current'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$newSupervisorId = trim($_POST['new_supervisor_id'] ?? '');
$newWorkStart = normalize_time($_POST['new_work_start_time'] ?? null);
$newWorkEnd = normalize_time($_POST['new_work_end_time'] ?? null);
$notifyStakeholders = filter_var($_POST['notify_stakeholders'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($empId === '' || $newBranchId <= 0 || $effectiveDate === '' || $reason === '') {
    respond('Employee, new branch, effective date, and reason are required.');
}

$effectiveDateObj = DateTime::createFromFormat('Y-m-d', $effectiveDate);
if (!$effectiveDateObj || $effectiveDateObj->format('Y-m-d') !== $effectiveDate) {
    respond('Effective date is invalid.');
}

if ($lastDayCurrent !== '') {
    $lastDayObj = DateTime::createFromFormat('Y-m-d', $lastDayCurrent);
    if (!$lastDayObj || $lastDayObj->format('Y-m-d') !== $lastDayCurrent) {
        respond('Last day in current branch is invalid.');
    }
    if ($lastDayObj > $effectiveDateObj) {
        respond('Last day in current branch cannot be after the effective date.');
    }
} else {
    $lastDayCurrent = null;
}

try {
    $employeeStmt = $pdo->prepare("SELECT e.emp_id, e.first_name, e.last_name, e.branch_id, e.branch, e.supervisor_id, e.work_start_time, e.work_end_time, b.name AS current_branch_name
        FROM employees e
        LEFT JOIN branches b ON e.branch = b.id
        WHERE e.emp_id = :emp_id");
    $employeeStmt->execute([':emp_id' => $empId]);
    $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        respond('Employee not found.', [], 'error', 404);
    }

    $branchStmt = $pdo->prepare('SELECT id, name FROM branches WHERE id = :id');
    $branchStmt->execute([':id' => $newBranchId]);
    $newBranch = $branchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$newBranch) {
        respond('Selected branch does not exist.');
    }

    $currentBranchId = !empty($employee['branch_id']) ? (int)$employee['branch_id'] : (int)$employee['branch'];
    if ($currentBranchId === $newBranchId) {
        respond('Employee is already assigned to the selected branch.');
    }

    if ($newSupervisorId !== '') {
        $supervisorCheck = $pdo->prepare('SELECT emp_id FROM employees WHERE emp_id = :sup LIMIT 1');
        $supervisorCheck->execute([':sup' => $newSupervisorId]);
        if (!$supervisorCheck->fetchColumn()) {
            respond('Selected supervisor does not exist.');
        }
    } else {
        $newSupervisorId = null;
    }

    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare("INSERT INTO employee_branch_transfers (
            employee_id, from_branch_id, to_branch_id, from_supervisor_id, to_supervisor_id,
            effective_date, last_day_in_previous_branch, reason,
            previous_work_start_time, previous_work_end_time,
            new_work_start_time, new_work_end_time,
            notify_stakeholders, processed_by
        ) VALUES (
            :employee_id, :from_branch_id, :to_branch_id, :from_supervisor_id, :to_supervisor_id,
            :effective_date, :last_day, :reason,
            :prev_start, :prev_end,
            :new_start, :new_end,
            :notify, :processed_by
        )");

    $insertStmt->execute([
        ':employee_id' => $empId,
        ':from_branch_id' => $currentBranchId ?: null,
        ':to_branch_id' => $newBranchId,
        ':from_supervisor_id' => $employee['supervisor_id'] ?: null,
        ':to_supervisor_id' => $newSupervisorId,
        ':effective_date' => $effectiveDate,
        ':last_day' => $lastDayCurrent,
        ':reason' => $reason,
        ':prev_start' => $employee['work_start_time'] ?? null,
        ':prev_end' => $employee['work_end_time'] ?? null,
        ':new_start' => $newWorkStart,
        ':new_end' => $newWorkEnd,
        ':notify' => $notifyStakeholders ? 1 : 0,
        ':processed_by' => $_SESSION['user_id'] ?? null,
    ]);

    $updateColumns = [
        'branch_id = :branch_id',
        'branch = :branch',
        'updated_at = NOW()'
    ];
    $updateParams = [
        ':branch_id' => $newBranchId,
        ':branch' => $newBranchId,
        ':emp_id' => $empId
    ];

    if ($newSupervisorId !== null) {
        $updateColumns[] = 'supervisor_id = :supervisor_id';
        $updateParams[':supervisor_id'] = $newSupervisorId;
    }

    if ($newWorkStart !== null) {
        $updateColumns[] = 'work_start_time = :work_start_time';
        $updateParams[':work_start_time'] = $newWorkStart;
    }

    if ($newWorkEnd !== null) {
        $updateColumns[] = 'work_end_time = :work_end_time';
        $updateParams[':work_end_time'] = $newWorkEnd;
    }

    $updateSql = 'UPDATE employees SET ' . implode(', ', $updateColumns) . ' WHERE emp_id = :emp_id';
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute($updateParams);

    $pdo->commit();

    $fullName = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
    $targetLink = 'modules/employees/employee-viewer.php?empId=' . urlencode($empId);

    if ($notifyStakeholders) {
        $recipients = [$empId];
        if ($newSupervisorId) {
            $recipients[] = $newSupervisorId;
        }

        $adminStmt = $pdo->query("SELECT emp_id FROM employees WHERE role_id = 1 AND emp_id IS NOT NULL");
        $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
        $recipients = array_unique(array_merge($recipients, $adminIds));

        if (!empty($recipients)) {
            $title = 'Branch transfer scheduled';
            $message = sprintf('%s will move to %s effective %s.', $fullName, $newBranch['name'], $effectiveDate);
            notify_users($recipients, $title, $message, 'info', $targetLink);
        }
    } else {
        $title = 'Branch transfer saved';
        $message = sprintf('Transfer of %s to %s effective %s recorded.', $fullName, $newBranch['name'], $effectiveDate);
        notify_user($empId, $title, $message, 'info', $targetLink);
    }

    respond('Employee transfer recorded successfully.', [], 'success', 200);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Transfer employee error: ' . $e->getMessage());
    respond('Unable to complete transfer. Please try again later.', [], 'error', 500);
}

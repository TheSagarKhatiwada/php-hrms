<?php
// Admin-only endpoint to reassign a task to another user and optionally update deadline
// POST: task_id, assigned_to, due_date (optional), csrf_token

declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/utilities.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';

function json_error_reassign(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error_reassign('Invalid request method', 405);
}

$csrf = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($csrf) || !is_string($sessionToken) || $csrf === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrf)) {
    json_error_reassign('Invalid CSRF token', 403);
}

if (!isset($_SESSION['user_id'])) {
    json_error_reassign('Not authenticated', 401);
}

if (!function_exists('is_admin') || !is_admin()) {
    json_error_reassign('Only administrators can reassign tasks', 403);
}

$taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$assignmentType = trim((string)($_POST['assignment_type'] ?? 'employee'));
$assignedTo = trim((string)($_POST['assigned_to'] ?? ''));
$targetDept = trim((string)($_POST['target_department_id'] ?? ''));
$dueDate = trim((string)($_POST['due_date'] ?? ''));

if ($taskId <= 0) {
    json_error_reassign('Invalid task id');
}
if (!in_array($assignmentType, ['employee', 'department', 'open'], true)) {
    json_error_reassign('Invalid assignment type');
}

try {
    global $pdo;
    if (!$pdo) {
        json_error_reassign('Database not available', 500);
    }

    // Validate inputs based on assignment type
    if ($assignmentType === 'employee') {
        if ($assignedTo === '') {
            json_error_reassign('Please select an employee');
        }
        $stmt = $pdo->prepare('SELECT 1 FROM employees WHERE emp_id = :id AND (exit_date IS NULL OR exit_date = "0000-00-00")');
        $stmt->execute([':id' => $assignedTo]);
        if (!$stmt->fetchColumn()) {
            json_error_reassign('Selected employee not found or inactive');
        }
    } elseif ($assignmentType === 'department') {
        $deptId = (int)$targetDept;
        if ($deptId <= 0) {
            json_error_reassign('Please select a department');
        }
        $dchk = $pdo->prepare('SELECT 1 FROM departments WHERE id = :id');
        $dchk->execute([':id' => $deptId]);
        if (!$dchk->fetchColumn()) {
            json_error_reassign('Selected department not found');
        }
    } else {
        // open: no extra validation
    }

    $pdo->beginTransaction();

    // Lock task row
    $tstmt = $pdo->prepare('SELECT id, status FROM tasks WHERE id = :id FOR UPDATE');
    $tstmt->execute([':id' => $taskId]);
    $task = $tstmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        $pdo->rollBack();
        json_error_reassign('Task not found', 404);
    }

    $fields = [];
    $setParts = [];
    if ($assignmentType === 'employee') {
        $fields['assigned_to'] = $assignedTo;
        $setParts[] = 'assigned_to = :assigned_to';
        $fields['task_type'] = 'assigned';
        $setParts[] = 'task_type = :task_type';
        $fields['target_department_id'] = null;
        $setParts[] = 'target_department_id = :target_department_id';
    } elseif ($assignmentType === 'department') {
        $fields['assigned_to'] = null;
        $setParts[] = 'assigned_to = :assigned_to';
        $fields['task_type'] = 'department';
        $setParts[] = 'task_type = :task_type';
        $fields['target_department_id'] = (int)$targetDept;
        $setParts[] = 'target_department_id = :target_department_id';
    } else { // open
        $fields['assigned_to'] = null;
        $setParts[] = 'assigned_to = :assigned_to';
        $fields['task_type'] = 'open';
        $setParts[] = 'task_type = :task_type';
        $fields['target_department_id'] = null;
        $setParts[] = 'target_department_id = :target_department_id';
    }
    if ($dueDate !== '') {
        // basic date sanity (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $pdo->rollBack();
            json_error_reassign('Invalid date format; expected YYYY-MM-DD');
        }
        $fields['due_date'] = $dueDate;
        $setParts[] = 'due_date = :due_date';
    }

    // Status: keep completed/cancelled; else employee -> in_progress, dept/open -> pending
    if (in_array(($task['status'] ?? ''), ['completed', 'cancelled'], true)) {
        $fields['status'] = $task['status'];
    } else {
        $fields['status'] = ($assignmentType === 'employee') ? 'in_progress' : 'pending';
    }
    $setParts[] = 'status = :status';

    $fields['id'] = $taskId;

    $setSql = implode(', ', $setParts) . ', updated_at = NOW()';
    $ustmt = $pdo->prepare("UPDATE tasks SET $setSql WHERE id = :id");
    $ustmt->execute($fields);

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_error_reassign('Server error: ' . $e->getMessage(), 500);
}

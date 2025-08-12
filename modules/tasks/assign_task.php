<?php
// Self-assign a task (for open/department tasks) or reassign if permitted
// Expects POST: task_id, csrf_token

declare(strict_types=1);

// Use unified session bootstrap to ensure correct session name and settings
require_once __DIR__ . '/../../includes/session_config.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../includes/hierarchy_helpers.php';

function json_error(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}

// Do a non-die CSRF validation to avoid HTML error responses
$csrf = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($csrf) || !is_string($sessionToken) || $csrf === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrf)) {
    json_error('Invalid CSRF token', 403);
}

if (!isset($_SESSION['user_id'])) {
    json_error('Not authenticated', 401);
}

$taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
if ($taskId <= 0) {
    json_error('Invalid task id');
}

try {
    // Use shared PDO from db_connection.php
    global $pdo;
    if (!$pdo) {
        json_error('Database not available', 500);
    }
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, title, task_type, target_department_id, assigned_to, status FROM tasks WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        $pdo->rollBack();
        json_error('Task not found', 404);
    }

    // Only open or department tasks can be self-assigned
    if (!in_array($task['task_type'], ['open', 'department'], true)) {
        $pdo->rollBack();
        json_error('Task is not available for self-assignment');
    }

    // If already assigned, block double take
    if (!empty($task['assigned_to'])) {
        $pdo->rollBack();
        json_error('Task is already assigned');
    }

    $currentUserId = $_SESSION['user_id'];

    // If department task, ensure user belongs to the target department
    if ($task['task_type'] === 'department') {
        $targetDeptId = (int)($task['target_department_id'] ?? 0);
        if ($targetDeptId > 0) {
            // Check membership via employees table (department_id)
            $chk = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE emp_id = :uid AND department_id = :did");
            $chk->execute([':uid' => $currentUserId, ':did' => $targetDeptId]);
            $isMember = ((int)$chk->fetchColumn() > 0);
            if (!$isMember) {
                $pdo->rollBack();
                json_error('You are not a member of the target department', 403);
            }
        }
    }

    // Assign to current user and set self_assigned_at; advance status if currently pending
    $stmt = $pdo->prepare("UPDATE tasks SET assigned_to = :uid, self_assigned_at = NOW(), status = CASE WHEN status = 'pending' THEN 'in_progress' ELSE status END WHERE id = :id");
    $stmt->execute([':uid' => $currentUserId, ':id' => $taskId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Task assigned to you']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_error('Server error: ' . $e->getMessage(), 500);
}

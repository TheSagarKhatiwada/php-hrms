<?php
// Admin-only endpoint to reassign a task
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/utilities.php';
require_once __DIR__ . '/task_helpers.php';

// Note: avoid verify_csrf_token() here because it dies() with HTML; we want JSON.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(['success' => false, 'message' => 'Invalid method']);
	exit;
}

if (!isset($_SESSION['user_id']) || !is_admin()) {
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit;
}

// Basic input extraction
$taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$assignmentType = isset($_POST['assignment_type']) ? strtolower(trim($_POST['assignment_type'])) : '';
$assignedTo = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;
$targetDeptId = isset($_POST['target_department_id']) && $_POST['target_department_id'] !== '' ? (int)$_POST['target_department_id'] : null;
$dueDate = isset($_POST['due_date']) ? trim($_POST['due_date']) : '';
$csrf = $_POST['csrf_token'] ?? '';

// CSRF check (manual to avoid HTML output)
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
	echo json_encode(['success' => false, 'message' => 'Security token invalid. Please refresh and try again.']);
	exit;
}

// Validate inputs
$allowedTypes = ['employee', 'department', 'open'];
if ($taskId <= 0 || !in_array($assignmentType, $allowedTypes, true)) {
	echo json_encode(['success' => false, 'message' => 'Invalid input']);
	exit;
}

try {
	// Fetch task
	$stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = :id');
	$stmt->execute([':id' => $taskId]);
	$task = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$task) {
		echo json_encode(['success' => false, 'message' => 'Task not found']);
		exit;
	}

	// Validate assignment target based on type
	if ($assignmentType === 'employee') {
		if (!$assignedTo) {
			echo json_encode(['success' => false, 'message' => 'Please select an employee']);
			exit;
		}
		// Validate employee exists and is active
		$stmt = $pdo->prepare('SELECT emp_id FROM employees WHERE emp_id = :id AND exit_date IS NULL');
		$stmt->execute([':id' => $assignedTo]);
		if (!$stmt->fetch()) {
			echo json_encode(['success' => false, 'message' => 'Selected employee is invalid']);
			exit;
		}
	} elseif ($assignmentType === 'department') {
		if (!$targetDeptId) {
			echo json_encode(['success' => false, 'message' => 'Please select a department']);
			exit;
		}
		// Validate department exists
		$stmt = $pdo->prepare('SELECT id FROM departments WHERE id = :id');
		$stmt->execute([':id' => $targetDeptId]);
		if (!$stmt->fetch()) {
			echo json_encode(['success' => false, 'message' => 'Selected department is invalid']);
			exit;
		}
	}

	// Normalize due date (optional)
	$newDue = null;
	if ($dueDate !== '') {
		// Accept YYYY-MM-DD; ignore invalid formats
		$d = DateTime::createFromFormat('Y-m-d', substr($dueDate, 0, 10));
		if ($d) {
			$newDue = $d->format('Y-m-d');
		}
	}

	// Prepare update per type
	$sets = [];
	$params = [':id' => $taskId];
	$oldCtx = [
		'task_type' => $task['task_type'] ?? 'assigned',
		'assigned_to' => $task['assigned_to'] ?? null,
		'target_department_id' => $task['target_department_id'] ?? null,
	];
	$newCtx = [];

	if ($assignmentType === 'employee') {
		$sets[] = 'assigned_to = :assigned_to';
		$params[':assigned_to'] = $assignedTo;
		$sets[] = "task_type = 'assigned'";
		$sets[] = 'target_department_id = NULL';
		$sets[] = 'target_role_id = NULL';
		$sets[] = 'self_assigned_at = NULL';
		$newCtx = ['task_type' => 'assigned', 'assigned_to' => $assignedTo, 'target_department_id' => null];
	} elseif ($assignmentType === 'department') {
		$sets[] = 'assigned_to = NULL';
		$sets[] = "task_type = 'department'";
		$sets[] = 'target_department_id = :dept';
		$params[':dept'] = $targetDeptId;
		$sets[] = 'target_role_id = NULL';
		$sets[] = 'self_assigned_at = NULL';
		$newCtx = ['task_type' => 'department', 'assigned_to' => null, 'target_department_id' => $targetDeptId];
	} else { // open
		$sets[] = 'assigned_to = NULL';
		$sets[] = "task_type = 'open'";
		$sets[] = 'target_department_id = NULL';
		$sets[] = 'target_role_id = NULL';
		$sets[] = 'self_assigned_at = NULL';
		$newCtx = ['task_type' => 'open', 'assigned_to' => null, 'target_department_id' => null];
	}

	if ($newDue !== null) {
		$sets[] = 'due_date = :due';
		$params[':due'] = $newDue;
	}

	$sets[] = 'updated_at = NOW()';

	$sql = 'UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = :id';

	$pdo->beginTransaction();
	$up = $pdo->prepare($sql);
	$up->execute($params);

	// History entry
	$who = $_SESSION['user_id'];
	$oldStr = json_encode($oldCtx);
	$newStr = json_encode($newCtx);
	addTaskHistory($pdo, $taskId, $who, 'reassigned', $oldStr, $newStr);

	// Notify target if directly assigned to employee
	if ($assignmentType === 'employee' && $assignedTo) {
		// New notification system with email/SMS
		require_once __DIR__ . '/../../includes/task_notification_helper.php';
		try {
			sendTaskAssignmentNotification($pdo, $taskId, $assignedTo, $who);
		} catch (Exception $notif_error) {
			error_log("Warning: Failed to send task reassignment notification: " . $notif_error->getMessage());
		}
		
		// Legacy notification system (keeping for backward compatibility)
		require_once __DIR__ . '/../../includes/notification_helpers.php';
		try {
			notify_employee($assignedTo, 'task_assigned', [
				'task_id' => $taskId,
				'task_title' => $task['title'] ?? 'Task',
				'assigned_by' => $who
			]);
		} catch (Throwable $nt) {
			// ignore notification errors
		}
	}

	$pdo->commit();

	echo json_encode(['success' => true]);
} catch (Exception $e) {
	if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
	echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

?>

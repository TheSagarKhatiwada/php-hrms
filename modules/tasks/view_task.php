<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/settings.php';
require_once 'task_helpers.php';

$page = 'View Task';
$home = '../../';

if (!isset($_SESSION['user_id'])) {
	header('Location: ../../index.php');
	exit();
}

$current_user_id = $_SESSION['user_id'];
$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($taskId <= 0) {
	header('Location: index.php');
	exit();
}

// Load task with creator/assignee
$task = null;
try {
	$stmt = $pdo->prepare(
		"SELECT t.*, 
						creator.first_name AS creator_first_name, creator.last_name AS creator_last_name,
						assignee.first_name AS assignee_first_name, assignee.last_name AS assignee_last_name
		 FROM tasks t
		 LEFT JOIN employees creator ON t.assigned_by = creator.emp_id
		 LEFT JOIN employees assignee ON t.assigned_to = assignee.emp_id
		 WHERE t.id = :id"
	);
	$stmt->execute([':id' => $taskId]);
	$task = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	$task = null;
}

if (!$task) {
	$error_message = 'Task not found.';
}

// Access control: show if assigned to/by current user, or available open/department task
$canView = false;
if ($task) {
	$canView = canAccessTask($pdo, $taskId, $current_user_id);
	if (!$canView) {
		$isAvailable = empty($task['assigned_to']) && in_array($task['task_type'] ?? 'assigned', ['open', 'department'], true);
		if ($isAvailable) {
			// If department task, ensure user is in target department via employees table
			if (($task['task_type'] ?? '') === 'department') {
				$targetDeptId = (int)($task['target_department_id'] ?? 0);
				if ($targetDeptId > 0) {
					$stmt = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE emp_id = ? AND department_id = ?');
					$stmt->execute([$current_user_id, $targetDeptId]);
					$canView = ((int)$stmt->fetchColumn() > 0);
				} else {
					$canView = true; // no specific dept set
				}
			} else {
				$canView = true; // open task
			}
		}
	}
}

require_once '../../includes/header.php';
?>

<div class="container-fluid p-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<div>
			<h1 class="fs-2 fw-bold mb-1"><i class="fas fa-clipboard me-2"></i>Task #<?= htmlspecialchars((string)$taskId) ?></h1>
			<?php if (!empty($task['title'])): ?>
				<p class="text-muted mb-0"><?= htmlspecialchars($task['title']) ?></p>
			<?php endif; ?>
		</div>
		<div class="d-flex gap-2">
			<a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
			<a href="index.php?scope=my_tasks" class="btn btn-outline-info"><i class="fas fa-user-check me-1"></i>My Tasks</a>
		</div>
	</div>

	<?php if (isset($error_message)): ?>
		<div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
	<?php elseif (!$canView): ?>
		<div class="alert alert-warning">You don’t have access to view this task.</div>
	<?php else: ?>
		<div class="row g-4">
			<div class="col-lg-8">
				<div class="card border-0 shadow-sm">
					<div class="card-header">
						<h3 class="card-title mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary"></i>Details</h3>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-md-6">
								<div class="small text-muted">Status</div>
								<div><span class="badge bg-<?= getStatusColor($task['status'] ?? 'pending') ?> text-capitalize"><?= htmlspecialchars(str_replace('_',' ', $task['status'] ?? 'pending')) ?></span></div>
							</div>
							<div class="col-md-6">
								<div class="small text-muted">Priority</div>
								<div><span class="badge bg-<?= getPriorityColor($task['priority'] ?? 'medium') ?> text-dark text-capitalize"><?= htmlspecialchars($task['priority'] ?? 'medium') ?></span></div>
							</div>
							<div class="col-md-6">
								<div class="small text-muted">Assigned By</div>
								<div><?= htmlspecialchars(trim(($task['creator_first_name'] ?? '').' '.($task['creator_last_name'] ?? ''))) ?: '—' ?></div>
							</div>
							<div class="col-md-6">
								<div class="small text-muted">Assigned To</div>
								<div><?= !empty($task['assignee_first_name']) ? htmlspecialchars(trim(($task['assignee_first_name'] ?? '').' '.($task['assignee_last_name'] ?? ''))) : 'Unassigned' ?></div>
							</div>
							<div class="col-md-6">
								<div class="small text-muted">Type</div>
								<div class="text-capitalize"><?= htmlspecialchars($task['task_type'] ?? 'assigned') ?></div>
							</div>
							<div class="col-md-6">
								<div class="small text-muted">Due Date</div>
								<div><?= !empty($task['due_date']) ? date('M d, Y', strtotime($task['due_date'])) : '—' ?></div>
							</div>
						</div>
						<?php if (!empty($task['description'])): ?>
						<hr>
						<div>
							<div class="small text-muted mb-1">Description</div>
							<div class="border rounded p-3"><?= nl2br(htmlspecialchars($task['description'])) ?></div>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<?php
				// Comments
				$comments = [];
				try {
					$comments = getTaskComments($pdo, $taskId);
				} catch (Throwable $e) { $comments = []; }
				?>
				<div class="card border-0 shadow-sm mt-3">
					<div class="card-header d-flex justify-content-between align-items-center">
						<h3 class="card-title mb-0 fw-bold"><i class="fas fa-comments me-2 text-primary"></i>Comments</h3>
						<span class="badge"><?= count($comments) ?></span>
					</div>
					<div class="card-body">
						<?php if (empty($comments)): ?>
							<div class="text-muted">No comments yet.</div>
						<?php else: ?>
							<ul class="list-unstyled mb-3">
								<?php foreach ($comments as $c): ?>
								<li class="mb-3">
									<div class="fw-semibold"><?= htmlspecialchars($c['commenter_name'] ?? 'User') ?></div>
									<div class="small text-muted mb-1"><?= htmlspecialchars($c['created_at'] ?? '') ?></div>
									<div><?= nl2br(htmlspecialchars($c['comment'] ?? '')) ?></div>
								</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<form method="post">
							<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? (function_exists('generate_csrf_token') ? generate_csrf_token() : '') ?>">
							<div class="input-group">
								<input type="text" name="new_comment" class="form-control" placeholder="Add a comment...">
								<button class="btn btn-primary" name="action" value="add_comment" type="submit"><i class="fas fa-paper-plane me-1"></i>Post</button>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div class="col-lg-4">
				<div class="card border-0 shadow-sm">
					<div class="card-header">
						<h3 class="card-title mb-0 fw-bold"><i class="fas fa-cogs me-2 text-primary"></i>Actions</h3>
					</div>
					<div class="card-body">
						<?php 
							$isAvailableToTake = empty($task['assigned_to']) && in_array($task['task_type'] ?? 'assigned', ['open','department'], true);
							$canTake = $isAvailableToTake;
							if ($canTake && ($task['task_type'] ?? '') === 'department') {
								$targetDeptId = (int)($task['target_department_id'] ?? 0);
								if ($targetDeptId > 0) {
									$stmt = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE emp_id = ? AND department_id = ?');
									$stmt->execute([$current_user_id, $targetDeptId]);
									$canTake = ((int)$stmt->fetchColumn() > 0);
								}
							}
						?>
						<?php if ($canTake): ?>
							<button class="btn btn-success w-100 mb-2" id="takeTaskBtn" data-task-id="<?= (int)$taskId ?>">
								<i class="fas fa-hand-paper me-1"></i>Take this task
							</button>
						<?php endif; ?>
						<?php
						// Show update form only to the assignee
						$assignedToId = isset($task['assigned_to']) ? (string)$task['assigned_to'] : '';
						$currentUserId = (string)$current_user_id;
						if ($assignedToId !== '' && $assignedToId === $currentUserId): ?>
							<form method="post" action="update_progress.php" class="mt-2">
								<input type="hidden" name="task_id" value="<?= (int)$taskId ?>">
								<div class="mb-2">
									<label class="form-label">Progress</label>
									<input type="range" name="progress" class="form-range" min="0" max="100" step="5" value="<?= (int)($task['progress'] ?? 0) ?>">
								</div>
								<div class="mb-2">
									<label class="form-label">Status</label>
									<select name="status" class="form-select">
										<?php 
										$statuses = ['pending'=>'Pending','in_progress'=>'In Progress','completed'=>'Completed','on_hold'=>'On Hold','cancelled'=>'Cancelled'];
										$cur = $task['status'] ?? 'pending';
										foreach ($statuses as $k=>$label): ?>
											<option value="<?= $k ?>" <?= $k===$cur?'selected':'' ?>><?= $label ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="mb-3">
									<label class="form-label">Notes</label>
									<textarea name="update_notes" class="form-control" rows="3"></textarea>
								</div>
								<button class="btn btn-primary w-100" type="submit"><i class="fas fa-save me-1"></i>Update</button>
							</form>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php
// Handle add comment POST after header to have CSRF token available from header include
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_comment') {
	if (function_exists('verify_csrf_post')) { verify_csrf_post(); }
	$comment = trim($_POST['new_comment'] ?? '');
	if ($comment !== '') {
		try { addTaskComment($pdo, $taskId, $current_user_id, $comment); } catch (Throwable $e) {}
	}
	header('Location: view_task.php?id=' . (int)$taskId);
	exit();
}

require_once '../../includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function(){
	const btn = document.getElementById('takeTaskBtn');
	if (btn) {
		btn.addEventListener('click', function(){
			const taskId = this.getAttribute('data-task-id');
			const tokenMeta = document.querySelector('meta[name="csrf-token"]');
			const csrf = tokenMeta ? tokenMeta.getAttribute('content') : '';
			this.disabled = true;
			this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Taking...';
			fetch('assign_task.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ task_id: taskId, action: 'assign', csrf_token: csrf })
			}).then(r => r.json()).then(resp => {
				if (resp && resp.success) {
					if (typeof Swal !== 'undefined') {
						Swal.fire({ title: 'Assigned', text: 'Task assigned to you', icon: 'success', timer: 1500, showConfirmButton: false }).then(()=> location.reload());
					} else {
						location.reload();
					}
				} else {
					const msg = (resp && resp.message) ? resp.message : 'Failed to assign task';
					if (typeof Swal !== 'undefined') {
						Swal.fire({ title: 'Error', text: msg, icon: 'error' });
					} else {
						alert('Error: ' + msg);
					}
					btn.disabled = false;
					btn.innerHTML = '<i class="fas fa-hand-paper me-1"></i>Take this task';
				}
			}).catch(() => {
				if (typeof Swal !== 'undefined') {
					Swal.fire({ title: 'Network Error', text: 'Please try again', icon: 'error' });
				} else {
					alert('Network error');
				}
				btn.disabled = false;
				btn.innerHTML = '<i class="fas fa-hand-paper me-1"></i>Take this task';
			});
		});
	}
});
</script>


<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/settings.php';
require_once 'task_helpers.php';

$page = 'Task Management';
// Use global $home from configuration.php (set in header) for absolute URLs

// Auth check
if (!isset($_SESSION['user_id'])) {
		header('Location: ../../index.php');
		exit();
}

$current_user_id = $_SESSION['user_id'];
$isAdmin = is_admin();

// Lightweight employees list for reassign modal (admin only)
$employees = [];
$departments = [];
if ($isAdmin) {
	try {
		$stmtEmp = $pdo->prepare("SELECT emp_id, first_name, last_name FROM employees WHERE exit_date IS NULL ORDER BY first_name, last_name");
		$stmtEmp->execute();
		$employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
	} catch (Exception $e) {
		$employees = [];
	}
	try {
		$stmtDept = $pdo->prepare("SELECT id, name FROM departments ORDER BY name");
		$stmtDept->execute();
		$departments = $stmtDept->fetchAll(PDO::FETCH_ASSOC);
	} catch (Exception $e) {
		$departments = [];
	}
}

// Task statistics for current user
$stats = getTaskStatistics($pdo, $current_user_id);
$myStats = $stats['my_tasks'] ?? ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'overdue' => 0];

// Filters
// Status filter (left)
$allowedStatus = ['all', 'pending', 'in_progress', 'completed', 'cancelled', 'overdue'];
$status_filter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'all';
if (!in_array($status_filter, $allowedStatus, true)) {
	$status_filter = 'all';
}

// Scope filter (right)
$allowedScopes = ['all', 'my_tasks', 'team_tasks', 'assigned_by_me', 'others'];
$scope_filter = isset($_GET['scope']) ? strtolower(trim($_GET['scope'])) : 'all';
// Allow hyphenated alt key from UI (assigned-by-me)
if ($scope_filter === 'assigned-by-me') { $scope_filter = 'assigned_by_me'; }
if (!in_array($scope_filter, $allowedScopes, true)) {
	$scope_filter = 'all';
}

// Fetch tasks with status + scope filters
try {
	$whereParts = [];
	$params = [];

	// Scope conditions
	if ($scope_filter === 'my_tasks') {
		$whereParts[] = 't.assigned_to = :uid_assignee';
		$params[':uid_assignee'] = $current_user_id;
	} elseif ($scope_filter === 'assigned_by_me') {
		$whereParts[] = 't.assigned_by = :uid_assignor';
		$params[':uid_assignor'] = $current_user_id;
	} elseif ($scope_filter === 'others') {
		$whereParts[] = 't.assigned_to <> :uid_not_assignee';
		$whereParts[] = 't.assigned_by <> :uid_not_assignor';
		$params[':uid_not_assignee'] = $current_user_id;
		$params[':uid_not_assignor'] = $current_user_id;
	} elseif ($scope_filter === 'team_tasks') {
		$subs = getSubordinates($pdo, $current_user_id);
		if (!empty($subs)) {
			$inPlaceholders = [];
			foreach ($subs as $i => $sid) {
				$ph = ":sub{$i}";
				$inPlaceholders[] = $ph;
				$params[$ph] = $sid;
			}
			$whereParts[] = 't.assigned_to IN (' . implode(',', $inPlaceholders) . ')';
		} else {
			// No subordinates -> no team tasks
			$tasks = [];
			$subs = null;
		}
	} else {
		// 'all' scope: show all tasks
		// no additional user restriction
	}

	// Status conditions
	if (isset($tasks)) {
		// already determined as empty due to no subs
	} else {
		if (in_array($status_filter, ['pending', 'in_progress', 'completed', 'cancelled'], true)) {
			$whereParts[] = 't.status = :status';
			$params[':status'] = $status_filter;
		} elseif ($status_filter === 'overdue') {
			$whereParts[] = 't.status <> "completed"';
			$whereParts[] = 't.due_date IS NOT NULL';
			$whereParts[] = "t.due_date <> ''";
			$whereParts[] = "t.due_date <> '0000-00-00'";
			$whereParts[] = "t.due_date <> '0000-00-00 00:00:00'";
			$whereParts[] = 'DATE(t.due_date) <= CURRENT_DATE';
		}

		$where = !empty($whereParts) ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

		$sql = "
			SELECT t.*,
				   creator.first_name as creator_first_name,
				   creator.last_name as creator_last_name,
				   creator.user_image as creator_image,
				   assignee.first_name as assignee_first_name,
				   assignee.last_name as assignee_last_name,
		   assignee.user_image as assignee_image,
		   d.name AS target_department_name
			FROM tasks t
			LEFT JOIN employees creator ON t.assigned_by = creator.emp_id
			LEFT JOIN employees assignee ON t.assigned_to = assignee.emp_id
	    LEFT JOIN departments d ON t.target_department_id = d.id
			$where
			ORDER BY t.created_at DESC
		";
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Exception $e) {
		$tasks = [];
		$error_message = 'Error loading tasks: ' . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<div class="container-fluid p-4">
	<!-- Page header -->
	<div class="d-flex justify-content-between align-items-center mb-4">
		<div>
			<h1 class="fs-2 fw-bold mb-1"><i class="fas fa-user-check me-2"></i>Task Dashboard</h1>
			<p class="text-muted mb-0">All Task Management.</p>
		</div>
		<div class="d-flex gap-2">
			<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
				<i class="fas fa-plus me-1"></i>Create Task
			</button>
			<a href="all-tasks.php" class="btn btn-outline-info">
				<i class="fas fa-list me-1"></i>All Tasks
			</a>
			<a href="index.php?scope=team_tasks&status=<?= urlencode($status_filter) ?>" class="btn btn-outline-secondary">
				<i class="fas fa-users me-1"></i>Team Tasks
			</a>
		</div>
	</div>

	<!-- Stats Cards -->
	<div class="row g-4 mb-4">
		<div class="col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm rounded-3">
				<div class="card-body">
					<div class="d-flex align-items-center mb-3">
						<div class="bg-primary bg-opacity-10 p-3 rounded-3"><i class="fas fa-clipboard-list text-primary fs-4"></i></div>
						<div class="ms-3">
							<h6 class="mb-1 text-muted">Total</h6>
							<h2 class="mb-0 fw-bold"><?= (int)($myStats['total'] ?? 0) ?></h2>
						</div>
					</div>
					<div class="text-primary small"><i class="fas fa-layer-group me-1"></i>All assigned to you</div>
				</div>
			</div>
		</div>
		<div class="col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm rounded-3">
				<div class="card-body">
					<div class="d-flex align-items-center mb-3">
						<div class="bg-warning bg-opacity-10 p-3 rounded-3"><i class="fas fa-hourglass-half text-warning fs-4"></i></div>
						<div class="ms-3">
							<h6 class="mb-1 text-muted">Pending</h6>
							<h2 class="mb-0 fw-bold"><?= (int)($myStats['pending'] ?? 0) ?></h2>
						</div>
					</div>
					<div class="text-warning small"><i class="fas fa-clock me-1"></i>Awaiting action</div>
				</div>
			</div>
		</div>
		<div class="col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm rounded-3">
				<div class="card-body">
					<div class="d-flex align-items-center mb-3">
						<div class="bg-info bg-opacity-10 p-3 rounded-3"><i class="fas fa-spinner text-info fs-4"></i></div>
						<div class="ms-3">
							<h6 class="mb-1 text-muted">In Progress</h6>
							<h2 class="mb-0 fw-bold"><?= (int)($myStats['in_progress'] ?? 0) ?></h2>
						</div>
					</div>
					<div class="text-info small"><i class="fas fa-sync me-1"></i>Ongoing tasks</div>
				</div>
			</div>
		</div>
		<div class="col-md-6 col-lg-3">
			<div class="card h-100 border-0 shadow-sm rounded-3">
				<div class="card-body">
					<div class="d-flex align-items-center mb-3">
						<div class="bg-success bg-opacity-10 p-3 rounded-3"><i class="fas fa-check-circle text-success fs-4"></i></div>
						<div class="ms-3">
							<h6 class="mb-1 text-muted">Completed</h6>
							<h2 class="mb-0 fw-bold"><?= (int)($myStats['completed'] ?? 0) ?></h2>
						</div>
					</div>
					<div class="text-success small"><i class="fas fa-check me-1"></i>Finished tasks</div>
				</div>
			</div>
		</div>
	</div>

	<!-- My Tasks Card -->
	<div class="card border-0 shadow-sm">
		<div class="card-header border-0 d-flex align-items-center justify-content-between">
			<h3 class="card-title mb-0 fw-bold"><i class="fas fa-user-check me-2 text-primary"></i>Task Management</h3>
			<span class="badge"><?= count($tasks) ?> Tasks</span>
		</div>
		<div class="card-body p-0">
			<!-- Filters Row: Status (left) + Scope (right) -->
			<div class="px-3 pt-3 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
				<?php 
					$statusFilters = [
						'all' => ['label' => 'All', 'icon' => 'fa-layer-group'],
						'pending' => ['label' => 'Pending', 'icon' => 'fa-clock'],
						'in_progress' => ['label' => 'In Progress', 'icon' => 'fa-spinner'],
						'completed' => ['label' => 'Completed', 'icon' => 'fa-check-circle'],
						'cancelled' => ['label' => 'Cancelled', 'icon' => 'fa-ban'],
						'overdue' => ['label' => 'Overdue', 'icon' => 'fa-exclamation-triangle'],
					];
					$scopeFilters = [
						'all' => ['label' => 'All Tasks', 'icon' => 'fa-layer-group'],
						'my_tasks' => ['label' => 'My Tasks', 'icon' => 'fa-user-check'],
						'team_tasks' => ['label' => 'Team Tasks', 'icon' => 'fa-users'],
						'assigned_by_me' => ['label' => 'Assigned by Me', 'icon' => 'fa-user'],
						'others' => ['label' => 'Others', 'icon' => 'fa-user-friends'],
					];
				?>
				<div class="btn-group" role="group" aria-label="Status filters">
					<?php foreach ($statusFilters as $key => $cfg): ?>
						<a href="?status=<?= $key ?>&scope=<?= urlencode($scope_filter) ?>" class="btn btn-sm <?= $status_filter === $key ? 'btn-primary' : 'btn-outline-secondary' ?>">
							<i class="fas <?= $cfg['icon'] ?> me-1"></i><?= $cfg['label'] ?>
						</a>
					<?php endforeach; ?>
				</div>
				<div class="btn-group" role="group" aria-label="Scope filters">
					<?php foreach ($scopeFilters as $key => $cfg): ?>
						<a href="?status=<?= $status_filter ?>&scope=<?= urlencode($key) ?>" class="btn btn-sm <?= $scope_filter === $key ? 'btn-primary' : 'btn-outline-secondary' ?>">
							<i class="fas <?= $cfg['icon'] ?> me-1"></i><?= $cfg['label'] ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if (empty($tasks)): ?>
				<div class="text-center py-5">
					<i class="fas fa-tasks fa-3x text-muted mb-3"></i>
					<h5 class="text-muted">No tasks found</h5>
					<p class="text-muted">Tasks assigned to you will show up here.</p>
					<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
						<i class="fas fa-plus me-1"></i>Create Task
					</button>
				</div>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0" id="myTasksTable">
						<thead class="table">
							<tr>
								<th><i class="fas fa-hashtag me-2"></i>ID</th>
								<th><i class="fas fa-tasks me-2"></i>Task Details</th>
								<th><i class="fas fa-user me-2"></i>Assigned To</th>
								<th><i class="fas fa-user-cog me-2"></i>Assigned By</th>
								<th><i class="fas fa-calendar-alt me-2"></i>Due Date</th>
								<th><i class="fas fa-flag me-2"></i>Priority</th>
								<th><i class="fas fa-info-circle me-2"></i>Status</th>
								<th><i class="fas fa-chart-line me-2"></i>Progress</th>
								<th><i class="fas fa-cogs me-2"></i>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($tasks as $task): ?>
								<tr>
									<td><span class="badge">#<?= $task['id'] ?></span></td>
									<td>
										<div>
											<h6 class="mb-1 fw-bold"><?= htmlspecialchars($task['title']) ?></h6>
											<?php if (!empty($task['description'])): ?>
												<small class="text-muted d-block"><?= htmlspecialchars(substr($task['description'], 0, 80)) . (strlen($task['description']) > 80 ? '...' : '') ?></small>
											<?php endif; ?>
										</div>
									</td>
									<td>
										<div class="d-flex align-items-center">
											<div class="avatar-sm me-2">
												<?php 
													$assigneeImg = $task['assignee_image'] ?? '';
													if (!empty($assigneeImg) && !preg_match('#^https?://|^/#', $assigneeImg)) {
														$assigneeImg = $home . ltrim($assigneeImg, '/');
													}
												?>
												<?php if (!empty($assigneeImg)): ?>
													<img src="<?= htmlspecialchars($assigneeImg) ?>" alt="<?= htmlspecialchars(($task['assignee_first_name'] ?? 'U') . ' ' . ($task['assignee_last_name'] ?? '')) ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" onerror="this.onerror=null;this.src='<?= htmlspecialchars($home . 'resources/userimg/default-image.jpg') ?>';">
												<?php else: ?>
													<div class="avatar-title bg-secondary rounded-circle">
														<?= strtoupper(substr($task['assignee_first_name'] ?? 'U', 0, 1)) ?>
													</div>
												<?php endif; ?>
											</div>
											<div>
												<span class="fw-medium">
													<?= htmlspecialchars(trim(($task['assignee_first_name'] ?? 'Unassigned') . ' ' . ($task['assignee_last_name'] ?? ''))) ?: 'Unassigned' ?>
												</span>
												<?php
												  // Determine assignment context line under name
												  $taskType = $task['task_type'] ?? 'assigned';
												  $assignedLine = '';
												  if (!empty($task['assigned_to'])) {
												    // Show assignment date if available
												    $assignDate = $task['self_assigned_at'] ?? $task['assigned_at'] ?? $task['updated_at'] ?? $task['created_at'] ?? '';
												    if (!empty($assignDate)) {
												      $assignedLine = 'on ' . date('jS M, Y', strtotime($assignDate));
												    }
												  } else {
												    // Not assigned: indicate department/open and creation date
												    if ($taskType === 'department') {
												      $dept = $task['target_department_name'] ?? '';
												      $when = !empty($task['created_at']) ? date('jS M, Y', strtotime($task['created_at'])) : '';
												      $assignedLine = ($dept ? ('Department: ' . htmlspecialchars($dept)) : 'Department Task') . ($when ? (' • ' . $when) : '');
												    } elseif ($taskType === 'open') {
												      $when = !empty($task['created_at']) ? date('jS M, Y', strtotime($task['created_at'])) : '';
												      $assignedLine = 'Open Task' . ($when ? (' • ' . $when) : '');
												    }
												  }
												?>
												<?php if (!empty($assignedLine)): ?>
												  <div class="text-muted small"><?= $assignedLine ?></div>
												<?php endif; ?>
											</div>
										</div>
									</td>
									<td>
										<div class="d-flex align-items-center">
											<div class="avatar-sm me-2">
												<?php 
													$creatorImg = $task['creator_image'] ?? '';
													// Build absolute URL for image if relative path is stored
													if (!empty($creatorImg) && !preg_match('#^https?://|^/#', $creatorImg)) {
														$creatorImg = $home . ltrim($creatorImg, '/');
													}
												?>
												<?php if (!empty($creatorImg)): ?>
													<img src="<?= htmlspecialchars($creatorImg) ?>" alt="<?= htmlspecialchars(($task['creator_first_name'] ?? 'U') . ' ' . ($task['creator_last_name'] ?? '')) ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" onerror="this.onerror=null;this.src='<?= htmlspecialchars($home . 'resources/userimg/default-image.jpg') ?>';">
												<?php else: ?>
													<div class="avatar-title bg-secondary rounded-circle">
														<?= strtoupper(substr($task['creator_first_name'] ?? 'U', 0, 1)) ?>
													</div>
												<?php endif; ?>
											</div>
											<div>
												<span class="fw-medium"><?= htmlspecialchars(($task['creator_first_name'] ?? 'Unknown') . ' ' . ($task['creator_last_name'] ?? '')) ?></span>
												<?php if (!empty($task['created_at'])): ?>
													<div class="text-muted small">on <?= date('jS M, Y', strtotime($task['created_at'])) ?></div>
												<?php endif; ?>
											</div>
										</div>
									</td>
									<td>
										<?php if (!empty($task['due_date'])): ?>
											<?php 
												$due_date = new DateTime($task['due_date']);
												$today = new DateTime();
												$diff = $today->diff($due_date);
												$is_overdue = $today > $due_date && $task['status'] !== 'completed';
												$is_due_soon = !$is_overdue && $diff->days <= 3;
											?>
											<div class="d-flex flex-column">
												<span class="fw-medium <?= $is_overdue ? 'text-danger' : ($is_due_soon ? 'text-warning' : 'text-muted') ?>">
													<i class="fas fa-calendar-alt me-1"></i><?= $due_date->format('M d, Y') ?>
												</span>
												<?php if ($is_overdue): ?>
													<small class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Overdue</small>
												<?php elseif ($is_due_soon): ?>
													<small class="badge bg-warning"><i class="fas fa-clock me-1"></i>Due Soon</small>
												<?php endif; ?>
											</div>
										<?php else: ?>
											<span class="text-muted"><i class="fas fa-calendar-times me-1"></i>No due date</span>
										<?php endif; ?>
									</td>
									<td>
										<span class="badge bg-<?= getPriorityColor($task['priority'] ?? 'medium') ?>">
											<i class="fas fa-flag me-1"></i><?= ucfirst($task['priority'] ?? 'medium') ?>
										</span>
									</td>
									<td>
										<?php 
											$status_colors = [
												'pending' => 'warning',
												'in_progress' => 'info',
												'completed' => 'success',
												'cancelled' => 'danger'
											];
											$status_icons = [
												'pending' => 'fa-clock',
												'in_progress' => 'fa-spinner',
												'completed' => 'fa-check',
												'cancelled' => 'fa-ban'
											];
											$sc = $status_colors[$task['status']] ?? 'secondary';
											$si = $status_icons[$task['status']] ?? 'fa-circle';
										?>
										<span class="badge bg-<?= $sc ?>"><i class="fas <?= $si ?> me-1"></i><?= ucfirst(str_replace('_',' ', $task['status'])) ?></span>
									</td>
									<td>
										<?php $p = (int)($task['progress'] ?? 0); $pcolor = $p < 33 ? 'danger' : ($p < 66 ? 'warning' : 'success'); ?>
										<div class="d-flex align-items-center">
											<div class="progress me-2" style="width: 80px; height: 8px;">
												<div class="progress-bar bg-<?= $pcolor ?>" style="width: <?= $p ?>%"></div>
											</div>
											<small class="text-muted"><?= $p ?>%</small>
										</div>
									</td>
									<td>
										<a href="view_task.php?id=<?= $task['id'] ?>" class="btn btn-outline-primary btn-sm" title="View Details">
											<i class="fas fa-eye"></i>
										</a>
										<?php $canQuickUpdate = !empty($task['assigned_to']) && (string)$task['assigned_to'] === (string)$current_user_id; ?>
										<?php if ($canQuickUpdate): ?>
											<button type="button"
												class="btn btn-primary btn-sm ms-1"
												title="Update Task"
												data-bs-toggle="modal"
												data-bs-target="#updateTaskModal"
												data-task-id="<?= (int)$task['id'] ?>"
												data-progress="<?= (int)($task['progress'] ?? 0) ?>"
												data-status="<?= htmlspecialchars($task['status'] ?? 'in_progress', ENT_QUOTES) ?>">
												<i class="fas fa-edit"></i>
											</button>
										<?php endif; ?>
					<?php if ($isAdmin && !$canQuickUpdate): ?>
											<button type="button"
												class="btn btn-outline-warning btn-sm ms-1 reassign-btn"
												title="Reassign Task"
												data-bs-toggle="modal"
												data-bs-target="#reassignTaskModal"
												data-task-id="<?= (int)$task['id'] ?>"
												data-assignee="<?= htmlspecialchars((string)($task['assigned_to'] ?? ''), ENT_QUOTES) ?>"
						data-due="<?= htmlspecialchars((string)($task['due_date'] ?? ''), ENT_QUOTES) ?>"
						data-task-type="<?= htmlspecialchars((string)($task['task_type'] ?? 'assigned'), ENT_QUOTES) ?>"
						data-target-dept="<?= (int)($task['target_department_id'] ?? 0) ?>">
												<i class="fas fa-exchange-alt"></i>
											</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Quick Update Task Modal -->
<div class="modal fade" id="updateTaskModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<form class="modal-content" method="post" action="update_progress.php">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Task</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<input type="hidden" name="task_id" value="">
				<input type="hidden" name="redirect_to" value="index.php">
				<div class="mb-3">
					<label class="form-label">Progress: <span id="updateProgressValue">0%</span></label>
					<input type="range" class="form-range" name="progress" min="0" max="100" step="5" value="0">
				</div>
				<div class="mb-3">
					<label class="form-label">Status</label>
					<select class="form-select" name="status">
						<option value="pending">Pending</option>
						<option value="in_progress">In Progress</option>
						<option value="completed">Completed</option>
						<option value="on_hold">On Hold</option>
						<option value="cancelled">Cancelled</option>
					</select>
				</div>
				<div class="mb-3">
					<label class="form-label">Notes</label>
					<textarea class="form-control" name="update_notes" rows="3" placeholder="Optional notes"></textarea>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
			</div>
		</form>
	</div>
</div>

<?php if ($isAdmin): ?>
<!-- Admin Reassign Task Modal -->
<div class="modal fade" id="reassignTaskModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<form id="reassignTaskForm" class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fas fa-exchange-alt me-2"></i>Reassign Task</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<input type="hidden" name="task_id" value="">
				<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
				<div class="mb-3">
					<label class="form-label">Assignment Type</label>
					<select name="assignment_type" id="assignment_type" class="form-select">
						<option value="employee">Employee</option>
							<option value="department">Department</option>
							<option value="open">Open</option>
					</select>
				</div>
				<div class="mb-3" id="assign_employee_group">
					<label class="form-label">Assign To (Employee)</label>
					<select name="assigned_to" class="form-select">
						<option value="">Select an employee...</option>
						<?php foreach ($employees as $emp): ?>
							<option value="<?= htmlspecialchars($emp['emp_id']) ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="mb-3 d-none" id="assign_department_group">
					<label class="form-label">Target Department</label>
					<select name="target_department_id" class="form-select">
						<option value="">Select a department...</option>
						<?php foreach ($departments as $dept): ?>
							<option value="<?= (int)$dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="mb-0">
					<label class="form-label">New Deadline</label>
					<input type="date" name="due_date" class="form-control">
					<div class="form-text">Optionally set or update the deadline for this task.</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i>Reassign</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<style>
.avatar-sm { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; }
.avatar-title { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; }
</style>

<script>
$(document).ready(function() {
	if ($.fn.DataTable) {
		$('#myTasksTable').DataTable({
			responsive: true,
			pageLength: 25,
			order: [[0, 'desc']],
			columnDefs: [
				{ orderable: false, targets: [8] }
			]
		});
	}

	// Populate quick update modal with task data
	var updateModal = document.getElementById('updateTaskModal');
	if (updateModal) {
		updateModal.addEventListener('show.bs.modal', function (event) {
			var button = event.relatedTarget;
			if (!button) return;
			var id = button.getAttribute('data-task-id');
			var progress = button.getAttribute('data-progress') || 0;
			var status = button.getAttribute('data-status') || 'in_progress';
			updateModal.querySelector('input[name="task_id"]').value = id;
			var progressInput = updateModal.querySelector('input[name="progress"]');
			var progressValue = updateModal.querySelector('#updateProgressValue');
			if (progressInput) {
				progressInput.value = progress;
				if (progressValue) progressValue.textContent = progress + '%';
			}
			var statusSelect = updateModal.querySelector('select[name="status"]');
			if (statusSelect) statusSelect.value = status;
		});

		var progressInputGlobal = document.querySelector('#updateTaskModal input[name="progress"]');
		var progressValueGlobal = document.getElementById('updateProgressValue');
		if (progressInputGlobal) {
			progressInputGlobal.addEventListener('input', function(){
				if (progressValueGlobal) progressValueGlobal.textContent = this.value + '%';
			});
		}
	}

	// Admin reassign modal wiring
	<?php if ($isAdmin): ?>
	var reassignModal = document.getElementById('reassignTaskModal');
	if (reassignModal) {
		reassignModal.addEventListener('show.bs.modal', function (event) {
			var button = event.relatedTarget;
			if (!button) return;
			var id = button.getAttribute('data-task-id');
			var assignee = button.getAttribute('data-assignee') || '';
			var due = button.getAttribute('data-due') || '';
			var ttype = button.getAttribute('data-task-type') || 'assigned';
			var tdept = button.getAttribute('data-target-dept') || '';
			reassignModal.querySelector('input[name="task_id"]').value = id;
			var assignType = reassignModal.querySelector('#assignment_type');
			var empGroup = reassignModal.querySelector('#assign_employee_group');
			var deptGroup = reassignModal.querySelector('#assign_department_group');
			var sel = reassignModal.querySelector('select[name="assigned_to"]');
			var dsel = reassignModal.querySelector('select[name="target_department_id"]');
			function toggleType(v){
				if (!assignType) return;
				assignType.value = v;
				if (empGroup && deptGroup) {
					if (v === 'employee') { empGroup.classList.remove('d-none'); deptGroup.classList.add('d-none'); }
					else if (v === 'department') { empGroup.classList.add('d-none'); deptGroup.classList.remove('d-none'); }
					else { empGroup.classList.add('d-none'); deptGroup.classList.add('d-none'); }
				}
				// Required toggles
				if (sel) sel.required = (v === 'employee');
				if (dsel) dsel.required = (v === 'department');
			}
			if (ttype === 'department') { toggleType('department'); }
			else if (ttype === 'open') { toggleType('open'); }
			else { toggleType(assignee ? 'employee' : 'open'); }
			if (sel) sel.value = assignee;
			if (dsel) dsel.value = tdept;
			var dueInput = reassignModal.querySelector('input[name="due_date"]');
			if (dueInput) {
				// Normalize to YYYY-MM-DD if value contains time
				if (due && due.length >= 10) {
					dueInput.value = due.substring(0,10);
				} else {
					dueInput.value = '';
				}
			}
			// Change handler for assignment type
			if (assignType) {
				assignType.addEventListener('change', function(){
					toggleType(this.value);
				});
			}
		});

		var reassignForm = document.getElementById('reassignTaskForm');
		if (reassignForm) {
			reassignForm.addEventListener('submit', function(e){
				e.preventDefault();
				var formData = new FormData(reassignForm);
				var submitBtn = reassignForm.querySelector('button[type="submit"]');
				if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reassigning...'; }
				fetch('reassign_task.php', {
					method: 'POST',
					body: new URLSearchParams(formData)
				}).then(r => r.json()).then(resp => {
					if (resp && resp.success) {
						if (typeof Swal !== 'undefined') {
							Swal.fire({ title: 'Reassigned', text: 'Task was reassigned successfully.', icon: 'success', timer: 1400, showConfirmButton: false }).then(()=> location.reload());
						} else {
							location.reload();
						}
					} else {
						var msg = (resp && resp.message) ? resp.message : 'Failed to reassign task';
						if (typeof Swal !== 'undefined') {
							Swal.fire({ title: 'Error', text: msg, icon: 'error' });
						} else {
							alert('Error: ' + msg);
						}
						if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Reassign'; }
					}
				}).catch(() => {
					if (typeof Swal !== 'undefined') {
						Swal.fire({ title: 'Network Error', text: 'Please try again', icon: 'error' });
					} else {
						alert('Network error');
					}
					if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Reassign'; }
				});
			});
		}
	}
	<?php endif; ?>
});
</script>

<?php
// Include the create task modal for quick add
require_once 'create_task_modal.php';

require_once '../../includes/footer.php';
?>


<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once 'task_helpers.php';

$page = 'Task Management';
$home = '../../';

// Get current user
$current_user = $_SESSION['emp_id'] ?? null;
if (!$current_user) {
    header("Location: " . $home . "index.php");
    exit();
}

// Get filters
$filter = $_GET['filter'] ?? 'assigned_to_me';
$status = $_GET['status'] ?? 'all';
$priority = $_GET['priority'] ?? 'all';

// Get tasks based on filter
$tasks = getTasks($pdo, $current_user, $filter);

// Apply additional filters
if ($status !== 'all') {
    $tasks = array_filter($tasks, function($task) use ($status) {
        return $task['status'] === $status;
    });
}

if ($priority !== 'all') {
    $tasks = array_filter($tasks, function($task) use ($priority) {
        return $task['priority'] === $priority;
    });
}

// Get task statistics
$stats = getTaskStatistics($pdo, $current_user);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Task Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo $home; ?>dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Tasks</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo $stats['my_tasks']['total']; ?></h3>
                            <p>My Total Tasks</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo $stats['my_tasks']['pending'] + $stats['my_tasks']['in_progress']; ?></h3>
                            <p>Active Tasks</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo $stats['my_tasks']['completed']; ?></h3>
                            <p>Completed Tasks</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?php echo $stats['my_tasks']['overdue']; ?></h3>
                            <p>Overdue Tasks</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Task Management Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-tasks mr-2"></i>Tasks
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createTaskModal">
                            <i class="fas fa-plus"></i> Create New Task
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select class="form-control" id="filterSelect" onchange="updateFilters()">
                                <option value="assigned_to_me" <?php echo $filter === 'assigned_to_me' ? 'selected' : ''; ?>>Tasks Assigned to Me</option>
                                <option value="assigned_by_me" <?php echo $filter === 'assigned_by_me' ? 'selected' : ''; ?>>Tasks Assigned by Me</option>
                                <option value="my_subordinates" <?php echo $filter === 'my_subordinates' ? 'selected' : ''; ?>>My Subordinates' Tasks</option>
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All My Tasks</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="statusSelect" onchange="updateFilters()">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="on_hold" <?php echo $status === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="prioritySelect" onchange="updateFilters()">
                                <option value="all" <?php echo $priority === 'all' ? 'selected' : ''; ?>>All Priority</option>
                                <option value="urgent" <?php echo $priority === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tasks Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="tasksTable">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Assigned To</th>
                                    <th>Assigned By</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                        <?php if ($task['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($task['description'], 0, 100)) . (strlen($task['description']) > 100 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($task['assignee_first'] . ' ' . $task['assignee_last']); ?>
                                        <?php if ($task['assignee_designation']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($task['assignee_designation']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($task['assignor_first'] . ' ' . $task['assignor_last']); ?>
                                        <?php if ($task['assignor_designation']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($task['assignor_designation']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $task['priority'] === 'urgent' ? 'danger' : 
                                                ($task['priority'] === 'high' ? 'warning' : 
                                                ($task['priority'] === 'medium' ? 'info' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $task['status'] === 'completed' ? 'success' : 
                                                ($task['status'] === 'in_progress' ? 'primary' : 
                                                ($task['status'] === 'on_hold' ? 'warning' : 
                                                ($task['status'] === 'cancelled' ? 'danger' : 'secondary'))); 
                                        ?>">
                                            <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-<?php echo $task['progress'] == 100 ? 'success' : 'primary'; ?>" 
                                                 style="width: <?php echo $task['progress']; ?>%"></div>
                                        </div>
                                        <small><?php echo $task['progress']; ?>%</small>
                                    </td>
                                    <td>
                                        <?php if ($task['due_date']): ?>
                                            <?php 
                                            $due_date = new DateTime($task['due_date']);
                                            $today = new DateTime();
                                            $is_overdue = $due_date < $today && !in_array($task['status'], ['completed', 'cancelled']);
                                            ?>
                                            <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                                <?php echo $due_date->format('M d, Y'); ?>
                                            </span>
                                            <?php if ($is_overdue): ?>
                                                <i class="fas fa-exclamation-triangle text-danger ml-1"></i>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No due date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-info" onclick="viewTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($task['assigned_to'] == $current_user): ?>
                                            <button class="btn btn-sm btn-primary" onclick="updateProgress(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Create New Task</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="create_task.php" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="title">Task Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="assigned_to">Assign To *</label>
                                <select class="form-control" id="assigned_to" name="assigned_to" required>
                                    <option value="">Select Employee</option>
                                    <?php
                                    $assignableEmployees = getAssignableEmployees($pdo, $current_user);
                                    ?>
                                    <optgroup label="Myself">
                                        <?php foreach ($assignableEmployees['self'] as $emp): ?>
                                        <option value="<?php echo $emp['emp_id']; ?>">
                                            <?php echo htmlspecialchars($emp['full_name']); ?>
                                            <?php if ($emp['designation_title']): ?>
                                                (<?php echo htmlspecialchars($emp['designation_title']); ?>)
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    
                                    <?php if (!empty($assignableEmployees['subordinates'])): ?>
                                    <optgroup label="My Subordinates">
                                        <?php foreach ($assignableEmployees['subordinates'] as $emp): ?>
                                        <option value="<?php echo $emp['emp_id']; ?>">
                                            <?php echo htmlspecialchars($emp['full_name']); ?>
                                            <?php if ($emp['designation_title']): ?>
                                                (<?php echo htmlspecialchars($emp['designation_title']); ?>)
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($assignableEmployees['colleagues'])): ?>
                                    <optgroup label="Colleagues">
                                        <?php foreach ($assignableEmployees['colleagues'] as $emp): ?>
                                        <option value="<?php echo $emp['emp_id']; ?>">
                                            <?php echo htmlspecialchars($emp['full_name']); ?>
                                            <?php if ($emp['designation_title']): ?>
                                                (<?php echo htmlspecialchars($emp['designation_title']); ?>)
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="priority">Priority</label>
                                <select class="form-control" id="priority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="due_date">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" class="form-control" id="category" name="category" placeholder="e.g., Development, Marketing, HR">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateFilters() {
    const filter = document.getElementById('filterSelect').value;
    const status = document.getElementById('statusSelect').value;
    const priority = document.getElementById('prioritySelect').value;
    
    const url = new URL(window.location);
    url.searchParams.set('filter', filter);
    url.searchParams.set('status', status);
    url.searchParams.set('priority', priority);
    
    window.location.href = url.toString();
}

function viewTask(taskId) {
    window.location.href = 'view_task.php?id=' + taskId;
}

function updateProgress(taskId) {
    window.location.href = 'update_task.php?id=' + taskId;
}

$(document).ready(function() {
    $('#tasksTable').DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false,
        "order": [[ 6, "asc" ]], // Sort by due date
        "pageLength": 25
    });
    
    // Set minimum date to today for due date
    document.getElementById('due_date').min = new Date().toISOString().split('T')[0];
});
</script>

<?php include '../../includes/footer.php'; ?>

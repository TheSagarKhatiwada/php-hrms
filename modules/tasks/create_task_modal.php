<?php
// Create Task Modal used by Task pages (dashboard, my-tasks, etc.)
// Fetch lightweight data for selects (employees, categories)
// Assumes $pdo and session are already available via including pages

$employees = [];
$task_categories = [];
$departments = [];

try {
    $stmt = $pdo->prepare("SELECT emp_id, first_name, last_name FROM employees WHERE exit_date IS NULL ORDER BY first_name, last_name");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM task_categories ORDER BY name");
    $stmt->execute();
    $task_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback small set if table missing
    $task_categories = [
        ['id' => 1, 'name' => 'General'],
        ['id' => 2, 'name' => 'Development'],
        ['id' => 3, 'name' => 'Support'],
    ];
}

try {
	$stmt = $pdo->prepare("SELECT id, name FROM departments ORDER BY name");
	$stmt->execute();
	$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	$departments = [];
}
?>
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New Task</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="createTaskForm" method="post" action="create-task.php">
					<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? (function_exists('generate_csrf_token') ? generate_csrf_token() : '') ?>">
					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label">Task Type</label>
							<select name="task_type" id="task_type" class="form-select">
								<option value="open" selected>Open (anyone can take)</option>
								<option value="department">Department (members can take)</option>
								<option value="assigned">Assigned (direct to employee)</option>
							</select>
						</div>
						<!-- Dynamic right-side selector aligned with Task Type -->
						<div class="col-md-6" id="assigned_to_group">
							<label class="form-label">Assign To <span class="text-danger">*</span></label>
							<select name="assigned_to" class="form-select">
								<option value="">Select an employee...</option>
								<?php foreach ($employees as $emp): ?>
									<option value="<?= htmlspecialchars($emp['emp_id']) ?>"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-6 d-none" id="department_group">
							<label class="form-label">Target Department</label>
							<select name="target_department_id" class="form-select">
								<option value="">Select a department...</option>
								<?php foreach ($departments as $dept): ?>
									<option value="<?= (int)$dept['id'] ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-8">
							<label class="form-label">Category</label>
							<select name="category" class="form-select">
								<option value="">Select a category...</option>
								<?php foreach ($task_categories as $cat): ?>
									<option value="<?= htmlspecialchars($cat['name']) ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-4">
							<label class="form-label">Priority</label>
							<select name="priority" class="form-select">
								<option value="low">Low</option>
								<option value="medium" selected>Medium</option>
								<option value="high">High</option>
								<option value="urgent">Urgent</option>
							</select>
						</div>
						<div class="col-md-8">
							<label class="form-label">Title</label>
							<input type="text" name="title" class="form-control" required>
						</div>
						<div class="col-md-4">
							<label class="form-label">Due Date</label>
							<input type="date" name="due_date" class="form-control" min="<?= date('Y-m-d') ?>">
						</div>
						<div class="col-12">
							<label class="form-label">Description</label>
							<textarea name="description" class="form-control" rows="4"></textarea>
						</div>
						
					</div>
					<input type="hidden" name="quick_create" value="1">
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				<button type="submit" class="btn btn-primary" form="createTaskForm"><i class="fas fa-paper-plane me-1"></i>Create</button>
			</div>
		</div>
	</div>

	<script>
	(function(){
		const typeEl = document.getElementById('task_type');
		const assignedGroup = document.getElementById('assigned_to_group');
		const deptGroup = document.getElementById('department_group');
		const assignedSelect = assignedGroup ? assignedGroup.querySelector('select[name="assigned_to"]') : null;
		const deptSelect = deptGroup ? deptGroup.querySelector('select[name="target_department_id"]') : null;

		function updateVisibility(){
			const v = typeEl ? typeEl.value : 'assigned';
			if (!assignedGroup || !deptGroup) return;
			if (v === 'assigned') {
				assignedGroup.classList.remove('d-none');
				deptGroup.classList.add('d-none');
				if (assignedSelect) assignedSelect.required = true;
				if (deptSelect) deptSelect.required = false;
			} else if (v === 'department') {
				assignedGroup.classList.add('d-none');
				deptGroup.classList.remove('d-none');
				if (assignedSelect) assignedSelect.required = false;
				if (deptSelect) deptSelect.required = true;
			} else { // open
				assignedGroup.classList.add('d-none');
				deptGroup.classList.add('d-none');
				if (assignedSelect) assignedSelect.required = false;
				if (deptSelect) deptSelect.required = false;
			}
		}
		if (typeEl) {
			typeEl.addEventListener('change', updateVisibility);
			updateVisibility();
		}
	})();
	</script>
</div>

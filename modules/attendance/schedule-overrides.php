<?php
$page = 'schedule-overrides';
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
require_once __DIR__ . '/../../includes/schedule_helpers.php'; // For any helpers if needed
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../includes/utilities.php'; // for is_admin/has_permission

// Fetch all employees for dropdown
$employees = [];
try {
    $stmt = $pdo->query("SELECT emp_id, first_name, last_name, branch FROM employees WHERE exit_date IS NULL ORDER BY first_name, last_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Fetch branches for branch selector
    $bstmt = $pdo->query("SELECT id, name FROM branches ORDER BY name");
    $branches = $bstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF for POST requests
    verify_csrf_post();

    // Permission check: only admins or users with permission can modify overrides
    if (!is_admin() && !has_permission('manage_schedule_overrides')) {
        http_response_code(403);
        die('Not authorized to perform this action');
    }

    if (isset($_POST['save_override'])) {
        $selected_branch = $_POST['branch'] ?? '';
        // emp_id can be multiple
        $emp_ids = [];
        if (isset($_POST['emp_id'])) {
            if (is_array($_POST['emp_id'])) {
                $emp_ids = array_filter($_POST['emp_id']);
            } else {
                $emp_ids = [$_POST['emp_id']];
            }
        }
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $work_start_time = $_POST['work_start_time'];
        $work_end_time = $_POST['work_end_time'];
        $reason = $_POST['reason'];
        $override_id = $_POST['override_id'] ?? null;

        if ($override_id) {
            // Updating a single override (override_id identifies the row)
            $emp_id_single = $emp_ids[0] ?? null;
            if ($emp_id_single) {
                $sql = "UPDATE employee_schedule_overrides SET emp_id = ?, start_date = ?, end_date = ?, work_start_time = ?, work_end_time = ?, reason = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$emp_id_single, $start_date, $end_date, $work_start_time, $work_end_time, $reason, $override_id]);
            }
        } else {
            // Insert new overrides - allow multiple employee selection
            $sql = "INSERT INTO employee_schedule_overrides (emp_id, start_date, end_date, work_start_time, work_end_time, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            foreach ($emp_ids as $eid) {
                $stmt->execute([$eid, $start_date, $end_date, $work_start_time, $work_end_time, $reason, $_SESSION['user_id'] ?? 'admin']);
            }
        }
    } elseif (isset($_POST['delete_override'])) {
        $override_id = $_POST['override_id'];
        $sql = "DELETE FROM employee_schedule_overrides WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$override_id]);
    }
}

// Fetch existing overrides
$overrides = [];
try {
    $stmt = $pdo->query("
        SELECT o.*, e.first_name, e.last_name, e.branch AS emp_branch 
        FROM employee_schedule_overrides o
        JOIN employees e ON o.emp_id = e.emp_id
        ORDER BY o.start_date DESC
    ");
    $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
}

?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Schedule Overrides</h1>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Add/Edit Schedule Override</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="schedule-overrides.php">
                <?php echo csrf_token_input(); ?>
                <input type="hidden" name="override_id" id="override_id">
                <div class="mb-2">
                    <input type="text" id="emp_search" class="form-control" placeholder="Search employees by name..." />
                </div>
                <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="branch">Branch (optional)</label>
                            <select name="branch" id="branch" class="form-control">
                                <option value="">All Branches</option>
                                <?php if (!empty($branches)): foreach ($branches as $b): ?>
                                    <option value="<?php echo (int)$b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="emp_id">Employee(s)</label>
                            <select name="emp_id[]" id="emp_id" class="form-control" multiple size="6" required>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo htmlspecialchars($employee['emp_id']); ?>" data-branch="<?php echo htmlspecialchars($employee['branch']); ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Hold Ctrl (Windows) / Cmd (Mac) to select multiple employees.</small>
                        </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="start_date">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="end_date">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="work_start_time">Work Start Time</label>
                        <input type="time" name="work_start_time" id="work_start_time" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="work_end_time">Work End Time</label>
                        <input type="time" name="work_end_time" id="work_end_time" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reason">Reason</label>
                    <input type="text" name="reason" id="reason" class="form-control" placeholder="e.g., Special project, client meeting">
                </div>
                <button type="submit" name="save_override" class="btn btn-primary">Save Override</button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">Clear</button>
                <button type="button" class="btn btn-info" id="previewBtn">Preview Selected</button>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Existing Overrides</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Branch</th>
                            <th>Date Range</th>
                            <th>Time</th>
                            <th>Reason</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overrides as $override): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($override['first_name'] . ' ' . $override['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($override['emp_branch'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($override['start_date']); ?> to <?php echo htmlspecialchars($override['end_date']); ?></td>
                                <td><?php echo htmlspecialchars(date('h:i A', strtotime($override['work_start_time']))); ?> - <?php echo htmlspecialchars(date('h:i A', strtotime($override['work_end_time']))); ?></td>
                                <td><?php echo htmlspecialchars($override['reason']); ?></td>
                                <td><?php echo htmlspecialchars($override['created_by'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick='editOverride(<?php echo json_encode($override); ?>)'>Edit</button>
                                    <form method="POST" action="schedule-overrides.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this override?');">
                                        <input type="hidden" name="override_id" value="<?php echo $override['id']; ?>">
                                        <?php echo csrf_token_input(); ?>
                                        <button type="submit" name="delete_override" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editOverride(override) {
    document.getElementById('override_id').value = override.id;
    // For editing, only one employee is associated with an override
    // Clear existing selections first
    Array.from(document.getElementById('emp_id').options).forEach(option => {
        option.selected = false;
    });
    document.getElementById('emp_id').value = override.emp_id;
    document.getElementById('start_date').value = override.start_date;
    document.getElementById('end_date').value = override.end_date;
    document.getElementById('work_start_time').value = override.work_start_time;
    document.getElementById('work_end_time').value = override.work_end_time;
    document.getElementById('reason').value = override.reason;
    // When editing, we don't pre-select branch as override is specific to one employee
    document.getElementById('branch').value = ''; 
    filterEmployees(); // Re-filter to show all employees for selection
    window.scrollTo(0, 0);
}

function resetForm() {
    document.getElementById('override_id').value = '';
    const formEl = document.querySelector('form');
    if (formEl) formEl.reset();
    if (branchSelect) branchSelect.value = '';
    currentSearchQuery = '';
    if (empSearchInput) empSearchInput.value = '';
    filterEmployees();
}

// Employee filtering by branch + search
const allEmployeeOptions = Array.from(document.querySelectorAll('#emp_id option'));
const branchSelect = document.getElementById('branch');
const empSearchInput = document.getElementById('emp_search');
let currentSearchQuery = '';

if (branchSelect) {
    branchSelect.addEventListener('change', function(){
        filterEmployees();
    });
}

if (empSearchInput) {
    empSearchInput.addEventListener('input', function(e){
        currentSearchQuery = e.target.value.toLowerCase().trim();
        filterEmployees();
    });
}

function filterEmployees() {
    const selectedBranch = branchSelect ? branchSelect.value : '';
    allEmployeeOptions.forEach(option => {
        const employeeBranch = option.dataset.branch || '';
        const optionText = (option.textContent || '').toLowerCase();
        const matchesBranch = selectedBranch === '' || employeeBranch === selectedBranch;
        const matchesSearch = currentSearchQuery === '' || optionText.indexOf(currentSearchQuery) !== -1;
        const shouldShow = matchesBranch && matchesSearch;
        option.hidden = !shouldShow;
        option.style.display = shouldShow ? '' : 'none';
        if (!shouldShow) {
            option.selected = false; // Deselect if hidden so preview/select visible stays accurate
        }
    });
}

// Run once so the initial state respects any pre-filled values
filterEmployees();
</script>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview Schedule Override</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="previewContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Preview button handler
document.getElementById('previewBtn').addEventListener('click', function(){
        const selected = Array.from(document.getElementById('emp_id').selectedOptions).map(o => o.textContent.trim());
        const start = document.getElementById('start_date').value;
        const end = document.getElementById('end_date').value;
        const startTime = document.getElementById('work_start_time').value;
        const endTime = document.getElementById('work_end_time').value;
        const reason = document.getElementById('reason').value;
        if(selected.length === 0){ alert('Please select at least one employee to preview.'); return; }
        let html = '<p><strong>Date Range:</strong> ' + start + ' to ' + end + '</p>';
        html += '<p><strong>Time:</strong> ' + startTime + ' - ' + endTime + '</p>';
        if(reason) html += '<p><strong>Reason:</strong> ' + (reason) + '</p>';
        html += '<hr /><p><strong>Employees:</strong></p><ul>';
        selected.forEach(s => { html += '<li>' + s + '</li>'; });
        html += '</ul>';
        document.getElementById('previewContent').innerHTML = html;
        // Show bootstrap modal
        var previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
        previewModal.show();
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

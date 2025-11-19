<?php
    // page slug used by header/sidebar to mark active menu. Keep it as a slug.
    $page = 'schedule-overrides';
    require_once __DIR__ . '/../../includes/session_config.php';
    require_once __DIR__ . '/../../includes/db_connection.php';
    // Load CSRF and utilities early so POST handling can run before any output (Post/Redirect/Get)
    require_once __DIR__ . '/../../includes/csrf_protection.php';
    require_once __DIR__ . '/../../includes/utilities.php'; // for is_admin/has_permission
    require_once __DIR__ . '/../../includes/schedule_helpers.php'; // For any helpers if needed

    // Handle form submission early to enable Post/Redirect/Get and avoid browser "resubmit form" prompt
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

        // Redirect to the same page (PRG) to avoid browser resubmit on refresh
        $redirectTo = $_SERVER['REQUEST_URI'] ?? 'schedule-overrides.php';
        header('Location: ' . $redirectTo);
        exit;
    }

    // Now include UI chrome (these output HTML)
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/sidebar.php';
    require_once __DIR__ . '/../../includes/topbar.php';

// Fetch all employees for dropdown
$employees = [];
try {
    $stmt = $pdo->query("SELECT emp_id, first_name, middle_name, last_name, branch, work_start_time, work_end_time FROM employees WHERE exit_date IS NULL AND (mach_id_not_applicable IS NULL OR mach_id_not_applicable = 0) ORDER BY first_name, middle_name, last_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Fetch branches for branch selector
    $bstmt = $pdo->query("SELECT id, name FROM branches ORDER BY name");
    $branches = $bstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
}

// Fetch existing overrides
$overrides = [];
try {
    $stmt = $pdo->query("
        SELECT o.*, e.first_name, e.middle_name, e.last_name, e.branch AS emp_branch, b.name AS branch_name,
               creator.first_name AS creator_first, creator.middle_name AS creator_middle, creator.last_name AS creator_last, o.created_at AS override_created_at
        FROM employee_schedule_overrides o
        JOIN employees e ON o.emp_id = e.emp_id
        LEFT JOIN branches b ON e.branch = b.id
        LEFT JOIN employees creator ON o.created_by = creator.emp_id
        ORDER BY o.start_date DESC
    ");
    $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
}

// Prepare a map of current schedule for employees (for modal preview)
$employeeSchedules = [];
try {
    $empIdsForPrefetch = array_map(function($e){ return $e['emp_id']; }, $employees);
    $today = date('Y-m-d');
    $overridesMap = prefetch_schedule_overrides($pdo, $empIdsForPrefetch, $today, $today);
    foreach($employees as $emp){
        $empId = $emp['emp_id'];
        $empOverrides = $overridesMap[$empId] ?? [];
        $sched = resolve_schedule_for_emp_date($emp, $today, $empOverrides, ($emp['work_start_time'] ?? '09:00'), ($emp['work_end_time'] ?? '18:00'));
        // Format times for display
        $start_fmt = isset($sched['start']) && $sched['start'] !== '' ? date('h:i A', strtotime($sched['start'])) : '';
        $end_fmt = isset($sched['end']) && $sched['end'] !== '' ? date('h:i A', strtotime($sched['end'])) : '';
        // Find override reason if applicable
        $override_reason = '';
        if (($sched['source'] ?? '') === 'override' && !empty($empOverrides)) {
            foreach($empOverrides as $ov) {
                if (isset($ov['id']) && $ov['id'] == ($sched['id'] ?? null)) { $override_reason = $ov['reason'] ?? ''; break; }
            }
        }
        $fullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['middle_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
        $employeeSchedules[$empId] = [
            'start' => $sched['start'] ?? '',
            'end' => $sched['end'] ?? '',
            'start_fmt' => $start_fmt,
            'end_fmt' => $end_fmt,
            'source' => $sched['source'] ?? '',
            'id' => $sched['id'] ?? null,
            'reason' => $override_reason,
            'name' => $fullName
        ];
    }
} catch (Exception $e) {
    // ignore
}

?>

<div class="container-fluid">
    <!-- Visible page title for body (keeps header/sidebar slug behavior intact) -->
    <div class="row mb-2">
        <div class="col-12">
            <h1 class="page-title">Schedule Overrides</h1>
        </div>
    </div>
    <div class="card-header py-3">
        <div class="d-flex align-items-center">
            <!-- Search box replaces static label -->
            <div class="search-wrapper" style="width:auto; max-width:260px;">
                <button id="overrides_search_btn" class="btn btn-primary" type="button" aria-label="Search"><i class="fas fa-search" aria-hidden="true"></i></button>
                <input id="overrides_search" type="search" class="form-control search-input" placeholder="Search overrides" aria-label="Search overrides">
            </div>
            <div class="ms-auto">
                <button id="openAddOverride" class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addOverrideModal"><i class="fas fa-plus me-2" aria-hidden="true"></i> Add Override</button>
            </div>
        </div>
    </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date & Time</th>
                            <th>Reason</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overrides as $override): ?>
                            <tr>
                                <td>
                                    <div><?php echo htmlspecialchars(trim(($override['first_name'] ?? '') . ' ' . ($override['middle_name'] ?? '') . ' ' . ($override['last_name'] ?? ''))); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($override['branch_name'] ?? $override['emp_branch'] ?? ''); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($override['start_date'] . ' to ' . $override['end_date']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars(date('h:i A', strtotime($override['work_start_time'])) . ' - ' . date('h:i A', strtotime($override['work_end_time']))); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($override['reason']); ?></td>
                                <td>
                                    <?php
                                        $creatorName = trim((isset($override['creator_first']) ? $override['creator_first'] : '') . ' ' . (isset($override['creator_middle']) ? $override['creator_middle'] : '') . ' ' . (isset($override['creator_last']) ? $override['creator_last'] : ''));
                                        if ($creatorName === '') $creatorName = ($override['created_by'] ?? '');
                                    ?>
                                    <div><?php echo htmlspecialchars($creatorName); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars(date('Y-m-d h:i A', strtotime($override['override_created_at'] ?? $override['created_at'] ?? ''))); ?></div>
                                </td>
                                <td>
                                                                    <div class="d-flex gap-1 align-items-center">
                                                                        <button class="btn btn-icon btn-warning" title="Edit" aria-label="Edit" data-bs-toggle="tooltip" onclick='editOverride(<?php echo json_encode($override); ?>)'><i class="fas fa-pen" aria-hidden="true"></i></button>
                                                                        <button class="btn btn-icon btn-danger" title="Delete" aria-label="Delete" data-bs-toggle="tooltip" onclick="deleteOverride(<?php echo json_encode($override['id']); ?>)"><i class="fas fa-trash" aria-hidden="true"></i></button>
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

<script>
// Employee schedules map (populated from server)
const employeeSchedules = <?php echo json_encode($employeeSchedules); ?> || {};

// Small HTML escape helper for JS-rendered content
function escapeHtml(str) {
    if (!str && str !== 0) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function editOverride(override) {
    // Populate modal form fields and open modal
    document.getElementById('modal_override_id').value = override.id;
    // Clear existing selections first
    const modalEmp = document.getElementById('modal_emp_id');
    if (modalEmp) {
        Array.from(modalEmp.options).forEach(option => {
            option.selected = false;
            option.style.display = '';
        });
    }

    // Preselect branch first so the employee option is visible after filtering
    try {
        document.getElementById('modal_branch').value = override.emp_branch || '';
        filterEmployees();
    } catch (e) { /* ignore */ }

    // select matching employee option (single employee per existing override)
    const empIdToSelect = override.emp_id;
    try {
        if (window.empTomSelect) {
            // Clear existing TomSelect items then add the one we want
            try { window.empTomSelect.clear(); } catch(e){}
            // Ensure options are refreshed so TomSelect knows about visibility/disabled state
            try { window.empTomSelect.refreshOptions(false); } catch(e){}
            if (empIdToSelect !== undefined && empIdToSelect !== null && String(empIdToSelect) !== '') {
                try { window.empTomSelect.addItem(String(empIdToSelect)); } catch(e) { console.error('TomSelect addItem failed', e); }
            }
        } else if (modalEmp) {
            const empOpt = Array.from(modalEmp.options).find(o => o.value == empIdToSelect);
            if (empOpt) empOpt.selected = true;
        }
    } catch (e) { console.error('select employee error', e); }

    document.getElementById('modal_start_date').value = override.start_date;
    document.getElementById('modal_end_date').value = override.end_date;
    document.getElementById('modal_work_start_time').value = override.work_start_time;
    document.getElementById('modal_work_end_time').value = override.work_end_time;
    document.getElementById('modal_reason').value = override.reason;

    // Show modal (Bootstrap will read data attributes or we can invoke programmatically)
    try {
        var addModal = new bootstrap.Modal(document.getElementById('addOverrideModal'));
        addModal.show();
    } catch (e) {
        showModalById('addOverrideModal');
    }
}

// Unified modal opener that falls back if Bootstrap JS isn't available
function showModalById(modalId) {
    const modalEl = document.getElementById(modalId);
    if (!modalEl) {
        console.error('Modal element not found:', modalId);
        alert('Internal error: modal element not found.');
        return;
    }

    try {
        if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            const m = new bootstrap.Modal(modalEl);
            m.show();
            return;
        }
    } catch (err) {
        console.error('Bootstrap modal show failed:', err);
    }

    // Try jQuery/Bootstrap(v4) style
    try {
        if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
            jQuery(modalEl).modal('show');
            return;
        }
    } catch (err) {
        console.error('jQuery modal show failed:', err);
    }

    // Last-resort DOM fallback: make modal visible (no backdrop, limited behavior)
    try {
        modalEl.style.display = 'block';
        modalEl.classList.add('show');
        modalEl.removeAttribute('aria-hidden');
        modalEl.setAttribute('aria-modal', 'true');
        // add a simple backdrop
        let backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
        return;
    } catch (err) {
        console.error('Fallback modal display failed:', err);
        alert('Unable to open modal; check browser console for details.');
    }
}

function resetForm() {
    document.getElementById('modal_override_id').value = '';
    const form = document.getElementById('overrideForm');
    if (form) form.reset();
    // Clear Tom Select selections if present and ensure all employees are visible after reset
    if (window.empTomSelect) {
        try { window.empTomSelect.clear(); } catch (e) { /* ignore */ }
        try { window.empTomSelect.removeItems(); } catch (e) { /* ignore */ }
        try { window.empTomSelect.refreshOptions(false); } catch (e) { /* ignore */ }
    }
    const modalEmp = document.getElementById('modal_emp_id');
    if (modalEmp) {
        Array.from(modalEmp.options).forEach(option => {
            option.style.display = '';
            option.selected = false;
            // Re-enable option in TomSelect if it exists
            if (window.empTomSelect && window.empTomSelect.options && window.empTomSelect.options[option.value]) {
                window.empTomSelect.options[option.value].disabled = false;
            }
        });
    }
    const mb = document.getElementById('modal_branch');
    if (mb) mb.value = '';
    const modalSearch = document.getElementById('modal_emp_search');
    if (modalSearch) modalSearch.value = '';
    currentModalSearch = '';
    // Uncheck "Select visible" when resetting
    const sv = document.getElementById('select_visible');
    if (sv) sv.checked = false;
    // Re-run filtering and preview update
    try { filterEmployees(); } catch (e) { /* ignore */ }
    try { renderCurrentSchedule(); } catch (e) { /* ignore */ }
}

// Tom Select integration + AJAX handlers + enhanced filtering
</script>

<!-- Tom Select integration + AJAX handlers -->
<script>
// Dynamically load Tom Select CSS/JS from CDN
(function(){
    var css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = 'https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.bootstrap5.min.css';
    document.head.appendChild(css);
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js';
    s.defer = true;
    document.head.appendChild(s);
})();

// Initialize TomSelect when available
function initTomSelectOnce(){
    if (!window.TomSelect) { setTimeout(initTomSelectOnce, 150); return; }
    if (window.empTomSelect) return;
    try {
        window.empTomSelect = new TomSelect('#modal_emp_id', {
            plugins: ['remove_button'],
            maxItems: null,
            hideSelected: true,
            closeAfterSelect: false,
            render: { option: function(data, escape) { return '<div>'+escape(data.text)+'</div>'; } }
        });
        // wire change event
        window.empTomSelect.on('change', function() { onEmpSelectionChange(); });
    } catch (err) { console.error('TomSelect init error', err); }
}
initTomSelectOnce();

// Toast helper (creates container on demand)
function ensureToastContainer() {
    let c = document.getElementById('toastContainer');
    if (!c) {
        c = document.createElement('div');
        c.id = 'toastContainer';
        c.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(c);
    }
    return c;
}
function showToast(message, type = 'success') {
    try {
        const container = ensureToastContainer();
        const toastEl = document.createElement('div');
        const bg = (type === 'success') ? 'success' : 'danger';
        toastEl.className = 'toast align-items-center text-bg-' + bg + ' border-0';
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.innerHTML = '<div class="d-flex"><div class="toast-body">' + escapeHtml(message) + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
        container.appendChild(toastEl);
        const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
        bsToast.show();
        toastEl.addEventListener('hidden.bs.toast', function () { toastEl.remove(); });
    } catch (e) { console.error('showToast error', e); }
}

function syncTomSelectOptionState(optionValue, disabled) {
    if (!window.empTomSelect || optionValue === undefined || optionValue === null) return;
    const value = String(optionValue);
    const optData = window.empTomSelect.options && window.empTomSelect.options[value];
    if (optData) optData.disabled = !!disabled;
    if (disabled) {
        try { window.empTomSelect.removeItem(value, true); } catch(e){}
    }
}

function filterEmployees() {
    const branchEl = document.getElementById('modal_branch');
    const selectedBranch = branchEl ? branchEl.value : '';
    const opts = Array.from(document.querySelectorAll('#modal_emp_id option'));
    opts.forEach(option => {
        const employeeBranch = option.dataset.branch || '';
        const optionText = (option.textContent || '').toLowerCase();
        const matchesBranch = selectedBranch === '' || employeeBranch === selectedBranch;
        const matchesSearch = currentModalSearch === '' || optionText.indexOf(currentModalSearch) !== -1;
        const shouldShow = matchesBranch && matchesSearch;
        option.hidden = !shouldShow;
        option.style.display = shouldShow ? '' : 'none';
        if (!shouldShow) {
            option.selected = false;
            syncTomSelectOptionState(option.value, true);
        } else {
            syncTomSelectOptionState(option.value, false);
        }
    });
    if (window.empTomSelect) {
        try { window.empTomSelect.refreshOptions(false); } catch(e){}
    }
    const selVisible = document.getElementById('select_visible');
    if (selVisible && selVisible.checked) selVisible.dispatchEvent(new Event('change'));
    try { renderCurrentSchedule(); } catch(e){}
}

// Search input integration (modal employee list)
const empSearchInput = document.getElementById('modal_emp_search');
let currentModalSearch = '';
if (empSearchInput) {
    empSearchInput.addEventListener('input', function(e){
        currentModalSearch = e.target.value.toLowerCase().trim();
        filterEmployees();
    });
}

// Search box in header filters the rendered table rows client-side
let currentOverridesSearch = '';
function applyOverridesSearchFilter(query) {
    const tbody = document.querySelector('#dataTable tbody');
    if (!tbody) return;
    const normalized = (query || '').toLowerCase().trim();
    Array.from(tbody.rows).forEach(row => {
        const text = (row.textContent || '').toLowerCase();
        row.style.display = normalized === '' || text.indexOf(normalized) !== -1 ? '' : 'none';
    });
}
const overridesSearch = document.getElementById('overrides_search');
if (overridesSearch) {
    overridesSearch.addEventListener('input', function(e){
        currentOverridesSearch = e.target.value;
        applyOverridesSearchFilter(currentOverridesSearch);
    });
}

// 'Select visible' checkbox behavior: select/deselect all visible employee options
const selectVisibleCheckbox = document.getElementById('select_visible');
if (selectVisibleCheckbox) {
    selectVisibleCheckbox.addEventListener('change', function () {
        const visibleOptions = Array.from(document.querySelectorAll('#modal_emp_id option')).filter(o => o.style.display !== 'none' && !o.hidden);
        if (window.empTomSelect) {
            if (this.checked) {
                visibleOptions.forEach(o => { try { window.empTomSelect.addItem(o.value, true); } catch(e){} });
            } else {
                visibleOptions.forEach(o => { try { window.empTomSelect.removeItem(o.value, true); } catch(e){} });
            }
        } else {
            visibleOptions.forEach(o => { o.selected = this.checked; });
        }
        renderCurrentSchedule();
    });
}

// Keep preview updated when selection changes
function onEmpSelectionChange() {
    renderCurrentSchedule();
}

// AJAX save: intercept form submit with client-side validation and toast feedback
const overrideFormElem = document.getElementById('overrideForm');
function clearValidation() {
    ['modal_start_date','modal_end_date','modal_work_start_time','modal_work_end_time','modal_emp_id'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('is-invalid');
    });
}
function validateForm() {
    clearValidation();
    let valid = true;
    const sdEl = document.getElementById('modal_start_date');
    const edEl = document.getElementById('modal_end_date');
    const wsEl = document.getElementById('modal_work_start_time');
    const weEl = document.getElementById('modal_work_end_time');
    const sd = sdEl ? sdEl.value : '';
    const ed = edEl ? edEl.value : '';
    const ws = wsEl ? wsEl.value : '';
    const we = weEl ? weEl.value : '';
    let selected = [];
    if (window.empTomSelect) {
        const val = window.empTomSelect.getValue();
        if (Array.isArray(val)) selected = val; else if (typeof val === 'string' && val.length) selected = val.split(',');
    } else {
        selected = Array.from(document.querySelectorAll('#modal_emp_id option')).filter(o => o.selected).map(o => o.value);
    }
    if (!selected || selected.length === 0) {
        const sel = document.getElementById('modal_emp_id');
        if (sel) sel.classList.add('is-invalid');
        valid = false;
    }
    if (!sd) { if (sdEl) sdEl.classList.add('is-invalid'); valid = false; }
    if (!ed) { if (edEl) edEl.classList.add('is-invalid'); valid = false; }
    if (sd && ed && sd > ed) { if (sdEl) sdEl.classList.add('is-invalid'); if (edEl) edEl.classList.add('is-invalid'); showToast('Start date must be before or equal to End date', 'error'); valid = false; }
    if (!ws) { if (wsEl) wsEl.classList.add('is-invalid'); valid = false; }
    if (!we) { if (weEl) weEl.classList.add('is-invalid'); valid = false; }
    return valid;
}
if (overrideFormElem) {
    overrideFormElem.addEventListener('submit', function(e){
        e.preventDefault();
        if (!validateForm()) return;
        const formData = new FormData(overrideFormElem);
        // If using TomSelect, serialize selected items properly
        if (window.empTomSelect) {
            const items = window.empTomSelect.getValue();
            formData.delete('emp_id[]');
            if (Array.isArray(items)) {
                items.forEach(v => formData.append('emp_id[]', v));
            } else if (typeof items === 'string') {
                items.split(',').forEach(v => { if (v) formData.append('emp_id[]', v); });
            }
        }

        fetch('/api/schedule-overrides.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(r => r.json()).then(json => {
                if (!json || !json.ok) { showToast('Save failed: ' + (json && json.error ? json.error : 'unknown'), 'error'); return; }
                try { const m = bootstrap.Modal.getInstance(document.getElementById('addOverrideModal')); if (m) m.hide(); } catch(e){}
                // refresh overrides table without full page reload
                loadOverrides();
                showToast('Override saved', 'success');
        }).catch(err => { console.error(err); showToast('Save failed, see console', 'error'); });
    });
}

// AJAX delete for existing overrides
// delete via API helper (used by dynamic rows)
function deleteOverride(id) {
    if (!confirm('Are you sure you want to delete this override?')) return;
    // obtain CSRF token from the form hidden input if present
    const tokenInput = document.querySelector('input[name="csrf_token"]');
    const headers = { 'Content-Type': 'application/json' };
    if (tokenInput) headers['X-CSRF-Token'] = tokenInput.value;
    fetch('/api/schedule-overrides.php', {
        method: 'DELETE',
        headers: headers,
        body: JSON.stringify({ override_id: id }),
        credentials: 'same-origin'
    }).then(r => r.json()).then(json => {
        if (!json || !json.ok) { showToast('Delete failed: ' + (json && json.error ? json.error : 'unknown'), 'error'); return; }
        loadOverrides();
        showToast('Override deleted', 'success');
    }).catch(err => { console.error(err); alert('Delete failed, see console'); });
}

// intercept existing forms (in case page still contains server forms)
document.querySelectorAll('form[action="schedule-overrides.php"]').forEach(f => {
    f.addEventListener('submit', function(e){
        e.preventDefault();
        const fid = new FormData(f);
        const id = fid.get('override_id');
        deleteOverride(id);
    });
});

// load overrides dynamically and render table
window.overridesCache = {};
function renderOverridesTable(items) {
    const tbody = document.querySelector('#dataTable tbody');
    if (!tbody) return;
    let html = '';
    items.forEach(o => {
        // cache
        window.overridesCache[o.id] = o;
        const fullName = ((o.first_name||'') + ' ' + (o.middle_name||'') + ' ' + (o.last_name||'')).trim();
        const branchName = o.branch_name || o.emp_branch || '';
        const dateRange = (o.start_date||'') + ' to ' + (o.end_date||'');
        const timeRange = (o.work_start_time ? (new Date('1970-01-01T' + o.work_start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})) : '') + (o.work_end_time ? (' - ' + new Date('1970-01-01T' + o.work_end_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})) : '');
        const reason = o.reason || '';
        const creatorName = ((o.creator_first||'') + ' ' + (o.creator_middle||'') + ' ' + (o.creator_last||'')).trim() || (o.created_by||'');
        const createdAt = o.override_created_at || o.created_at || '';
        // (current schedule shown inside modal only) don't include in table

        html += '<tr>' +
            '<td><div>' + escapeHtml(fullName) + '</div><div class="text-muted small">' + escapeHtml(branchName) + '</div></td>' +
            '<td><div>' + escapeHtml(dateRange) + '</div><div class="text-muted small">' + escapeHtml(timeRange) + '</div></td>' +
            '<td>' + escapeHtml(reason) + '</td>' +
            '<td><div>' + escapeHtml(creatorName) + '</div><div class="text-muted small">' + escapeHtml(createdAt) + '</div></td>' +
            '<td><div class="d-flex gap-1 align-items-center"><button class="btn btn-icon btn-warning" title="Edit" aria-label="Edit" data-bs-toggle="tooltip" onclick="editOverrideFromId(' + JSON.stringify(o.id) + ')"><i class="fas fa-pen" aria-hidden="true"></i></button>'
                + '<button class="btn btn-icon btn-danger" title="Delete" aria-label="Delete" data-bs-toggle="tooltip" onclick="deleteOverride(' + JSON.stringify(o.id) + ')"><i class="fas fa-trash" aria-hidden="true"></i></button></div></td>' +
        '</tr>';
    });
    tbody.innerHTML = html;
    if (typeof applyOverridesSearchFilter === 'function') {
        applyOverridesSearchFilter(currentOverridesSearch);
    }
}

function loadOverrides() {
    fetch('/api/schedule-overrides.php', { method: 'GET', credentials: 'same-origin' }).then(r => r.json()).then(json => {
        if (!json || !json.ok) { console.error('Failed to load overrides', json); return; }
        renderOverridesTable(json.data || []);
    }).catch(err => { console.error('Failed to load overrides', err); });
}

function editOverrideFromId(id) {
    const override = window.overridesCache[id];
    if (!override) { alert('Override data not found'); return; }
    // reuse existing editOverride which expects the override object
    editOverride(override);
}

// initial load
document.addEventListener('DOMContentLoaded', function(){ loadOverrides(); });

// Initialize Bootstrap tooltips for action buttons and other elements with title attributes
document.addEventListener('DOMContentLoaded', function(){
    try {
        if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (el) { new bootstrap.Tooltip(el); });
        }
    } catch (e) { console.error('Tooltip init failed', e); }
});

</script>

<!-- Add/Edit Override Modal -->
<div class="modal fade" id="addOverrideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add / Edit Schedule Override</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="schedule-overrides.php" id="overrideForm">
                    <?php echo csrf_token_input(); ?>
                    <input type="hidden" name="override_id" id="modal_override_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_branch" class="form-label">Branch (optional)</label>
                                <select name="branch" id="modal_branch" class="form-control">
                                    <option value="">All Branches</option>
                                    <?php if (!empty($branches)): foreach ($branches as $b): ?>
                                        <option value="<?php echo (int)$b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </div>
                                        <div class="mb-3">
                                            <label for="modal_emp_search" class="form-label">Search employees</label>
                                            <input type="search" id="modal_emp_search" class="form-control" placeholder="Type to filter employees">
                                        </div>
                                        <div class="mb-3">
                                            <label for="modal_emp_id">Employee(s)</label>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" value="1" id="select_visible">
                                                <label class="form-check-label" for="select_visible">Select visible</label>
                                            </div>
                                            <select name="emp_id[]" id="modal_emp_id" class="form-control" multiple size="8" required>
                                                <?php foreach ($employees as $employee): ?>
                                                    <option value="<?php echo htmlspecialchars($employee['emp_id']); ?>" data-branch="<?php echo htmlspecialchars($employee['branch']); ?>"><?php echo htmlspecialchars(trim(($employee['first_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback" id="emp_invalid_feedback">Please select at least one employee.</div>
                                            <small class="form-text text-muted">Hold Ctrl (Windows) / Cmd (Mac) to select multiple employees.</small>
                                        </div>
                            <div class="mb-3">
                                <label for="modal_reason" class="form-label">Reason</label>
                                <input type="text" name="reason" id="modal_reason" class="form-control" placeholder="e.g., Special project, client meeting">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_start_date" class="form-label">Start Date</label>
                                <input type="date" name="start_date" id="modal_start_date" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="modal_end_date" class="form-label">End Date</label>
                                <input type="date" name="end_date" id="modal_end_date" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="modal_work_start_time" class="form-label">Work Start Time</label>
                                <input type="time" name="work_start_time" id="modal_work_start_time" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="modal_work_end_time" class="form-label">Work End Time</label>
                                <input type="time" name="work_end_time" id="modal_work_end_time" class="form-control" required>
                            </div>
                            <!-- Current schedule preview moved to the right column as the last element -->
                            <div id="modal_current_schedule" class="mt-3 border p-2 rounded">
                                <!-- Populated by renderCurrentSchedule() -->
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="overrideForm" name="save_override" class="btn btn-primary">Save Override</button>
            </div>
        </div>
    </div>
</div>

<script>
// Open Add Override Modal when clicking Add Override
const openAddBtn = document.getElementById('openAddOverride');
if (openAddBtn) {
    openAddBtn.addEventListener('click', function(){
        // Prepare modal form before Bootstrap shows it
        resetForm();
    });
}

// Also wire the mobile FAB (if present)
const openAddFab = document.getElementById('openAddOverrideFab');
if (openAddFab) {
    openAddFab.addEventListener('click', function(){
        resetForm();
    });
}

// Attach branch change listener now that modal exists in DOM
const modalBranchEl2 = document.getElementById('modal_branch');
if (modalBranchEl2) modalBranchEl2.addEventListener('change', filterEmployees);

// (preview button removed) - current schedule now shows inside modal as the last element

// Update current schedule display when selection changes
function renderCurrentSchedule() {
    const container = document.getElementById('modal_current_schedule');
    if (!container) return;
    let selected = [];
    if (window.empTomSelect) {
        const val = window.empTomSelect.getValue();
        if (Array.isArray(val)) selected = val; else if (typeof val === 'string' && val.length) selected = val.split(',');
    } else {
        const modalEmp = document.getElementById('modal_emp_id');
        if (modalEmp) selected = Array.from(modalEmp.selectedOptions).map(o => o.value);
    }
    if (!selected || selected.length === 0) {
        container.innerHTML = '<div class="text-muted">No employee selected</div>';
        return;
    }
    let html = '<div class="fw-bold mb-1">Current schedule</div><ul class="list-unstyled small mb-0">';
    selected.forEach(empId => {
        const sched = employeeSchedules[empId];
        if (!sched) {
            html += '<li><span class="text-muted">No data for ' + escapeHtml(empId) + '</span></li>';
            return;
        }
        const name = sched.name || empId;
        const timeStr = (sched.start_fmt || sched.start ? (sched.start_fmt || sched.start) : '') + (sched.end_fmt || sched.end ? (' - ' + (sched.end_fmt || sched.end)) : '');
        html += '<li><div><strong>' + escapeHtml(name) + '</strong></div>';
        if (timeStr.trim() !== '') html += '<div class="text-muted small">' + escapeHtml(timeStr) + '</div>';
        if (sched.source) html += '<div class="text-muted small">Source: ' + escapeHtml(sched.source) + (sched.id ? (' (id: ' + escapeHtml(String(sched.id)) + ')') : '') + '</div>';
        if (sched.reason) html += '<div class="text-muted small">Reason: ' + escapeHtml(sched.reason) + '</div>';
        html += '</li>';
    });
    html += '</ul>';
    container.innerHTML = html;
}

// Wire render on changes
document.addEventListener('DOMContentLoaded', function(){
    // render initially
    renderCurrentSchedule();
    // wire select change if plain select used
    const plainSel = document.getElementById('modal_emp_id');
    if (plainSel) plainSel.addEventListener('change', renderCurrentSchedule);
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

<!-- Mobile Floating Add Button (visible on small screens) -->
<style>
    .fab-add-override {
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 1060;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 0;
    }
</style>
<style>
    /* Compact search: show button only initially, expand input on click */
    .search-wrapper { position: relative; }
    .search-input { 
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        width: 0; 
        opacity: 0; 
        padding: .375rem .5rem; 
        transition: width .22s ease, opacity .16s ease, left .18s ease; 
        border-radius: .375rem; 
        border: 1px solid transparent;
        background-clip: padding-box;
        z-index: 5; /* sit above button when expanded */
        margin-left: .35rem;
    }
    .search-wrapper.expanded .search-input {
        width: 200px; /* reduced width */
        opacity: 1; 
        border-color: #ced4da;
        left: calc(100% + .35rem);
    }
    /* ensure button sits below the input when expanded */
    .search-wrapper .btn { position: relative; z-index: 1; }
    /* Keep search button visually aligned with Add button */
    .search-wrapper .btn { height: calc(2.25rem + 2px); transition: opacity .18s ease, transform .18s ease; }
    /* When expanded, slightly fade the button (input will overlay it) */
    .search-wrapper.expanded .btn { opacity: 0.6; transform: translateX(2px); pointer-events: none; }
    /* Add left/right margin to header buttons (search + add) */
    #openAddOverride, .search-wrapper .btn { margin-left: .5rem; margin-right: .5rem; }
</style>
<button id="openAddOverrideFab" class="btn btn-primary d-md-none fab-add-override" aria-label="Add Override" data-bs-toggle="modal" data-bs-target="#addOverrideModal"><i class="fas fa-plus" aria-hidden="true"></i></button>

<script>
    // Ensure FAB also opens the modal when clicked (in case JS above ran before FAB existed)
    (function(){
        var fab = document.getElementById('openAddOverrideFab');
        if (!fab) return;
        fab.addEventListener('click', function(){ resetForm(); showModalById('addOverrideModal'); });
    })();
</script>

<script>
// Search expand/collapse behavior: show button only initially, expand input when clicked
(function(){
    const wrapper = document.querySelector('.search-wrapper');
    if (!wrapper) return;
    const btn = document.getElementById('overrides_search_btn');
    const input = document.getElementById('overrides_search');

    // Start collapsed
    wrapper.classList.remove('expanded');

    function expandAndFocus(){
        // Just add the class; CSS will animate the button and input
        wrapper.classList.add('expanded');
        try { input.focus(); input.select(); } catch(e){}
    }
    function collapseIfEmpty(){
        if (input && (input.value === null || input.value.trim() === '')) {
            // Remove class so CSS animates back to compact state
            wrapper.classList.remove('expanded');
        }
    }

    btn.addEventListener('click', function(e){
        if (!wrapper.classList.contains('expanded')) { expandAndFocus(); return; }
        // If already expanded, trigger filter (same as typing)
        const ev = new Event('input', { bubbles: true });
        input.dispatchEvent(ev);
    });

    // Collapse on outside click when empty
    document.addEventListener('click', function(e){
        if (!wrapper.contains(e.target)) collapseIfEmpty();
    });

    // Collapse on Escape key
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') collapseIfEmpty(); });

    // When user types, keep expanded and perform filtering (already handled by existing input listener)
    if (input) {
        input.addEventListener('input', function(){
            if (!wrapper.classList.contains('expanded')) wrapper.classList.add('expanded');
            // filtering logic already wired elsewhere via overrides_search input listener
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

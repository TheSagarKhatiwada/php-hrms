<?php
// Reusable Apply for Leave Modal Component
// Assumes header.php already included session_config.php and db_connection.php
require_once __DIR__ . '/../modules/leave/accrual.php'; // for balance helpers

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    // Don't render modal if not logged in
    return;
}

// Resolve current employee ID (emp_id maps to session user_id)
$currentEmployeeId = null;
try {
    $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE emp_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if ($row) {
        $currentEmployeeId = $row['emp_id'];
    }
} catch (Throwable $e) {
    $currentEmployeeId = null;
}

if (!$currentEmployeeId) {
    // Don't render modal if employee not found
    return;
}

// Fetch leave types
$leave_types = [];
try {
    $leave_types_sql = "SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name";
    $leave_types = $pdo->query($leave_types_sql)->fetchAll();
} catch (Throwable $e) { /* ignore */ }

// Fetch balances using accrual helpers
$balance_result = [];
try {
    $balance_result = getEmployeeLeaveBalance($currentEmployeeId);
} catch (Throwable $e) { /* ignore */ }

// Index balances by leave_type_id for quick lookup
$balancesByType = [];
foreach ($balance_result as $bal) {
    if (isset($bal['leave_type_id'])) {
        $balancesByType[(string)$bal['leave_type_id']] = $bal;
    }
}
?>

<div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Apply for Leave</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="applyLeaveForm">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="leave_type_id_modal" class="form-label">Leave Type <span class="text-danger">*</span></label>
              <select class="form-select" id="leave_type_id_modal" name="leave_type_id" required>
                <option value="">Select Leave Type</option>
                <?php foreach ($leave_types as $type):
                  $typeId = (string)$type['id'];
                  $typeBal = $balancesByType[$typeId] ?? null;
                  $remaining = $typeBal ? ($typeBal['remaining_days'] ?? 0) : 0;
                  $accruedYtd = $typeBal ? ($typeBal['total_accrued_ytd'] ?? 0) : 0;
                ?>
                  <option value="<?php echo (int)$type['id']; ?>" data-days="<?php echo htmlspecialchars((string)$accruedYtd); ?>" data-available="<?php echo htmlspecialchars((string)$remaining); ?>">
                    <?php echo htmlspecialchars($type['name']); ?> (<?php echo $remaining; ?> available)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" id="is_half_day_modal" name="is_half_day" value="1">
                <label class="form-check-label" for="is_half_day_modal">Half Day Leave</label>
              </div>
              <div class="mt-2" id="half_day_period_group_modal" style="display:none;">
                <label class="form-label">Half Day Period</label>
                <select class="form-select" name="half_day_period" id="half_day_period_modal">
                  <option value="morning">Morning (First Half)</option>
                  <option value="afternoon">Afternoon (Second Half)</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <label for="start_date_modal" class="form-label">Start Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="start_date_modal" name="start_date" required>
            </div>
            <div class="col-md-6">
              <label for="end_date_modal" class="form-label">End Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="end_date_modal" name="end_date" required>
            </div>
            <div class="col-12">
              <label for="reason_modal" class="form-label">Reason for Leave <span class="text-danger">*</span></label>
              <textarea class="form-control" id="reason_modal" name="reason" rows="3" placeholder="Please provide a detailed reason..." required></textarea>
            </div>
            <div class="col-12">
              <div class="alert alert-info py-2" id="days_info_modal" style="display:none;">
                <i class="fas fa-info-circle me-1"></i>
                <span id="days_text_modal">Total days: 0</span>
              </div>
            </div>
          </div>
          <input type="hidden" name="submit_request" value="1">
          <input type="hidden" name="ajax" value="1">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Close</button>
        <button type="button" class="btn btn-primary" id="submitLeaveBtn"><i class="fas fa-paper-plane me-1"></i>Submit Request</button>
      </div>
    </div>
  </div>
  
</div>

<script>
(function(){
  // Resolve absolute base URL from PHP configuration (includes/configuration.php)
  const BASE_URL = (function(){
    try {
      return <?php echo json_encode(rtrim(isset($home) ? $home : '', '/')); ?> || '';
    } catch(e){ return ''; }
  })();
  const startInput = document.getElementById('start_date_modal');
  const endInput = document.getElementById('end_date_modal');
  const halfChk = document.getElementById('is_half_day_modal');
  const halfGroup = document.getElementById('half_day_period_group_modal');
  const daysInfo = document.getElementById('days_info_modal');
  const daysText = document.getElementById('days_text_modal');

  function todayStr(){
    const d = new Date();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const day = String(d.getDate()).padStart(2,'0');
    return d.getFullYear() + '-' + m + '-' + day;
  }

  function setMins(){
    const t = todayStr();
    startInput.min = t;
    endInput.min = t;
  }

  function calcDays(){
    if (!startInput.value || !endInput.value) { daysInfo.style.display = 'none'; return; }
    if (halfChk.checked){
      endInput.value = startInput.value;
      daysInfo.style.display = 'block';
      daysText.textContent = 'Total days: 0.5';
      return;
    }
    const s = new Date(startInput.value);
    const e = new Date(endInput.value);
    if (e < s){ endInput.value = startInput.value; }
    const diff = Math.ceil((new Date(endInput.value) - new Date(startInput.value)) / (1000*3600*24)) + 1;
    daysInfo.style.display = 'block';
    daysText.textContent = 'Total days: ' + diff;
  }

  function validateForm(){
    const s = new Date(startInput.value);
    const e = new Date(endInput.value);
    const t = new Date(); t.setHours(0,0,0,0);
    if (s < t){ showAlert('Cannot apply for leave in the past.','error'); return false; }
    if (s > e){ showAlert('End date cannot be earlier than start date.','error'); return false; }
    const reason = document.getElementById('reason_modal').value.trim();
    if (reason.length < 10){ showAlert('Please provide a detailed reason (at least 10 characters).','error'); return false; }
    if (!document.getElementById('leave_type_id_modal').value){ showAlert('Please select a leave type.','error'); return false; }
    return true;
  }

  function submitForm(){
    if (!validateForm()) return;
    const form = document.getElementById('applyLeaveForm');
    const fd = new FormData(form);
    // CSRF token if present
    const csrf = document.querySelector('meta[name="csrf-token"]');
    const headers = {};
    if (csrf) { headers['X-CSRF-Token'] = csrf.getAttribute('content'); }

    const btn = document.getElementById('submitLeaveBtn');
    const original = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';

    const url = getLeaveEndpoint();
    fetch(url, { method: 'POST', headers, body: fd })
      .then(async r => {
        if (r.ok) return r.json();
        const t = await r.text();
        // If server returned HTML (e.g., IIS 404 page), show concise message
        if (t && /^\s*<!DOCTYPE|<html/i.test(t)) {
          throw new Error('Request failed (' + r.status + '). Endpoint not found: ' + url);
        }
        throw new Error(t || ('HTTP ' + r.status));
      })
      .then(data => {
        if (data && data.success){
          const msg = data.message || 'Leave request submitted successfully!';
          const modalEl = document.getElementById('applyLeaveModal');
          const instance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
          instance.hide();
          resetForm();
          // Show a brief success message, then refresh the page to reflect updates
          if (window.Swal){
            Swal.fire({ title: 'Success', text: msg, icon: 'success', timer: 1500, showConfirmButton: false })
              .then(() => window.location.reload());
          } else {
            alert(msg);
            window.location.reload();
          }
        } else {
          showAlert((data && data.error) || 'Failed to submit leave request.','error');
        }
      })
      .catch(err => showAlert(err.message || String(err),'error'))
      .finally(() => { btn.disabled = false; btn.innerHTML = original; });
  }

  function showAlert(msg, type){
    if (window.Swal){
      Swal.fire({ title: type==='success' ? 'Success' : 'Error', text: msg, icon: type==='success' ? 'success' : 'error', timer: 2500, showConfirmButton: false });
    } else { alert(msg); }
  }

  function resetForm(){
    const form = document.getElementById('applyLeaveForm');
    form.reset();
    daysInfo.style.display = 'none';
    document.getElementById('end_date_modal').disabled = false;
  }

  function onHalfToggle(){
    if (halfChk.checked){
      halfGroup.style.display = 'block';
      endInput.value = startInput.value;
      endInput.disabled = true;
    } else {
      halfGroup.style.display = 'none';
      endInput.disabled = false;
    }
    calcDays();
  }

  function getLeaveEndpoint(){
    // Build an absolute URL. If BASE_URL is absolute (http/https), use it; else use current origin.
    const isAbs = /^https?:\/\//i.test(BASE_URL);
    const root = isAbs ? BASE_URL.replace(/\/$/, '') : window.location.origin;
    return root + '/api/leave/submit.php';
  }

  document.addEventListener('DOMContentLoaded', function(){
    setMins();
    startInput.addEventListener('change', function(){ if (halfChk.checked){ endInput.value = startInput.value; } calcDays(); });
    endInput.addEventListener('change', calcDays);
    halfChk.addEventListener('change', onHalfToggle);
    document.getElementById('submitLeaveBtn').addEventListener('click', submitForm);
    const modalEl = document.getElementById('applyLeaveModal');
    if (modalEl){
      modalEl.addEventListener('show.bs.modal', function(){ resetForm(); setMins(); });
    }
  });
})();
</script>

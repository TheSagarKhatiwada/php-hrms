<?php
$page = 'attendance-reports';
$home = '../../';
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';
require_once '../../includes/db_connection.php';

// Roles & permissions matrix for attendance reports
$isAdmin = (function_exists('is_admin') && is_admin()) ? true : false;
$canViewDailyReports = $isAdmin || has_permission('view_attendance_reports_daily');
$canViewPeriodicReports = $isAdmin || has_permission('view_attendance_reports_periodic');
$canViewTimesheetReports = $isAdmin || has_permission('view_attendance_reports_timesheet');
$canGenerateReports = $isAdmin || has_permission('generate_attendance_reports');
$canViewAllBranchAttendance = $isAdmin || has_permission('view_all_branch_attendance');
$canManageReportArtifacts = $isAdmin || has_permission('manage_attendance_report_artifacts');
$canViewAllGeneratedReports = $isAdmin || $canManageReportArtifacts || has_permission('view_all_user_generated_reports');
$canViewSensitiveAttendance = $isAdmin || has_permission('view_sensitive_attendance_metrics');

if (!$canViewDailyReports && !$canViewPeriodicReports && !$canViewTimesheetReports) {
  $_SESSION['error'] = 'You do not have permission to view attendance reports.';
  header('Location: ../../dashboard.php');
  exit;
}

$reportTypeOptions = [];
if ($canViewDailyReports) {
  $reportTypeOptions['daily'] = 'Daily Attendance';
}
if ($canViewPeriodicReports) {
  $reportTypeOptions['periodic'] = 'Periodic Attendance';
}
if ($canViewTimesheetReports) {
  $reportTypeOptions['timesheet'] = 'Periodic Time Sheet';
}
$defaultReportType = array_key_first($reportTypeOptions);
$hasMultipleReportTypes = count($reportTypeOptions) > 1;

$viewerBranch = ['id' => null, 'name' => null];
if (!$canViewAllBranchAttendance && isset($_SESSION['user_id'])) {
  try {
    $branchStmt = $pdo->prepare('SELECT e.branch_id, e.branch, b.name FROM employees e LEFT JOIN branches b ON e.branch = b.id WHERE e.emp_id = :emp LIMIT 1');
    $branchStmt->execute([':emp' => $_SESSION['user_id']]);
    if ($row = $branchStmt->fetch(PDO::FETCH_ASSOC)) {
      $branchId = $row['branch_id'] ?: $row['branch'];
      $viewerBranch['id'] = $branchId ? (int)$branchId : null;
      $viewerBranch['name'] = $row['name'] ?: 'Assigned Branch';
    }
  } catch (PDOException $e) {
    // Fail silently; backend enforcement still applies
  }
}

$branchOptions = [];
if ($canViewAllBranchAttendance) {
  try {
    $stmt = $pdo->query('SELECT id, name FROM branches ORDER BY name');
    while ($b = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $branchOptions[] = ['id' => (int)$b['id'], 'name' => $b['name']];
    }
  } catch (PDOException $e) {
    $branchOptions = [];
  }
} elseif ($viewerBranch['id']) {
  $branchOptions[] = ['id' => $viewerBranch['id'], 'name' => $viewerBranch['name'] ?: 'My Branch'];
}
if (!$canViewAllBranchAttendance && empty($branchOptions)) {
  $branchOptions[] = ['id' => '', 'name' => 'My Branch'];
}

$branchSelectDisabled = !$canViewAllBranchAttendance;
$selectedBranchId = $branchOptions[0]['id'] ?? '';

$showGeneratedByColumn = $isAdmin || $canManageReportArtifacts;
$showBranchColumn = $canViewAllBranchAttendance;

$reportPermissionsPayload = [
  'defaultType' => $defaultReportType,
  'allowedTypes' => array_keys($reportTypeOptions),
  'canGenerate' => $canGenerateReports,
  'canViewAllBranches' => $canViewAllBranchAttendance,
  'canViewAllGenerated' => $canViewAllGeneratedReports,
  'selectedBranchId' => $selectedBranchId,
  'manageArtifacts' => $canManageReportArtifacts,
  'showGeneratedColumn' => $showGeneratedByColumn,
  'viewerBranch' => $viewerBranch,
  'canViewSensitive' => $canViewSensitiveAttendance,
];

require_once '../../includes/header.php';

if (!isset($_SESSION['generated_reports'])) { $_SESSION['generated_reports'] = []; }

function h($s){return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');}
?>
<style>
  :root {
    --card-bg:#ffffff;
    --card-border:#e3e6f0;
    --card-text:#222;
    --muted-text:#6c757d;
    --accent:#0d6efd;
    --accent-bg:#e7f1ff;
    --input-bg:#fff;
    --input-border:#ced4da;
    --dropdown-bg:#fff;
    --dropdown-border:#d0d7de;
    --dropdown-hover:#f1f3f5;
    --table-header:#f5f6f8;
    --table-header-text:#222;
  }
  .dark-mode:root, .dark-mode {
    --card-bg:#1f242b;
    --card-border:#2d3239;
    --card-text:#f5f7fa;
    --muted-text:#b5bcc5;
    --accent:#4da3ff;
    --accent-bg:#173248;
    --input-bg:#252c34;
    --input-border:#3a424b;
    --dropdown-bg:#252c34;
    --dropdown-border:#39414a;
    --dropdown-hover:#303941;
    --table-header:#2a323b;
    --table-header-text:#f5f7fa;
  }
  .form-section { background:var(--card-bg); border:1px solid var(--card-border); border-radius:6px; padding:15px; margin-bottom:20px; color:var(--card-text); }
  .form-section label { color:var(--card-text); }
  .generated-table th { background:var(--table-header); color:var(--table-header-text); }
  /* Reset to default Bootstrap font size by removing forced 12px */
  .generated-table th, .generated-table td { font-size:inherit; vertical-align:middle; }
  .input-field { background:var(--input-bg); border:1px solid var(--input-border)!important; }
  .input-field input, .input-field select { background:transparent!important; color:var(--card-text); }
  .input-field input::placeholder { color:var(--muted-text); }
  .employee-select-wrapper { padding:2px!important; border-radius:5px; display:flex; align-items:center; gap:.5rem; position:relative; }
  .employee-select-wrapper i { color:var(--muted-text); font-size:1.2rem!important; }
  .employee-select-wrapper .employee-multiselect { position:relative; width:100%; }
  .employee-multiselect { position:relative; }
  .employee-multiselect .ems-display {
    width:100%;
    background:transparent;
    color:var(--card-text);
    border:0;
    padding:4px 34px 4px 4px;
    min-height:34px;
    font-size:.875rem;
    display:flex;
    justify-content:space-between;
    align-items:center;
    text-align:left;
    cursor:pointer;
  }
  .employee-multiselect .ems-right { display:flex; align-items:center; gap:6px; color:var(--muted-text); }
  .employee-multiselect .count-badge { background:var(--accent-bg); color:var(--accent); font-size:.65rem; padding:2px 6px; border-radius:10px; }
  .form-section .form-select, .form-section .form-control { min-height:38px; }
  .form-section .input-field { min-height:38px; }
  .employee-multiselect .ems-menu { position:absolute; top:100%; left:0; width:100%; max-height:260px; overflow:auto; background:var(--dropdown-bg); border:1px solid var(--dropdown-border); border-radius:6px; margin-top:4px; z-index:30; box-shadow:0 4px 14px rgba(0,0,0,.12); display:none; }
  .employee-multiselect.open .ems-menu { display:block; }
  .employee-multiselect .ems-search { padding:6px 8px; border-bottom:1px solid var(--dropdown-border); display:flex; align-items:center; gap:6px; }
  .employee-multiselect .ems-search input { flex:1; border:1px solid var(--input-border); background:var(--input-bg); color:var(--card-text); border-radius:4px; font-size:.75rem; padding:4px 8px; }
  .employee-multiselect .ems-search .btn { font-size:.6rem; white-space:nowrap; padding:4px 6px; }
  .employee-multiselect .ems-options { list-style:none; margin:0; padding:0; }
  .employee-multiselect .ems-options li { padding:6px 10px; display:flex; align-items:center; gap:6px; font-size:.72rem; cursor:pointer; border-bottom:1px solid var(--dropdown-border); }
  .employee-multiselect .ems-options li:last-child { border-bottom:none; }
  .employee-multiselect .ems-options li:hover { background:var(--dropdown-hover); }
  .employee-multiselect .ems-options li label { flex:1; cursor:pointer; }
  .employee-multiselect .ems-options li input[type=checkbox] { margin:0; width:14px; height:14px; cursor:pointer; }
  .employee-multiselect .ems-footer { padding:6px 8px; border-top:1px solid var(--dropdown-border); display:flex; justify-content:space-between; gap:4px; background:var(--card-bg); position:sticky; bottom:0; }
  .employee-multiselect .ems-footer button { font-size:.65rem; padding:4px 8px; }
  .badge-rt { background:var(--accent-bg); color:var(--accent); font-weight:500; }
  .theme-soft-text { color:var(--muted-text); }
  .generated-table tbody tr { background:var(--card-bg); color:var(--card-text); }
  /* Slightly more line spacing for Generated column two-line layout */
  .gr-generated-stack { line-height:1.15; }
  .gr-generated-stack small { margin-top:2px; }
  .gr-more-text { color:var(--accent); cursor:help; text-decoration:underline dotted; font-weight:600; white-space:nowrap; }
  /* Date pill for better contrast especially on danger rows */
  .date-pill { display:inline-block; padding:1px 6px; border-radius:12px; font-size:.68rem; background:rgba(0,0,0,.08); color:#222; letter-spacing:.3px; }
  .dark-mode .date-pill { background:rgba(255,255,255,.15); color:#f5f7fa; }
  .table-danger .date-pill { background:rgba(0,0,0,.15); color:#1e1e1e; font-weight:500; }
  .dark-mode .table-danger .date-pill { background:rgba(255,255,255,.25); color:#fff; }
  /* Force deleted (table-danger) row date text to black as requested */
  .table-danger .gr-generated-stack small { color:#000 !important; }
  .dark-mode .table-danger .gr-generated-stack small { color:#000 !important; }
  /* New stacked date/time block for better readability */
  .gr-dt { display:inline-flex; flex-direction:column; background:rgba(0,0,0,.07); padding:3px 7px 4px; border-radius:6px; font-size:.66rem; line-height:1.05; letter-spacing:.25px; min-width:78px; }
  .gr-dt .gr-date { font-weight:600; }
  .gr-dt .gr-time { font-family:monospace; opacity:.8; margin-top:1px; }
  .table-danger .gr-dt { background:rgba(0,0,0,.15); color:#111; }
  .dark-mode .gr-dt { background:rgba(255,255,255,.12); color:#f5f7fa; }
  .dark-mode .table-danger .gr-dt { background:rgba(255,255,255,.25); color:#fff; }
  @media (min-width:1400px){ .gr-dt { font-size:.66rem; } }
  .dark-mode .table-bordered>:not(caption)>*{ border-color:var(--card-border); }
  /* Themed generic field wrapper for dynamic light/dark */
  .themed-field { border:1px solid var(--input-border)!important; background:var(--input-bg)!important; }
  .themed-field select { background:var(--input-bg)!important; color:var(--card-text)!important; }
  /* Daterangepicker theming */
  .daterangepicker { background:var(--dropdown-bg); color:var(--card-text); border:1px solid var(--dropdown-border); box-shadow:0 4px 14px rgba(0,0,0,.15); }
  .daterangepicker .calendar-table { background:var(--dropdown-bg); border-color:var(--dropdown-border); }
  .daterangepicker .calendar-table th, .daterangepicker .calendar-table td { color:var(--card-text); }
  .daterangepicker td.available:hover, .daterangepicker th.available:hover { background:var(--dropdown-hover); color:var(--card-text); }
  .daterangepicker td.in-range { background:var(--accent-bg); color:var(--card-text); }
  .daterangepicker td.active, .daterangepicker td.active:hover { background:var(--accent)!important; color:#fff!important; }
  .daterangepicker .drp-buttons { background:var(--card-bg); border-top:1px solid var(--dropdown-border); }
  .daterangepicker .drp-selected { color:var(--muted-text); }
  .daterangepicker .ranges li { background:var(--dropdown-bg); color:var(--card-text); border:1px solid var(--dropdown-border); }
  .daterangepicker .ranges li:hover { background:var(--dropdown-hover); }
  .daterangepicker .ranges li.active { background:var(--accent); color:#fff; border-color:var(--accent); }
  .dark-mode .daterangepicker .calendar-table th, .dark-mode .daterangepicker .calendar-table td { color:var(--card-text); }
  /* Additional dark-mode calendar cell theming */
  .dark-mode .daterangepicker .calendar-table { background:var(--dropdown-bg); }
  .dark-mode .daterangepicker td, .dark-mode .daterangepicker th { background:var(--dropdown-bg); border-color:var(--dropdown-border)!important; }
  .dark-mode .daterangepicker td.available:not(.in-range):not(.active) { background:#252c34; color:var(--card-text); }
  .dark-mode .daterangepicker td.available:hover { background:var(--dropdown-hover)!important; color:var(--card-text)!important; }
  .dark-mode .daterangepicker td.off, .dark-mode .daterangepicker td.off.in-range { background:#1f242b; color:var(--muted-text); opacity:.55; }
  .dark-mode .daterangepicker td.in-range { background:rgba(77,163,255,.20)!important; color:var(--card-text); }
  .dark-mode .daterangepicker td.start-date, .dark-mode .daterangepicker td.end-date { background:var(--accent)!important; color:#fff!important; position:relative; }
  .dark-mode .daterangepicker td.start-date:before, .dark-mode .daterangepicker td.end-date:before { content:''; position:absolute; inset:0; box-shadow:0 0 0 1px #fff inset; border-radius:4px; }
  .dark-mode .daterangepicker td.active, .dark-mode .daterangepicker td.active:hover { background:var(--accent)!important; color:#fff!important; }
  .dark-mode .daterangepicker .drp-buttons { background:var(--card-bg); border-top:1px solid var(--dropdown-border); }
  .dark-mode .daterangepicker .drp-buttons .btn { border-color:var(--input-border); }
  .dark-mode .daterangepicker .drp-buttons .btn-primary { background:var(--accent); border-color:var(--accent); }
</style>
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Attendance Reports</h1></div></div>
  </div>
</div>
<section class="content">
  <div class="container-fluid">
    <?php if ($canGenerateReports): ?>
    <div class="form-section">
      <?php if (!$hasMultipleReportTypes): ?>
        <input type="hidden" id="reportType" value="<?php echo h($defaultReportType); ?>">
      <?php endif; ?>
      <?php if ($branchSelectDisabled): ?>
        <input type="hidden" id="branchSelect" value="<?php echo h((string)$selectedBranchId); ?>">
      <?php endif; ?>
      <div class="row g-3">
        <?php if ($hasMultipleReportTypes): ?>
        <div class="col-md-2">
          <label class="form-label fw-semibold">Report Type</label>
          <div class="input-field themed-field" style="width:100%; border-radius:5px; padding:2px; display:flex; align-items:center;">
            <i id="reportTypeIcon" class="fas fa-clipboard-list mr-2" style="font-size:1.4rem;"></i>
            <select id="reportType" class="form-select border-0 bg-transparent" style="box-shadow:none;">
              <?php foreach ($reportTypeOptions as $value => $labelText): ?>
                <option value="<?php echo h($value); ?>" <?php echo $value === $defaultReportType ? 'selected' : ''; ?>><?php echo h($labelText); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php endif; ?>
        <!-- Daily single date (replicates daily-report.php native date input markup) -->
    <div class="col-md-2" id="singleDateWrap">
          <label for="reportdate" class="form-label fw-semibold">Report Date <span class="text-danger">*</span></label>
          <div class="input-field themed-field" style="width:100%; border-radius:5px; padding:2px; display:flex; align-items:center;">
            <i class="fas fa-calendar-alt mr-2" style="font-size: 1.5rem;"></i>
      <input type="text" class="form-control border-0" id="reportdate" name="reportdate" value="<?= date('d/m/Y'); ?>" autocomplete="off" required>
          </div>
        </div>
        <!-- Periodic date range (replicates periodic-time-report.php daterangepicker) -->
        <div class="col-md-3 d-none" id="dateRangeWrap">
          <label for="reportDateRange" class="form-label fw-semibold">Date Range <span class="text-danger">*</span></label>
          <div class="input-field themed-field" style="width:100%; border-radius:5px; padding:2px; display:flex; align-items:center;">
            <i class="fas fa-calendar-alt mr-2" style="font-size: 1.5rem;"></i>
            <input type="text" class="form-control border-0" id="reportDateRange" name="reportDateRange" required>
          </div>
        </div>
        <?php if (!$branchSelectDisabled): ?>
        <div class="col-md-2">
          <label class="form-label fw-semibold" for="branchSelect">Branch</label>
          <div class="input-field themed-field" style="width:100%; border-radius:5px; padding:2px; display:flex; align-items:center;">
            <i class="fas fa-building mr-2" style="font-size:1.5rem;"></i>
            <select id="branchSelect" class="form-control border-0">
              <?php if ($canViewAllBranchAttendance): ?>
                <option value="">All Branches</option>
                <?php foreach ($branchOptions as $branch): ?>
                  <option value="<?php echo h($branch['id']); ?>" <?php echo (string)$branch['id'] === (string)$selectedBranchId ? 'selected' : ''; ?>><?php echo h($branch['name']); ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <?php foreach ($branchOptions as $branch): ?>
                  <option value="<?php echo h($branch['id']); ?>" selected><?php echo h($branch['name']); ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
        </div>
        <?php endif; ?>
  <div class="col-md-3">
          <label class="form-label fw-semibold" for="employeeSelectBox">Employees</label>
          <div class="input-field themed-field employee-select-wrapper" style="width:100%; border-radius:5px; padding:2px; display:flex; align-items:center;">
            <i class="fas fa-users mr-2"></i>
            <div class="employee-multiselect flex-grow-1" id="employeeSelectBox" aria-haspopup="listbox" aria-expanded="false">
              <button type="button" class="ems-display" id="emsToggle">
                <span class="ems-label">All Employees</span>
                <span class="ems-right d-flex align-items-center"><span class="count-badge" id="emsCount">ALL</span><i class="fas fa-chevron-down ms-2 small"></i></span>
              </button>
              <div class="ems-menu" role="listbox" aria-multiselectable="true">
                <div class="ems-search">
                  <button type="button" class="btn btn-outline-secondary" id="emsSelectAll">All</button>
                  <input type="text" id="emsSearch" placeholder="Search employees..." aria-label="Search employees">
                  <button type="button" class="btn btn-outline-secondary" id="emsClear">Clear</button>
                </div>
                <ul class="ems-options" id="emsOptions"></ul>
                <div class="ems-footer">
                  <button type="button" class="btn btn-sm btn-primary ms-auto" id="emsApply">Apply</button>
                </div>
              </div>
              <input type="hidden" id="employeeValues" value="*">
            </div>
          </div>
        </div>
        <?php if ($canGenerateReports): ?>
        <div class="col-md-2 d-flex align-items-end">
          <button id="generateBtn" class="btn btn-primary w-100"><i class="fas fa-play me-1"></i>Generate</button>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header py-2"><h5 class="mb-0">Generated Reports</h5></div>
      <div class="card-body p-2">
  <div class="row g-2 mb-2">
          <div class="<?php echo $hasMultipleReportTypes ? 'col-md-2 col-6' : 'col-md-2 col-6 d-none'; ?>" id="grFilterTypeCol">
            <select id="grFilterType" class="form-select form-select-sm" aria-label="Filter by report type">
              <?php if ($hasMultipleReportTypes): ?>
              <option value="" selected>All Types</option>
              <?php endif; ?>
              <?php foreach ($reportTypeOptions as $value => $labelText): ?>
                <option value="<?php echo h($value); ?>" <?php echo (!$hasMultipleReportTypes && $value === $defaultReportType) ? 'selected' : ''; ?>><?php
                  if ($value === 'daily') {
                      echo 'Daily';
                  } elseif ($value === 'periodic') {
                      echo 'Periodic';
                  } else {
                      echo 'Time Sheet';
                  }
                ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 col-6">
            <input type="date" id="grDateFrom" class="form-control form-control-sm" placeholder="From">
          </div>
            <div class="col-md-2 col-6">
            <input type="date" id="grDateTo" class="form-control form-control-sm" placeholder="To">
          </div>
          <div class="col-md-3 col-6">
            <input type="text" id="grSearch" class="form-control form-control-sm" placeholder="Search...">
          </div>
          <div class="col-md-3 text-end">
            <div class="d-flex flex-column flex-md-row gap-2 align-items-end justify-content-end">
              <div class="form-check form-switch small mb-0" id="grShowDeletedWrap" style="display:<?php echo $canManageReportArtifacts ? 'block' : 'none'; ?>;">
                <input class="form-check-input" type="checkbox" role="switch" id="grShowDeleted">
                <label class="form-check-label" for="grShowDeleted" title="Show soft-deleted (last 30 days)">Deleted</label>
              </div>
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-secondary" id="grReset">Reset</button>
                <button class="btn btn-primary" id="grApplyFilters">Apply Filters</button>
              </div>
            </div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped table-bordered generated-table" id="generatedReportsTable">
            <thead class="table" id="grHead">
              <tr id="grHeadRow">
                <th style="width:40px;">#</th>
                <th>Report Type</th>
                <th>Date / Range</th>
                <?php if ($showBranchColumn): ?>
                <th>Branch</th>
                <?php endif; ?>
                <th>Employees</th>
                <?php if ($showGeneratedByColumn): ?>
                <th>Generated By</th>
                <?php endif; ?>
                <th class="d-none" data-col="deletedBy">Deleted By</th>
                <th style="width:120px;">Actions</th>
              </tr>
            </thead>
            <tbody id="generatedReportsBody">
              <?php $initialCols = $showGeneratedByColumn ? 8 : 7; ?>
              <tr><td colspan="<?= $initialCols; ?>" class="text-center text-muted">Loading...</td></tr>
            </tbody>
          </table>
          <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2" id="grPaginationWrap" style="display:none;">
            <div class="d-flex align-items-center small gap-2">
              <span id="grSummary"></span>
              <select id="grPerPage" class="form-select form-select-sm" style="width:auto;">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
              </select>
            </div>
            <nav>
              <ul class="pagination pagination-sm mb-0" id="grPagination"></ul>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once '../../includes/footer.php'; ?>
<!-- Periodic daterange assets -->
<link rel="stylesheet" href="<?= $home; ?>plugins/daterangepicker/daterangepicker.css">
<script src="<?= $home; ?>plugins/moment/moment.min.js"></script>
<script src="<?= $home; ?>plugins/daterangepicker/daterangepicker.js"></script>
<script>
const REPORT_PERMISSIONS = <?php echo json_encode($reportPermissionsPayload, JSON_UNESCAPED_SLASHES); ?>;
(function(){
  const CAN_GENERATE_REPORTS = !!(REPORT_PERMISSIONS && REPORT_PERMISSIONS.canGenerate);
  const CAN_MANAGE_ARTIFACTS = !!(REPORT_PERMISSIONS && REPORT_PERMISSIONS.manageArtifacts);
  const SHOW_GENERATED_COLUMN = !!(REPORT_PERMISSIONS && REPORT_PERMISSIONS.showGeneratedColumn);
  const CAN_VIEW_ALL_BRANCHES = !!(REPORT_PERMISSIONS && REPORT_PERMISSIONS.canViewAllBranches);
  const DEFAULT_REPORT_TYPE = REPORT_PERMISSIONS && REPORT_PERMISSIONS.defaultType ? REPORT_PERMISSIONS.defaultType : '';
  const ALLOWED_REPORT_TYPES = (REPORT_PERMISSIONS && Array.isArray(REPORT_PERMISSIONS.allowedTypes)) ? REPORT_PERMISSIONS.allowedTypes : [];
  const HAS_MULTIPLE_REPORT_TYPES = ALLOWED_REPORT_TYPES.length > 1;
  const grFilterTypeEl = document.getElementById('grFilterType');
  // Ensure SweetAlert2 availability (footer loads it async via CDN); create lightweight stub to queue calls
  if(!window.Swal){
    window.__swalQueue=[];
    window.Swal={ fire:function(opts){ window.__swalQueue.push(opts); if(!window.__swalFlushed){ setTimeout(function(){ if(window.realSwal && window.__swalQueue){ window.__swalQueue.forEach(o=>window.realSwal.fire(o)); window.__swalQueue=[]; } },800); } } };
    Object.defineProperty(window,'realSwal',{ set:function(v){ window.realSwal=v; if(window.__swalQueue && v){ window.__swalQueue.forEach(o=>v.fire(o)); window.__swalQueue=[]; window.__swalFlushed=true; } } });
  }
  // Toast / dialog helpers using SweetAlert2 if available
  function notifySuccess(msg){
    if(window.Swal){
      Swal.fire({toast:true, position:'bottom-end', icon:'success', title:msg, showConfirmButton:false, timer:2000, timerProgressBar:true});
    } else { console.log('SUCCESS:', msg); }
  }
  function notifyError(msg){
    if(window.Swal){
      Swal.fire({toast:true, position:'top-end', icon:'error', title:msg, showConfirmButton:false, timer:2500, timerProgressBar:true});
    } else { console.error('ERROR:', msg); }
  }
  function confirmDialog(title, text, icon, confirmText){
    if(window.Swal){
      return Swal.fire({title, text, icon: icon||'warning', showCancelButton:true, confirmButtonText:confirmText||'Yes', cancelButtonText:'Cancel', reverseButtons:true});
    } else {
      return Promise.resolve({ isConfirmed: window.confirm(title + (text?"\n"+text: '')) });
    }
  }
  const reportType = document.getElementById('reportType');
  if(reportType){
    const singleWrap = document.getElementById('singleDateWrap');
    const rangeWrap = document.getElementById('dateRangeWrap');
    const dateRangeInput = document.getElementById('reportDateRange');
    const branchSelect = document.getElementById('branchSelect');
    if(branchSelect && REPORT_PERMISSIONS && typeof REPORT_PERMISSIONS.selectedBranchId !== 'undefined' && branchSelect.value === ''){
      branchSelect.value = REPORT_PERMISSIONS.selectedBranchId || '';
    }
    const canChangeReportType = reportType.tagName === 'SELECT';
    const canChangeBranch = branchSelect && branchSelect.tagName === 'SELECT';

    const emsBox = document.getElementById('employeeSelectBox');
    const emsToggle = document.getElementById('emsToggle');
    const emsMenu = emsBox ? emsBox.querySelector('.ems-menu') : null;
    const emsOptions = document.getElementById('emsOptions');
    const emsLabel = emsBox ? emsBox.querySelector('.ems-label') : null;
    const emsCount = document.getElementById('emsCount');
    const emsSearch = document.getElementById('emsSearch');
    const hiddenValues = document.getElementById('employeeValues');
    const btnAll = document.getElementById('emsSelectAll');
    const btnClear = document.getElementById('emsClear');
    const btnApply = document.getElementById('emsApply');

    function toggleDateInputs(){
      if(!singleWrap || !rangeWrap) return;
      if(reportType.value==='daily') {
        singleWrap.classList.remove('d-none');
        rangeWrap.classList.add('d-none');
      } else {
        singleWrap.classList.add('d-none');
        rangeWrap.classList.remove('d-none');
      }
      updateReportTypeIcon();
    }
    function updateReportTypeIcon(){
      const iconEl = document.getElementById('reportTypeIcon');
      if(!iconEl) return;
      const value = reportType.value;
      iconEl.className = 'fas mr-2';
      if(value==='daily') iconEl.classList.add('fa-calendar-day');
      else if(value==='periodic') iconEl.classList.add('fa-calendar-alt');
      else if(value==='timesheet') iconEl.classList.add('fa-clock');
      else iconEl.classList.add('fa-clipboard-list');
    }
    if(canChangeReportType){
      reportType.addEventListener('change', function(){
        toggleDateInputs();
        loadEmployees();
      });
    }
    toggleDateInputs();
    updateReportTypeIcon();

    if(window.jQuery && window.jQuery.fn && window.jQuery.fn.daterangepicker && dateRangeInput){
      const MAX_RANGE_DAYS = 32;
      $(dateRangeInput).daterangepicker({
        locale:{ format:'DD/MM/YYYY' },
        opens:'auto',
        alwaysShowCalendars:false,
        startDate: moment().subtract(1,'months').startOf('month'),
        endDate: moment().subtract(1,'months').endOf('month'),
        maxDate: moment(),
        autoApply:false,
        isInvalidDate: function(date){
          const dr = $(dateRangeInput).data('daterangepicker');
          if(reportType.value !== 'timesheet' || !dr) return false;
          if(dr.startDate && !dr.endDate){
            const diff = Math.abs(date.startOf('day').diff(dr.startDate.startOf('day'),'days'))+1;
            if(diff>MAX_RANGE_DAYS) return true;
          }
          return false;
        },
        ranges:{
          'This Month':[moment().startOf('month'), moment().endOf('month')],
          'Last Month':[moment().subtract(1,'months').startOf('month'), moment().subtract(1,'months').endOf('month')],
          'Last 30 Days':[moment().subtract(29,'days'), moment()],
          'This Quarter':[moment().startOf('quarter'), moment().endOf('quarter')],
          'Last Quarter':[moment().subtract(1,'quarter').startOf('quarter'), moment().subtract(1,'quarter').endOf('quarter')]
        }
      });
      $(dateRangeInput).on('apply.daterangepicker', function(ev, picker){
        if(reportType.value !== 'timesheet') return;
        let s = picker.startDate.clone();
        let e = picker.endDate.clone();
        let diff = e.diff(s,'days')+1;
        if(diff>MAX_RANGE_DAYS){
          e = s.clone().add(MAX_RANGE_DAYS-1,'days');
          picker.setEndDate(e);
          $(this).val(s.format('DD/MM/YYYY')+' - '+e.format('DD/MM/YYYY'));
          if(window.Swal){ Swal.fire({icon:'info',title:'Range trimmed',text:'Time Sheet maximum is 32 days.'}); }
        }
      });
    }

    const singleDateEl = document.getElementById('reportdate');
    if(window.jQuery && window.jQuery.fn && window.jQuery.fn.daterangepicker && singleDateEl){
      $(singleDateEl).daterangepicker({
        singleDatePicker:true,
        showDropdowns:true,
        locale:{ format:'DD/MM/YYYY' },
        maxDate: moment(),
        autoApply:true
      });
    }

    function renderEmployees(list){
      if(!emsOptions) return;
      emsOptions.innerHTML='';
      const liAll=document.createElement('li');
      liAll.dataset.text='all employees';
      const allChk=document.createElement('input');
      allChk.type='checkbox';
      allChk.id='emsAllChk';
      allChk.checked=true;
      const allLabel=document.createElement('label');
      allLabel.setAttribute('for','emsAllChk');
      allLabel.className='mb-0 flex-grow-1';
      allLabel.textContent='All Employees';
      liAll.append(allChk, allLabel);
      emsOptions.appendChild(liAll);

      list.forEach(emp=>{
        const li=document.createElement('li');
        li.dataset.text=(emp.emp_id+' - '+emp.name).toLowerCase();
        const id='ems_'+emp.emp_id;
        const checkbox=document.createElement('input');
        checkbox.type='checkbox';
        checkbox.id=id;
        checkbox.dataset.val=emp.emp_id;
        checkbox.dataset.label=emp.emp_id+' - '+emp.name;
        checkbox.checked=true;
        const label=document.createElement('label');
        label.setAttribute('for', id);
        label.className='mb-0 flex-grow-1';
        label.textContent=emp.emp_id+' - '+emp.name;
        li.append(checkbox, label);
        emsOptions.appendChild(li);
      });
      updateSummary();
    }

    function loadEmployees(){
      if(!branchSelect || !hiddenValues) return;
      const branch = branchSelect.value;
      const payload = {
        branch,
        report_type: reportType.value || 'daily'
      };
      if(payload.report_type === 'daily'){
        const dateInput = document.getElementById('reportdate');
        if(dateInput){ payload.date = dateInput.value; }
      } else if(dateRangeInput){
        payload.range = dateRangeInput.value;
      }
      const csrf = getCsrfToken();
      if(csrf) payload.csrf_token = csrf;

      fetch('../../api/fetch-employees-by-branch.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        credentials:'include',
        body:new URLSearchParams(payload)
      })
        .then(async r=>{
          const text = await r.text();
          try { return JSON.parse(text); }
          catch(err){ console.error('Employee fetch parse error', text); throw err; }
        })
        .then(data=>{
          if(Array.isArray(data)){
            renderEmployees(data);
            hiddenValues.value='*';
          } else if(data && data.status === 'error'){
            notifyError(data.message || 'Failed to load employees');
            renderEmployees([]);
          }
        })
        .catch(()=>{ renderEmployees([]); });
    }
    if(canChangeBranch){
      branchSelect.addEventListener('change', loadEmployees);
    }
    loadEmployees();

    function updateSummary(){
      if(!emsOptions || !hiddenValues || !emsLabel || !emsCount) return;
      const allChk = document.getElementById('emsAllChk');
      const empChks = Array.from(emsOptions.querySelectorAll('li input[type=checkbox]')).filter(c=>c.id!=='emsAllChk');
      const checkedEmp = empChks.filter(c=>c.checked).map(c=>c.getAttribute('data-val'));
      if(allChk && allChk.checked){
        emsLabel.textContent='All Employees';
        emsCount.textContent='ALL';
        hiddenValues.value='*';
      } else {
        if(checkedEmp.length===0){
          emsLabel.textContent='None Selected';
          emsCount.textContent='0';
          hiddenValues.value='';
        } else {
          emsLabel.textContent=checkedEmp.length+' Selected';
          emsCount.textContent=checkedEmp.length;
          hiddenValues.value=checkedEmp.join(',');
        }
      }
    }

    if(emsOptions){
      emsOptions.addEventListener('change',(e)=>{
        const tgt=e.target;
        if(tgt.id==='emsAllChk'){
          const state=tgt.checked; emsOptions.querySelectorAll('li input[type=checkbox]').forEach(c=>{ if(c.id!=='emsAllChk') c.checked=state; });
        } else {
          if(!tgt.checked){ const allChk=document.getElementById('emsAllChk'); if(allChk) allChk.checked=false; }
        }
        updateSummary();
      });
    }
    if(btnAll){ btnAll.addEventListener('click', ()=>{ const allChk=document.getElementById('emsAllChk'); if(allChk){ allChk.checked=true; emsOptions.querySelectorAll('li input[type=checkbox]').forEach(c=>{ c.checked=true; }); updateSummary(); }}); }
    if(btnClear){ btnClear.addEventListener('click', ()=>{ const allChk=document.getElementById('emsAllChk'); if(allChk){ allChk.checked=false; } emsOptions.querySelectorAll('li input[type=checkbox]').forEach(c=>{ if(c.id!=='emsAllChk') c.checked=false; }); updateSummary(); }); }
    if(btnApply){ btnApply.addEventListener('click', ()=>{ closeMenu(); }); }

    function closeMenu(){ if(emsBox){ emsBox.classList.remove('open'); emsBox.setAttribute('aria-expanded','false'); } }
    function openMenu(){ if(emsBox){ emsBox.classList.add('open'); emsBox.setAttribute('aria-expanded','true'); if(emsSearch) emsSearch.focus(); } }
    if(emsToggle){ emsToggle.addEventListener('click', ()=>{ if(emsBox && emsBox.classList.contains('open')) closeMenu(); else openMenu(); }); }
    document.addEventListener('click', (e)=>{ if(emsBox && !emsBox.contains(e.target)) closeMenu(); });
    if(emsSearch){ emsSearch.addEventListener('input', ()=>{
      const term = emsSearch.value.toLowerCase();
      emsOptions.querySelectorAll('li').forEach(li=>{ const t=li.dataset.text||''; li.style.display = t.includes(term)?'flex':'none'; });
    }); }
    const generateBtn = document.getElementById('generateBtn');
    if(generateBtn){
      generateBtn.addEventListener('click', function(e){
        e.preventDefault();
        const branch = branchSelect ? branchSelect.value : '';
        const type = reportType.value || DEFAULT_REPORT_TYPE || 'daily';
        const values = hiddenValues ? hiddenValues.value : '*';
        const payload = { report_type: type, branch, employees: values || '*' };
        if(type==='daily') {
          const dateInput = document.getElementById('reportdate');
          if(dateInput){ payload.date = dateInput.value; }
        } else if(dateRangeInput){
          payload.range = dateRangeInput.value;
        }
        const csrf = getCsrfToken();
        if(csrf) payload.csrf_token = csrf;
        fetch('../../api/generate-attendance-report.php', {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(payload)})
          .then(async r=>{
            const text = await r.text();
            try { return { ok:true, json: JSON.parse(text) }; }
            catch(parseErr){ console.error('Report generate parse error raw response:', text); return { ok:false, raw:text, status:r.status }; }
          })
          .then(res => {
            if(res.ok && res.json && res.json.status==='ok') { notifySuccess('Report generated'); loadGeneratedReports(1); return; }
            if(res.ok && res.json){ notifyError('Generation failed: '+(res.json.message||'Unknown error')); return; }
            notifyError('Request failed: '+(res.raw?res.raw.slice(0,300):'No response'));
          })
          .catch(err=>{ console.error('Report generate network error', err); notifyError('Request failed'); });
      });
    }
  }
  if(!HAS_MULTIPLE_REPORT_TYPES && grFilterTypeEl && !grFilterTypeEl.value && DEFAULT_REPORT_TYPE){
    grFilterTypeEl.value = DEFAULT_REPORT_TYPE;
  }
  // Generated reports dynamic loader
  function loadGeneratedReports(page=1){
    const params = new URLSearchParams({
      page,
      per_page: document.getElementById('grPerPage')?document.getElementById('grPerPage').value:10,
      type: grFilterTypeEl ? grFilterTypeEl.value : (DEFAULT_REPORT_TYPE || ''),
      search: document.getElementById('grSearch').value.trim(),
      date_from: document.getElementById('grDateFrom').value,
      date_to: document.getElementById('grDateTo').value,
      show_deleted: document.getElementById('grShowDeleted') && document.getElementById('grShowDeleted').checked ? 1 : 0
    });
    const tbody = document.getElementById('generatedReportsBody');
    // compute visible header columns so placeholders match current column visibility
    function grVisibleCols(){
      return document.querySelectorAll('#grHeadRow th:not(.d-none)').length || 1;
    }
  tbody.innerHTML = `<tr><td colspan="${grVisibleCols()}" class="text-center text-muted">Loading...</td></tr>`;
    fetch('../../api/list-generated-reports.php?'+params.toString(), {credentials:'include'})
      .then(async r=>{
        const text = await r.text();
        let res;
        try { res = JSON.parse(text); } catch(parseErr){
          tbody.innerHTML = `<tr><td colspan="${grVisibleCols()}" class="text-danger small">Parse error loading data.<br><code>${escapeHtml(text.slice(0,400))}</code></td></tr>`;
          throw parseErr;
        }
        return res;
      })
      .then(res=>{
  if(res.status!=='ok'){ tbody.innerHTML = `<tr><td colspan="${grVisibleCols()}" class="text-center text-danger">Failed to load${res.message?': '+escapeHtml(res.message):''}</td></tr>`; return; }
  if(!res.data.length){ tbody.innerHTML = `<tr><td colspan="${grVisibleCols()}" class="text-center text-muted">No reports found.</td></tr>`; document.getElementById('grPaginationWrap').style.display='none'; return; }
        let i= (res.pagination.page-1)*res.pagination.per_page + 1;
    // Toggle Deleted By column visibility
    const delByTh = document.querySelector('th[data-col="deletedBy"]');
    if(delByTh){
      if(CAN_MANAGE_ARTIFACTS && res.show_deleted){ delByTh.classList.remove('d-none'); }
      else { delByTh.classList.add('d-none'); }
    }
        const showDelCol = CAN_MANAGE_ARTIFACTS && !!(res.show_deleted);
        const showGenCol = SHOW_GENERATED_COLUMN;
        const showBranchCol = CAN_VIEW_ALL_BRANCHES;
  tbody.innerHTML = res.data.map(r=>`<tr class="${r.deleted_at?'table-danger':''}">
          <td>${i++}</td>
          <td>${escapeHtml(r.type_label)}</td>
          <td>${escapeHtml(r.date_label)}</td>
          ${showBranchCol ? `<td>${escapeHtml(r.branch_label)}</td>` : ''}
          <td>${buildEmployeesCell(r)}</td>
          ${showGenCol ? `<td>
            <div class="d-flex align-items-center gap-2">
              <img src="${escapeAttr(r.generated_by_avatar||'')}" alt="" onerror="this.src='../../resources/userimg/default-image.jpg'" style="width:32px;height:32px;border-radius:50%;object-fit:cover;"> 
              <div class="d-flex flex-column gr-generated-stack">
                <span class="fw-semibold">${escapeHtml(r.generated_by)}</span>
                <small class="text-muted" style="font-size:.7rem;" title="${escapeHtml(r.generated_at)}">${escapeHtml(formatDateTime(r.generated_at))}</small>
              </div>
            </div>
          </td>` : ''}
          ${showDelCol ? `<td>${r.deleted_at?`<div class=\"d-flex align-items-center gap-2\"><img src=\"${escapeAttr(r.deleted_by_avatar||'')}\" alt=\"\" onerror=\"this.src='../../resources/userimg/default-image.jpg'\" style=\"width:32px;height:32px;border-radius:50%;object-fit:cover;\"><div class=\"d-flex flex-column gr-generated-stack\"><span class=\"fw-semibold\">${escapeHtml(r.deleted_by_name||r.deleted_by||'')}</span><small class=\"text-muted\" style=\"font-size:.7rem;\" title=\"${escapeHtml(r.deleted_at)}\">${escapeHtml(formatDateTime(r.deleted_at))}</small></div></div>`:''}</td>`:''}
          <td class="text-nowrap">
        ${r.deleted_at 
          ? `${r.can_purge ? `<button class=\"btn btn-xs btn-outline-danger gr-purge\" data-id=\"${r.id}\" title=\"Permanently delete report\" aria-label=\"Permanently delete report ${escapeAttr(r.type_label)} ${escapeAttr(r.date_label)}\">Delete</button>`:''}
            ${r.can_restore ? `<button class=\"btn btn-xs btn-outline-success ms-1 gr-restore\" data-id=\"${r.id}\" title=\"Restore\">↺</button>`:''}`
          : `<a href=\"${escapeAttr(r.file_url)}\" target=\"_blank\" class=\"btn btn-xs btn-outline-primary\">View</a>
            ${r.can_delete ? `<button class=\"btn btn-xs btn-outline-danger ms-1 gr-del\"
             title=\"Delete report\"
             data-id=\"${r.id}\"
             data-type=\"${escapeAttr(r.type_label)}\"
             data-date=\"${escapeAttr(r.date_label)}\"
             data-branch=\"${escapeAttr(r.branch_label)}\"
             data-emps=\"${escapeAttr(r.employees_label)}\"
             data-generated_by=\"${escapeAttr(r.generated_by)}\"
             data-generated_at=\"${escapeAttr(r.generated_at)}\"
             data-generated_avatar=\"${escapeAttr(r.generated_by_avatar||'')}\"
             aria-label=\"Delete report ${escapeAttr(r.type_label)} ${escapeAttr(r.date_label)}\">&times;</button>`:''}`}
          </td>
        </tr>`).join('');
        activateTooltipElements(tbody);
        buildPagination(res.pagination);
        // Reveal show-deleted switch if admin
    })
  .catch(err=>{ if(!tbody.innerHTML.includes('Parse error')) tbody.innerHTML = `<tr><td colspan="${grVisibleCols()}" class="text-center text-danger">Error loading data</td></tr>`; console.error('Generated reports load failed', err); });
  }
  function escapeHtml(str){ return (str||'').replace(/[&<>"]+/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s])); }
  function escapeAttr(str){ return escapeHtml(str); }
  function formatDateTime(ts){
    if(!ts) return '';
    // Expecting YYYY-MM-DD HH:MM:SS
    const m = ts.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/);
    if(!m) return ts; // fallback
    const [_,Y,Mo,D,H,Mi] = m;
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const monthLabel = monthNames[parseInt(Mo,10)-1] || Mo;
    return `${D} ${monthLabel} ${Y} ${H}:${Mi}`; // e.g. 17 Aug 2025 13:32
  }
  function formatDatePart(ts){
    const m = ts && ts.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/);
    if(!m) return ts||'';
    const [_,Y,Mo,D] = m;
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const monthLabel = monthNames[parseInt(Mo,10)-1] || Mo;
    return `${D} ${monthLabel} ${Y}`;
  }
  function formatTimePart(ts){
    const m = ts && ts.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/);
    if(!m) return '';
    return `${m[4]}:${m[5]}`;
  }
  function formatDateTime12(ts){
    const m = ts && ts.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/);
    if(!m) return ts||'';
    const [_,Y,Mo,D,H,Mi] = m;
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const monthLabel = monthNames[parseInt(Mo,10)-1] || Mo;
    let h = parseInt(H,10); const ampm = h>=12 ? 'PM':'AM'; h = h%12; if(h===0) h=12;
    return `${D} ${monthLabel} ${Y} ${h}:${Mi} ${ampm}`; // 17 Aug 2025 1:32 PM
  }
  function buildEmployeesCell(record){
    const label = record && record.employees_label ? record.employees_label : '';
    if(!label) return '';
    const hiddenRaw = record && record.employees_hidden ? record.employees_hidden : '';
    const hiddenList = hiddenRaw.split(',').map(n=>n.trim()).filter(Boolean);
    const plusMatch = label.match(/^(.*?)(\s*\+\d+\s+more)$/i);
    if(!hiddenList.length || !plusMatch){
      return escapeHtml(label);
    }
    const visiblePart = plusMatch[1].replace(/\s+$/, '');
    const morePart = plusMatch[2].trim();
    const tooltipText = hiddenList.join('\n');
    const beforeHtml = visiblePart ? escapeHtml(visiblePart) : '';
    const tooltipAttr = escapeAttr(tooltipText);
    const moreHtml = `<span class="gr-more-text" data-bs-toggle="tooltip" data-bs-placement="top" title="${tooltipAttr}">${escapeHtml(morePart)}</span>`;
    if(beforeHtml){
      return `${beforeHtml} ${moreHtml}`;
    }
    return moreHtml;
  }
  function activateTooltipElements(scope){
    if(!(window.bootstrap && window.bootstrap.Tooltip)) return;
    const ctx = scope || document;
    ctx.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>{
      const existing = window.bootstrap.Tooltip.getInstance(el);
      if(existing){ existing.dispose(); }
      new window.bootstrap.Tooltip(el);
    });
  }
  function getCsrfToken(){
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? (meta.getAttribute('content') || '') : '';
  }
  function buildStackedDate(ts){
    if(!ts) return '';
    const date = formatDatePart(ts);
    const time = formatTimePart(ts);
    return `<span class=\"gr-dt\" title=\"${escapeHtml(ts)}\"><span class=\"gr-date\">${escapeHtml(date)}</span><span class=\"gr-time\">${escapeHtml(time)}</span></span>`;
  }
  function buildDeletedCell(r){
    if(!r.deleted_at) return '';
    const avatar = escapeAttr(r.deleted_by_avatar||'');
    const name = escapeHtml(r.deleted_by_name||r.deleted_by||'');
    const rawTs = escapeAttr(r.deleted_at);
    const fmtTs = escapeHtml(formatDateTime(r.deleted_at));
    return '<div class="d-flex align-items-center gap-2">'
      + '<img src="'+avatar+'" alt="" onerror="this.src=\'../../resources/userimg/default-image.jpg\'" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">'
      + '<div class="d-flex flex-column gr-generated-stack">'
      + '<span class="fw-semibold">'+name+'</span>'
      + '<small class="text-muted" title="'+rawTs+'">'+fmtTs+'</small>'
      + '</div></div>';
  }
  function buildPagination(p){
    const wrap = document.getElementById('grPaginationWrap');
    const ul = document.getElementById('grPagination');
    const summary = document.getElementById('grSummary');
    summary.textContent = `Showing page ${p.page} of ${p.pages} (${p.total} total)`;
    ul.innerHTML='';
    function pageItem(num,label= null,disabled=false,active=false){
      label = label||num;
      const li=document.createElement('li'); li.className='page-item'+(disabled?' disabled':'')+(active?' active':'');
      const a=document.createElement('a'); a.className='page-link'; a.href='#'; a.textContent=label; a.dataset.page=num; li.appendChild(a); return li;
    }
    ul.appendChild(pageItem(p.page-1,'«',p.page===1));
    for(let i=1;i<=p.pages;i++){ if(i===1||i===p.pages||Math.abs(i-p.page)<=2){ ul.appendChild(pageItem(i,null,false,i===p.page)); } }
    ul.appendChild(pageItem(p.page+1,'»',p.page===p.pages));
    wrap.style.display = p.pages>1 ? 'flex':'none';
  }
  document.getElementById('grPagination').addEventListener('click',e=>{ if(e.target.matches('.page-link')){ e.preventDefault(); const pg=parseInt(e.target.dataset.page); if(!isNaN(pg)) loadGeneratedReports(pg); }});
  document.getElementById('grPerPage').addEventListener('change',()=>loadGeneratedReports(1));
  document.getElementById('grApplyFilters').addEventListener('click',()=>loadGeneratedReports(1));
  document.getElementById('grReset').addEventListener('click',()=>{ if(grFilterTypeEl){ grFilterTypeEl.value = HAS_MULTIPLE_REPORT_TYPES ? '' : (DEFAULT_REPORT_TYPE || ''); } document.getElementById('grDateFrom').value=''; document.getElementById('grDateTo').value=''; document.getElementById('grSearch').value=''; loadGeneratedReports(1); });
  const showDeleted = document.getElementById('grShowDeleted'); if(showDeleted){ showDeleted.addEventListener('change',()=>loadGeneratedReports(1)); }
  // (Removed legacy confirm() based deletion handler in favor of richer modal)
  // Replace confirm with modal (simplified version)
  // Unified click delegation for delete & restore buttons
  const grBody = document.getElementById('generatedReportsBody');
  grBody.addEventListener('click', e => {
    const restoreBtn = e.target.closest('.gr-restore');
    if(restoreBtn){
      const id = restoreBtn.dataset.id; if(!id) return;
      restoreBtn.disabled = true;
      const fd = new FormData(); fd.append('id', id);
      fetch('../../api/restore-generated-report.php',{method:'POST',body:fd,credentials:'include'})
        .then(r=>r.json())
        .then(res=>{ if(res.status==='ok'){ notifySuccess('Report restored'); loadGeneratedReports(); } else { restoreBtn.disabled=false; notifyError(res.message||'Restore failed'); } })
        .catch(()=>{ restoreBtn.disabled=false; notifyError('Restore failed'); });
      return;
    }
    const purgeBtn = e.target.closest('.gr-purge');
    if(purgeBtn){
      const id = purgeBtn.dataset.id; if(!id) return;
      confirmDialog('Permanently delete?','This action cannot be undone and will remove the file.','warning','Delete')
        .then(result=>{
          if(!result.isConfirmed) return;
          purgeBtn.disabled = true;
          const fd = new FormData(); fd.append('id', id);
          fetch('../../api/purge-generated-report.php',{method:'POST',body:fd,credentials:'include'})
            .then(r=>r.json())
            .then(res=>{ if(res.success){ notifySuccess('Report permanently deleted'); loadGeneratedReports(); } else { purgeBtn.disabled=false; notifyError(res.message||'Purge failed'); } })
            .catch(()=>{ purgeBtn.disabled=false; notifyError('Purge failed'); });
        });
      return;
    }
    const delBtn = e.target.closest('.gr-del');
    if(!delBtn) return;
    e.preventDefault();
    (document.getElementById('grDelType')||{}).textContent = delBtn.dataset.type||'';
    (document.getElementById('grDelDate')||{}).textContent = delBtn.dataset.date||'';
    (document.getElementById('grDelBranch')||{}).textContent = delBtn.dataset.branch||'';
  (document.getElementById('grDelEmployees')||{}).textContent = delBtn.dataset.emps||'';
    const genByEl = document.getElementById('grDelGeneratedBy');
    const genAtEl = document.getElementById('grDelGeneratedAt');
    if(genByEl){
      const avatar = delBtn.dataset.generated_avatar||'';
      const name = delBtn.dataset.generated_by||'';
      genByEl.innerHTML = avatar ? `<span class=\"d-inline-flex align-items-center gap-2\"><img src=\"${escapeAttr(avatar)}\" onerror=\"this.src='../../resources/userimg/default-image.jpg'\" style=\"width:28px;height:28px;border-radius:50%;object-fit:cover;\" alt=\"\"> <span>${escapeHtml(name)}</span></span>` : escapeHtml(name);
    }
    if(genAtEl){ genAtEl.textContent = delBtn.dataset.generated_at?formatDateTime12(delBtn.dataset.generated_at):''; }
    const errorBox = document.getElementById('grDeleteError'); if(errorBox){ errorBox.classList.add('d-none'); errorBox.textContent=''; }
    const confirmBtn=document.getElementById('grDeleteConfirm'); if(confirmBtn){ confirmBtn.dataset.id = delBtn.dataset.id; confirmBtn.disabled=false; }
    const spinner=document.getElementById('grDeleteSpinner'); if(spinner) spinner.classList.add('d-none');
    const deleteModalEl=document.getElementById('grDeleteModal');
    if(deleteModalEl && window.bootstrap){ const deleteModal = bootstrap.Modal.getOrCreateInstance(deleteModalEl); deleteModal.show(); }
    else if(confirm('Delete this report?')){ deleteReportSimple(delBtn.dataset.id); }
  });
  function deleteReportSimple(id){
    if(!id) return;
    const fd=new FormData(); fd.append('id',id);
    fetch('../../api/delete-generated-report.php',{method:'POST',body:fd,credentials:'include'})
  .then(r=>r.json())
  .then(res=>{ if(res.status==='ok'){ notifySuccess('Report deleted'); loadGeneratedReports(); } else { notifyError(res.message||'Delete failed'); } })
  .catch(()=>notifyError('Delete failed'));
  }
  window.grConfirmDelete = function(btn){
    const id = (btn && btn.dataset.id)||null;
    if(!id) return;
    btn.disabled = true; const spinner=document.getElementById('grDeleteSpinner'); if(spinner) spinner.classList.remove('d-none');
    const fd=new FormData(); fd.append('id',id);
    fetch('../../api/delete-generated-report.php',{method:'POST',body:fd,credentials:'include'})
      .then(r=>r.json())
      .then(res=>{
        btn.disabled=false; if(spinner) spinner.classList.add('d-none');
        if(res.status==='ok'){
          const deleteModalEl=document.getElementById('grDeleteModal');
          if(deleteModalEl && window.bootstrap){ const m=bootstrap.Modal.getInstance(deleteModalEl); if(m) m.hide(); }
          notifySuccess('Report deleted');
          loadGeneratedReports();
        } else { notifyError(res.message||'Delete failed'); }
      })
      .catch(()=>{ btn.disabled=false; if(spinner) spinner.classList.add('d-none'); notifyError('Delete failed'); });
  };
  // Initial load
  loadGeneratedReports();
})();
</script>
<!-- Delete Confirmation Modal -->
<div class="modal fade" id="grDeleteModal" tabindex="-1" aria-labelledby="grDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title d-flex align-items-center gap-2 mb-0" id="grDeleteModalLabel">
          <span class="text-danger" aria-hidden="true">⚠️</span>
          <span>Delete Report</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2 small">This action will move the report to Deleted state. You can still restore it later unless it is permanently purged.</p>
        <div class="border rounded p-2 small bg-body-secondary bg-opacity-10 mb-2">
          <div class="row g-1">
            <div class="col-6"><strong>Type:</strong> <div id="grDelType" class="fw-normal"></div></div>
            <div class="col-6"><strong>Date/Range:</strong> <div id="grDelDate" class="fw-normal"></div></div>
            <div class="col-6"><strong>Branch:</strong> <div id="grDelBranch" class="fw-normal"></div></div>
            <div class="col-6"><strong>Employees:</strong> <div id="grDelEmployees" class="fw-normal"></div></div>
            <?php if ($showGeneratedByColumn): ?>
            <div class="col-6"><strong>Generated By:</strong> <div id="grDelGeneratedBy" class="fw-normal"></div></div>
            <div class="col-6"><strong>Generated At:</strong> <div id="grDelGeneratedAt" class="fw-normal"></div></div>
            <?php endif; ?>
          </div>
        </div>
        <div id="grDeleteError" class="alert alert-danger py-1 px-2 small mt-2 d-none" role="alert"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger d-flex align-items-center gap-2" id="grDeleteConfirm" onclick="if(window.grConfirmDelete) grConfirmDelete(this);" aria-describedby="grDeleteModalLabel">
          <span>Delete</span>
          <span id="grDeleteSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
        </button>
      </div>
    </div>
  </div>
</div>
</body></html>

<?php
$page = 'Backup Management';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/utilities.php';

// Check if user has admin access
if (!is_admin() && !has_permission('system_settings')) {
    $_SESSION['error'] = "You don't have permission to access backup management.";
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/backup.php';
include 'includes/header.php';
?>

<style>
/* Light Mode Styles */
.backup-stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.backup-stats-card .stat-item {
    text-align: center;
    padding: 1rem;
}

.backup-stats-card .stat-value {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.backup-stats-card .stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
}

.backup-action-card {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
    margin-bottom: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.backup-table-card {
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    background-color: #fff;
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.btn-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
}

.btn-gradient-success {
    background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
    border: none;
    color: #333;
}

.btn-gradient-danger {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    border: none;
    color: #333;
}

.table-hover tbody tr:hover {
    background-color: rgba(102, 126, 234, 0.1);
    transform: translateY(-1px);
    transition: all 0.3s ease;
}

.backup-empty-state {
    background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    border-radius: 15px;
    padding: 3rem;
    text-align: center;
    color: #333;
}

/* Dark Mode Styles */
body.dark-mode .backup-stats-card {
    background: linear-gradient(135deg, #3a4a6b 0%, #4a3a5a 100%);
    color: #f8f9fa;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

body.dark-mode .backup-action-card {
    background: linear-gradient(135deg, #6a4c93 0%, #8b5a5a 100%);
    color: #f8f9fa;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

body.dark-mode .backup-table-card {
    background-color: #2c3e50;
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
}

body.dark-mode .card-header {
    background-color: #34495e !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #f8f9fa;
}

body.dark-mode .table {
    color: #f8f9fa;
    --bs-table-bg: transparent;
}

body.dark-mode .table-light {
    background-color: #34495e !important;
    color: #f8f9fa !important;
}

body.dark-mode .table-hover tbody tr:hover {
    background-color: rgba(52, 73, 94, 0.8) !important;
    color: #f8f9fa;
}

body.dark-mode .backup-empty-state {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: #f8f9fa;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

body.dark-mode .btn-gradient-primary {
    background: linear-gradient(135deg, #3a4a6b 0%, #4a3a5a 100%);
    color: #f8f9fa;
}

body.dark-mode .btn-gradient-success {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: #f8f9fa;
}

body.dark-mode .btn-gradient-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: #f8f9fa;
}

body.dark-mode .btn-outline-primary {
    border-color: #3498db;
    color: #3498db;
}

body.dark-mode .btn-outline-primary:hover {
    background-color: #3498db;
    color: #f8f9fa;
}

body.dark-mode .btn-outline-danger {
    border-color: #e74c3c;
    color: #e74c3c;
}

body.dark-mode .btn-outline-danger:hover {
    background-color: #e74c3c;
    color: #f8f9fa;
}

body.dark-mode .badge.bg-light {
    background-color: #34495e !important;
    color: #f8f9fa !important;
}

body.dark-mode .alert {
    background-color: #2c3e50;
    border-color: rgba(255, 255, 255, 0.1);
    color: #f8f9fa;
}

body.dark-mode .alert-warning {
    background-color: #f39c12;
    border-color: #e67e22;
    color: #2c3e50;
}

body.dark-mode .alert-danger {
    background-color: #e74c3c;
    border-color: #c0392b;
    color: #f8f9fa;
}

body.dark-mode .alert-success {
    background-color: #27ae60;
    border-color: #229954;
    color: #f8f9fa;
}

body.dark-mode .alert-info {
    background-color: #3498db;
    border-color: #2980b9;
    color: #f8f9fa;
}

body.dark-mode .modal-content {
    background-color: #2c3e50;
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #f8f9fa;
}

body.dark-mode .modal-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

body.dark-mode .modal-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

body.dark-mode .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

body.dark-mode .text-muted {
    color: #95a5a6 !important;
}

body.dark-mode .text-primary {
    color: #3498db !important;
}

body.dark-mode .form-control {
    background-color: #34495e;
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #f8f9fa;
}

body.dark-mode .form-control:focus {
    background-color: #34495e;
    border-color: #3498db;
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    color: #f8f9fa;
}

body.dark-mode .form-control::placeholder {
    color: #adb5bd;
}

/* Progress Bar Dark Mode Styles */
body.dark-mode .progress {
    background-color: #34495e;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

body.dark-mode .progress-bar {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: #f8f9fa;
}

body.dark-mode .progress-bar-striped {
    background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent);
}

/* Responsive adjustments for dark mode */
@media (max-width: 768px) {
    body.dark-mode .backup-stats-card,
    body.dark-mode .backup-action-card {
        margin-bottom: 1rem;
        padding: 1rem;
    }
    
    body.dark-mode .backup-stats-card .stat-value {
        font-size: 1.5rem;
    }
}

body.dark-mode .backup-action-card {
    background: linear-gradient(135deg, #6a4c93 0%, #8b3a62 100%);
    color: #f8f9fa;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

body.dark-mode .backup-table-card {
    background-color: #343a40;
    border: 1px solid #495057;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

body.dark-mode .btn-gradient-primary {
    background: linear-gradient(135deg, #4c63d2 0%, #5a4fcf 100%);
    color: #f8f9fa;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

body.dark-mode .btn-gradient-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: #f8f9fa;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

body.dark-mode .btn-gradient-danger {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    color: #f8f9fa;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

body.dark-mode .table-hover tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.1);
    transform: translateY(-1px);
    transition: all 0.3s ease;
}

body.dark-mode .backup-empty-state {
    background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
    color: #f8f9fa;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Additional Dark Mode Support for Tables and Cards */
body.dark-mode .card {
    background-color: #343a40;
    border-color: #495057;
    color: #f8f9fa;
}

body.dark-mode .card-header {
    background-color: #495057;
    border-color: #6c757d;
    color: #f8f9fa;
}

body.dark-mode .table {
    color: #f8f9fa;
}

body.dark-mode .table th,
body.dark-mode .table td {
    border-color: #495057;
}

body.dark-mode .table thead th {
    border-color: #495057;
    background-color: #495057;
    color: #f8f9fa;
}

/* Modal Dark Mode Support */
body.dark-mode .modal-content {
    background-color: #343a40;
    color: #f8f9fa;
    border-color: #495057;
}

body.dark-mode .modal-header {
    border-color: #495057;
    background-color: #495057;
}

body.dark-mode .modal-footer {
    border-color: #495057;
    background-color: #495057;
}

body.dark-mode .close {
    color: #f8f9fa;
    text-shadow: 0 1px 0 #000;
}

/* Alert Dark Mode Support */
body.dark-mode .alert-success {
    background-color: rgba(40, 167, 69, 0.2);
    border-color: #28a745;
    color: #d4edda;
}

body.dark-mode .alert-danger {
    background-color: rgba(220, 53, 69, 0.2);
    border-color: #dc3545;
    color: #f5c6cb;
}

body.dark-mode .alert-warning {
    background-color: rgba(255, 193, 7, 0.2);
    border-color: #ffc107;
    color: #fff3cd;
}

body.dark-mode .alert-info {
    background-color: rgba(23, 162, 184, 0.2);
    border-color: #17a2b8;
    color: #d1ecf1;
}

/* Badge Dark Mode Support */
body.dark-mode .badge.bg-success {
    background-color: #28a745 !important;
    color: #fff;
}

body.dark-mode .badge.bg-danger {
    background-color: #dc3545 !important;
    color: #fff;
}

body.dark-mode .badge.bg-warning {
    background-color: #ffc107 !important;
    color: #212529;
}

body.dark-mode .badge.bg-info {
    background-color: #17a2b8 !important;
    color: #fff;
}

body.dark-mode .badge.bg-secondary {
    background-color: #6c757d !important;
    color: #fff;
}

/* Form Control Dark Mode Support for Backup Management */
body.dark-mode .form-control,
body.dark-mode .form-select {
    background-color: #495057;
    border-color: #6c757d;
    color: #f8f9fa;
}

body.dark-mode .form-control:focus,
body.dark-mode .form-select:focus {
    background-color: #495057;
    border-color: #80bdff;
    color: #f8f9fa;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

body.dark-mode .form-control::placeholder {
    color: #adb5bd;
}

/* Responsive adjustments for dark mode */
@media (max-width: 768px) {
    body.dark-mode .backup-stats-card,
    body.dark-mode .backup-action-card {
        margin-bottom: 1rem;
        padding: 1rem;
    }
    
    body.dark-mode .backup-stats-card .stat-value {
        font-size: 1.5rem;
    }
}
</style>

<?php

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $backup = new DatabaseBackup();
    
    switch ($_POST['action']) {
        case 'create_backup':
            $result = $backup->createBackup();
            echo json_encode($result);
            exit();
            
        case 'restore_backup':
            $filename = $_POST['filename'] ?? '';
            if (empty($filename)) {
                echo json_encode(['success' => false, 'error' => 'No filename provided']);
                exit();
            }
            $result = $backup->restoreBackup($filename);
            echo json_encode($result);
            exit();
            
        case 'delete_backup':
            $filename = $_POST['filename'] ?? '';
            if (empty($filename)) {
                echo json_encode(['success' => false, 'error' => 'No filename provided']);
                exit();
            }
            $result = $backup->deleteBackup($filename);
            echo json_encode($result);
            exit();
            
        case 'list_backups':
            $backups = $backup->listBackups();
            echo json_encode(['success' => true, 'backups' => $backups]);
            exit();
              case 'clean_old_backups':
            $days = intval($_POST['days'] ?? 30);
            $result = $backup->cleanOldBackups($days);
            echo json_encode($result);
            exit();
            
        case 'get_progress':
            $result = $backup->getProgress();
            echo json_encode($result);
            exit();
    }
}

// Initialize backup class for displaying data
$backup = new DatabaseBackup();
$backups = $backup->listBackups();
?>

<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">
                <i class="fas fa-database me-2 text-primary"></i>
                Database Backup & Restore
            </h1>
            <p class="text-muted mb-0">Manage database backups and restore points</p>
        </div>        <div>
            <button type="button" class="btn btn-outline-danger me-2" onclick="cleanOldBackups()">
                <i class="fas fa-trash-alt me-2"></i>Clean Old Backups
            </button>
            <button type="button" class="btn btn-gradient-primary" onclick="createBackup()">
                <i class="fas fa-plus me-2"></i>Create Backup
            </button>
        </div>
    </div>

    <!-- Alert for messages -->
    <div id="alert-container"></div>    <!-- Backup Statistics Card -->
    <div class="backup-stats-card">
        <div class="row">
            <div class="col-md-3">
                <div class="stat-item">
                    <div class="stat-value" id="total-backups"><?php echo count($backups); ?></div>
                    <div class="stat-label">Total Backups</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-item">
                    <div class="stat-value" id="latest-backup">
                        <?php echo !empty($backups) ? date('M d', strtotime($backups[0]['date'])) : 'None'; ?>
                    </div>
                    <div class="stat-label">Latest Backup</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-item">
                    <div class="stat-value" id="total-size">
                        <?php 
                        $totalSize = array_sum(array_column($backups, 'size'));
                        echo $backup->formatBytes($totalSize);
                        ?>
                    </div>
                    <div class="stat-label">Total Size</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-item">
                    <div class="stat-value">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-label">Status: Protected</div>
                </div>
            </div>
        </div>
    </div>    <!-- Backup Management Card -->
    <div class="card border-0 shadow-sm backup-table-card">
        <div class="card-header border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2 text-primary"></i>Available Backups
                </h5>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshBackupList()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($backups)): ?>
            <div class="backup-empty-state">
                <i class="fas fa-database fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No backups available</h4>
                <p class="text-muted mb-4">Create your first backup to get started with database protection</p>
                <button type="button" class="btn btn-gradient-primary btn-lg px-4" onclick="createBackup()">
                    <i class="fas fa-plus me-2"></i>Create First Backup
                </button>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="backups-table">
                    <thead class="table-light">
                        <tr>
                            <th><i class="fas fa-file me-1"></i>Filename</th>
                            <th><i class="fas fa-calendar me-1"></i>Date Created</th>
                            <th><i class="fas fa-weight me-1"></i>Size</th>
                            <th><i class="fas fa-clock me-1"></i>Age</th>
                            <th class="text-center"><i class="fas fa-cogs me-1"></i>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="backups-tbody">
                        <?php foreach ($backups as $backupFile): ?>
                        <tr data-filename="<?php echo htmlspecialchars($backupFile['filename']); ?>">
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file-archive text-primary me-2"></i>
                                    <span class="font-monospace"><?php echo htmlspecialchars($backupFile['filename']); ?></span>
                                </div>
                            </td>
                            <td><?php echo date('M d, Y \a\t g:i A', strtotime($backupFile['date'])); ?></td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <?php echo $backup->formatBytes($backupFile['size']); ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo $backupFile['age']; ?></small>
                            </td>                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-gradient-success" 
                                            onclick="restoreBackup('<?php echo htmlspecialchars($backupFile['filename']); ?>')"
                                            title="Restore this backup">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" 
                                            onclick="downloadBackup('<?php echo htmlspecialchars($backupFile['filename']); ?>')"
                                            title="Download backup file">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button type="button" class="btn btn-gradient-danger" 
                                            onclick="deleteBackup('<?php echo htmlspecialchars($backupFile['filename']); ?>')"
                                            title="Delete this backup">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Warning Notice -->
    <div class="alert alert-warning mt-4" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Important:</strong> 
        <ul class="mb-0 mt-2">
            <li>Backup and restore operations may take several minutes depending on database size</li>
            <li>Restoration will overwrite existing data - ensure you have a current backup before proceeding</li>
            <li>Regular backups are recommended to prevent data loss</li>
            <li>Store important backups in multiple secure locations</li>
        </ul>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmModalBody">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmButton">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Progress Modal -->
<div class="modal fade" id="progressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="progressTitle">Processing...</h5>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" 
                             id="progressBar"
                             style="width: 0%"
                             aria-valuenow="0" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <span id="progressText">0%</span>
                        </div>
                    </div>
                    <p class="text-muted mb-0" id="progressSubtext">Initializing...</p>
                    <small class="text-muted" id="progressDetails"></small>
                </div>
                
                <!-- Status indicators -->
                <div class="d-flex justify-content-between small text-muted">
                    <span id="progressStep">Step 1</span>
                    <span id="progressTime">Elapsed: 0s</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal (fallback) -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 id="loadingText">Processing...</h5>
                <p class="text-muted mb-0" id="loadingSubtext">Please wait while the operation completes</p>
            </div>
        </div>
    </div>
</div>

<script>
const applyDigitsIfBs = (value) => (window.hrmsUseBsDates && typeof window.hrmsToNepaliDigits === 'function')
    ? window.hrmsToNepaliDigits(value)
    : value;

let currentOperation = null;
let progressInterval = null;
let operationStartTime = null;

function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alert-container').innerHTML = alertHtml;
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

function showProgress(title, subtitle) {
    document.getElementById('progressTitle').textContent = title;
    document.getElementById('progressSubtext').textContent = subtitle;
    document.getElementById('progressDetails').textContent = '';
    document.getElementById('progressStep').textContent = 'Step 1';
    document.getElementById('progressTime').textContent = applyDigitsIfBs('Elapsed: 0s');
    
    // Reset progress bar
    const progressBar = document.getElementById('progressBar');
    progressBar.style.width = '0%';
    progressBar.setAttribute('aria-valuenow', '0');
    document.getElementById('progressText').textContent = '0%';
    
    operationStartTime = Date.now();
    new bootstrap.Modal(document.getElementById('progressModal')).show();
}

function updateProgress(percentage, step, details) {
    const progressBar = document.getElementById('progressBar');
    progressBar.style.width = percentage + '%';
    progressBar.setAttribute('aria-valuenow', percentage);
    document.getElementById('progressText').textContent = Math.round(percentage) + '%';
    
    if (step) {
        document.getElementById('progressStep').textContent = step;
    }
    
    if (details) {
        document.getElementById('progressDetails').textContent = details;
    }
    
    // Update elapsed time
    if (operationStartTime) {
        const elapsed = Math.round((Date.now() - operationStartTime) / 1000);
        document.getElementById('progressTime').textContent = applyDigitsIfBs(`Elapsed: ${elapsed}s`);
    }
}

function hideProgress() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('progressModal'));
    if (modal) modal.hide();
    
    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
    }
    
    operationStartTime = null;
}

function showLoading(title, subtitle) {
    document.getElementById('loadingText').textContent = title;
    document.getElementById('loadingSubtext').textContent = subtitle;
    new bootstrap.Modal(document.getElementById('loadingModal')).show();
}

function hideLoading() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
    if (modal) modal.hide();
}

function startProgressPolling() {
    progressInterval = setInterval(() => {
        fetch('backup-management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_progress'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.progress !== null) {
                updateProgress(
                    data.progress.percentage, 
                    data.progress.step, 
                    data.progress.details
                );
                
                // If operation is complete, stop polling
                if (data.progress.percentage >= 100) {
                    setTimeout(() => {
                        if (progressInterval) {
                            clearInterval(progressInterval);
                            progressInterval = null;
                        }
                    }, 500);
                }
            }
        })
        .catch(error => {
            console.error('Progress polling error:', error);
        });
    }, 500); // Poll every 500ms
}

function createBackup() {
    showProgress('Creating Database Backup', 'Initializing backup process...');
    startProgressPolling();
    
    fetch('backup-management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=create_backup'
    })
    .then(response => response.json())
    .then(data => {
        hideProgress();
        if (data.success) {
            showAlert(`
                <i class="fas fa-check-circle me-2"></i>
                <strong>Backup created successfully!</strong><br>
                <small>File: ${data.filename} (${formatBytes(data.size)}, ${data.tables} tables)</small>
            `, 'success');
            refreshBackupList();
        } else {
            showAlert(`<i class="fas fa-exclamation-circle me-2"></i><strong>Backup failed:</strong> ${data.error}`, 'danger');
        }
    })
    .catch(error => {
        hideProgress();
        showAlert(`<i class="fas fa-exclamation-circle me-2"></i><strong>Error:</strong> ${error.message}`, 'danger');
    });
}

function restoreBackup(filename) {
    document.getElementById('confirmModalBody').innerHTML = `
        <div class="text-center">
            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
            <h5>Are you sure you want to restore this backup?</h5>
            <p class="text-muted">This will overwrite all current data with the backup data from:</p>
            <p class="font-monospace text-primary">${filename}</p>
            <div class="alert alert-danger mt-3">
                <strong>Warning:</strong> This action cannot be undone. Make sure you have a current backup before proceeding.
            </div>
        </div>
    `;
    
    document.getElementById('confirmButton').onclick = function() {
        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
        
        showProgress('Restoring Database', 'Preparing to restore backup data...');
        startProgressPolling();
        
        fetch('backup-management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=restore_backup&filename=${encodeURIComponent(filename)}`
        })
        .then(response => response.json())
        .then(data => {
            hideProgress();
            if (data.success) {
                showAlert(`
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Database restored successfully!</strong><br>
                    <small>Executed ${data.statements} SQL statements from ${data.filename}</small>
                `, 'success');
                
                // Refresh page after 3 seconds to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
                showAlert(`<i class="fas fa-exclamation-circle me-2"></i><strong>Restore failed:</strong> ${data.error}`, 'danger');
            }
        })
        .catch(error => {
            hideProgress();
            showAlert(`<i class="fas fa-exclamation-circle me-2"></i><strong>Error:</strong> ${error.message}`, 'danger');
        });
    };
    
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}

function deleteBackup(filename) {
    document.getElementById('confirmModalBody').innerHTML = `
        <div class="text-center">
            <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
            <h5>Delete Backup File</h5>
            <p class="text-muted">Are you sure you want to delete this backup?</p>
            <p class="font-monospace text-primary">${filename}</p>
            <div class="alert alert-warning">
                <strong>Note:</strong> This action cannot be undone.
            </div>
        </div>
    `;
    
    document.getElementById('confirmButton').onclick = function() {
        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
        
        fetch('backup-management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_backup&filename=${encodeURIComponent(filename)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(`<i class="fas fa-check-circle me-2"></i><strong>Backup deleted successfully!</strong>`, 'success');
                refreshBackupList();
            } else {
                showAlert(`<i class="fas fa-exclamation-circle me-2"></i><strong>Delete failed:</strong> ${data.error}`, 'danger');
            }
        })
        .catch(error => {
            showAlert(`<i class="fas fa-exclamation-circle me-2"></i><strong>Error:</strong> ${error.message}`, 'danger');
        });
    };
    
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}

function downloadBackup(filename) {
    const downloadUrl = `download-backup.php?file=${encodeURIComponent(filename)}`;
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showAlert(`<i class="fas fa-download me-2"></i>Download started for ${filename}`, 'info');
}

function cleanOldBackups() {
    const days = prompt('Delete backups older than how many days?', '30');
    if (days === null || isNaN(days) || days < 1) return;
    
    showLoading('Cleaning Old Backups', 'Removing backup files older than ' + days + ' days...');
    
    fetch('backup-management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=clean_old_backups&days=${days}`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert(`<i class="fas fa-check-circle me-2"></i><strong>Cleanup completed!</strong> Deleted ${data.deleted} old backup(s).`, 'success');
            refreshBackupList();
        } else {
            showAlert(`<i class="fas fa-exclamation-circle me-2"></i><strong>Cleanup failed:</strong> ${data.error}`, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert(`<i class="fas fa-exclamation-circle me-2"></i><strong>Error:</strong> ${error.message}`, 'danger');
    });
}
            showAlert(`<i class="fas fa-exclamation-circle me-2"></i><strong>Cleanup failed:</strong> ${data.error}`, 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert(`<i class="fas fa-exclamation-circle me-2"></i><strong>Error:</strong> ${error.message}`, 'danger');
    });
}

function refreshBackupList() {
    fetch('backup-management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=list_backups'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateBackupTable(data.backups);
            updateStatistics(data.backups);
        }
    })
    .catch(error => {
        console.error('Error refreshing backup list:', error);
    });
}

function updateBackupTable(backups) {
    const tbody = document.getElementById('backups-tbody');
    if (backups.length === 0) {
        location.reload(); // Reload to show empty state
        return;
    }
    
    tbody.innerHTML = backups.map(backup => `
        <tr data-filename="${escapeHtml(backup.filename)}">
            <td>
                <div class="d-flex align-items-center">
                    <i class="fas fa-file-archive text-primary me-2"></i>
                    <span class="font-monospace">${escapeHtml(backup.filename)}</span>
                </div>
            </td>
            <td>${applyDigitsIfBs(new Date(backup.date).toLocaleDateString('en-US', {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit'
            }))}</td>
            <td>
                <span class="badge bg-light text-dark">${formatBytes(backup.size)}</span>
            </td>
            <td>
                <small class="text-muted">${backup.age}</small>
            </td>            <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-gradient-success" 
                            onclick="restoreBackup('${escapeHtml(backup.filename)}')"
                            title="Restore this backup">
                        <i class="fas fa-undo"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary" 
                            onclick="downloadBackup('${escapeHtml(backup.filename)}')"
                            title="Download backup file">
                        <i class="fas fa-download"></i>
                    </button>
                    <button type="button" class="btn btn-gradient-danger" 
                            onclick="deleteBackup('${escapeHtml(backup.filename)}')"
                            title="Delete this backup">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function updateStatistics(backups) {
    document.getElementById('total-backups').textContent = backups.length;
    document.getElementById('latest-backup').textContent = backups.length > 0 ? 
        applyDigitsIfBs(new Date(backups[0].date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})) : 
        'None';
    
    const totalSize = backups.reduce((sum, backup) => sum + backup.size, 0);
    document.getElementById('total-size').textContent = formatBytes(totalSize);
}

function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Auto-refresh backup list every 30 seconds
setInterval(refreshBackupList, 30000);
</script>

<?php include 'includes/footer.php'; ?>

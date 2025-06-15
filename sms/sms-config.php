<?php
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/SparrowSMS.php';

$sms = new SparrowSMS();

// Set correct home path for sidebar images/links
$home = '../';

// Handle AJAX requests first (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {            case 'update_config':
                if (isset($_POST['config']) && is_array($_POST['config'])) {
                    $configs = $_POST['config'];
                    $stmt = $pdo->prepare("UPDATE sms_config SET config_value = ? WHERE config_key = ?");
                    
                    foreach ($configs as $key => $value) {
                        $stmt->execute([$value, $key]);
                    }
                    
                    $_SESSION['success'] = "SMS configuration updated successfully!";
                } else {
                    $_SESSION['error'] = "No configuration data received!";
                }
                
                // Redirect to prevent form resubmission for config updates
                header('Location: ' . $_SERVER['PHP_SELF']);                exit;
                
            case 'test_connection':
                try {                    // Debug: Check if we have valid config values
                    $token = '';
                    $sender = '';
                    
                    try {                        $stmt = $pdo->prepare("SELECT config_key, config_value FROM sms_config WHERE config_key IN ('api_token', 'sender_identity')");
                        $stmt->execute();
                        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($configs as $config) {
                            if ($config['config_key'] === 'api_token') {
                                $token = $config['config_value'];
                            }
                            if ($config['config_key'] === 'sender_identity') {
                                $sender = $config['config_value'];
                            }
                        }
                    } catch (Exception $e) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'credit_balance' => 0,
                            'error' => 'Database error: ' . $e->getMessage()
                        ]);
                        exit;
                    }
                    
                    // Check if token is still placeholder or empty
                    if ($token === 'your_api_token_here' || empty($token) || strlen($token) < 10) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'credit_balance' => 0,
                            'error' => 'Please configure your actual Sparrow SMS API token first. Get it from: https://sparrowsms.com/sms-panel/developers'
                        ]);
                        exit;
                    }
                    
                    $credit = $sms->checkCredit();
                    // Return JSON response for AJAX request
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => $credit['success'],
                        'credit_balance' => $credit['success'] ? $credit['credit_balance'] : 0,
                        'error' => $credit['success'] ? '' : $credit['error']
                    ]);
                } catch (Exception $e) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'credit_balance' => 0,
                        'error' => 'Connection test failed: ' . $e->getMessage()
                    ]);
                }                exit;
        }
    }
}

// Handle sender identity management actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identity_action'])) {
    $sms = new SparrowSMS();
    if ($_POST['identity_action'] === 'add') {
        $identity = $_POST['new_identity'] ?? '';
        $desc = $_POST['new_description'] ?? '';
        $setDefault = !empty($_POST['set_default']);
        $result = $sms->addSenderIdentity($identity, $desc, $setDefault);
        $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
    } elseif ($_POST['identity_action'] === 'remove') {
        $identity = $_POST['remove_identity'] ?? '';
        $result = $sms->removeSenderIdentity($identity);
        $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
    } elseif ($_POST['identity_action'] === 'set_default') {
        $identity = $_POST['default_identity'] ?? '';
        $result = $sms->setDefaultSenderIdentity($identity);
        $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Set page title for navigation and include header (after POST handling)
$page = 'SMS Configuration';
require_once __DIR__ . '/../includes/header.php';

// Get current configurations with error handling
$configs = [];
try {
    $stmt = $pdo->query("SELECT * FROM sms_config ORDER BY config_key");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

// Get SMS statistics
try {
    $stats = $sms->getSMSStatistics(30);
    $credit = $sms->checkCredit();
} catch (Exception $e) {
    $stats = [];
    $credit = ['success' => false, 'error' => 'Database not available'];
}

$identities = $sms->getSenderIdentities();
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-cog me-2"></i>SMS Configuration</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="sms-dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
            </a>
            <a href="sms-logs.php" class="btn btn-outline-success">
                <i class="fas fa-history me-1"></i>View Logs
            </a>
        </div>
    </div>    <div class="row">
        <!-- Configuration Form -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">SMS API Configuration</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($configs)): ?>                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Database Setup Required</h6>
                        <p>The SMS configuration table is not set up yet.</p>
                        <a href="web_setup.php?setup=hrms_sms_setup_2025" class="btn btn-warning btn-sm">
                            <i class="fas fa-database me-1"></i>Setup Database
                        </a>
                    </div>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_config">
                          <?php foreach ($configs as $config): ?>
                            <?php if ($config['config_key'] === 'sender_name') continue; // Remove sender_name from config UI ?>
                            <div class="mb-3">
                                <?php
                                // Custom labels for better UX
                                $labels = [
                                    'api_token' => 'Sparrow SMS API Token',
                                    //'sender_name' => 'SMS Sender Name', // Removed
                                    'api_url' => 'API Base URL',
                                    'is_active' => 'Enable SMS Service'
                                ];
                                $label = isset($labels[$config['config_key']]) ? $labels[$config['config_key']] : ucwords(str_replace('_', ' ', $config['config_key']));
                                ?>
                                <label for="<?php echo $config['config_key']; ?>" class="form-label">
                                    <?php echo $label; ?>
                                    <?php if ($config['config_key'] === 'api_token'): ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php if ($config['config_key'] === 'api_token'): ?>
                                <input type="password" class="form-control" 
                                       id="<?php echo $config['config_key']; ?>" 
                                       name="config[<?php echo $config['config_key']; ?>]"
                                       value="<?php echo htmlspecialchars($config['config_value']); ?>"
                                       placeholder="Enter your Sparrow SMS API token" required>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>Get this from: <a href="https://web.sparrowsms.com/token/" target="_blank">Sparrow SMS Token Dashboard</a>
                                </small>

                                <?php elseif ($config['config_key'] === 'api_url'): ?>
                                <input type="url" class="form-control" 
                                       id="<?php echo $config['config_key']; ?>" 
                                       name="config[<?php echo $config['config_key']; ?>]"
                                       value="<?php echo htmlspecialchars($config['config_value']); ?>"
                                       placeholder="https://api.sparrowsms.com/v2/" readonly disabled>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>Sparrow SMS API endpoint (do not change)
                                </small>
                                
                                <?php elseif ($config['config_key'] === 'is_active'): ?>
                                <select class="form-control" 
                                        id="<?php echo $config['config_key']; ?>" 
                                        name="config[<?php echo $config['config_key']; ?>]">
                                    <option value="0" <?php echo $config['config_value'] == '0' ? 'selected' : ''; ?>>Disabled</option>
                                    <option value="1" <?php echo $config['config_value'] == '1' ? 'selected' : ''; ?>>Enabled</option>
                                </select>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>Enable this after configuring your API token
                                </small>
                                
                                <?php else: ?>
                                <input type="text" class="form-control" 
                                       id="<?php echo $config['config_key']; ?>" 
                                       name="config[<?php echo $config['config_key']; ?>]"
                                       value="<?php echo htmlspecialchars($config['config_value']); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Configuration
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="testConnection()">
                                <i class="fas fa-satellite-dish me-1"></i>Test Connection
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
              <!-- Sender Identities Management -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Sender Identities</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-3 d-flex flex-wrap gap-2 align-items-end">
                        <input type="hidden" name="identity_action" value="add">
                        <div>
                            <label class="form-label mb-1">New Identity</label>
                            <input type="text" name="new_identity" maxlength="11" class="form-control" required placeholder="e.g. HRMS">
                        </div>
                        <div>
                            <label class="form-label mb-1">Description</label>
                            <input type="text" name="new_description" maxlength="40" class="form-control" placeholder="Optional">
                        </div>
                        <div class="form-check mb-1 ms-2">
                            <input class="form-check-input" type="checkbox" name="set_default" id="setDefaultAdd">
                            <label class="form-check-label" for="setDefaultAdd">Set as default</label>
                        </div>
                        <button type="submit" class="btn btn-primary ms-2"><i class="fas fa-plus me-1"></i>Add</button>
                    </form>
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Identity</th>
                                <th>Description</th>
                                <th>Default</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($identities as $id): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($id['identity']); ?></strong></td>
                                <td><?php echo htmlspecialchars($id['description'] ?? ''); ?></td>
                                <td><?php if (!empty($id['is_default'])): ?><span class="badge bg-success">Default</span><?php endif; ?></td>
                                <td>
                                    <?php if (empty($id['is_default'])): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="identity_action" value="set_default">
                                        <input type="hidden" name="default_identity" value="<?php echo htmlspecialchars($id['identity']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Set Default</button>
                                    </form>
                                    <form method="POST" class="d-inline ms-1">
                                        <input type="hidden" name="identity_action" value="remove">
                                        <input type="hidden" name="remove_identity" value="<?php echo htmlspecialchars($id['identity']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this sender identity?')">Remove</button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Status Panel -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Connection Status</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-grow-1">
                            <span class="fw-bold">API Status</span>
                        </div>
                        <span class="badge bg-<?php echo $credit['success'] ? 'success' : 'danger'; ?>">
                            <?php echo $credit['success'] ? 'Connected' : 'Disconnected'; ?>
                        </span>
                    </div>
                    
                    <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="refreshStatus()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh Status
                    </button>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">Quick Setup Guide</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item border-0 px-0">
                            <small class="text-muted">1. Sign up at</small><br>
                            <a href="https://sparrowsms.com" target="_blank" class="text-decoration-none">
                                sparrowsms.com <i class="fas fa-external-link-alt ms-1"></i>
                            </a>
                        </div>
                        <div class="list-group-item border-0 px-0">
                            <small class="text-muted">2. Get your API token from dashboard</small>
                        </div>
                        <div class="list-group-item border-0 px-0">
                            <small class="text-muted">3. Enter token above and test connection</small>
                        </div>                        <div class="list-group-item border-0 px-0">
                            <small class="text-muted">4. Start sending SMS!</small>
                        </div>
                        <?php if (!$credit['success']): ?>
                        <div class="list-group-item border-0 px-0 mt-2">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-globe-asia me-2 text-primary"></i>
                                <span>Your current public IP:</span>
                                <span id="publicIpValue" class="fw-bold mx-2">...</span>
                                <button type="button" class="btn btn-link btn-sm px-1 py-0" id="copyPublicIpBtn" title="Copy IP"><i class="fas fa-copy"></i></button>
                                <button type="button" class="btn btn-link btn-sm px-1 py-0" id="refreshPublicIpBtn" title="Refresh IP"><i class="fas fa-sync-alt"></i></button>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <i class="fas fa-info-circle me-1"></i>Add this IP to your Sparrow SMS whitelist if needed
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testConnection() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testing...';
    btn.disabled = true;
    
    fetch('sms-config.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=test_connection'
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response type:', response.headers.get('content-type'));
        return response.text();
    })
    .then(data => {
        console.log('Raw response:', data);
        try {
            const jsonData = JSON.parse(data);
            if (jsonData.success) {
                alert('Connection Test Successful!\nCredit Balance: ' + jsonData.credit_balance);
            } else {
                alert('Connection Test Failed!\nError: ' + jsonData.error);
            }
        } catch (e) {
            console.error('JSON parse error:', e.message);
            console.error('Response data:', data);
            alert('Error: Received invalid response from server. Check browser console for details.');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Error testing connection: ' + error.message);
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function refreshStatus() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
    btn.disabled = true;
    
    fetch('sms-config.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=test_connection'
    })
    .then(response => response.text())
    .then(data => {
        location.reload(); // Refresh to show updated status
    })
    .catch(error => {
        alert('Error refreshing status: ' + error.message);
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// Load sender identities on page load
document.addEventListener('DOMContentLoaded', function() {
    loadSenderIdentities();
});

function loadSenderIdentities() {
    fetch('manage-sender-identities-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_identities'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displaySenderIdentities(data.identities);
        } else {
            document.getElementById('identitiesTable').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Error loading sender identities: ${data.message || 'Unknown error'}
                </div>
            `;
        }
    })
    .catch(error => {
        document.getElementById('identitiesTable').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Error loading sender identities: ${error.message}
            </div>
        `;
    });
}

function displaySenderIdentities(identities) {
    if (identities.length === 0) {
        document.getElementById('identitiesTable').innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-id-card fa-3x text-muted mb-3"></i>
                <p class="text-muted">No validated sender identities found.</p>
                <p class="small text-muted">Click "Add Identity" to test and add approved sender IDs from your Sparrow SMS account.</p>
                <button type="button" class="btn btn-primary btn-sm" onclick="showAddIdentityModal()">
                    <i class="fas fa-plus me-1"></i>Add First Identity
                </button>
            </div>
        `;
        return;
    }

    let html = `
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Identity</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Validated</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    identities.forEach(identity => {
        const defaultBadge = identity.is_default 
            ? '<span class="badge bg-primary"><i class="fas fa-star me-1"></i>Default</span>' 
            : '<span class="text-muted">-</span>';
        
        const validatedAt = identity.validated_at 
            ? new Date(identity.validated_at).toLocaleDateString()
            : 'Unknown';
        
        html += `
            <tr>
                <td>
                    <strong class="text-primary">${identity.identity}</strong>
                </td>
                <td>${identity.description || 'No description'}</td>
                <td>${defaultBadge}</td>
                <td>
                    <small class="text-muted">
                        <i class="fas fa-check-circle text-success me-1"></i>
                        ${validatedAt}
                    </small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="removeIdentity('${identity.identity}')" 
                                title="Remove Identity"
                                ${identity.is_default ? 'disabled' : ''}>
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('identitiesTable').innerHTML = html;
}

function refreshSenderIdentities() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Validating...';
    btn.disabled = true;
    
    fetch('manage-sender-identities-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=refresh_identities'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displaySenderIdentities(data.identities);
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Network error: ' + error.message
        });
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function showAddIdentityModal() {
    const modalHtml = `
        <div class="modal fade" id="identityModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-plus-circle me-2"></i>
                            Add Sender Identity
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info border-0">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Note:</strong> The identity will be validated against your Sparrow SMS account.
                            Only approved sender IDs will be accepted.
                        </div>
                        <form id="identityForm">
                            <div class="mb-3">
                                <label for="identityName" class="form-label">
                                    Identity Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="identityName" maxlength="11" required 
                                       placeholder="e.g., COMPANY, ALERT, NOTICE" style="text-transform: uppercase;">
                                <small class="form-text text-muted">
                                    Maximum 11 characters, alphanumeric only. Must be approved in your Sparrow SMS dashboard.
                                </small>
                            </div>
                            <div class="mb-3">
                                <label for="identityDescription" class="form-label">Description</label>
                                <input type="text" class="form-control" id="identityDescription" maxlength="200" 
                                       placeholder="Brief description of this identity">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveIdentity()">
                            <i class="fas fa-check me-1"></i>Validate & Add
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('identityModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('identityModal'));
    modal.show();
    
    // Auto-uppercase the identity name
    document.getElementById('identityName').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
}

function saveIdentity() {
    const identity = document.getElementById('identityName').value.trim();
    const description = document.getElementById('identityDescription').value.trim();
    
    if (!identity) {
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: 'Please enter a sender identity name.'
        });
        return;
    }
    
    // Validate format
    if (!/^[A-Z0-9]{1,11}$/.test(identity)) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Format',
            text: 'Sender identity must be alphanumeric and maximum 11 characters.'
        });
        return;
    }
    
    const saveBtn = event.target;
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Validating...';
    saveBtn.disabled = true;
    
    fetch('manage-sender-identities-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add_identity&identity=${encodeURIComponent(identity)}&description=${encodeURIComponent(description)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('identityModal'));
            modal.hide();
            
            // Reload identities
            loadSenderIdentities();
            
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Validation Failed',
                text: data.message
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Network error: ' + error.message
        });
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

function removeIdentity(identity) {
    Swal.fire({
        title: 'Remove Sender Identity?',
        text: `Are you sure you want to remove "${identity}" from the list?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('manage-sender-identities-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove_identity&identity=${encodeURIComponent(identity)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadSenderIdentities();
                    Swal.fire({
                        icon: 'success',
                        title: 'Removed!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Network error: ' + error.message
                });
            });        }
    });
}

function fetchPublicIp() {
    const value = document.getElementById('publicIpValue');
    const refreshBtn = document.getElementById('refreshPublicIpBtn');
    
    // Only proceed if elements exist (i.e., API is not connected)
    if (!value || !refreshBtn) return;
    
    value.textContent = '...';
    refreshBtn.disabled = true;
    fetch('../api/public-ip.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                value.textContent = data.ip;
            } else {
                value.textContent = 'Unavailable';
            }
        })
        .catch(() => {
            value.textContent = 'Unavailable';
        })
        .finally(() => {
            refreshBtn.disabled = false;
        });
}

function copyPublicIp() {
    const value = document.getElementById('publicIpValue');
    
    // Only proceed if element exists (i.e., API is not connected)
    if (!value) return;
    
    const ipText = value.textContent;
    if (ipText && ipText !== '...') {
        navigator.clipboard.writeText(ipText).then(() => {
            const btn = document.getElementById('copyPublicIpBtn');
            btn.innerHTML = '<i class="fas fa-check text-success"></i>';
            setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 1200);
        });
    }
}

// Only initialize IP functionality if API is not connected
document.addEventListener('DOMContentLoaded', function() {
    const refreshBtn = document.getElementById('refreshPublicIpBtn');
    const copyBtn = document.getElementById('copyPublicIpBtn');
    
    if (refreshBtn && copyBtn) {
        refreshBtn.onclick = fetchPublicIp;
        copyBtn.onclick = copyPublicIp;
        fetchPublicIp();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

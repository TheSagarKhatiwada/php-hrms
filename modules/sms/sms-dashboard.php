<?php
require_once __DIR__ . '/../../includes/db_connection.php';

// Set page title for navigation
$page = 'SMS Dashboard';

require_once __DIR__ . '/SparrowSMS.php';

$sms = new SparrowSMS();

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {        case 'send_sms':
            $message = $_POST['message'];
            $from = $_POST['from'] ?? null;
            $phoneNumbersJson = $_POST['phone_numbers'] ?? '';
            
            if (empty($phoneNumbersJson)) {
                echo json_encode(['success' => false, 'error' => 'No phone numbers provided']);
                exit();
            }
            
            $phoneNumbers = json_decode($phoneNumbersJson, true);
            if (!is_array($phoneNumbers) || empty($phoneNumbers)) {
                echo json_encode(['success' => false, 'error' => 'Invalid phone numbers format']);
                exit();
            }
            
            // Verify all phone numbers
            $verifiedNumbers = [];
            $invalidNumbers = [];
            
            foreach ($phoneNumbers as $number) {
                $verified = $sms->verifyPhoneNumber($number);
                if ($verified) {
                    $verifiedNumbers[] = $verified;
                } else {
                    $invalidNumbers[] = $number;
                }
            }
            
            if (empty($verifiedNumbers)) {
                echo json_encode(['success' => false, 'error' => 'No valid phone numbers found']);
                exit();
            }
            
            $results = [];
            $overallSuccess = false; // default to false, we'll set true if any message succeeded

            if (count($verifiedNumbers) === 1) {
                // Single SMS
                $result = $sms->sendSMS($verifiedNumbers[0], $message, $from);
                $results[] = $result;
                $overallSuccess = !empty($result['success']);
            } else {
                // Multiple SMS - send to each number individually for better error tracking
                foreach ($verifiedNumbers as $number) {
                    $result = $sms->sendSMS($number, $message, $from);
                    $results[] = $result;
                }

                // Consider the overall operation a success if at least one message was sent
                $successfulCount = count(array_filter($results, function($r) { return !empty($r['success']); }));
                $overallSuccess = $successfulCount > 0;
            }
            
            // Prepare response
            $response = [
                'success' => $overallSuccess,
                'results' => $results,
                'total_sent' => count($verifiedNumbers),
                'invalid_numbers' => $invalidNumbers
            ];
            
            if (!$overallSuccess) {
                $failedCount = count(array_filter($results, function($r) { return !$r['success']; }));
                $response['error'] = "Some messages failed. {$failedCount} out of " . count($results) . " failed.";
            }
            
            echo json_encode($response);
            exit();
            
        case 'check_credit':
            $result = $sms->checkCredit();
            echo json_encode($result);
            exit();
            
        case 'get_stats':
            $days = intval($_POST['days'] ?? 7);
            $stats = $sms->getSMSStatistics($days);
            echo json_encode(['success' => true, 'data' => $stats]);
            exit();
    }
}

// Get statistics with error handling
try {
    $weeklyStats = $sms->getSMSStatistics(7);
    $monthlyStats = $sms->getSMSStatistics(30);
    $credit = $sms->checkCredit();
} catch (Exception $e) {
    // Fallback data if database is not available
    $weeklyStats = [];
    $monthlyStats = [];
    $credit = ['success' => false, 'error' => 'Database not available'];
}

// Include page header for non-AJAX requests
require_once __DIR__ . '/../../includes/header.php';

// Get recent SMS logs with error handling
$recentSMS = [];
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("
            SELECT l.*, e.first_name, e.last_name 
            FROM sms_logs l 
            LEFT JOIN employees e ON l.employee_id = e.emp_id 
            ORDER BY l.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $recentSMS = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table might not exist, use empty array
    $recentSMS = [];
}

// Calculate totals with fallback
$totalSent = $monthlyStats ? array_sum(array_column($monthlyStats, 'total_sent')) : 0;
$totalSuccessful = $monthlyStats ? array_sum(array_column($monthlyStats, 'successful')) : 0;
$totalFailed = $monthlyStats ? array_sum(array_column($monthlyStats, 'failed')) : 0;
$totalCost = $monthlyStats ? array_sum(array_column($monthlyStats, 'total_cost')) : 0;
$successRate = $totalSent > 0 ? round(($totalSuccessful / $totalSent) * 100, 1) : 0;
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-tachometer-alt me-2"></i>SMS Dashboard</h1>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendSMSModal">
                <i class="fas fa-paper-plane me-1"></i>Send SMS
            </button>
            <a href="sms-logs.php" class="btn btn-outline-success">
                <i class="fas fa-history me-1"></i>View Logs
            </a>
            <a href="sms-templates.php" class="btn btn-outline-info">
                <i class="fas fa-file-alt me-1"></i>Templates
            </a>
            <a href="sms-config.php" class="btn btn-outline-secondary">
                <i class="fas fa-cog me-1"></i>Configuration
            </a>
        </div>
    </div>

    <?php if (empty($recentSMS) && (!$credit['success'] || !$weeklyStats)): ?>
    <!-- Database Setup Notice -->
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Database Setup Required</h5>
        <p>The SMS module database tables are not set up yet. Some features may not work properly.</p>
        <hr>
        <div class="mb-0">
            <strong>To fix this:</strong>
            <ol class="mb-2">
                <li>Enable PDO MySQL extension in XAMPP</li>
                <li>Run: <code>php setup_database.php</code> in the SMS folder</li>
                <li>Configure your SMS API credentials</li>
            </ol>
            <a href="sms-config.php" class="btn btn-warning btn-sm">
                <i class="fas fa-cog me-1"></i>Go to Configuration
            </a>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                Credit Balance
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <span id="creditBalance">
                                    <?php echo $credit['success'] ? number_format($credit['credit_balance']) : 'N/A'; ?>
                                </span>
                                <button class="btn btn-sm btn-outline-primary ms-2" onclick="refreshCredit()">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-credit-card fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                SMS Sent (This Month)
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo number_format($totalSent); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-paper-plane fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                Success Rate
                            </div>
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <div class="h5 mb-0 mr-3 fw-bold text-gray-800">
                                        <?php echo $successRate; ?>%
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="progress progress-sm mr-2">
                                        <div class="progress-bar bg-info" role="progressbar"
                                             style="width: <?php echo $successRate; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                Total Cost (This Month)
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                Rs. <?php echo number_format($totalCost, 2); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">SMS Usage Overview</h6>
                </div>
                <div class="card-body">
                    <canvas id="smsChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">SMS Status Distribution</h6>
                </div>
                <div class="card-body">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent SMS Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-primary">Recent SMS Activity</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Phone</th>
                            <th>Message</th>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentSMS)): ?>
                            <?php foreach ($recentSMS as $smsRecord): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($smsRecord['created_at'])); ?></td>
                                <td>
                                    <span class="font-monospace">
                                        <?php echo htmlspecialchars($smsRecord['phone_number']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 200px;" 
                                          title="<?php echo htmlspecialchars($smsRecord['message']); ?>">
                                        <?php echo htmlspecialchars(substr($smsRecord['message'], 0, 50)) . (strlen($smsRecord['message']) > 50 ? '...' : ''); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($smsRecord['first_name']): ?>
                                        <?php echo htmlspecialchars($smsRecord['first_name'] . ' ' . $smsRecord['last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch ($smsRecord['status']) {
                                        case 'sent':
                                            $statusClass = 'success';
                                            break;
                                        case 'failed':
                                            $statusClass = 'danger';
                                            break;
                                        case 'pending':
                                            $statusClass = 'warning';
                                            break;
                                        default:
                                            $statusClass = 'secondary';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst($smsRecord['status']); ?>
                                    </span>
                                </td>
                                <td>Rs. <?php echo number_format($smsRecord['cost'], 4); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                    No SMS activity yet. Send your first SMS to see it here!
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/sms-modal.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// SMS Usage Chart
const smsData = <?php echo json_encode(array_reverse($weeklyStats)); ?>;
const ctx = document.getElementById('smsChart').getContext('2d');

// Handle empty data
if (smsData.length === 0) {
    smsData.push({date: 'No Data', total_sent: 0, failed: 0});
}

const smsChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: smsData.map(item => item.date),
        datasets: [{
            label: 'SMS Sent',
            data: smsData.map(item => item.total_sent || 0),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }, {
            label: 'Failed',
            data: smsData.map(item => item.failed || 0),
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Status Distribution Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const totalSuccess = <?php echo $totalSuccessful; ?>;
const totalFail = <?php echo $totalFailed; ?>;

const statusChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Successful', 'Failed'],
        datasets: [{
            data: [totalSuccess || 0, totalFail || 0],
            backgroundColor: ['#28a745', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: (totalSuccess + totalFail) > 0
            }
        }
    }
});

// Dashboard-specific functions
function refreshCredit() {
    fetch('sms-dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=check_credit'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('creditBalance').innerHTML = 
                data.credit_balance + ' <button class="btn btn-sm btn-outline-primary ms-2" onclick="refreshCredit()"><i class="fas fa-sync-alt"></i></button>';
        } else {
            alert('Failed to refresh credit: ' + data.error);
        }
    });
}

// Page-specific SMS modal configuration
document.addEventListener('DOMContentLoaded', function() {
    // Show templates for dashboard
    toggleSMSTemplates(true);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

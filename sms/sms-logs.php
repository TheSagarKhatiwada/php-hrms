<?php
require_once __DIR__ . '/../includes/db_connection.php';

// Set page title for navigation
$page = 'SMS Logs';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/SparrowSMS.php';

$sms = new SparrowSMS();

// Handle filtering
$whereClause = "WHERE 1=1";
$params = [];

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $whereClause .= " AND l.status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $whereClause .= " AND DATE(l.created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $whereClause .= " AND DATE(l.created_at) <= ?";
    $params[] = $_GET['date_to'];
}

if (isset($_GET['phone']) && !empty($_GET['phone'])) {
    $whereClause .= " AND l.phone_number LIKE ?";
    $params[] = '%' . $_GET['phone'] . '%';
}

// Pagination
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 25;
$offset = ($currentPage - 1) * $limit;

// Get total count with error handling
$totalRecords = 0;
$totalPages = 1;
$smsLogs = [];

try {
    $countQuery = "SELECT COUNT(*) FROM sms_logs l LEFT JOIN employees e ON l.employee_id = e.id $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // Get SMS logs
    $query = "
        SELECT l.*, e.first_name, e.last_name, e.emp_id as emp_id
        FROM sms_logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        $whereClause
        ORDER BY l.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $smsLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Database might not be set up yet
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-history me-2"></i>SMS Logs</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="sms-dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
            </a>
            <a href="export-sms-logs.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline-success">
                <i class="fas fa-download me-1"></i>Export CSV
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendSMSModal">
                <i class="fas fa-paper-plane me-1"></i>Send SMS
            </button>
        </div>    </div>

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header">
            <h6 class="mb-0">Filter SMS Logs</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="sent" <?php echo (isset($_GET['status']) && $_GET['status'] === 'sent') ? 'selected' : ''; ?>>Sent</option>
                        <option value="failed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'failed') ? 'selected' : ''; ?>>Failed</option>
                        <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="text" class="form-control" id="phone" name="phone" 
                           placeholder="Search phone..." 
                           value="<?php echo isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : ''; ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i>Apply Filters
                    </button>
                    <a href="sms-logs.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- SMS Logs Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">SMS History</h6>
            <div class="text-muted">
                Total: <?php echo number_format($totalRecords); ?> records
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="smsLogsTable">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Phone Number</th>
                            <th>Message</th>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Cost</th>
                            <th>Response Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($smsLogs)): ?>
                            <?php foreach ($smsLogs as $log): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($log['created_at'])); ?></small>
                                </td>
                                <td>
                                    <span class="font-monospace text-primary">
                                        <?php echo htmlspecialchars($log['phone_number']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="message-preview" style="max-width: 300px;">
                                        <span class="text-truncate d-inline-block w-100" 
                                              title="<?php echo htmlspecialchars($log['message']); ?>">
                                            <?php echo htmlspecialchars(substr($log['message'], 0, 60)) . (strlen($log['message']) > 60 ? '...' : ''); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($log['first_name']): ?>
                                        <div class="fw-bold"><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></div>
                                        <small class="text-muted">ID: <?php echo htmlspecialchars($log['emp_id']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusIcon = '';
                                    switch ($log['status']) {
                                        case 'sent':
                                            $statusClass = 'success';
                                            $statusIcon = 'check-circle';
                                            break;
                                        case 'failed':
                                            $statusClass = 'danger';
                                            $statusIcon = 'times-circle';
                                            break;
                                        case 'pending':
                                            $statusClass = 'warning';
                                            $statusIcon = 'clock';
                                            break;
                                        default:
                                            $statusClass = 'secondary';
                                            $statusIcon = 'question-circle';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold">Rs. <?php echo number_format($log['cost'], 4); ?></span>
                                </td>                                <td>
                                    <?php if ($log['response_data']): ?>
                                        <span class="text-muted" 
                                              title="<?php echo htmlspecialchars($log['response_data']); ?>">
                                            <?php echo htmlspecialchars(substr($log['response_data'], 0, 30)) . '...'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                    <h5>No SMS logs found</h5>
                                    <p>No SMS messages match your current filters.</p>
                                    <?php if (!empty($_GET)): ?>
                                        <a href="sms-logs.php" class="btn btn-outline-primary">
                                            <i class="fas fa-times me-1"></i>Clear Filters
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="SMS logs pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    $queryParams = $_GET;
                    unset($queryParams['page']);
                    $baseUrl = 'sms-logs.php?' . http_build_query($queryParams) . (empty($queryParams) ? '' : '&');
                    ?>
                    
                    <?php if ($currentPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $baseUrl; ?>page=<?php echo $currentPage - 1; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                    <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $baseUrl; ?>page=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $baseUrl; ?>page=<?php echo $currentPage + 1; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>    </div>
</div>

<?php require_once __DIR__ . '/../includes/sms-modal.php'; ?>

<script>
// Page-specific SMS modal configuration
document.addEventListener('DOMContentLoaded', function() {
    // Show templates for this page
    toggleSMSTemplates(true);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

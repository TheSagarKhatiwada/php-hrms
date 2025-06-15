<?php
$page = 'Holiday Management';
// Include utilities for role check functions
require_once 'includes/session_config.php';
require_once 'includes/utilities.php';

// Check if user has permission to manage holidays (admin or HR)
if (!is_admin() && !has_permission('manage_holidays')) {
    $_SESSION['error'] = "You don't have permission to manage holidays.";
    header('Location: dashboard.php');
    exit();
}

// Include database connection
include 'includes/db_connection.php';

// Create holidays table if it doesn't exist
try {
    $createTableSQL = "CREATE TABLE IF NOT EXISTS holidays (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        date DATE NOT NULL,
        type ENUM('national', 'company', 'optional') DEFAULT 'company',
        description TEXT,
        is_recurring BOOLEAN DEFAULT FALSE,
        branch_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTableSQL);
} catch (PDOException $e) {
    error_log("Error creating holidays table: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_holiday'])) {
        // Add new holiday
        $name = trim($_POST['holiday_name']);
        $date = $_POST['holiday_date'];
        $type = $_POST['holiday_type'];
        $description = trim($_POST['holiday_description']);
        $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
        $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;

        try {
            $stmt = $pdo->prepare("INSERT INTO holidays (name, date, type, description, is_recurring, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $date, $type, $description, $is_recurring, $branch_id]);
            $_SESSION['success'] = "Holiday added successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding holiday: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit_holiday'])) {
        // Edit holiday
        $id = $_POST['holiday_id'];
        $name = trim($_POST['holiday_name']);
        $date = $_POST['holiday_date'];
        $type = $_POST['holiday_type'];
        $description = trim($_POST['holiday_description']);
        $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
        $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;

        try {
            $stmt = $pdo->prepare("UPDATE holidays SET name = ?, date = ?, type = ?, description = ?, is_recurring = ?, branch_id = ? WHERE id = ?");
            $stmt->execute([$name, $date, $type, $description, $is_recurring, $branch_id, $id]);
            $_SESSION['success'] = "Holiday updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating holiday: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_holiday'])) {
        // Delete holiday
        $id = $_POST['holiday_id'];

        try {
            $stmt = $pdo->prepare("DELETE FROM holidays WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Holiday deleted successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting holiday: " . $e->getMessage();
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: holidays.php");
    exit();
}

// Fetch holidays for the current year
$currentYear = date('Y');
$stmt = $pdo->prepare("SELECT h.*, b.name as branch_name 
                       FROM holidays h 
                       LEFT JOIN branches b ON h.branch_id = b.id 
                       WHERE YEAR(h.date) = ? OR h.is_recurring = 1
                       ORDER BY h.date ASC");
$stmt->execute([$currentYear]);
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch branches for dropdown
$branchStmt = $pdo->query("SELECT id, name FROM branches ORDER BY name");
$branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

// Include the header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Holiday Management</h1>
            <p class="text-muted mb-0">Manage company holidays and special days</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
            <i class="fas fa-plus me-2"></i> Add Holiday
        </button>
    </div>

    <!-- Year Filter and Stats -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h3 class="text-primary mb-1"><?php echo count($holidays); ?></h3>
                                <small class="text-muted">Total Holidays</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h3 class="text-success mb-1"><?php echo count(array_filter($holidays, function($h) { return $h['type'] === 'national'; })); ?></h3>
                                <small class="text-muted">National Holidays</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h3 class="text-info mb-1"><?php echo count(array_filter($holidays, function($h) { return $h['type'] === 'company'; })); ?></h3>
                                <small class="text-muted">Company Holidays</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h3 class="text-warning mb-1"><?php echo count(array_filter($holidays, function($h) { return $h['type'] === 'optional'; })); ?></h3>
                                <small class="text-muted">Optional Holidays</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <label for="yearFilter" class="form-label">Filter by Year</label>
                    <select class="form-select" id="yearFilter" onchange="filterByYear(this.value)">
                        <?php for ($year = date('Y') - 2; $year <= date('Y') + 2; $year++): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($year == $currentYear) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Holidays Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="holidays-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Holiday Name</th>
                            <th class="text-center">Date</th>
                            <th class="text-center">Day</th>
                            <th class="text-center">Type</th>
                            <th>Branch</th>
                            <th class="text-center">Recurring</th>
                            <th>Description</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($holidays as $holiday): ?>
                        <tr>
                            <td class="align-middle">
                                <strong><?php echo htmlspecialchars($holiday['name']); ?></strong>
                            </td>
                            <td class="text-center align-middle">
                                <?php echo date('M d, Y', strtotime($holiday['date'])); ?>
                            </td>
                            <td class="text-center align-middle">
                                <?php echo date('l', strtotime($holiday['date'])); ?>
                            </td>
                            <td class="text-center align-middle">
                                <?php
                                $badgeClass = '';
                                switch ($holiday['type']) {
                                    case 'national':
                                        $badgeClass = 'bg-success';
                                        break;
                                    case 'company':
                                        $badgeClass = 'bg-info';
                                        break;
                                    case 'optional':
                                        $badgeClass = 'bg-warning';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <?php echo ucfirst($holiday['type']); ?>
                                </span>
                            </td>
                            <td class="align-middle">
                                <?php echo $holiday['branch_name'] ? htmlspecialchars($holiday['branch_name']) : '<span class="text-muted">All Branches</span>'; ?>
                            </td>
                            <td class="text-center align-middle">
                                <?php if ($holiday['is_recurring']): ?>
                                    <i class="fas fa-check-circle text-success" title="Recurring"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-muted" title="One-time"></i>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle">
                                <?php echo $holiday['description'] ? htmlspecialchars(substr($holiday['description'], 0, 50)) . '...' : '<span class="text-muted">No description</span>'; ?>
                            </td>
                            <td class="text-center align-middle">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button class="dropdown-item edit-holiday" 
                                                    data-id="<?php echo $holiday['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($holiday['name']); ?>"
                                                    data-date="<?php echo $holiday['date']; ?>"
                                                    data-type="<?php echo $holiday['type']; ?>"
                                                    data-description="<?php echo htmlspecialchars($holiday['description']); ?>"
                                                    data-recurring="<?php echo $holiday['is_recurring']; ?>"
                                                    data-branch="<?php echo $holiday['branch_id']; ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editHolidayModal">
                                                <i class="fas fa-edit me-2"></i> Edit
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger delete-holiday" 
                                                    data-id="<?php echo $holiday['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($holiday['name']); ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteHolidayModal">
                                                <i class="fas fa-trash me-2"></i> Delete
                                            </button>
                                        </li>
                                    </ul>
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

<!-- Add Holiday Modal -->
<div class="modal fade" id="addHolidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="holiday_name" class="form-label">Holiday Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="holiday_name" name="holiday_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="holiday_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="holiday_date" name="holiday_date" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="holiday_type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="holiday_type" name="holiday_type" required>
                                <option value="company">Company Holiday</option>
                                <option value="national">National Holiday</option>
                                <option value="optional">Optional Holiday</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="branch_id" class="form-label">Branch</label>
                            <select class="form-select" id="branch_id" name="branch_id">
                                <option value="">All Branches</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="holiday_description" class="form-label">Description</label>
                        <textarea class="form-control" id="holiday_description" name="holiday_description" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_recurring" name="is_recurring">
                        <label class="form-check-label" for="is_recurring">
                            Recurring Holiday (occurs every year)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_holiday" class="btn btn-primary">Add Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Holiday Modal -->
<div class="modal fade" id="editHolidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit_holiday_id" name="holiday_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_holiday_name" class="form-label">Holiday Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_holiday_name" name="holiday_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_holiday_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_holiday_date" name="holiday_date" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_holiday_type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_holiday_type" name="holiday_type" required>
                                <option value="company">Company Holiday</option>
                                <option value="national">National Holiday</option>
                                <option value="optional">Optional Holiday</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_branch_id" class="form-label">Branch</label>
                            <select class="form-select" id="edit_branch_id" name="branch_id">
                                <option value="">All Branches</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_holiday_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_holiday_description" name="holiday_description" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="edit_is_recurring" name="is_recurring">
                        <label class="form-check-label" for="edit_is_recurring">
                            Recurring Holiday (occurs every year)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_holiday" class="btn btn-primary">Update Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Holiday Modal -->
<div class="modal fade" id="deleteHolidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete_holiday_id" name="holiday_id">
                    <p>Are you sure you want to delete the holiday "<strong id="delete_holiday_name"></strong>"?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_holiday" class="btn btn-danger">Delete Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include the main footer -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- DataTables CSS & JS -->
<link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="plugins/datatables/jquery.dataTables.min.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<!-- SweetAlert2 Flash Messages -->
<script>
<?php if (isset($_SESSION['success'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: <?php echo json_encode($_SESSION['success']); ?>,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    <?php unset($_SESSION['success']); ?>
<?php elseif (isset($_SESSION['error'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: <?php echo json_encode($_SESSION['error']); ?>,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true
    });
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>

<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    const holidaysTable = new DataTable('#holidays-table', {
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        order: [[1, 'asc']], // Sort by date by default
        pageLength: 10,
        language: {
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            }
        }
    });

    // Edit Holiday Modal Handler
    const editHolidayModal = document.getElementById('editHolidayModal');
    if (editHolidayModal) {
        editHolidayModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            document.getElementById('edit_holiday_id').value = button.getAttribute('data-id');
            document.getElementById('edit_holiday_name').value = button.getAttribute('data-name');
            document.getElementById('edit_holiday_date').value = button.getAttribute('data-date');
            document.getElementById('edit_holiday_type').value = button.getAttribute('data-type');
            document.getElementById('edit_holiday_description').value = button.getAttribute('data-description');
            document.getElementById('edit_is_recurring').checked = button.getAttribute('data-recurring') == '1';
            document.getElementById('edit_branch_id').value = button.getAttribute('data-branch') || '';
        });
    }

    // Delete Holiday Modal Handler
    const deleteHolidayModal = document.getElementById('deleteHolidayModal');
    if (deleteHolidayModal) {
        deleteHolidayModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            document.getElementById('delete_holiday_id').value = button.getAttribute('data-id');
            document.getElementById('delete_holiday_name').textContent = button.getAttribute('data-name');
        });
    }
});

function filterByYear(year) {
    window.location.href = 'holidays.php?year=' + year;
}
</script>

<style>
.stat-item {
    padding: 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.stat-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Table header styling - light and dark mode compatible */
.table th {
    font-weight: 600;
    border-top: none;
    background-color: var(--bs-gray-100);
    color: var(--bs-gray-800);
}

/* Dark mode table header */
body.dark-mode .table th {
    background-color: var(--bs-gray-800);
    color: var(--bs-gray-100);
    border-color: var(--bs-gray-700);
}

/* Dark mode table styling */
body.dark-mode .table {
    color: var(--bs-gray-100);
}

body.dark-mode .table td {
    border-color: var(--bs-gray-700);
}

body.dark-mode .table-hover tbody tr:hover {
    background-color: var(--bs-gray-700);
}

.badge {
    font-size: 0.75rem;
}
</style>

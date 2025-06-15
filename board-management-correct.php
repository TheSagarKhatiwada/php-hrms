<?php
/**
 * Board of Directors Management Page
 * Manage board members as separate governance body (NOT employees)
 */
$page = 'board-management';
require_once 'includes/header.php';
require_once 'includes/db_connection.php';
require_once 'includes/utilities.php';
require_once 'includes/csrf_protection.php';

// Check if user is logged in and has admin privileges
if (!is_logged_in() || !is_admin()) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: index.php');
    exit();
}

$csrf_token = generate_csrf_token();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect_with_message('board-management.php', 'error', 'Invalid security token. Please try again.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_board_member') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $position_level = $_POST['position_level'] ?? 3;
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $appointment_date = $_POST['appointment_date'] ?? '';
        $is_independent = isset($_POST['is_independent']) ? 1 : 0;
        $expertise_areas = trim($_POST['expertise_areas'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($title)) {
            $_SESSION['error'] = 'First name, last name, and title are required.';
        } else {
            try {
                $sql = "INSERT INTO board_of_directors (first_name, last_name, title, position_level, email, phone, appointment_date, is_independent, expertise_areas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$first_name, $last_name, $title, $position_level, $email, $phone, $appointment_date ?: null, $is_independent, $expertise_areas]);

                $_SESSION['success'] = 'Board member added successfully.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error adding board member: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'update_board_member') {
        $id = $_POST['id'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $position_level = $_POST['position_level'] ?? 3;
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $is_independent = isset($_POST['is_independent']) ? 1 : 0;
        $expertise_areas = trim($_POST['expertise_areas'] ?? '');

        if (empty($id) || empty($first_name) || empty($last_name) || empty($title)) {
            $_SESSION['error'] = 'All required fields must be filled.';
        } else {
            try {
                $sql = "UPDATE board_of_directors SET first_name=?, last_name=?, title=?, position_level=?, email=?, phone=?, is_independent=?, expertise_areas=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$first_name, $last_name, $title, $position_level, $email, $phone, $is_independent, $expertise_areas, $id]);

                $_SESSION['success'] = 'Board member updated successfully.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error updating board member: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'deactivate_board_member') {
        $id = $_POST['id'] ?? '';

        if (empty($id)) {
            $_SESSION['error'] = 'Invalid board member selection.';
        } else {
            try {
                $sql = "UPDATE board_of_directors SET is_active = 0 WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);

                $_SESSION['success'] = 'Board member deactivated successfully.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error deactivating board member: ' . $e->getMessage();
            }
        }
    }

    header('Location: board-management.php');
    exit();
}

// Get board members
try {
    $board_members_sql = "SELECT * FROM board_of_directors WHERE is_active = 1 ORDER BY position_level, title, last_name";
    $board_members_stmt = $pdo->query($board_members_sql);
    $board_members = $board_members_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $board_members = [];
}

// Get board statistics
try {
    $stats = [];
    $stats['total_members'] = count($board_members);
    $stats['independent_directors'] = count(array_filter($board_members, function($m) { return $m['is_independent'] == 1; }));
    $stats['executive_directors'] = count(array_filter($board_members, function($m) { return $m['is_independent'] == 0; }));
    $stats['leadership'] = count(array_filter($board_members, function($m) { return $m['position_level'] <= 2; }));
} catch (Exception $e) {
    $stats = ['total_members' => 0, 'independent_directors' => 0, 'executive_directors' => 0, 'leadership' => 0];
}
?>

<div class="main-content">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-crown text-warning me-2"></i>
                            Board of Directors
                        </h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBoardMemberModal">
                            <i class="fas fa-plus me-1"></i>
                            Add Board Member
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Board Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card border-warning">
                                    <div class="card-body text-center">
                                        <h3 class="text-warning"><?php echo $stats['total_members']; ?></h3>
                                        <p class="mb-0">Total Board Members</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <h3 class="text-info"><?php echo $stats['independent_directors']; ?></h3>
                                        <p class="mb-0">Independent Directors</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <h3 class="text-success"><?php echo $stats['executive_directors']; ?></h3>
                                        <p class="mb-0">Executive Directors</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary"><?php echo $stats['leadership']; ?></h3>
                                        <p class="mb-0">Board Leadership</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> The Board of Directors is a separate governance body that oversees the company. 
                            These are not employees - they are external directors who provide strategic oversight and governance.
                        </div>

                        <!-- Current Board Members -->
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="mb-3">
                                    <i class="fas fa-users text-warning me-2"></i>
                                    Current Board of Directors
                                </h5>
                                
                                <?php if (empty($board_members)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-crown fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No board members added yet.</p>
                                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addBoardMemberModal">
                                            Add First Board Member
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($board_members as $member): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="card h-100 <?php echo $member['position_level'] <= 2 ? 'border-warning' : 'border-secondary'; ?>">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="card-title mb-0">
                                                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                                            </h6>
                                                            <?php if ($member['position_level'] <= 2): ?>
                                                                <span class="badge bg-warning text-dark">
                                                                    <i class="fas fa-crown"></i> Leadership
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <p class="text-primary mb-1">
                                                            <strong><?php echo htmlspecialchars($member['title']); ?></strong>
                                                        </p>
                                                        
                                                        <p class="text-muted small mb-2">
                                                            <?php echo $member['is_independent'] ? 'Independent Director' : 'Executive Director'; ?>
                                                        </p>
                                                        
                                                        <?php if (!empty($member['expertise_areas'])): ?>
                                                            <p class="small mb-2">
                                                                <strong>Expertise:</strong> <?php echo htmlspecialchars($member['expertise_areas']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($member['email'])): ?>
                                                            <p class="small mb-1">
                                                                <i class="fas fa-envelope me-1"></i>
                                                                <?php echo htmlspecialchars($member['email']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($member['appointment_date'])): ?>
                                                            <p class="small text-muted mb-2">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                Appointed: <?php echo date('M d, Y', strtotime($member['appointment_date'])); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <div class="mt-auto">
                                                            <button class="btn btn-sm btn-outline-primary me-1" 
                                                                    onclick="editBoardMember(<?php echo htmlspecialchars(json_encode($member)); ?>)">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            
                                                            <form method="POST" style="display: inline;" 
                                                                  onsubmit="return confirm('Are you sure you want to deactivate this board member?');">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                                <input type="hidden" name="action" value="deactivate_board_member">
                                                                <input type="hidden" name="id" value="<?php echo $member['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-user-slash"></i> Deactivate
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Board Member Modal -->
<div class="modal fade" id="addBoardMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="boardMemberForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Board Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add_board_member" id="formAction">
                    <input type="hidden" name="id" id="memberId">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" name="first_name" id="first_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" name="last_name" id="last_name" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Board Title *</label>
                                <select name="title" id="title" class="form-select" required>
                                    <option value="">Choose a title...</option>
                                    <option value="Chairman of the Board">Chairman of the Board</option>
                                    <option value="Vice Chairman">Vice Chairman</option>
                                    <option value="Board Member">Board Member</option>
                                    <option value="Independent Director">Independent Director</option>
                                    <option value="Executive Director">Executive Director</option>
                                    <option value="Lead Independent Director">Lead Independent Director</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="position_level" class="form-label">Position Level</label>
                                <select name="position_level" id="position_level" class="form-select">
                                    <option value="1">1 - Chairman</option>
                                    <option value="2">2 - Vice Chairman</option>
                                    <option value="3" selected>3 - Board Member</option>
                                    <option value="4">4 - Executive Director</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" name="phone" id="phone" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="appointment_date" class="form-label">Appointment Date</label>
                                <input type="date" name="appointment_date" id="appointment_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 pt-4">
                                <div class="form-check">
                                    <input type="checkbox" name="is_independent" id="is_independent" class="form-check-input">
                                    <label for="is_independent" class="form-check-label">
                                        Independent Director
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="expertise_areas" class="form-label">Expertise Areas</label>
                        <textarea name="expertise_areas" id="expertise_areas" class="form-control" rows="2" 
                                  placeholder="e.g., Finance, Technology, Legal, Marketing, Operations"></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Remember:</strong> Board members are external governance officials, not company employees.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Board Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBoardMember(member) {
    document.getElementById('formAction').value = 'update_board_member';
    document.getElementById('memberId').value = member.id;
    document.getElementById('first_name').value = member.first_name;
    document.getElementById('last_name').value = member.last_name;
    document.getElementById('title').value = member.title;
    document.getElementById('position_level').value = member.position_level;
    document.getElementById('email').value = member.email || '';
    document.getElementById('phone').value = member.phone || '';
    document.getElementById('appointment_date').value = member.appointment_date || '';
    document.getElementById('is_independent').checked = member.is_independent == 1;
    document.getElementById('expertise_areas').value = member.expertise_areas || '';
    
    document.querySelector('#addBoardMemberModal .modal-title').textContent = 'Edit Board Member';
    
    new bootstrap.Modal(document.getElementById('addBoardMemberModal')).show();
}

// Reset modal when closed
document.getElementById('addBoardMemberModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('boardMemberForm').reset();
    document.getElementById('formAction').value = 'add_board_member';
    document.getElementById('memberId').value = '';
    document.querySelector('#addBoardMemberModal .modal-title').textContent = 'Add Board Member';
});
</script>

<?php require_once 'includes/footer.php'; ?>

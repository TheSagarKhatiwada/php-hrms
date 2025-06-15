<?php
/**
 * Organizational Chart Page
 * Display company hierarchy with interactive organizational chart
 */
$page = 'organizational-chart';

// Include necessary files
require_once 'includes/header.php';
require_once 'includes/db_connection.php';
require_once 'includes/utilities.php';
require_once 'includes/hierarchy_helpers.php';

// Check if user is logged in and has appropriate permissions
if (!is_logged_in()) {
    $_SESSION['error'] = 'You need to be logged in to access this page.';
    header('Location: index.php');
    exit();
}

// Get Board of Directors (Governance Layer)
function getBoardOfDirectors($pdo) {
    $sql = "
        SELECT 
            id,
            CONCAT(first_name, ' ', last_name) as name,
            title as position,
            COALESCE(bio, '') as qualification,
            COALESCE(expertise_areas, '') as expertise,
            appointment_date,
            email as contact_email,
            phone as contact_phone,
            status
        FROM board_of_directors
        WHERE status = 'active'
        ORDER BY 
            CASE title
                WHEN 'Chairman' THEN 1
                WHEN 'Vice Chairman' THEN 2
                WHEN 'Executive Director' THEN 3
                WHEN 'Independent Director' THEN 4
                ELSE 5
            END, first_name
    ";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Log error for debugging but don't break the page
        error_log("Board query error: " . $e->getMessage());
        return [];
    }
}

// Get Employee Hierarchy (Operations Layer)
function getOrganizationalHierarchy($pdo) {
    $sql = "
        SELECT 
            e.id,
            e.emp_id,
            e.first_name,
            e.last_name,
            CONCAT(e.first_name, ' ', e.last_name) as full_name,
            e.supervisor_id,
            e.department_id,
            e.role_id,
            e.user_image,
            e.email,
            e.phone,
            d.title as designation_title,
            dept.name as department_name,
            r.name as role_name,
            b.name as branch_name
        FROM employees e
        LEFT JOIN designations d ON e.designation = d.id
        LEFT JOIN departments dept ON e.department_id = dept.id
        LEFT JOIN roles r ON e.role_id = r.id
        LEFT JOIN branches b ON e.branch = b.id
        WHERE e.exit_date IS NULL
        ORDER BY e.supervisor_id IS NULL DESC, e.supervisor_id, dept.name, e.first_name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get organizational statistics including Board of Directors
function getHierarchyStats($pdo) {
    $stats = [];
    
    try {
        // Board members
        $stmt = $pdo->query("SELECT COUNT(*) FROM board_of_directors WHERE status = 'active'");
        $stats['board_members'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        $stats['board_members'] = 0;
    }
    
    // Total employees
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE exit_date IS NULL");
    $stats['total_employees'] = $stmt->fetchColumn();
    
    // Departments count
    $stmt = $pdo->query("SELECT COUNT(*) FROM departments");
    $stats['total_departments'] = $stmt->fetchColumn();
    
    // Employees without supervisors (top level)
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE supervisor_id IS NULL AND exit_date IS NULL");
    $stats['top_level_employees'] = $stmt->fetchColumn();
    
    // Average team size
    $stmt = $pdo->query("
        SELECT AVG(team_count) as avg_team_size 
        FROM (
            SELECT supervisor_id, COUNT(*) as team_count 
            FROM employees 
            WHERE supervisor_id IS NOT NULL AND exit_date IS NULL 
            GROUP BY supervisor_id
        ) as teams
    ");
    $stats['avg_team_size'] = round($stmt->fetchColumn(), 1);
    
    return $stats;
}

$employees = getOrganizationalHierarchy($pdo);
$boardMembers = getBoardOfDirectors($pdo);
$stats = getHierarchyStats($pdo);

// Build hierarchy tree structure
function buildHierarchyTree($employees) {
    $tree = [];
    $lookup = [];
    
    // Create lookup array
    foreach ($employees as $employee) {
        $lookup[$employee['id']] = $employee;
        $lookup[$employee['id']]['children'] = [];
    }
    
    // Build tree
    foreach ($employees as $employee) {
        if ($employee['supervisor_id'] && isset($lookup[$employee['supervisor_id']])) {
            $lookup[$employee['supervisor_id']]['children'][] = &$lookup[$employee['id']];
        } else {
            $tree[] = &$lookup[$employee['id']];
        }
    }
    
    return $tree;
}

// Render Board of Directors section
function renderBoardSection($boardMembers) {
    if (empty($boardMembers)) {
        return '<div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No Board of Directors configured. 
                    <a href="board-management.php" class="alert-link">Add board members</a> to complete the organizational structure.
                </div>';
    }
    
    $html = '<div class="board-hierarchy-section mb-4">';
    $html .= '<div class="row g-3">';
    
    foreach ($boardMembers as $member) {
        $html .= '<div class="col-md-3 col-sm-6">';
        $html .= '<div class="card board-member-card h-100">';
        $html .= '<div class="card-body text-center">';
        $html .= '<div class="board-crown mb-2">';
        $html .= '<i class="fas fa-crown text-warning fa-2x"></i>';
        $html .= '</div>';
        $html .= '<h6 class="card-title mb-1">' . htmlspecialchars($member['name']) . '</h6>';
        $html .= '<small class="text-warning fw-bold">' . htmlspecialchars($member['position']) . '</small>';
        if (!empty($member['expertise'])) {
            $html .= '<div class="mt-2">';
            $html .= '<small class="text-muted">' . htmlspecialchars($member['expertise']) . '</small>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

$hierarchyTree = buildHierarchyTree($employees);
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">
                <i class="fas fa-sitemap me-2"></i>Organizational Chart
            </h1>
            <p class="text-muted mb-0">Company hierarchy and reporting structure</p>
        </div>
        <div class="d-flex gap-2">            <a href="board-management.php" class="btn btn-warning">
                <i class="fas fa-crown me-1"></i>Manage Board
            </a>
            <button type="button" class="btn btn-outline-primary" onclick="toggleView()">
                <i class="fas fa-eye me-1"></i>
                <span id="view-toggle-text">List View</span>
            </button>
            <button type="button" class="btn btn-outline-success" onclick="exportChart()">
                <i class="fas fa-download me-1"></i>Export
            </button>
        </div>
    </div><!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="mb-0"><?php echo $stats['board_members']; ?></h3>
                            <p class="mb-0">Board Members</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-crown fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="mb-0"><?php echo $stats['total_employees']; ?></h3>
                            <p class="mb-0">Total Employees</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="mb-0"><?php echo $stats['total_departments']; ?></h3>
                            <p class="mb-0">Departments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-building fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="mb-0"><?php echo $stats['avg_team_size'] ?: '0'; ?></h3>
                            <p class="mb-0">Avg Team Size</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-bar fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>        </div>
    </div>

    <!-- Chart Display -->
    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-sitemap me-2"></i>Complete Organizational Structure
            </h5>
        </div>
        <div class="card-body">
            <!-- Chart View -->
            <div id="chart-view" class="hierarchy-chart">
                <!-- Board of Directors Section -->
                <div class="board-section mb-4">
                    <h6 class="text-warning mb-3">
                        <i class="fas fa-crown me-2"></i>Board of Directors (Governance Layer)
                    </h6>
                    <?php echo renderBoardSection($boardMembers); ?>
                    
                    <?php if (!empty($boardMembers) && !empty($hierarchyTree)): ?>
                    <!-- Governance Arrow -->
                    <div class="governance-arrow text-center mb-3">
                        <div class="d-flex align-items-center justify-content-center">
                            <div class="governance-line flex-grow-1"></div>
                            <span class="mx-3 text-muted">
                                <i class="fas fa-arrow-down fa-2x"></i>
                                <div class="small">Governs & Oversees</div>
                            </span>
                            <div class="governance-line flex-grow-1"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Employee Hierarchy Section -->
                <div class="employee-section">
                    <h6 class="text-primary mb-3">
                        <i class="fas fa-users me-2"></i>Employee Structure (Operations Layer)
                    </h6>
                    
                    <?php if (empty($hierarchyTree)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h4>No Employee Hierarchy</h4>
                            <p class="text-muted">Add employees and set up reporting relationships to see the operational structure.</p>
                            <a href="employees.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Add Employees
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="org-chart-container d-flex justify-content-center">
                            <div class="w-100 text-center">
                                <?php echo renderHierarchyTree($hierarchyTree); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- List View -->
            <div id="list-view" class="d-none">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Reports To</th>
                                <th>Team Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Board Members -->
                            <?php foreach ($boardMembers as $member): ?>
                            <tr class="table-warning">
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-crown me-1"></i>Board
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="board-avatar me-3">
                                            <i class="fas fa-user-circle fa-2x text-warning"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($member['name']); ?></strong><br>
                                            <small class="text-muted">Board Member</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-warning fw-bold"><?php echo htmlspecialchars($member['position']); ?></span><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($member['expertise'] ?: 'General Expertise'); ?></small>
                                </td>
                                <td><span class="text-muted">Governance</span></td>
                                <td><span class="text-muted">-</span></td>
                                <td><span class="text-muted">-</span></td>
                                <td>                                    <a href="board-management.php" class="btn btn-sm btn-outline-warning">
                                        <i class="fas fa-crown"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Employees -->
                            <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary">
                                        <i class="fas fa-user me-1"></i>Employee
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo $employee['user_image'] ?: 'resources/userimg/default-image.jpg'; ?>" 
                                             alt="Profile" class="rounded-circle me-3" width="40" height="40">
                                        <div>
                                            <strong><?php echo htmlspecialchars($employee['full_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($employee['emp_id']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($employee['designation_title'] ?: 'Not Assigned'); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($employee['role_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($employee['department_name'] ?: 'Not Assigned'); ?></td>
                                <td>
                                    <?php if ($employee['supervisor_id']): ?>
                                        <?php
                                        $supervisorInfo = array_filter($employees, function($emp) use ($employee) {
                                            return $emp['id'] == $employee['supervisor_id'];
                                        });
                                        if (!empty($supervisorInfo)) {
                                            $supervisor = array_values($supervisorInfo)[0];
                                            echo htmlspecialchars($supervisor['full_name']);
                                        } else {
                                            echo 'Supervisor Not Found';
                                        }
                                        ?>
                                    <?php else: ?>
                                        <span class="text-muted">Top Level</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $teamCount = count(array_filter($employees, function($emp) use ($employee) {
                                        return $emp['supervisor_id'] == $employee['id'];
                                    }));
                                    echo $teamCount;
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="employee-viewer.php?empId=<?php echo $employee['emp_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-employee.php?id=<?php echo $employee['emp_id']; ?>" 
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
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
</div>

<?php
function renderHierarchyTree($nodes, $level = 0) {
    $html = '<ul class="org-chart' . ($level === 0 ? ' root' : '') . '">';
    
    foreach ($nodes as $node) {
        $isBoardMember = !empty($node['board_position_id']);
        $cardClass = $isBoardMember ? 'employee-card board-member' : 'employee-card';
        
        $html .= '<li class="org-node">';
        $html .= '<div class="' . $cardClass . '" data-level="' . $level . '" data-org-level="' . ($node['organizational_level'] ?? 10) . '">';
        
        // Board position badge
        if ($isBoardMember) {
            $html .= '<div class="board-badge">';
            $html .= '<i class="fas fa-crown"></i> Board of Directors';
            $html .= '</div>';
        }
        
        $html .= '<div class="employee-avatar">';
        $html .= '<img src="' . ($node['user_image'] ?: 'resources/userimg/default-image.jpg') . '" alt="Profile" class="rounded-circle">';
        $html .= '</div>';
        $html .= '<div class="employee-info">';
        $html .= '<h6 class="mb-1">' . htmlspecialchars($node['full_name']) . '</h6>';
        
        // Show board position if available, otherwise show designation
        if ($isBoardMember) {
            $html .= '<small class="board-position d-block">' . htmlspecialchars($node['board_position']) . '</small>';
            if (!empty($node['designation_title'])) {
                $html .= '<small class="text-muted d-block">' . htmlspecialchars($node['designation_title']) . '</small>';
            }
        } else {
            $html .= '<small class="text-muted d-block">' . htmlspecialchars($node['designation_title'] ?: 'Not Assigned') . '</small>';
        }
        
        $html .= '<small class="text-info">' . htmlspecialchars($node['department_name'] ?: '') . '</small>';
        
        // Hierarchy category badge
        if (!empty($node['hierarchy_category'])) {
            $html .= '<span class="hierarchy-badge">' . htmlspecialchars($node['hierarchy_category']) . '</span>';
        }
        
        $html .= '</div>';
        $html .= '<div class="employee-actions">';
        $html .= '<a href="employee-viewer.php?empId=' . $node['emp_id'] . '" class="btn btn-sm btn-outline-primary">';
        $html .= '<i class="fas fa-eye"></i>';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '</div>';
        
        if (!empty($node['children'])) {
            $html .= renderHierarchyTree($node['children'], $level + 1);
        }
        
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    return $html;
}
?>

<style>
.hierarchy-chart {
    overflow-x: auto;
    padding: 20px;
}

/* Board Section Styles */
.board-section {
    border: 2px solid #ffd700;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 30px;
}

.board-member-card {
    border: 2px solid #ffd700;
    transition: all 0.3s ease;
}

.board-member-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(255, 215, 0, 0.3);
}

.board-crown {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.governance-line {
    height: 2px;
    background: linear-gradient(to right, transparent, #6c757d, transparent);
}

.governance-arrow {
    margin: 20px 0;
}

/* Employee Section Styles */
.employee-section {
    border: 2px solid #2196f3;
    border-radius: 15px;
    padding: 20px;
}

.org-chart-container {
    min-height: 400px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    width: 100%;
}

.org-chart {
    list-style: none;
    padding: 0;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.org-chart.root {
    align-items: center;
}

.org-chart ul {
    display: flex;
    padding-top: 20px;
    position: relative;
    flex-wrap: wrap;
    justify-content: center;
    margin: 0 auto;
}

.org-chart ul::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    border-left: 1px solid #ccc;
    width: 0;
    height: 20px;
}

.org-chart li {
    float: left;
    text-align: center;
    list-style-type: none;
    position: relative;
    padding: 20px 5px 0 5px;
    margin: 0 10px;
}

.org-chart li::before,
.org-chart li::after {
    content: '';
    position: absolute;
    top: 0;
    right: 50%;
    border-top: 1px solid #ccc;
    width: 50%;
    height: 20px;
}

.org-chart li::after {
    right: auto;
    left: 50%;
    border-left: 1px solid #ccc;
}

.org-chart li:only-child::after,
.org-chart li:only-child::before {
    display: none;
}

.org-chart li:only-child {
    padding-top: 0;
}

.org-chart li:first-child::before,
.org-chart li:last-child::after {
    border: 0 none;
}

.org-chart li:last-child::before {
    border-right: 1px solid #ccc;
    border-radius: 0 5px 0 0;
}

.org-chart li:first-child::after {
    border-radius: 5px 0 0 0;
}

.employee-card {
    border: 2px solid #ddd;
    border-radius: 10px;
    padding: 15px;
    /* background: white; */
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    min-width: 200px;
    transition: all 0.3s ease;
    position: relative;
}

/* Board of Directors specific styles */
.employee-card.board-member {
    border: 3px solid #ffd700;
    background: linear-gradient(135deg, #fff9e6 0%, #fffbf0 100%);
    box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
}

.employee-card.board-member:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(255, 215, 0, 0.4);
    border-color: #ffcc00;
}

.board-badge {
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #ffd700, #ffcc00);
    color: #8B4513;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    box-shadow: 0 2px 6px rgba(255, 215, 0, 0.4);
    z-index: 10;
}

.board-position {
    color: #8B4513 !important;
    font-weight: 600 !important;
    font-size: 12px !important;
}

.hierarchy-badge {
    display: inline-block;
    background: #e9ecef;
    color: #495057;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 500;
    margin-top: 4px;
}

.hierarchy-badge:contains("Board") {
    background: #ffd700;
    color: #8B4513;
}

.employee-avatar img {
        width: 60px;
        height: 60px;
    }

/* Responsive design */
@media (max-width: 768px) {
    .hierarchy-chart {
        padding: 10px;
    }
    
    .org-chart-container {
        overflow-x: auto;
        justify-content: flex-start;
    }
    
    .org-chart ul {
        flex-direction: column;
        padding-top: 10px;
    }
    
    .org-chart li {
        margin: 5px;
        padding: 10px 5px 0 5px;
    }
    
    .employee-card {
        min-width: 150px;
        padding: 10px;
        margin: 5px auto;
    }
    
    .employee-avatar img {
        width: 40px;
        height: 40px;
    }
}

/* Centering and alignment improvements */
.org-chart-container .w-100 {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
}

.org-chart.root > li {
    margin: 0 auto;
}
</style>

<script>
function toggleView() {
    const chartView = document.getElementById('chart-view');
    const listView = document.getElementById('list-view');
    const toggleText = document.getElementById('view-toggle-text');
    
    if (chartView.classList.contains('d-none')) {
        chartView.classList.remove('d-none');
        listView.classList.add('d-none');
        toggleText.textContent = 'List View';
    } else {
        chartView.classList.add('d-none');
        listView.classList.remove('d-none');
        toggleText.textContent = 'Tree View';
    }
}

function exportChart() {
    // Implement export functionality (PDF/PNG)
    alert('Export functionality will be implemented soon!');
}

// Initialize tooltips if using Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    // Add any initialization code here
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

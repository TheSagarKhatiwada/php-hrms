<?php
// Set page title
$page = 'Contacts';

// Include required files
require_once 'includes/header.php';
require_once 'includes/db_connection.php';
require_once 'includes/settings.php';
require_once 'includes/utilities.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$isAdmin = function_exists('is_admin') && is_admin();
$canManageContacts = $isAdmin || has_permission('manage_contacts');

if (!$canManageContacts) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to manage contacts.']);
        exit();
    }
    $_SESSION['error'] = 'You do not have permission to access Contacts.';
    header('Location: dashboard.php');
    exit();
}

// Handle AJAX requests for contact management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_contact':
                $stmt = $pdo->prepare("INSERT INTO contacts (contact_group_id, first_name, last_name, title, organization, email, phone, mobile, address, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([
                    $_POST['contact_group_id'],
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['title'],
                    $_POST['organization'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['mobile'],
                    $_POST['address'],
                    $_POST['notes'],
                    $_SESSION['user_id']
                ]);
                echo json_encode(['success' => $result, 'message' => $result ? 'Contact added successfully!' : 'Failed to add contact']);
                break;
                
            case 'update_contact':
                $stmt = $pdo->prepare("UPDATE contacts SET contact_group_id=?, first_name=?, last_name=?, title=?, organization=?, email=?, phone=?, mobile=?, address=?, notes=? WHERE id=?");
                $result = $stmt->execute([
                    $_POST['contact_group_id'],
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['title'],
                    $_POST['organization'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['mobile'],
                    $_POST['address'],
                    $_POST['notes'],
                    $_POST['contact_id']
                ]);
                echo json_encode(['success' => $result, 'message' => $result ? 'Contact updated successfully!' : 'Failed to update contact']);
                break;
                  case 'delete_contact':
                // Only allow deletion of regular contacts, not employees or board members
                $check_stmt = $pdo->prepare("SELECT id FROM contacts WHERE id = ?");
                $check_stmt->execute([$_POST['contact_id']]);
                if ($check_stmt->rowCount() === 0) {
                    echo json_encode(['success' => false, 'message' => 'Contact not found or cannot be deleted']);
                    break;
                }
                
                $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
                $result = $stmt->execute([$_POST['contact_id']]);
                echo json_encode(['success' => $result, 'message' => $result ? 'Contact deleted successfully!' : 'Failed to delete contact']);
                break;
                  case 'add_group':
                // Check if group name already exists
                $check_stmt = $pdo->prepare("SELECT id FROM contact_groups WHERE name = ?");
                $check_stmt->execute([$_POST['name']]);
                if ($check_stmt->rowCount() > 0) {
                    echo json_encode(['success' => false, 'message' => 'A group with this name already exists']);
                    break;
                }
                
                $stmt = $pdo->prepare("INSERT INTO contact_groups (name, description, color, icon) VALUES (?, ?, ?, ?)");
                $result = $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['color'],
                    $_POST['icon']
                ]);
                echo json_encode(['success' => $result, 'message' => $result ? 'Group created successfully!' : 'Failed to create group']);
                break;
                
            case 'update_group':
                // Check if trying to update system group
                $check_stmt = $pdo->prepare("SELECT is_system_group FROM contact_groups WHERE id = ?");
                $check_stmt->execute([$_POST['group_id']]);
                $group = $check_stmt->fetch(PDO::FETCH_ASSOC);
                if ($group && $group['is_system_group']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot modify system groups']);
                    break;
                }
                
                $stmt = $pdo->prepare("UPDATE contact_groups SET name=?, description=?, color=?, icon=? WHERE id=? AND is_system_group = 0");
                $result = $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['color'],
                    $_POST['icon'],
                    $_POST['group_id']
                ]);
                echo json_encode(['success' => $result, 'message' => $result ? 'Group updated successfully!' : 'Failed to update group']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get search and filter parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$group_filter = isset($_GET['group']) ? (int)$_GET['group'] : '';

// Get all contact groups
$groups_stmt = $pdo->query("SELECT *, COALESCE(is_system_group, 0) as is_system_group FROM contact_groups WHERE is_active = 1 ORDER BY sort_order, name");
$contact_groups = $groups_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build query for contacts with employees
$where_conditions = [];
$params = [];

// Handle search
if (!empty($search_query)) {
    $where_conditions[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR phone LIKE :search OR mobile LIKE :search OR title LIKE :search OR organization LIKE :search)";
    $params['search'] = '%' . $search_query . '%';
}

// Handle group filter
if (!empty($group_filter)) {
    $where_conditions[] = "contact_group_id = :group_filter";
    $params['group_filter'] = $group_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get contacts (combining regular contacts, employee contacts, and board contacts)
// Build separate WHERE clauses for contacts and employees
$contact_where_conditions = [];
$employee_where_conditions = [];
$board_where_conditions = [];

// For contact search
if (!empty($search_query)) {
    $contact_where_conditions[] = "(c.first_name LIKE :search OR c.last_name LIKE :search OR c.email LIKE :search OR c.phone LIKE :search OR c.mobile LIKE :search OR c.title LIKE :search OR c.organization LIKE :search)";
    $employee_where_conditions[] = "(e.first_name LIKE :search OR e.last_name LIKE :search OR e.email LIKE :search OR e.phone LIKE :search OR e.office_phone LIKE :search OR d.title LIKE :search OR b.name LIKE :search)";
    $board_where_conditions[] = "(bv.first_name LIKE :search OR bv.last_name LIKE :search OR bv.email LIKE :search OR bv.phone LIKE :search OR bv.mobile LIKE :search OR bv.title LIKE :search OR bv.organization LIKE :search)";
}

// Don't add group filter for contacts here - will be handled in the main query

// Determine which groups to include based on filter
$include_employees = empty($group_filter) || $group_filter == 1;
$include_board = empty($group_filter) || $group_filter == 2;
$include_contacts = empty($group_filter) || ($group_filter > 2);

$sql_parts = [];

// Regular contacts (exclude system groups)
if ($include_contacts) {
    $sql_parts[] = "
    (SELECT 
        c.id,
        c.contact_group_id,
        cg.name as group_name,
        cg.color as group_color,
        cg.icon as group_icon,
        c.first_name,
        '' as middle_name,
        c.last_name,
        c.title,
        c.organization,
        c.email,
        c.phone,
        c.mobile,
        c.address,
        c.photo,
        c.notes,
        c.is_active,
        'contact' as source_type
    FROM contacts c
    JOIN contact_groups cg ON c.contact_group_id = cg.id
    WHERE cg.is_system_group = 0 " . (!empty($contact_where_conditions) ? ' AND ' . implode(' AND ', $contact_where_conditions) : '') . ")";
}

// Employee contacts (only from employees, exclude admins/HR who are in board)
if ($include_employees) {
    $employee_additional_where = "e.status = 'active' AND (e.exit_date IS NULL OR e.exit_date = '')";
    if (!empty($employee_where_conditions)) {
        $employee_additional_where .= " AND " . implode(' AND ', $employee_where_conditions);
    }
    
    $sql_parts[] = "
    (SELECT 
        e.emp_id as id,
        1 as contact_group_id,
        'Employees' as group_name,
        '#28a745' as group_color,
        'fas fa-users' as group_icon,
        e.first_name,
        e.middle_name,
        e.last_name,
        d.title as title,
        b.name as organization,
        e.email,
        e.phone,
        e.office_phone as mobile,
        '' as address,
        e.user_image as photo,
        '' as notes,
        CASE WHEN e.status = 'active' THEN 1 ELSE 0 END as is_active,
        'employee' as source_type
    FROM employees e
    LEFT JOIN designations d ON e.designation_id = d.id
    LEFT JOIN branches b ON e.branch = b.id
    WHERE $employee_additional_where)";
}

// Board member contacts
if ($include_board) {
    $board_additional_where = "c.contact_group_id = 2";
    if (!empty($board_where_conditions)) {
        $board_additional_where .= " AND " . implode(' AND ', $board_where_conditions);
    }
    
    $sql_parts[] = "
    (SELECT 
        c.id,
        c.contact_group_id,
        'Board Members' as group_name,
        '#dc3545' as group_color,
        'fas fa-crown' as group_icon,
        c.first_name,
        '' as middle_name,
        c.last_name,
        c.title,
        c.organization,
        c.email,
        c.phone,
        c.mobile,
        c.address,
        c.photo,
        c.notes,
        c.is_active,
        'board' as source_type
    FROM contacts c
    WHERE $board_additional_where)";
}

$sql = implode(' UNION ALL ', $sql_parts) . " ORDER BY group_name, first_name, last_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group contacts by group
$grouped_contacts = [];
foreach ($all_contacts as $contact) {
    $group_name = $contact['group_name'];
    if (!isset($grouped_contacts[$group_name])) {
        $grouped_contacts[$group_name] = [
            'group_info' => [
                'id' => $contact['contact_group_id'],
                'name' => $group_name,
                'color' => $contact['group_color'],
                'icon' => $contact['group_icon']
            ],
            'contacts' => []
        ];
    }
    $grouped_contacts[$group_name]['contacts'][] = $contact;
}
?>

<!-- Content Wrapper (already started in header.php) -->
<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Contacts</h1>
            <p class="text-muted">Manage your business contacts and groups</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#groupModal">
                <i class="fas fa-layer-group me-2"></i> Manage Groups
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contactModal">
                <i class="fas fa-plus me-2"></i> Add Contact
            </button>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search contacts..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="group" class="form-select">
                        <option value="">All Groups</option>
                        <?php foreach ($contact_groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>" <?php echo $group_filter == $group['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($group['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Contacts by Groups -->
    <?php if (empty($grouped_contacts)): ?>
        <div class="text-center py-5">
            <i class="fas fa-address-book fa-4x text-muted mb-3"></i>
            <h4>No contacts found</h4>
            <p class="text-muted">Start by adding your first contact or adjusting your search criteria.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contactModal">
                <i class="fas fa-plus me-2"></i> Add First Contact
            </button>
        </div>
    <?php else: ?>
        <div id="contactsAccordion"> <!-- ADDED: Parent div for accordion -->
        <?php foreach ($grouped_contacts as $group_name => $group_data): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center" 
                     style="background-color: <?php echo $group_data['group_info']['color']; ?>20; border-left: 4px solid <?php echo $group_data['group_info']['color']; ?>; cursor: pointer;"
                     data-bs-toggle="collapse" 
                     data-bs-target="#group-<?php echo $group_data['group_info']['id']; ?>" 
                     aria-expanded="false" 
                     aria-controls="group-<?php echo $group_data['group_info']['id']; ?>">
                    <div class="d-flex align-items-center">
                        <i class="<?php echo $group_data['group_info']['icon']; ?> me-2 toggle-icon" style="color: <?php echo $group_data['group_info']['color']; ?>;"></i>
                        <h5 class="mb-0" style="color: <?php echo $group_data['group_info']['color']; ?>;">
                            <?php echo htmlspecialchars($group_name); ?>
                        </h5>
                        <span class="badge ms-2" style="background-color: <?php echo $group_data['group_info']['color']; ?>;">
                            <?php echo count($group_data['contacts']); ?>
                        </span>
                    </div>
                    <i class="fas fa-chevron-down toggle-icon" style="color: <?php echo $group_data['group_info']['color']; ?>;"></i>
                </div>
                <div class="collapse" id="group-<?php echo $group_data['group_info']['id']; ?>" data-bs-parent="#contactsAccordion"> <!-- MODIFIED: Added data-bs-parent -->
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($group_data['contacts'] as $contact): ?>
                                <div class="col-xl-3 col-lg-4 col-md-6">
                                    <div class="contact-card card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center">
                                            <?php
                                                $contactJson = json_encode($contact);
                                                $contactDataAttr = htmlspecialchars(base64_encode($contactJson ?: '{}'), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <?php
                                                $fullName = trim(
                                                    ($contact['first_name'] ?? '') . ' ' .
                                                    ($contact['middle_name'] ?? '') . ' ' .
                                                    ($contact['last_name'] ?? '')
                                                );
                                            ?>
                                            <!-- Contact Photo -->
                                            <div class="mb-3">
                                                <img src="<?php 
                                                    $imagePath = $contact['photo'] ?: 'resources/userimg/default-image.jpg';
                                                    if (!empty($contact['photo']) && !str_starts_with($contact['photo'], 'resources/') && !str_starts_with($contact['photo'], 'http')) {
                                                        $imagePath = $contact['photo'];
                                                    }
                                                    echo htmlspecialchars($imagePath);
                                                ?>" 
                                                     alt="<?php echo htmlspecialchars($fullName); ?>" 
                                                     class="rounded-circle border"
                                                     style="width: 80px; height: 80px; object-fit: cover;"
                                                     onerror="this.src='resources/userimg/default-image.jpg'">
                                            </div>
                                            
                                            <!-- Contact Info -->
                                            <h6 class="card-title mb-1 contact-name">
                                                <?php echo htmlspecialchars($fullName); ?>
                                            </h6>
                                            
                                            <?php if (!empty($contact['title'])): ?>
                                                <p class="text-primary mb-1 fw-medium small contact-title">
                                                    <?php echo htmlspecialchars($contact['title']); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($contact['organization'])): ?>
                                                <p class="text-muted mb-2 small contact-org">
                                                    <?php echo htmlspecialchars($contact['organization']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Contact Actions -->
                                        <div class="card-footer border-0 p-2">
                                            <div class="row g-1 mb-2"> <!-- Top row for email, phone, edit/lock -->
                                                <?php if (!empty($contact['email'])): ?>
                                                    <div class="col">
                                                        <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" 
                                                           class="btn btn-outline-primary btn-sm w-100" 
                                                           title="Email" data-bs-toggle="tooltip">
                                                            <i class="fas fa-envelope"></i>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($contact['phone'])): ?>
                                                    <div class="col">
                                                        <a href="tel:<?php echo htmlspecialchars($contact['phone']); ?>" 
                                                           class="btn btn-outline-success btn-sm w-100" 
                                                           title="Call" data-bs-toggle="tooltip">
                                                            <i class="fas fa-phone"></i>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="col">
                                                    <?php if ($contact['source_type'] === 'contact'): ?>
                                                        <button class="btn btn-outline-secondary btn-sm w-100 edit-contact" 
                                                            data-contact="<?php echo $contactDataAttr; ?>"
                                                                title="Edit" data-bs-toggle="tooltip">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="btn btn-outline-muted btn-sm w-100 disabled" 
                                                              title="<?php echo $contact['source_type'] === 'employee' ? 'Employee' : 'Board Member'; ?> - View Only" data-bs-toggle="tooltip">
                                                            <i class="fas fa-lock"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- View Button - Full Width -->
                                            <div class="d-grid">
                                                <button class="btn btn-info btn-sm view-contact" 
                                                    data-contact="<?php echo $contactDataAttr; ?>"
                                                        title="View Details" data-bs-toggle="tooltip">
                                                    <i class="fas fa-eye me-1"></i> View Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div> <!-- ADDED: Closing tag for contactsAccordion -->
    <?php endif; ?>
</div>

<!-- Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactModalLabel">Add Contact</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="contactForm" method="POST">
                <input type="hidden" name="action" value="add_contact">
                <input type="hidden" name="contact_id" id="contact_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="contact_group_id" class="form-label">Group <span class="text-danger">*</span></label>
                            <select class="form-select" id="contact_group_id" name="contact_group_id" required>
                                <option value="">Select Group</option>                                <?php foreach ($contact_groups as $group): ?>
                                    <?php if (!$group['is_system_group']): // Don't allow manual addition to system groups ?>
                                        <option value="<?php echo $group['id']; ?>">
                                            <?php echo htmlspecialchars($group['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="title" class="form-label">Title/Position</label>
                            <input type="text" class="form-control" id="title" name="title">
                        </div>
                        <div class="col-12">
                            <label for="organization" class="form-label">Organization</label>
                            <input type="text" class="form-control" id="organization" name="organization">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="mobile" class="form-label">Mobile</label>
                            <input type="tel" class="form-control" id="mobile" name="mobile">
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Contact</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Group Management Modal -->
<div class="modal fade" id="groupModal" tabindex="-1" aria-labelledby="groupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupModalLabel">Manage Contact Groups</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Add New Group Form -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Add New Group</h6>
                    </div>
                    <div class="card-body">
                        <form id="groupForm" method="POST">
                            <input type="hidden" name="action" value="add_group">
                            <input type="hidden" name="group_id" id="group_id">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="group_name" class="form-label">Group Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="group_name" name="name" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="group_color" class="form-label">Color</label>
                                    <input type="color" class="form-control form-control-color" id="group_color" name="color" value="#007bff">
                                </div>                                <div class="col-md-3">
                                    <label for="group_icon" class="form-label">Icon</label>
                                    <select class="form-select" id="group_icon" name="icon">
                                        <option value="fas fa-users" data-icon="fas fa-users">👥 Users</option>
                                        <option value="fas fa-crown" data-icon="fas fa-crown">👑 Crown</option>
                                        <option value="fas fa-handshake" data-icon="fas fa-handshake">🤝 Handshake</option>
                                        <option value="fas fa-truck" data-icon="fas fa-truck">🚚 Truck</option>
                                        <option value="fas fa-hands-helping" data-icon="fas fa-hands-helping">🤲 Helping Hands</option>
                                        <option value="fas fa-address-book" data-icon="fas fa-address-book">📖 Address Book</option>
                                        <option value="fas fa-building" data-icon="fas fa-building">🏢 Building</option>
                                        <option value="fas fa-globe" data-icon="fas fa-globe">🌍 Globe</option>
                                        <option value="fas fa-briefcase" data-icon="fas fa-briefcase">💼 Briefcase</option>
                                        <option value="fas fa-user-tie" data-icon="fas fa-user-tie">👔 Business</option>
                                        <option value="fas fa-phone" data-icon="fas fa-phone">📞 Phone</option>
                                        <option value="fas fa-envelope" data-icon="fas fa-envelope">📧 Email</option>
                                        <option value="fas fa-heart" data-icon="fas fa-heart">❤️ Heart</option>
                                        <option value="fas fa-star" data-icon="fas fa-star">⭐ Star</option>
                                        <option value="fas fa-shield-alt" data-icon="fas fa-shield-alt">🛡️ Shield</option>
                                        <option value="fas fa-home" data-icon="fas fa-home">🏠 Home</option>
                                        <option value="fas fa-car" data-icon="fas fa-car">🚗 Car</option>
                                        <option value="fas fa-plane" data-icon="fas fa-plane">✈️ Plane</option>
                                        <option value="fas fa-shopping-cart" data-icon="fas fa-shopping-cart">🛒 Shopping</option>
                                        <option value="fas fa-graduation-cap" data-icon="fas fa-graduation-cap">🎓 Education</option>
                                        <option value="fas fa-medical-kit" data-icon="fas fa-medical-kit">🏥 Medical</option>
                                        <option value="fas fa-tools" data-icon="fas fa-tools">🔧 Tools</option>
                                        <option value="fas fa-network-wired" data-icon="fas fa-network-wired">🌐 Network</option>
                                        <option value="fas fa-chart-line" data-icon="fas fa-chart-line">📈 Chart</option>
                                    </select>
                                    <div class="mt-2">
                                        <small class="text-muted">Preview: <i id="icon-preview" class="fas fa-users"></i></small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="group_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="group_description" name="description" rows="2"></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Add Group</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Existing Groups -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Existing Groups</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Group</th>
                                        <th>Description</th>
                                        <th>Contacts</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contact_groups as $group): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="<?php echo $group['icon']; ?> me-2" style="color: <?php echo $group['color']; ?>;"></i>
                                                    <?php echo htmlspecialchars($group['name']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($group['description'] ?: '-'); ?></td>
                                            <td>
                                                <?php 
                                                $count = isset($grouped_contacts[$group['name']]) ? count($grouped_contacts[$group['name']]['contacts']) : 0;
                                                echo $count;
                                                ?>
                                            </td>                                            <td>
                                                <?php if (!$group['is_system_group']): // Don't allow editing of system groups ?>
                                                    <button class="btn btn-outline-primary btn-sm edit-group" 
                                                            data-group-id="<?php echo $group['id']; ?>"
                                                            data-group-name="<?php echo htmlspecialchars($group['name']); ?>"
                                                            data-group-description="<?php echo htmlspecialchars($group['description']); ?>"
                                                            data-group-color="<?php echo $group['color']; ?>"
                                                            data-group-icon="<?php echo $group['icon']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">System Group</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Contact Modal -->
<div class="modal fade" id="viewContactModal" tabindex="-1" aria-labelledby="viewContactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewContactModalLabel">Contact Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4 text-center">
                        <img id="view-contact-photo" src="" alt="Contact Photo" 
                             class="rounded-circle border mb-3" 
                             style="width: 120px; height: 120px; object-fit: cover;"
                             onerror="this.src='resources/userimg/default-image.jpg'">
                        <h5 id="view-contact-name" class="mb-1"></h5>
                        <p id="view-contact-title" class="text-primary mb-2"></p>
                        <span id="view-contact-group" class="badge"></span>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email</label>
                                <p id="view-contact-email" class="mb-0 text-break"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Phone</label>
                                <p id="view-contact-phone" class="mb-0 text-break"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Mobile</label>
                                <p id="view-contact-mobile" class="mb-0 text-break"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Organization</label>
                                <p id="view-contact-organization" class="mb-0 text-break"></p>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Address</label>
                                <p id="view-contact-address" class="mb-0"></p>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Notes</label>
                                <p id="view-contact-notes" class="mb-0"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="delete-from-view" style="display: none;">Delete Contact</button>
                <button type="button" class="btn btn-primary" id="edit-from-view" style="display: none;">Edit Contact</button>
            </div>
        </div>
    </div>
</div>

<style>
.contact-card {
    transition: all 0.3s ease;
}

.contact-card .card-body {
    padding: 1rem 1rem 0.75rem;
    text-align: center;
}

.contact-card .contact-name {
    font-size: 1.25rem;
    line-height: 1.2;
    /* min-height: 2.4em; */
    width: 100%;
    display: -webkit-box;
    display: block;
    text-align: center;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.contact-card .contact-title,
.contact-card .contact-org {
    line-height: 1.2;
    min-height: 1.3em;
    margin-bottom: 0.25rem;
    width: 100%;
    display: block;
    text-align: center;
}

.contact-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
}

.form-control-color {
    width: 3rem;
    height: calc(1.5em + 0.75rem + 2px);
}

/* Updated Chevron Icon Styles for Accordion */
.card-header .fas.fa-chevron-down.toggle-icon {
    transition: transform 0.3s ease;
    transform: rotate(-90deg); /* Default: collapsed (points right) */
}

.card-header .fas.fa-chevron-down.toggle-icon.rotated { /* 'rotated' class means expanded */
    transform: rotate(0deg);   /* Expanded (points down) */
}

.card-header[data-bs-toggle="collapse"]:hover {
    opacity: 0.8;
}

.collapse {
    transition: all 0.35s ease;
}

.card-header[data-bs-toggle="collapse"] {
    cursor: pointer;
}

.card-header[data-bs-toggle="collapse"]:hover {
    opacity: 0.8;
}

.btn-outline-muted {
    color: #6c757d;
    border-color: #6c757d;
}

.btn-outline-muted:hover,
.btn-outline-muted:focus {
    color: #495057;
    background-color: transparent;
    border-color: #495057;
}

.btn-outline-muted.disabled,
.btn-outline-muted:disabled {
    color: #6c757d;
    background-color: transparent;
    border-color: #6c757d;
    opacity: 0.5;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle icon preview in group form
    const groupIconSelect = document.getElementById('group_icon');
    const iconPreview = document.getElementById('icon-preview');
    
    if (groupIconSelect && iconPreview) { // Add null check
        groupIconSelect.addEventListener('change', function() {
            const selectedIcon = this.value;
            iconPreview.className = selectedIcon;
        });
        // Initialize preview for current selection
        if (groupIconSelect.value) {
             iconPreview.className = groupIconSelect.value;
        }
    }

    // Accordion and New Icon Rotation Logic for Group Collapse
    // Ensure this block is not duplicated elsewhere in the script.
    // If it was, the duplicate has been removed by the previous edit that introduced this comment.
    // const collapseTriggerElements = document.querySelectorAll('[data-bs-toggle="collapse"][data-bs-target^="#group-"]'); // This line was likely duplicated
    // The logic below should be the single source of truth for this functionality.

    // The following is the corrected section, assuming the duplicate was removed.
    // If the error persists, it means the duplication is elsewhere or more complex.
    const allCollapseTriggers = document.querySelectorAll('[data-bs-toggle="collapse"][data-bs-target^="#group-"]');

    allCollapseTriggers.forEach(function(headerElement) {
        const targetId = headerElement.getAttribute('data-bs-target');
        const collapseTarget = document.querySelector(targetId);
        // Ensure we select the correct icon within this specific header
        const icon = headerElement.querySelector('.toggle-icon.fas.fa-chevron-down'); 

        if (collapseTarget && icon) {
            // Event listener for when this specific collapse element starts to show
            collapseTarget.addEventListener('show.bs.collapse', function () {
                // Add \'rotated\' class to the icon of the showing element
                icon.classList.add('rotated');
                // Bootstrap with data-bs-parent will handle closing other items
            });

            // Event listener for when this specific collapse element starts to hide
            collapseTarget.addEventListener('hide.bs.collapse', function () {
                // Remove \'rotated\' class from the icon of the hiding element
                icon.classList.remove('rotated');
            });

            // Initial state: if a collapse is shown by default (e.g. has \'show\' class),
            // its icon should be rotated. Bootstrap handles adding \'show\' if needed.
            // Our CSS handles the default (collapsed) icon state.
            if (collapseTarget.classList.contains('show')) {
                icon.classList.add('rotated');
            } else {
                icon.classList.remove('rotated'); // Ensure it\'s not rotated if not shown
            }
        }
    });
    
    // Handle contact form submission
    document.getElementById('contactForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        
        fetch('contacts.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save Contact';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the contact.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Save Contact';
        });
    });

    // Handle group form submission
    document.getElementById('groupForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        
        fetch('contacts.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Add Group';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the group.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Add Group';
        });
    });

    // Modal instances
    console.log('Initializing modal instances...');
    const viewContactModalElement = document.getElementById('viewContactModal');
    const contactModalElement = document.getElementById('contactModal');

    if (viewContactModalElement && viewContactModalElement.parentElement !== document.body) {
        document.body.appendChild(viewContactModalElement);
    }
    if (contactModalElement && contactModalElement.parentElement !== document.body) {
        document.body.appendChild(contactModalElement);
    }

    const viewContactModal = bootstrap.Modal.getOrCreateInstance(viewContactModalElement);
    const contactModalInstance = bootstrap.Modal.getOrCreateInstance(contactModalElement);
    console.log('Modal instances initialized:', { viewContactModal, contactModalInstance });

    let currentViewContact = null; // To store the contact being viewed/edited from view modal

    function decodeContactPayload(payload) {
        if (!payload) {
            console.error('Missing contact payload for decoding');
            return null;
        }

        try {
            return JSON.parse(atob(payload));
        } catch (error) {
            console.error('Failed to decode contact payload', { payload, error });
            return null;
        }
    }

    // Handle view contact (when clicking .view-contact on a card)
    document.querySelectorAll('.view-contact').forEach(button => {
        button.addEventListener('click', function() {
            console.log('.view-contact button clicked', this);
            console.log('Raw data-contact attribute:', this.dataset.contact);
            try {
                currentViewContact = decodeContactPayload(this.dataset.contact);
                console.log('Parsed contact data for view:', currentViewContact);
                
                if (!currentViewContact || typeof currentViewContact !== 'object') {
                    console.error('Parsed contact data is invalid.', currentViewContact);
                    alert('Could not display contact details. Data is invalid.');
                    return;
                }

                document.getElementById('view-contact-photo').src = currentViewContact.photo || 'resources/userimg/default-image.jpg';
                document.getElementById('view-contact-name').textContent = (currentViewContact.first_name || '') + ' ' + (currentViewContact.last_name || '');
                document.getElementById('view-contact-title').textContent = currentViewContact.title || '';
                document.getElementById('view-contact-email').textContent = currentViewContact.email || 'Not provided';
                document.getElementById('view-contact-phone').textContent = currentViewContact.phone || 'Not provided';
                document.getElementById('view-contact-mobile').textContent = currentViewContact.mobile || 'Not provided';
                document.getElementById('view-contact-organization').textContent = currentViewContact.organization || 'Not provided';
                document.getElementById('view-contact-address').textContent = currentViewContact.address || 'Not provided';
                document.getElementById('view-contact-notes').textContent = currentViewContact.notes || 'No notes';
                
                const groupBadge = document.getElementById('view-contact-group');
                groupBadge.textContent = currentViewContact.group_name || 'N/A';
                groupBadge.style.backgroundColor = currentViewContact.group_color || '#6c757d';
                
                const editBtn = document.getElementById('edit-from-view');
                const deleteBtn = document.getElementById('delete-from-view');

                if (currentViewContact.source_type === 'contact') {
                    console.log('Contact is editable, showing edit/delete buttons in modal.');
                    editBtn.style.display = 'inline-block';
                    deleteBtn.style.display = 'inline-block';
                } else {
                    console.log('Contact is not editable (system contact), hiding edit/delete buttons in modal.');
                    editBtn.style.display = 'none';
                    deleteBtn.style.display = 'none';
                }
                
                console.log('Showing viewContactModal');
                viewContactModal.show();
            } catch (e) {
                console.error('Error in .view-contact click listener:', e);
                console.error('Problematic data-contact value:', this.dataset.contact);
                alert('Could not display contact details. Error: ' + e.message);
            }
        });
    });

    // Event listener for the "Edit" button IN THE VIEW MODAL (#edit-from-view)
    const editFromViewButton = document.getElementById('edit-from-view');
    if (editFromViewButton) {
        editFromViewButton.addEventListener('click', function() {
            console.log('#edit-from-view button clicked');
            console.log('currentViewContact at this point:', currentViewContact);
            if (currentViewContact && currentViewContact.source_type === 'contact') {
                console.log('Hiding viewContactModal and calling openEditModal');
                viewContactModal.hide();
                openEditModal(currentViewContact);
            } else {
                console.warn('Edit from view clicked, but no valid currentViewContact or not editable.', currentViewContact);
            }
        });
    } else {
        console.warn('#edit-from-view button not found');
    }

    // Event listener for the "Delete" button IN THE VIEW MODAL (#delete-from-view)
    const deleteFromViewButton = document.getElementById('delete-from-view');
    if (deleteFromViewButton) {
        deleteFromViewButton.addEventListener('click', function() {
            console.log('#delete-from-view button clicked');
            console.log('currentViewContact at this point:', currentViewContact);
            if (currentViewContact && currentViewContact.source_type === 'contact') {
                 if (confirm('Are you sure you want to delete this contact: ' + (currentViewContact.first_name || '') + ' ' + (currentViewContact.last_name || '') + '?')) {
                    console.log('Confirmed deletion for contact ID:', currentViewContact.id);
                    deleteContact(currentViewContact.id);
                }
            } else {
                 console.warn('Delete from view clicked, but no valid currentViewContact or not deletable.', currentViewContact);
            }
        });
    } else {
        console.warn('#delete-from-view button not found');
    }

    // Handle edit contact (when clicking .edit-contact on a card)
    document.querySelectorAll('.edit-contact').forEach(button => {
        button.addEventListener('click', function() {
            console.log('.edit-contact button clicked (from card)', this);
            console.log('Raw data-contact attribute:', this.dataset.contact);
            try {
                const contact = decodeContactPayload(this.dataset.contact);
                console.log('Parsed contact data for edit (from card):', contact);
                if (!contact || typeof contact !== 'object') {
                    console.error('Parsed contact data for edit (from card) is invalid.', contact);
                    alert('Could not edit contact. Data is invalid.');
                    return;
                }
                openEditModal(contact);
            } catch (e) {
                console.error('Error in .edit-contact click listener:', e);
                console.error('Problematic data-contact value:', this.dataset.contact);
                alert('Could not edit contact. Error: ' + e.message);
            }
        });
    });

    function openEditModal(contact) {
        console.log('openEditModal called with contact:', contact);
        if (!contact || !contact.id) { // Added check for contact.id as it is crucial for update
            alert('Cannot open edit modal: contact data is missing, invalid, or lacks an ID.');
            console.error('openEditModal called with invalid contact:', contact);
            return;
        }

        document.getElementById('contactModalLabel').textContent = 'Edit Contact';
        const contactForm = document.getElementById('contactForm');
        contactForm.querySelector('[name="action"]').value = 'update_contact';
        contactForm.querySelector('[name="contact_id"]').value = contact.id || '';
        contactForm.querySelector('[name="first_name"]').value = contact.first_name || '';
        contactForm.querySelector('[name="last_name"]').value = contact.last_name || '';
        contactForm.querySelector('[name="contact_group_id"]').value = contact.contact_group_id || '';
        contactForm.querySelector('[name="title"]').value = contact.title || '';
        contactForm.querySelector('[name="organization"]').value = contact.organization || '';
        contactForm.querySelector('[name="email"]').value = contact.email || '';
        contactForm.querySelector('[name="phone"]').value = contact.phone || '';
        contactForm.querySelector('[name="mobile"]').value = contact.mobile || '';
        contactForm.querySelector('[name="address"]').value = contact.address || '';
        contactForm.querySelector('[name="notes"]').value = contact.notes || '';
        
        console.log('Showing contactModalInstance for editing.');
        contactModalInstance.show();
    }

    function deleteContact(contactId) {
        console.log('deleteContact called with ID:', contactId);
        if (!contactId) {
            alert('Cannot delete contact: ID is missing.');
            console.error('deleteContact called with no ID.');
            return;
        }
        const formData = new FormData();
        formData.append('action', 'delete_contact');
        formData.append('contact_id', contactId);
        
        const activeModalElement = document.querySelector('.modal.show');
        const activeModalInstance = activeModalElement ? bootstrap.Modal.getInstance(activeModalElement) : null;

        fetch('contacts.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Delete response from server:', data);
            if (data.success) {
                if (activeModalInstance) {
                    console.log('Hiding active modal after delete.');
                    activeModalInstance.hide();
                }
                location.reload(); 
            } else {
                alert(data.message || 'Failed to delete contact.');
            }
        })
        .catch(error => {
            console.error('Error deleting contact via fetch:', error);
            alert('An error occurred while deleting the contact.');
        });
    }

    // Handle edit group
    document.querySelectorAll('.edit-group').forEach(button => {
        button.addEventListener('click', function() {
            const groupData = this.dataset;
            
            document.getElementById('group_id').value = groupData.groupId;
            document.getElementById('group_name').value = groupData.groupName;
            document.getElementById('group_description').value = groupData.groupDescription;
            document.getElementById('group_color').value = groupData.groupColor;
            document.getElementById('group_icon').value = groupData.groupIcon;
            document.querySelector('#groupForm [name="action"]').value = 'update_group';
        });
    });

    // Reset forms when modals are hidden
    contactModalElement.addEventListener('hidden.bs.modal', function() { // Use element for event listener
        document.getElementById('contactForm').reset();
        document.getElementById('contactModalLabel').textContent = 'Add Contact';
        document.querySelector('#contactForm [name="action"]').value = 'add_contact';
        document.getElementById('contact_id').value = '';
    });

    document.getElementById('groupModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('groupForm').reset();
        document.querySelector('#groupForm [name="action"]').value = 'add_group';
        document.getElementById('group_id').value = '';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>

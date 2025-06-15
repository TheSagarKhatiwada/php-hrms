<?php
session_start();
$home = './';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include necessary files
include 'includes/configuration.php';
include 'includes/db_connection.php';
include 'includes/settings.php';
include 'includes/functions.php';

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check permissions (allow all logged in users to view contacts)
if (!$current_user) {
    header('Location: login.php');
    exit();
}

// Handle search functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';

// Get all departments for filter dropdown
$dept_stmt = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// Build the main query
$where_conditions = ["status = 'active'"];
$params = [];

if (!empty($search_query)) {
    $where_conditions[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR phone LIKE :search OR designation LIKE :search)";
    $params['search'] = '%' . $search_query . '%';
}

if (!empty($department_filter)) {
    $where_conditions[] = "department = :department";
    $params['department'] = $department_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get contacts with pagination
$limit = 12; // Number of contacts per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Count total contacts
$count_sql = "SELECT COUNT(*) FROM employees $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_contacts = $count_stmt->fetchColumn();
$total_pages = ceil($total_contacts / $limit);

// Get contacts for current page
$sql = "SELECT id, first_name, last_name, email, phone, designation, department, user_image, 
               DATE_FORMAT(created_at, '%M %Y') as joined_date
        FROM employees 
        $where_clause 
        ORDER BY first_name, last_name 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Employee Contacts';
$page_icon = 'fas fa-address-book';
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . APP_NAME; ?></title>
    
    <?php include 'includes/header.php'; ?>
    
    <style>
        .contact-card {
            transition: all 0.3s ease;
            border: 1px solid #e3e6f0;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
            border-color: var(--primary-color);
        }
        
        .contact-avatar {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .contact-info {
            min-height: 140px;
        }
        
        .contact-actions {
            background-color: #f8f9fa;
            border-top: 1px solid #e3e6f0;
        }
        
        .search-section {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid #e3e6f0;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        
        .pagination .page-link {
            border-radius: 0.5rem;
            margin: 0 0.125rem;
            border: 1px solid #dee2e6;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .contact-card .btn {
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>

<body class="hold-transition layout-top-nav">
<div class="wrapper">
    <?php include 'includes/topbar.php'; ?>
    
    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 d-flex align-items-center">
                            <i class="<?php echo $page_icon; ?> me-2 text-primary"></i>
                            <?php echo $page_title; ?>
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                
                <!-- Search and Filter Section -->
                <div class="search-section p-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-3 mb-md-0">Find Your Colleagues</h4>
                            <p class="mb-0 opacity-75">Search and connect with employees across all departments</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="stats-card p-3 text-dark">
                                <h5 class="mb-1"><?php echo number_format($total_contacts); ?></h5>
                                <small class="text-muted">Total Contacts</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Form -->
                    <form method="GET" class="mt-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control border-start-0" 
                                           placeholder="Search by name, email, phone, or designation..." 
                                           value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="department" class="form-select">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>" 
                                                <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="d-grid gap-2 d-md-flex">
                                    <button type="submit" class="btn btn-light">
                                        <i class="fas fa-search me-1"></i> Search
                                    </button>
                                    <?php if (!empty($search_query) || !empty($department_filter)): ?>
                                        <a href="contacts.php" class="btn btn-outline-light">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if (empty($contacts)): ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>No contacts found</h4>
                        <p class="text-muted">
                            <?php if (!empty($search_query) || !empty($department_filter)): ?>
                                Try adjusting your search criteria or filters.
                            <?php else: ?>
                                No employee contacts are available at the moment.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($search_query) || !empty($department_filter)): ?>
                            <a href="contacts.php" class="btn btn-primary">
                                <i class="fas fa-refresh me-1"></i> View All Contacts
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Contact Cards Grid -->
                    <div class="row g-4">
                        <?php foreach ($contacts as $contact): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <div class="contact-card card h-100">
                                    <div class="card-body text-center contact-info">
                                        <!-- Avatar -->
                                        <div class="mb-3">
                                            <img src="<?php echo htmlspecialchars($contact['user_image'] ?: 'resources/images/default-user.png'); ?>" 
                                                 alt="<?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>" 
                                                 class="rounded-circle contact-avatar">
                                        </div>
                                        
                                        <!-- Name and Designation -->
                                        <h5 class="card-title mb-1">
                                            <?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>
                                        </h5>
                                        <p class="text-primary mb-2 fw-medium">
                                            <?php echo htmlspecialchars($contact['designation'] ?: 'Employee'); ?>
                                        </p>
                                        
                                        <!-- Department -->
                                        <?php if (!empty($contact['department'])): ?>
                                            <span class="badge bg-light text-dark mb-2">
                                                <?php echo htmlspecialchars($contact['department']); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- Joined Date -->
                                        <p class="text-muted small mb-0">
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            Joined <?php echo htmlspecialchars($contact['joined_date']); ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Contact Actions -->
                                    <div class="contact-actions p-3">
                                        <div class="row g-2">
                                            <?php if (!empty($contact['email'])): ?>
                                                <div class="col-6">
                                                    <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" 
                                                       class="btn btn-outline-primary btn-sm w-100" 
                                                       title="Send Email">
                                                        <i class="fas fa-envelope"></i>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($contact['phone'])): ?>
                                                <div class="col-6">
                                                    <a href="tel:<?php echo htmlspecialchars($contact['phone']); ?>" 
                                                       class="btn btn-outline-success btn-sm w-100" 
                                                       title="Call Phone">
                                                        <i class="fas fa-phone"></i>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (empty($contact['email']) && empty($contact['phone'])): ?>
                                                <div class="col-12">
                                                    <span class="text-muted small">No contact info available</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- View Profile Button -->
                                        <div class="mt-2">
                                            <a href="employee-viewer.php?id=<?php echo $contact['id']; ?>" 
                                               class="btn btn-primary btn-sm w-100">
                                                <i class="fas fa-user me-1"></i> View Profile
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-5">
                            <nav aria-label="Contact pagination">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                                <?php echo $total_pages; ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                Next <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
            </div>
        </section>
    </div>

    <?php include 'includes/footer.php'; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add some interactive animations
    const contactCards = document.querySelectorAll('.contact-card');
    
    contactCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Handle contact action buttons with tooltips
    const contactButtons = document.querySelectorAll('.contact-actions .btn');
    
    contactButtons.forEach(button => {
        // Initialize Bootstrap tooltips if available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            new bootstrap.Tooltip(button);
        }
    });
    
    // Auto-focus search input if it has a value
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput && searchInput.value) {
        searchInput.focus();
        searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
    }
    
    console.log('Contacts page initialized with <?php echo count($contacts); ?> contacts');
});
</script>

</body>
</html>

<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/settings.php';
require_once 'task_helpers.php';

$page = 'Task Categories';

// Load user data properly
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Get current user info - $_SESSION['user_id'] contains the emp_id
$current_user_id = $_SESSION['user_id'];

// Load user data for sidebar/header
$stmt = $pdo->prepare("SELECT e.*, d.title AS designation_title, r.name AS role_name 
                       FROM employees e 
                       LEFT JOIN designations d ON e.designation = d.id 
                       LEFT JOIN roles r ON e.role_id = r.id
                       WHERE e.emp_id = :id");
$stmt->execute(['id' => $current_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: ../../index.php");
    exit();
}

// Check if user has permission to manage categories
if (!is_admin() && !has_permission('manage_all_tasks')) {
    header("Location: index.php");
    exit();
}

// Initialize variables
$editing_category = false;
$edit_data = null;

// Handle form submission for adding/editing categories
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_category'])) {
            $name = trim($_POST['category_name']);
            $description = trim($_POST['category_description']);
            
            if (!empty($name)) {
                $stmt = $pdo->prepare("INSERT INTO task_categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $_SESSION['success'] = "Category added successfully!";
                header("Location: task-categories.php");
                exit();
            } else {
                $error_message = "Category name is required.";
            }
        } elseif (isset($_POST['edit_category'])) {
            $id = intval($_POST['category_id']);
            $name = trim($_POST['category_name']);
            $description = trim($_POST['category_description']);
            
            if (!empty($name) && $id > 0) {
                $stmt = $pdo->prepare("UPDATE task_categories SET name = ?, description = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                $_SESSION['success'] = "Category updated successfully!";
                header("Location: task-categories.php");
                exit();
            } else {
                $error_message = "Category name is required.";
            }
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $id = intval($_GET['delete']);
        
        // Check if category is being used by any tasks
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE category = (SELECT name FROM task_categories WHERE id = ?)");
        $stmt->execute([$id]);
        $usage_count = $stmt->fetchColumn();
        
        if ($usage_count > 0) {
            $_SESSION['error'] = "Cannot delete category. It is being used by $usage_count task(s).";
        } else {
            $stmt = $pdo->prepare("DELETE FROM task_categories WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Category deleted successfully!";
        }
        
        header("Location: task-categories.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting category: " . $e->getMessage();
        header("Location: task-categories.php");
        exit();
    }
}

// Handle edit request
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM task_categories WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_data) {
        $editing_category = true;
    }
}

// Get existing categories
try {
    $stmt = $pdo->query("SELECT * FROM task_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

require_once '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-tags me-2"></i>Task Categories</h1>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                <i class="fas fa-plus me-1"></i>Create Task
            </button>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Categories Management -->
    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="card-title mb-0 fw-bold">
                        <i class="fas fa-list me-2 text-primary"></i>Task Categories
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($categories)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No categories created yet</h5>
                            <p class="text-muted">Create your first task category to get started!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="fw-semibold">Category Name</th>
                                        <th class="fw-semibold">Description</th>
                                        <th class="fw-semibold">Created</th>
                                        <th class="fw-semibold text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-2">
                                                        <div class="avatar-title bg-primary rounded-circle">
                                                            <i class="fas fa-tag"></i>
                                                        </div>
                                                    </div>
                                                    <span class="fw-bold"><?php echo htmlspecialchars($category['name']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php echo htmlspecialchars($category['description'] ?: 'No description'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($category['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a href="?edit=<?php echo $category['id']; ?>" 
                                                       class="btn btn-sm btn-outline-warning" 
                                                       data-bs-toggle="tooltip" 
                                                       title="Edit Category">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $category['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger delete-category" 
                                                       data-bs-toggle="tooltip" 
                                                       title="Delete Category"
                                                       data-category-name="<?php echo htmlspecialchars($category['name']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
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
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="card-title mb-0 fw-bold">
                        <i class="fas fa-plus me-2 text-success"></i>
                        <?php echo $editing_category ? 'Edit Category' : 'Add New Category'; ?>
                    </h3>
                </div>
                <form method="POST">
                    <div class="card-body">
                        <?php if ($editing_category): ?>
                            <input type="hidden" name="category_id" value="<?php echo $edit_data['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="category_name" class="form-label fw-semibold">Category Name *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="category_name" 
                                   name="category_name" 
                                   value="<?php echo $editing_category ? htmlspecialchars($edit_data['name']) : ''; ?>"
                                   placeholder="Enter category name"
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_description" class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" 
                                      id="category_description" 
                                      name="category_description" 
                                      rows="3"
                                      placeholder="Enter category description (optional)"><?php echo $editing_category ? htmlspecialchars($edit_data['description']) : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-flex gap-2">
                            <button type="submit" 
                                    name="<?php echo $editing_category ? 'edit_category' : 'add_category'; ?>" 
                                    class="btn btn-<?php echo $editing_category ? 'warning' : 'success'; ?>">
                                <i class="fas fa-<?php echo $editing_category ? 'save' : 'plus'; ?> me-1"></i>
                                <?php echo $editing_category ? 'Update Category' : 'Add Category'; ?>
                            </button>
                            
                            <?php if ($editing_category): ?>
                                <a href="task-categories.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-sm {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-title {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    color: white;
}

@media (max-width: 768px) {
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group .btn {
        margin: 1px 0;
    }
}
</style>

<script>
$(document).ready(function() {
    // Initialize tooltips
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Auto-focus on category name input when in add mode
    <?php if (!$editing_category): ?>
        $('#category_name').focus();
    <?php endif; ?>
    
    // Confirmation for delete with more details
    $('.delete-category').on('click', function(e) {
        e.preventDefault();
        var categoryName = $(this).data('category-name');
        var deleteUrl = $(this).attr('href');
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete Category?',
                text: 'Are you sure you want to delete "' + categoryName + '"? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = deleteUrl;
                }
            });
        } else {
            if (confirm('Are you sure you want to delete "' + categoryName + '"? This action cannot be undone.')) {
                window.location.href = deleteUrl;
            }
        }
    });
});
</script>

<?php 
// Include the create task modal
require_once 'create_task_modal.php';

require_once '../../includes/footer.php'; 
?>

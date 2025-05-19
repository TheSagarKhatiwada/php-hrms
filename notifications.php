<?php
/**
 * Notifications Page
 * 
 * Displays and manages all notifications for the current user
 */

// Start the session
session_start();

// Include necessary files
require_once 'includes/db_connection.php';
require_once 'includes/services/NotificationService.php';
require_once 'includes/utilities.php';
require_once 'includes/csrf_protection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Create notification service instance
$notificationService = new NotificationService($pdo);

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get notifications for current user
$notifications = $notificationService->getAllNotifications($_SESSION['user_id'], $limit, $offset);

// Get total count for pagination
$totalNotifications = $notificationService->countAllNotifications($_SESSION['user_id']);
$totalPages = ceil($totalNotifications / $limit);

// Handle mark all as read action
if (isset($_POST['mark_all_read']) && verify_csrf_token($_POST['csrf_token'])) {
    $notificationService->markAllAsRead($_SESSION['user_id']);
    $_SESSION['success'] = "All notifications marked as read";
    header("Location: notifications.php");
    exit();
}

// Handle delete all read action
if (isset($_POST['delete_all_read']) && verify_csrf_token($_POST['csrf_token'])) {
    $notificationService->deleteAllReadNotifications($_SESSION['user_id']);
    $_SESSION['success'] = "All read notifications deleted";
    header("Location: notifications.php");
    exit();
}

// Set page title and include header
$pageTitle = "Notifications";
require_once 'includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0"><i class="fas fa-bell me-2"></i> Notifications</h1>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        
        <div class="card shadow-sm rounded-3 border-0">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <h3 class="card-title fs-5 m-0">Manage Your Notifications</h3>
                
                <div class="card-tools">
                    <form method="post" class="d-inline me-2">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-check-double me-1"></i> Mark All as Read
                        </button>
                    </form>
                    
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <button type="submit" name="delete_all_read" class="btn btn-sm btn-outline-danger" 
                                onclick="return confirm('Are you sure you want to delete all read notifications?');">
                            <i class="fas fa-trash me-1"></i> Delete Read Notifications
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if (empty($notifications)): ?>
                <div class="card-body text-center py-5">
                    <div class="empty-state">
                        <div class="empty-state-icon mb-3">
                            <i class="fas fa-bell-slash fa-3x text-muted opacity-25"></i>
                        </div>
                        <h4 class="text-muted">No notifications found</h4>
                        <p class="text-muted">When you receive notifications, they will appear here.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card-body p-0">
                    <div class="notification-list">
                        <?php 
                        $counter = ($page - 1) * $limit + 1;
                        foreach ($notifications as $notification): 
                            // Determine icon based on notification type
                            $iconClass = 'fa-info-circle text-info';
                            $bgColorClass = 'bg-info-light';
                            
                            switch ($notification['type']) {
                                case 'success':
                                    $iconClass = 'fa-check-circle text-success';
                                    $bgColorClass = 'bg-success-light';
                                    break;
                                case 'warning':
                                    $iconClass = 'fa-exclamation-triangle text-warning';
                                    $bgColorClass = 'bg-warning-light';
                                    break;
                                case 'danger':
                                    $iconClass = 'fa-times-circle text-danger';
                                    $bgColorClass = 'bg-danger-light';
                                    break;
                            }
                            
                            // Format date
                            $date = new DateTime($notification['created_at']);
                            $now = new DateTime();
                            $diff = $now->diff($date);
                            
                            if ($diff->days == 0) {
                                if ($diff->h == 0) {
                                    if ($diff->i == 0) {
                                        $timeAgo = 'Just now';
                                    } else {
                                        $timeAgo = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                                    }
                                } else {
                                    $timeAgo = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                }
                            } else if ($diff->days < 7) {
                                $timeAgo = $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
                            } else {
                                $timeAgo = $date->format('M j, Y g:i A');
                            }
                        ?>
                            <div class="notification-item p-3 border-bottom <?php echo !$notification['is_read'] ? 'unread bg-light' : ''; ?>">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <div class="icon-circle <?php echo $bgColorClass; ?> p-3 rounded-circle">
                                            <i class="fas <?php echo $iconClass; ?>"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <div class="notification-actions">
                                                <div class="btn-group btn-group-sm">
                                                    <?php if (!$notification['is_read']): ?>
                                                        <button type="button" class="btn btn-outline-primary mark-read-btn" 
                                                                data-id="<?php echo $notification['id']; ?>" 
                                                                data-bs-toggle="tooltip" 
                                                                title="Mark as Read">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($notification['link']): ?>
                                                        <a href="<?php echo $notification['link']; ?>" class="btn btn-outline-info"
                                                        data-bs-toggle="tooltip" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-outline-danger delete-btn" 
                                                            data-id="<?php echo $notification['id']; ?>" 
                                                            data-bs-toggle="tooltip" 
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted"><?php echo $timeAgo; ?></small>
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="badge bg-primary rounded-pill">New</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary rounded-pill">Read</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Notification pagination">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
/* Notification page specific styles */
.notification-item {
    transition: all 0.2s ease;
}

.notification-item:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.notification-item.unread {
    border-left: 3px solid var(--primary-color);
}

.icon-circle {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bg-success-light {
    background-color: rgba(25, 135, 84, 0.1);
}

.bg-warning-light {
    background-color: rgba(255, 193, 7, 0.1);
}

.bg-danger-light {
    background-color: rgba(220, 53, 69, 0.1);
}

.bg-info-light {
    background-color: rgba(13, 202, 240, 0.1);
}

.notification-actions {
    opacity: 0.2;
    transition: opacity 0.2s ease;
}

.notification-item:hover .notification-actions {
    opacity: 1;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .notification-actions {
        opacity: 1;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .card-tools {
        margin-top: 1rem;
        display: flex;
        width: 100%;
    }
    
    .card-tools form {
        flex: 1;
    }
    
    .card-tools form button {
        width: 100%;
    }
}

/* Empty state styling */
.empty-state {
    padding: 2rem 1rem;
}

.empty-state-icon {
    margin-bottom: 1rem;
}

/* Dark mode adjustments */
body.dark-mode .notification-item:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

body.dark-mode .notification-item.unread {
    background-color: rgba(13, 110, 253, 0.05);
}

body.dark-mode .bg-success-light {
    background-color: rgba(25, 135, 84, 0.2);
}

body.dark-mode .bg-warning-light {
    background-color: rgba(255, 193, 7, 0.2);
}

body.dark-mode .bg-danger-light {
    background-color: rgba(220, 53, 69, 0.2);
}

body.dark-mode .bg-info-light {
    background-color: rgba(13, 202, 240, 0.2);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Mark as read buttons
    document.querySelectorAll('.mark-read-btn').forEach(button => {
        button.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-id');
            const notificationItem = this.closest('.notification-item');
            
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('notification_id', notificationId);
            
            // Add CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                formData.append('csrf_token', csrfToken.getAttribute('content'));
            }
            
            fetch('api/notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI to show notification is read
                    notificationItem.classList.remove('unread', 'bg-light');
                    
                    // Update status badge
                    const badge = notificationItem.querySelector('.badge');
                    if (badge) {
                        badge.classList.remove('bg-primary');
                        badge.classList.add('bg-secondary');
                        badge.textContent = 'Read';
                    }
                    
                    // Remove mark as read button
                    button.remove();
                    
                    // Show success toast
                    Swal.fire({
                        position: 'top-end',
                        icon: 'success',
                        title: 'Notification marked as read',
                        showConfirmButton: false,
                        timer: 2000,
                        toast: true
                    });
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
                Swal.fire({
                    position: 'top-end',
                    icon: 'error',
                    title: 'Failed to mark notification as read',
                    showConfirmButton: false,
                    timer: 2000,
                    toast: true
                });
            });
        });
    });
    
    // Delete buttons
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-id');
            const notificationItem = this.closest('.notification-item');
            
            Swal.fire({
                title: 'Are you sure?',
                text: "This notification will be permanently deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('notification_id', notificationId);
                    
                    // Add CSRF token
                    const csrfToken = document.querySelector('meta[name="csrf-token"]');
                    if (csrfToken) {
                        formData.append('csrf_token', csrfToken.getAttribute('content'));
                    }
                    
                    fetch('api/notifications.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove notification item from list
                            notificationItem.remove();
                            
                            // Show success toast
                            Swal.fire({
                                position: 'top-end',
                                icon: 'success',
                                title: 'Notification deleted',
                                showConfirmButton: false,
                                timer: 2000,
                                toast: true
                            });
                            
                            // Check if notification list is now empty
                            const notificationList = document.querySelector('.notification-list');
                            if (notificationList && notificationList.children.length === 0) {
                                // Replace with empty state
                                const cardBody = notificationList.closest('.card-body');
                                if (cardBody) {
                                    cardBody.innerHTML = `
                                        <div class="text-center py-5">
                                            <div class="empty-state">
                                                <div class="empty-state-icon mb-3">
                                                    <i class="fas fa-bell-slash fa-3x text-muted opacity-25"></i>
                                                </div>
                                                <h4 class="text-muted">No notifications found</h4>
                                                <p class="text-muted">When you receive notifications, they will appear here.</p>
                                            </div>
                                        </div>
                                    `;
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting notification:', error);
                        Swal.fire({
                            position: 'top-end',
                            icon: 'error',
                            title: 'Failed to delete notification',
                            showConfirmButton: false,
                            timer: 2000,
                            toast: true
                        });
                    });
                }
            });
        });
    });
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
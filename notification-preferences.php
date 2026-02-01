<?php
require_once 'includes/session_config.php';
require_once 'includes/db_connection.php';
require_once 'includes/csrf_protection.php';
require_once 'includes/header.php';

$current_user = $_SESSION['user_id'] ?? null;

if (!$current_user) {
    header("Location: ../index.php");
    exit();
}

// Get user's employee ID (emp_id)
$employee_id = $current_user;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: notification-preferences.php");
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update preferences for each notification type
        $notification_types = ['task_assigned', 'task_status_update', 'task_completed', 'task_overdue'];
        
        foreach ($notification_types as $type) {
            $email_enabled = isset($_POST[$type . '_email']) ? 1 : 0;
            $sms_enabled = isset($_POST[$type . '_sms']) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO notification_preferences (employee_id, notification_type, email_enabled, sms_enabled)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    email_enabled = VALUES(email_enabled),
                    sms_enabled = VALUES(sms_enabled)
            ");
            $stmt->execute([$employee_id, $type, $email_enabled, $sms_enabled]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Notification preferences updated successfully!";
        header("Location: notification-preferences.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to update preferences: " . $e->getMessage();
    }
}

// Get current preferences
$stmt = $pdo->prepare("
    SELECT notification_type, email_enabled, sms_enabled 
    FROM notification_preferences 
    WHERE employee_id = ?
");
$stmt->execute([$employee_id]);
$preferences = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $preferences[$row['notification_type']] = $row;
}

// Default values if no preferences exist
$default = ['email_enabled' => 1, 'sms_enabled' => 0];
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><i class="fas fa-bell mr-2"></i> Notification Preferences</h1>
    </div>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cog mr-2"></i>Task Notification Settings</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th style="width: 75%;">Notification Type</th>
                                                <th class="text-center" style="width: 25%;">
                                                    <i class="fas fa-envelope"></i> Email
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $notifications = [
                                                'task_assigned' => [
                                                    'title' => 'New Task Assigned',
                                                    'description' => 'When a new task is assigned to you',
                                                    'icon' => 'fa-tasks'
                                                ],
                                                'task_status_update' => [
                                                    'title' => 'Task Status Updates',
                                                    'description' => 'When someone updates the status of a task you created',
                                                    'icon' => 'fa-sync'
                                                ],
                                                'task_completed' => [
                                                    'title' => 'Task Completed',
                                                    'description' => 'When a task you assigned is marked as completed',
                                                    'icon' => 'fa-check-circle'
                                                ],
                                                'task_overdue' => [
                                                    'title' => 'Overdue Task Reminders',
                                                    'description' => 'Daily reminders for tasks that are past their due date',
                                                    'icon' => 'fa-exclamation-triangle'
                                                ]
                                            ];
                                            
                                            foreach ($notifications as $type => $info):
                                                $prefs = $preferences[$type] ?? $default;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <i class="fas <?= $info['icon'] ?> text-primary me-2"></i>
                                                        <strong><?= $info['title'] ?></strong><br>
                                                        <small class="text-muted"><?= $info['description'] ?></small>
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        <div class="form-check form-switch d-inline-block">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   name="<?= $type ?>_email" 
                                                                   id="<?= $type ?>_email"
                                                                   <?= $prefs['email_enabled'] ? 'checked' : '' ?>>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h5><i class="icon fas fa-info"></i> Note</h5>
                                    Enable email notifications to stay updated on task activities.
                                </div>
                                
                                <div class="text-right">
                                    <button type="submit" name="save_preferences" class="btn btn-primary">
                                        <i class="fas fa-save mr-1"></i> Save Preferences
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-question-circle mr-2"></i>Help & Tips</h3>
                        </div>
                        <div class="card-body">
                            <h6 class="mb-2">Best Practices</h6>
                            <ul class="small mb-0">
                                <li>Enable email for all task types</li>
                                <li>Keep overdue reminders enabled</li>
                                <li>Check notifications regularly</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
</div>

<?php require_once 'includes/footer.php'; ?>

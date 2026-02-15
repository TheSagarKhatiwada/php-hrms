<?php
// Include session configuration before accessing any session data
require_once 'includes/session_config.php';

$page = 'Profile';
require_once __DIR__ . '/includes/header.php';
include 'includes/db_connection.php';
require_once 'includes/csrf_protection.php';

// Fetch user details from the database
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT e.first_name, 
                                      e.middle_name, 
                                      e.last_name, 
                                      e.gender, 
                                      e.email, 
                                      e.phone, 
                                      e.join_date, 
                                      e.designation_id, 
                                      e.user_image, 
                                      e.emp_id, 
                                      e.branch, 
                                      e.date_of_birth, 
                                      e.exit_date, 
                                      b.name AS branch_name, 
                                      r.name AS role_name, 
                                      d.title AS designation_title, 
                                      e.office_email, 
                                      e.office_phone 
                                      FROM employees e 
                                      INNER JOIN branches b ON e.branch = b.id 
                                      LEFT JOIN roles r ON e.role_id = r.id 
                                      LEFT JOIN designations d ON e.designation_id = d.id 
                                      WHERE e.emp_id = :emp_id");
$stmt->execute(['emp_id' => $user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    // Destroy all session data redirect to login page if user is not found
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Calculate employment duration
$current_date = new DateTime();
$join_date = new DateTime($user_data['join_date']);
$employment_interval = $join_date->diff($current_date);
$years = $employment_interval->y;
$months = $employment_interval->m;
$employmentDuration = "";
if ($years > 0) {
    $employmentDuration = $years . " year" . ($years > 1 ? "s" : "");
    if ($months > 0) {
        $employmentDuration .= ", " . $months . " month" . ($months > 1 ? "s" : "");
    }
} else {
    $employmentDuration = $months . " month" . ($months > 1 ? "s" : "");
    if ($employment_interval->d > 0) {
        $employmentDuration .= ", " . $employment_interval->d . " day" . ($employment_interval->d > 1 ? "s" : "");
    }
}

// Convert duration digits to Nepali in BS mode
$employmentDurationDisplay = hrms_should_use_bs_dates()
    ? hrms_to_nepali_digits($employmentDuration)
    : $employmentDuration;

// Prepare Employee ID for BS digit display if needed
$employeeIdDisplay = hrms_should_use_bs_dates()
    ? hrms_to_nepali_digits($user_data['emp_id'] ?? '')
    : ($user_data['emp_id'] ?? '');

// Fetch assigned assets for the user
$assigned_assets_stmt = $pdo->prepare("SELECT 
                                        fa.AssetName, 
                                        fa.AssetSerial, 
                                        aa.AssignmentDate,
                                        fa.Status AS AssetStatus
                                    FROM assetassignments aa
                                    JOIN fixedassets fa ON aa.AssetID = fa.AssetID
                                    WHERE aa.EmployeeID = :employee_id AND aa.ReturnDate IS NULL
                                    ORDER BY aa.AssignmentDate DESC");
$assigned_assets_stmt->execute(['employee_id' => $user_id]); // Assuming $user_id is the ID from the employees table used in AssetAssignments.EmployeeID
$assigned_assets = $assigned_assets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle notification preferences update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_preferences'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        header('Location: profile.php#notifications');
        exit;
    }
    
    $notification_types = ['task_assigned', 'task_status_update', 'task_completed', 'task_overdue'];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($notification_types as $type) {
            $email_enabled = isset($_POST[$type . '_email']) ? 1 : 0;
            $sms_enabled = isset($_POST[$type . '_sms']) ? 1 : 0;
            
            $stmt = $pdo->prepare("INSERT INTO notification_preferences (employee_id, notification_type, email_enabled, sms_enabled) 
                                   VALUES (:employee_id, :notification_type, :email_enabled, :sms_enabled)
                                   ON DUPLICATE KEY UPDATE email_enabled = :email_enabled, sms_enabled = :sms_enabled");
            
            $stmt->execute([
                'employee_id' => $user_id,
                'notification_type' => $type,
                'email_enabled' => $email_enabled,
                'sms_enabled' => $sms_enabled
            ]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = 'Notification preferences updated successfully!';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Failed to update preferences: ' . $e->getMessage();
    }
    
    header('Location: profile.php#notifications');
    exit;
}

// Fetch notification preferences
$preferences = [];
$stmt = $pdo->prepare("SELECT notification_type, email_enabled, sms_enabled FROM notification_preferences WHERE employee_id = :employee_id");
$stmt->execute(['employee_id' => $user_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $preferences[$row['notification_type']] = [
        'email_enabled' => $row['email_enabled'],
        'sms_enabled' => $row['sms_enabled']
    ];
}
$default = ['email_enabled' => 1, 'sms_enabled' => 0];

// Handle profile picture update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['croppedImage'])) {
    $targetDir = "resources/userimg/uploads/";
    $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['croppedImage']));
    $imageName = uniqid() . '.png';
    $targetFile = $targetDir . $imageName;
    file_put_contents($targetFile, $imageData);

    // Update the database with the new profile picture
    $stmt = $pdo->prepare("UPDATE employees SET user_image = :user_image WHERE emp_id = :id");
    $stmt->execute(['user_image' => $targetFile, 'id' => $user_id]);
    $user_data['user_image'] = $targetFile;
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Verify the current password first (if not a password reset request)
    if (isset($_POST['is_reset_request']) && $_POST['is_reset_request'] == 1) {
        // This is a password reset request, skip current password verification
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE employees SET password = :password WHERE id = :id");
            $stmt->execute(['password' => $hashed_password, 'id' => $user_id]);
            $_SESSION['success'] = 'Password reset successfully.';
        } else {
            $_SESSION['error'] = 'Passwords do not match.';
        }
    } else {
        // Normal password change - verify current password
        $stmt = $pdo->prepare("SELECT password FROM employees WHERE emp_id = :id");
        $stmt->execute(['id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($current_password, $user['password'])) {
            $_SESSION['error'] = 'Current password is incorrect.';
        } else if ($new_password !== $confirm_password) {
            $_SESSION['error'] = 'New passwords do not match.';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE employees SET password = :password WHERE id = :id");
            $stmt->execute(['password' => $hashed_password, 'id' => $user_id]);
            $_SESSION['success'] = 'Password updated successfully.';
        }
    }
    header('Location: profile.php');
    exit;
}

// Handle forgot password request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['forgot_password_request'])) {
    $email = $_POST['email_reset'] ?? '';
    
    // Verify the email exists in the database
    $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name FROM employees WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Generate a unique token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store the token in the database
        $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
        $stmt->execute([
            'user_id' => $user['id'],
            'token' => $token,
            'expires_at' => $expires
        ]);
        
        // Build the reset link
        $resetLink = "http://{$_SERVER['HTTP_HOST']}/php-hrms/reset-password.php?token=" . urlencode($token);
        
        // Build email message
        $to = $email;
        $subject = "Password Reset Request";
        $message = "
        <html>
        <head>
            <title>Password Reset</title>
        </head>
            <body>
                <p>Dear {$user['first_name']} {$user['middle_name']} {$user['last_name']},</p>
                <p>We received a request to reset your password. Click the link below to set a new password:</p>
                <p><a href='{$resetLink}'>Reset Password</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>If you did not request a password reset, please ignore this email or contact your administrator.</p>
                <p>Regards,<br>HR Management System</p>
            </body>
        </html>
        ";
        
        // Set email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@hrms.com" . "\r\n";
        
        // Attempt to send the email
        $emailSent = mail($to, $subject, $message, $headers);
        
        if ($emailSent) {
            $_SESSION['success'] = 'Password reset link has been sent to your email address.';
        } else {
            $_SESSION['error'] = 'Failed to send the password reset email. Please try again or contact your administrator.';
        }
    } else {
        // For security, we still show success even if the email doesn't exist
        $_SESSION['success'] = 'If your email is registered, you will receive a password reset link shortly.';
    }
    
    header('Location: profile.php');
    exit;
}

// Handle session termination requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['terminate_session']) && !empty($_POST['sessid'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid security token.';
        header('Location: profile.php#session_location');
        exit;
    }

    $sessid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['sessid']);
    // Prevent terminating current session
    if ($sessid === session_id()) {
        $_SESSION['warning'] = 'You cannot terminate your current session from here.';
        header('Location: profile.php#session_location');
        exit;
    }

    $sessionFile = __DIR__ . '/sessions/sess_' . $sessid;
    if (file_exists($sessionFile) && is_writable($sessionFile)) {
        @unlink($sessionFile);
        $_SESSION['success'] = 'Selected session terminated successfully.';
    } else {
        $_SESSION['error'] = 'Failed to terminate session or session not found.';
    }
    header('Location: profile.php#session_location');
    exit;
}

// Handle AJAX/POST request to store/generate a location access code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_location_code'])) {
    header('Content-Type: application/json');
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit;
    }

    $lat = isset($_POST['lat']) ? trim($_POST['lat']) : null;
    $lon = isset($_POST['lon']) ? trim($_POST['lon']) : null;
    if (!$lat || !$lon) {
        echo json_encode(['success' => false, 'message' => 'Coordinates missing']);
        exit;
    }

    // Generate a short access code (non-guessable substring of a hash)
    $code = substr(hash('sha256', $user_id . '|' . $lat . '|' . $lon . '|' . time()), 0, 12);
    $_SESSION['location_access'] = [
        'code' => $code,
        'lat' => $lat,
        'lon' => $lon,
        'generated_at' => time()
    ];

    echo json_encode(['success' => true, 'code' => $code]);
    exit;
}

?>

<!-- Cropper.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">

<style>
    .profile-picture-container {
        position: relative;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        margin: 0 auto;
        overflow: hidden;
        border: 5px solid rgba(var(--bs-primary-rgb), 0.1);
        box-shadow: var(--card-shadow);
    }
    .profile-picture-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .profile-picture-container .upload-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 30%;
        text-align: center;
        display: none;
        background-color: rgba(0, 0, 0, 0.6);
        transition: all 0.3s ease;
    }
    .profile-picture-container:hover .upload-overlay {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .upload-overlay button {
        background: none;
        border: none;
        color: white;
        font-size: 1rem;
        cursor: pointer;
    }
    .upload-overlay button:hover {
        color: var(--bs-info);
    }

    /* Crop Modal CSS */
    .img-container {
        max-height: 400px;
        overflow: hidden;
    }
    .img-container img {
        max-width: 100%;
        display: block;
    }
    
    .profile-info-item {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
    }

    
    .modern-info-box {
        border-radius: 10px;
        transition: transform 0.3s, box-shadow 0.3s;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        margin-bottom: 1.5rem;
    }
    
    .modern-info-box:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-shadow-hover);
    }
    
    .profile-header {
        padding: 20px;
        text-align: center;
        border-bottom: 1px solid var(--border-color);
    }
    
    /* Fix for active tab background in dark mode */
    body.dark-mode .nav-tabs .nav-link.active {
        background-color: #343a40;
        color: #f8f9fa;
        border-color: #495057;
        border-bottom-color: #343a40;
    }
    
    body.dark-mode .card-header {
        background-color: #2c3136;
        border-color: #495057;
    }
    
    body.dark-mode .card {
        background-color: #343a40;
        border-color: #495057;
    }
    
    /* Timeline enhancements */
    .timeline-item {
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }
    
    .timeline-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    body.dark-mode .timeline-item {
        background-color: #3a3f48;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }
    
    /* Improve table appearance */
    .table-hover tbody tr:hover {
        background-color: rgba(var(--bs-primary-rgb), 0.05);
    }
    
    body.dark-mode .table-hover tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }
    
    /* Enhance badge styling */
    .badge {
        font-weight: 500;
        letter-spacing: 0.5px;
        padding: 0.4em 0.7em;
        border-radius: 4px;
    }
    
    /* Improve tab transition */
    .tab-content .tab-pane {
        transition: opacity 0.15s linear;
    }
    
    /* Make list items more readable in dark mode */
    body.dark-mode .list-group-item {
        background-color: #343a40;
        border-color: #495057;
        color: #f8f9fa;
    }
</style>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Session Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['success']; 
                unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['warning']; 
                unset($_SESSION['warning']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">My Profile</h1>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
            <i class="fas fa-key me-2"></i> Change Password
        </button>
    </div>

    <div class="row">
        <!-- Left column -->
        <div class="col-md-4">
            <!-- Profile Image -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body box-profile text-center">
                    <div class="profile-picture-container mb-3">
                        <img id="profileImage" src="<?php echo !empty($user_data['user_image']) ? htmlspecialchars($user_data['user_image']) : 'dist/img/default-avatar.png'; ?>" alt="Profile Image" class="img-fluid">
                        <div class="upload-overlay">
                            <button type="button" onclick="document.getElementById('profilePictureInput').click();">
                                <i class="fas fa-camera"></i> Change
                            </button>
                        </div>
                    </div>
                    <form id="profilePictureForm" action="profile.php" method="post" enctype="multipart/form-data">
                        <input type="file" class="form-control-file" id="profilePictureInput" name="profile_picture" accept="image/*" style="display: none;" onchange="openCropModal(event)">
                        <input type="hidden" id="croppedImage" name="croppedImage">
                    </form>

                    <h3 class="profile-username mb-1">
                        <?php echo htmlspecialchars($user_data['first_name'] . ' ' . ($user_data['middle_name'] ?? '') . ' ' . $user_data['last_name']); ?>
                    </h3>
                    <p class="text-muted mb-3">
                        <?php echo htmlspecialchars($user_data['designation_title'] ?? 'Not Assigned'); ?> <!-- Changed to designation_title -->
                    </p>

                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <b>Employee ID</b> <span><?php echo htmlspecialchars($employeeIdDisplay); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <b>Branch</b> <span><?php echo htmlspecialchars($user_data['branch_name']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <b>Joined Date</b> <span><?php echo htmlspecialchars(hrms_format_preferred_date($user_data['join_date'] ?? null)); ?></span>
                        </li>
                    </ul>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->

            <!-- About Me Box -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Contact Information</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <h5><strong><i class="fas fa-user-circle me-2"></i> Personal</strong></h5>
                    <p class="text-muted">
                        <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($user_data['email']); ?><br>
                        <i class="fas fa-phone-alt me-2"></i> <?php echo htmlspecialchars($user_data['phone']); ?>
                    </p>
                    
                    <?php if (!empty($user_data['office_email']) || !empty($user_data['office_phone'])): ?>
                    <hr>
                    <h5><strong><i class="fas fa-briefcase me-2"></i> Official</strong></h5>
                    <p class="text-muted">
                        <?php if (!empty($user_data['office_email'])): ?>
                            <i class="fas fa-at me-2"></i> <?php echo htmlspecialchars($user_data['office_email']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($user_data['office_phone'])): ?>
                            <i class="fas fa-phone-square-alt me-2"></i> <?php echo htmlspecialchars($user_data['office_phone']); ?>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    
                    <hr>
                    <strong><i class="fas fa-map-marker-alt me-2"></i> Address</strong>
                    <p class="text-muted">Tinkune, Kathmandu</p>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
        <!-- /.col -->

        <!-- Right column -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header p-2">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" href="#details" data-bs-toggle="tab">
                                <i class="fas fa-user me-1"></i> Profile Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#assets" data-bs-toggle="tab">
                                <i class="fas fa-laptop me-1"></i> Assigned Assets
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#notifications" data-bs-toggle="tab">
                                <i class="fas fa-bell me-1"></i> Notification Preferences
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#session_location" data-bs-toggle="tab">
                                <i class="fas fa-shield-alt me-1"></i> Session &amp; Location
                            </a>
                        </li>
                    </ul>
                </div><!-- /.card-header -->
                <div class="card-body">
                    <div class="tab-content">
                        <div class="active tab-pane" id="details">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title m-0">Personal Information</h5>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="profile-info-item">
                                                <strong>Full Name:</strong>
                                                <p class="mb-0"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['middle_name'] . ' ' . $user_data['last_name']); ?></p>
                                            </div>
                                            <div class="profile-info-item">
                                                <strong>Gender:</strong>
                                                <?php
                                                    $rawGender = strtolower((string)($user_data['gender'] ?? ''));
                                                    if (in_array($rawGender, ['m', 'male'], true)) {
                                                        $genderLabel = 'Male';
                                                    } elseif (in_array($rawGender, ['f', 'female'], true)) {
                                                        $genderLabel = 'Female';
                                                    } else {
                                                        $genderLabel = 'Not specified';
                                                    }
                                                ?>
                                                <p class="mb-0"><?php echo htmlspecialchars($genderLabel); ?></p>
                                            </div>
                                            <div class="profile-info-item">
                                                <strong>Date of Birth:</strong>
                                                <p class="mb-0"><?php echo htmlspecialchars(hrms_format_preferred_date($user_data['date_of_birth'] ?? null)); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title m-0">Employment Information</h5>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="profile-info-item">
                                                <strong>Designation:</strong>
                                                <p class="mb-0"><?php echo htmlspecialchars($user_data['designation_title'] ?? 'Not Assigned'); ?></p> <!-- Changed to designation_title -->
                                            </div>
                                            <div class="profile-info-item">
                                                <strong>Joined Date:</strong>
                                                <p class="mb-0"><?php echo htmlspecialchars(hrms_format_preferred_date($user_data['join_date'] ?? null)); ?></p>
                                            </div>
                                            <div class="profile-info-item">
                                                <strong>Employment Duration:</strong>
                                                <p class="mb-0">
                                                <span class="special-badge employment-badge">
                                                    <i class="fas fa-clock me-1"></i> <?php echo htmlspecialchars($employmentDurationDisplay); ?>
                                                </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="timeline timeline-inverse mt-4">
                                <div class="time-label">
                                    <span class="bg-danger">Employment Timeline</span>
                                </div>
                                <div>
                                    <i class="fas fa-user bg-primary"></i>
                                    <div class="timeline-item">
                                        <span class="time"><i class="far fa-clock"></i> <?php echo htmlspecialchars(hrms_format_preferred_date($user_data['join_date'] ?? null)); ?></span>
                                        <h3 class="timeline-header">Joined as <?php echo htmlspecialchars($user_data['designation_title'] ?? 'Employee'); ?></h3> <!-- Changed to designation_title -->
                                        <div class="timeline-body">
                                            Joined <?php echo htmlspecialchars($user_data['branch_name']); ?> branch.
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <i class="fas fa-check bg-success"></i>
                                    <div class="timeline-item">
                                        <span class="time"><i class="far fa-clock"></i> Now</span>
                                        <h3 class="timeline-header">Current Status</h3>
                                        <div class="timeline-body">
                                            <?php echo empty($user_data['exit_date']) ? 'Currently active employee' : 'Exit on ' . htmlspecialchars(hrms_format_preferred_date($user_data['exit_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <i class="far fa-clock bg-gray"></i>
                                </div>
                            </div>
                        </div>
                        <!-- /.tab-pane -->

                        <div class="tab-pane" id="assets">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 10px">#</th>
                                            <th>Asset</th>
                                            <th>Serial Number</th>
                                            <th>Assigned Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($assigned_assets)): ?>
                                            <?php $asset_count = 1; ?>
                                            <?php foreach ($assigned_assets as $asset): ?>
                                                <tr>
                                                    <td><?php echo $asset_count++; ?>.</td>
                                                    <td><?php echo htmlspecialchars($asset['AssetName']); ?></td>
                                                    <td><?php echo htmlspecialchars($asset['AssetSerial']); ?></td>
                                                    <td><?php echo htmlspecialchars(hrms_format_preferred_date($asset['AssignmentDate'] ?? null, 'M d, Y')); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $asset['AssetStatus'] == 'Assigned' ? 'success' : 
                                                                ($asset['AssetStatus'] == 'Maintenance' ? 'warning' : 'danger'); 
                                                        ?>">
                                                            <?php echo htmlspecialchars($asset['AssetStatus']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No assets currently assigned.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- /.tab-pane -->
                        
                        <div class="tab-pane" id="notifications">
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-3">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0">Task Notification Settings</h5>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted">Configure how you want to receive notifications for task-related activities.</p>
                                            
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered">
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
                                                
                                                <div class="alert alert-info mt-3">
                                                    <i class="fas fa-info-circle"></i> 
                                                    <strong>Note:</strong> Enable email notifications to stay updated on task activities.
                                                </div>
                                                
                                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                    <button type="submit" name="save_preferences" class="btn btn-primary">
                                                        <i class="fas fa-save"></i> Save Preferences
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.tab-pane -->

                        <div class="tab-pane" id="session_location">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h5 class="card-title m-0">Active Sessions</h5>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted">Below are your active sessions. You can terminate other sessions you don't recognize.</p>
                                            <?php
                                            $session_dir = __DIR__ . '/sessions';
                                            $session_files = glob($session_dir . '/sess_*');
                                            $user_sessions = [];
                                            foreach ($session_files as $file) {
                                                $contents = @file_get_contents($file);
                                                if ($contents === false) continue;

                                                // Quick check: does this session file belong to current user?
                                                if (strpos($contents, 'user_id') !== false && strpos($contents, (string)$user_id) !== false) {
                                                    $filename = basename($file);
                                                    $sid = substr($filename, 5);

                                                    // Try to extract stored meta values, fallback to file timestamps
                                                    $created = '';
                                                    $last_activity = '';
                                                    if (preg_match('/created_at.*?i:([0-9]{10,13})/s', $contents, $m)) {
                                                        $created = date('Y-m-d H:i:s', intval($m[1]));
                                                    }
                                                    if (preg_match('/last_activity.*?i:([0-9]{10,13})/s', $contents, $m2)) {
                                                        $last_activity = date('Y-m-d H:i:s', intval($m2[1]));
                                                    }

                                                    $ip = '';
                                                    if (preg_match('/ip.*?s:\d+:"([^\"]+)"/s', $contents, $ipm)) {
                                                        $ip = $ipm[1];
                                                    }

                                                    $ua = '';
                                                    if (preg_match('/user_agent.*?s:\d+:"([^\"]+)"/s', $contents, $uam)) {
                                                        $ua = $uam[1];
                                                    }

                                                    // Extract last_location lat/lon if available in session file
                                                    $lat = '';
                                                    $lon = '';
                                                    if (preg_match('/last_location.*?s:\d+:"lat";s:\d+:"([^\"]+)"/s', $contents, $lm)) {
                                                        $lat = $lm[1];
                                                    }
                                                    if (preg_match('/last_location.*?s:\d+:"lon";s:\d+:"([^\"]+)"/s', $contents, $lm2)) {
                                                        $lon = $lm2[1];
                                                    }

                                                    $device = '';
                                                    // If session meta included a device field, try to extract it
                                                    if (preg_match('/device.*?s:\d+:"([^\"]+)"/s', $contents, $dm)) {
                                                        $device = $dm[1];
                                                    } elseif (!empty($ua)) {
                                                        // Fallback to detection based on UA string
                                                        $device = detect_device($ua);
                                                    }

                                                    // Remove any trailing browser suffix like "(Chrome)" from older sessions
                                                    if (!empty($device)) {
                                                        $device = preg_replace('/\s*\([^)]*\)\s*$/', '', $device);
                                                    }

                                                    // Derive short browser name/version
                                                    $browser = detect_browser($ua);

                                                    $user_sessions[] = [
                                                        'sid' => $sid,
                                                        'created' => $created ?: date('Y-m-d H:i:s', filemtime($file)),
                                                        'last_activity' => $last_activity ?: date('Y-m-d H:i:s', filemtime($file)),
                                                        'ip' => $ip,
                                                        'device' => $device,
                                                        'browser' => $browser,
                                                        'lat' => $lat,
                                                        'lon' => $lon,
                                                        'ua' => $ua
                                                    ];
                                                }
                                            }
                                            ?>

                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Device</th>
                                                            <th>Log on at</th>
                                                            <th>Last Active</th>
                                                            <th>Browser</th>
                                                            <th>IP</th>
                                                            <th>Location</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($user_sessions)): ?>
                                                            <?php foreach ($user_sessions as $s): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars(preg_replace('/\s*\([^)]*\)\s*$/', '', $s['device'])); ?></td>
                                                                    <td><?php echo htmlspecialchars($s['created']); ?></td>
                                                                    <td><?php echo htmlspecialchars($s['last_activity']); ?></td>
                                                                    <td><?php echo htmlspecialchars($s['browser']); ?></td>
                                                                    <td><?php echo htmlspecialchars($s['ip']); ?></td>
                                                                    <td>
                                                                        <?php if (!empty($s['lat']) && !empty($s['lon'])): ?>
                                                                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($s['lat'] . ',' . $s['lon']); ?>" target="_blank" rel="noopener">Map</a>
                                                                        <?php else: ?>
                                                                            N/A
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <?php if (session_id() !== $s['sid']): ?>
                                                                            <form method="POST" class="d-inline">
                                                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                                                <input type="hidden" name="sessid" value="<?php echo htmlspecialchars($s['sid']); ?>">
                                                                                <button type="submit" name="terminate_session" class="btn btn-sm btn-danger" onclick="return confirm('Terminate this session?');">Terminate</button>
                                                                            </form>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-success">Current</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else:
                                                            // No session files detected for this user - fall back to showing the current session if available
                                                            $meta = $_SESSION['meta'] ?? null;
                                                            if ($meta): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars(preg_replace('/\s*\([^)]*\)\s*$/', '', $meta['device'] ?? 'Unknown')); ?></td>
                                                                    <td><?php echo htmlspecialchars(isset($meta['created_at']) ? date('Y-m-d H:i:s', intval($meta['created_at'])) : 'Unknown'); ?></td>
                                                                    <td><?php echo htmlspecialchars(isset($meta['last_activity']) ? date('Y-m-d H:i:s', intval($meta['last_activity'])) : 'Unknown'); ?></td>
                                                                    <td><?php echo htmlspecialchars(detect_browser($meta['user_agent'] ?? '')); ?></td>
                                                                    <td><?php echo htmlspecialchars($meta['ip'] ?? 'Unknown'); ?></td>
                                                                    <td>
                                                                        <?php if (!empty($meta['last_location']['lat']) && !empty($meta['last_location']['lon'])): ?>
                                                                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($meta['last_location']['lat'] . ',' . $meta['last_location']['lon']); ?>" target="_blank" rel="noopener">Map</a>
                                                                        <?php else: ?>
                                                                            N/A
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge bg-success">Current</span>
                                                                    </td>
                                                                </tr>
                                                            <?php else: ?>
                                                                <tr><td colspan="7" class="text-center">No active sessions found for this account.</td></tr>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h5 class="card-title m-0">Location Access</h5>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted">Use your browser's location API to share your current coordinates and generate a short access code you can use for location-based features.</p>

                                            <div class="mb-3">
                                                <button id="requestLocationBtn" class="btn btn-outline-primary">
                                                    <i class="fas fa-location-arrow me-1"></i> Request Location
                                                </button>
                                                <small id="locationStatus" class="form-text text-muted ms-2"></small>
                                            </div>

                                            <div class="mb-2">
                                                <strong>Coordinates:</strong>
                                                <span id="locationCoords">
                                                    <?php
                                                    // Prefer an explicitly generated access location (recent), otherwise show last login location from session meta
                                                    if (isset($_SESSION['location_access'])) {
                                                        echo htmlspecialchars($_SESSION['location_access']['lat'] . ', ' . $_SESSION['location_access']['lon']);
                                                    } elseif (!empty($_SESSION['meta']['last_location'])) {
                                                        $ll = $_SESSION['meta']['last_location'];
                                                        echo htmlspecialchars($ll['lat'] . ', ' . $ll['lon']) . ' <small class="text-muted">(from last login)</small>';
                                                    } else {
                                                        echo 'Not available';
                                                    }
                                                    ?>
                                                </span>
                                            </div>

                                            <div class="mb-2">
                                                <strong>Access Code:</strong>
                                                <span id="locationCode"><?php echo isset($_SESSION['location_access']) ? htmlspecialchars($_SESSION['location_access']['code']) : 'Not generated'; ?></span>
                                                <button id="copyLocationCodeBtn" class="btn btn-sm btn-outline-secondary ms-2" style="display: <?= isset($_SESSION['location_access']) ? 'inline-block' : 'none' ?>;">Copy</button>
                                            </div>


                                            <div class="text-muted small">Privacy: This application will not store your coordinates beyond the session unless you explicitly save them in your profile.</div>
                                            <div class="mt-2 small">Why required: We use your approximate location to protect accounts and verify attendance. <a href="#" data-bs-toggle="modal" data-bs-target="#locationHelpModal">Learn more</a>.</div> 
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.tab-pane -->
                    </div>
                    <!-- /.tab-content -->
                </div><!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
        <!-- /.col -->
    </div>
    <!-- /.row -->
</div><!-- /.container-fluid -->

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const requestBtn = document.getElementById('requestLocationBtn');
    const statusEl = document.getElementById('locationStatus');
    const coordsEl = document.getElementById('locationCoords');
    const codeEl = document.getElementById('locationCode');
    const copyBtn = document.getElementById('copyLocationCodeBtn');
    const csrfToken = encodeURIComponent('<?= generate_csrf_token() ?>');

    function handlePermissionStatus(state) {
        if (state === 'granted') {
            if (requestBtn) requestBtn.style.display = 'none';
            if (statusEl) statusEl.textContent = 'Location permission: granted';
        } else if (state === 'denied') {
            if (requestBtn) requestBtn.style.display = 'none';
            if (statusEl) statusEl.textContent = 'Location permission: denied';
            // If permission denied while on this page, sign out the user
            // to enforce mandatory location permission
            setTimeout(function () { window.location = 'signout.php'; }, 1500);
        } else {
            if (requestBtn) requestBtn.style.display = 'inline-block';
            if (statusEl && (!coordsEl.textContent || coordsEl.textContent === 'Not available')) statusEl.textContent = '';
        }
    }

    // Try to query the permissions API to determine current geolocation permission
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then(function (permissionStatus) {
            handlePermissionStatus(permissionStatus.state);
            permissionStatus.onchange = function () { handlePermissionStatus(this.state); };
        }).catch(function () {
            // Permissions API available but query failed - leave button visible
            if (requestBtn) requestBtn.style.display = 'inline-block';
        });
    } else {
        // Permissions API not supported - keep the button visible (can't reliably detect state)
        if (requestBtn) requestBtn.style.display = 'inline-block';
    }

    if (requestBtn) {
        requestBtn.addEventListener('click', function () {
            statusEl.textContent = 'Requesting permission...';
            if (!navigator.geolocation) {
                statusEl.textContent = 'Geolocation not supported by your browser.';
                return;
            }

            navigator.geolocation.getCurrentPosition(function (pos) {
                const lat = pos.coords.latitude;
                const lon = pos.coords.longitude;
                coordsEl.textContent = lat + ', ' + lon;
                statusEl.textContent = 'Location obtained.';

                // Send coords to server to generate short access code
                fetch('profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'generate_location_code=1&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon) + '&csrf_token=' + csrfToken
                }).then(r => r.json()).then(j => {
                    if (j && j.success) {
                        codeEl.textContent = j.code;
                        copyBtn.style.display = 'inline-block';
                    } else {
                        statusEl.textContent = j.message || 'Failed to generate access code.';
                    }
                }).catch(() => {
                    statusEl.textContent = 'Failed to communicate with server.';
                });

            }, function (err) {
                statusEl.textContent = 'Error: ' + (err.message || 'Permission denied or unavailable.');
            }, { enableHighAccuracy: true, timeout: 10000 });
        });
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            const code = codeEl.textContent.trim();
            if (!code || code === 'Not generated') return;
            navigator.clipboard.writeText(code).then(function () {
                copyBtn.textContent = 'Copied';
                setTimeout(() => copyBtn.textContent = 'Copy', 1500);
            });
        });
    }

});
</script>

<!-- Crop Modal -->
<div class="modal fade" id="cropModal" tabindex="-1" aria-labelledby="cropModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cropModalLabel">Crop Profile Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="img-container">
                    <img id="imageToCrop" src="" alt="Image to Crop">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="cropButton">Crop & Upload</button>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="password-change-form">
                    <form id="passwordResetForm" action="profile.php" method="post" class="form">
                        <div class="mb-3">
                            <label for="current_password_modal" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password_modal" name="current_password" placeholder="Current Password" required>
                            <div class="d-flex justify-content-end mt-1">
                                <a href="#" id="forgotPasswordLink" class="text-primary small">Forgot Password?</a>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="new_password_modal" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password_modal" name="new_password" placeholder="New Password" required>
                                <button type="button" class="btn btn-secondary" id="generatePassword">Generate</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password_modal" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password_modal" name="confirm_password" placeholder="Confirm Password" required>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="showPassword">
                                <label class="form-check-label" for="showPassword">Show Password</label>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </div>
                    </form>
                </div>
                
                <div id="forgot-password-form" style="display: none;">
                    <div class="text-center mb-4">
                        <h4>Reset Password</h4>
                        <p class="text-muted">A reset link will be sent to your email</p>
                    </div>
                    <form id="forgotPasswordForm" action="profile.php" method="post" class="form">
                        <input type="hidden" name="forgot_password_request" value="1">
                        <div class="mb-3">
                            <label for="email_reset" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email_reset" name="email_reset" readonly disabled value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            <small class="form-text text-muted">Your registered email address</small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button type="submit" class="btn btn-primary">Send Reset Link</button>
                            <a href="#" id="backToLoginLink" class="text-primary">Back to Change Password</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- Cropper.js JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(function() {
        let cropper;
        let cropModal;
        
        // Initialize Bootstrap 5 modals
        const cropModalEl = document.getElementById('cropModal');
        if (cropModalEl) {
            cropModal = new bootstrap.Modal(cropModalEl);
        }
        
        const passwordModalEl = document.getElementById('changePasswordModal');
        let passwordModal;
        if (passwordModalEl) {
            passwordModal = new bootstrap.Modal(passwordModalEl);
        }
        
        // Hide loading overlay when page is loaded
        $("#loadingOverlay").fadeOut();
        
        // Function to open crop modal and initialize cropper
        window.openCropModal = function(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#imageToCrop').attr('src', e.target.result);
                
                // Show modal using Bootstrap 5 method
                cropModal.show();
                
                // Initialize cropper with a small delay to ensure the modal is visible
                setTimeout(() => {
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    cropper = new Cropper($('#imageToCrop')[0], {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 1,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false
                    });
                }, 300);
            };
            reader.readAsDataURL(file);
        };
        
        // Handle crop button click
        $('#cropButton').on('click', function() {
            const canvas = cropper.getCroppedCanvas({
                width: 300,
                height: 300,
                minWidth: 100,
                minHeight: 100,
                maxWidth: 1000,
                maxHeight: 1000,
                fillColor: '#fff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            
            const dataURL = canvas.toDataURL('image/png');
            $('#croppedImage').val(dataURL);
            $('#profileImage').attr('src', dataURL);
            
                    icon: 'error',
                    confirmButtonText: 'Try Again'
                });
                return false;
            }
            
            // Show loading overlay
            $("#loadingOverlay").fadeIn();
            // Form will submit normally and page will reload
        });
        
        // Initialize tabs with Bootstrap 5
        document.querySelectorAll('.nav-tabs .nav-link').forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const tabId = this.getAttribute('href');
                
                // Remove active class from all tabs and contents
                document.querySelectorAll('.nav-tabs .nav-link').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active', 'show'));
                
                // Add active class to current tab and content
                this.classList.add('active');
                document.querySelector(tabId).classList.add('active', 'show');
            });
        });

        // Toggle between password change and forgot password forms
        $('#forgotPasswordLink').on('click', function(e) {
            e.preventDefault();
            // Change modal title to "Reset Password"
            $('#changePasswordModalLabel').text('Reset Password');
            $('#password-change-form').hide();
            $('#forgot-password-form').show();
        });

        $('#backToLoginLink').on('click', function(e) {
            e.preventDefault();
            // Change modal title back to "Change Password"
            $('#changePasswordModalLabel').text('Change Password');
            $('#forgot-password-form').hide();
            $('#password-change-form').show();
        });

        // Generate random password for reset form
        $('#generatePasswordReset').on('click', function() {
            const length = 12;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
            let password = "";
            
            for (let i = 0; i < length; i++) {
                const randomIndex = Math.floor(Math.random() * charset.length);
                password += charset.charAt(randomIndex);
            }
            
            $('#new_password_reset').val(password);
            $('#confirm_password_reset').val(password);
            
            // Show password after generation
            $('#new_password_reset, #confirm_password_reset').attr('type', 'text');
            $('#showPasswordReset').prop('checked', true);
        });

        // Show/hide password for reset form
        $('#showPasswordReset').on('change', function() {
            const type = this.checked ? 'text' : 'password';
            $('#new_password_reset, #confirm_password_reset').attr('type', type);
        });

        // Forgot password form submission
        $('#forgotPasswordForm').on('submit', function(e) {
            // Show loading overlay
            $("#loadingOverlay").fadeIn();
            // Form will submit normally and page will reload
        });
    });
</script>

<script>
// Enforce Nepali digits on profile content in BS mode
(function() {
    if (!window.hrmsUseBsDates || typeof window.hrmsToNepaliDigits !== 'function') {
        return;
    }

    const root = document.querySelector('.container-fluid');
    if (!root) return;

    const convertNodeText = (node) => {
        if (node && node.nodeType === Node.TEXT_NODE && node.nodeValue && /[0-9]/.test(node.nodeValue)) {
            node.nodeValue = window.hrmsToNepaliDigits(node.nodeValue);
        }
    };

    const walk = (el) => {
        if (!el || el.nodeType === Node.COMMENT_NODE) return;
        if (el.nodeType === Node.TEXT_NODE) {
            convertNodeText(el);
            return;
        }
        // Skip inputs/textareas to avoid altering user input
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') return;
        let child = el.firstChild;
        while (child) {
            walk(child);
            child = child.nextSibling;
        }
    };

    const enforce = () => walk(root);
    enforce();

    if (window.MutationObserver) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((m) => {
                if (m.type === 'characterData') {
                    convertNodeText(m.target);
                } else if (m.addedNodes && m.addedNodes.length) {
                    m.addedNodes.forEach((n) => walk(n));
                }
            });
        });
        observer.observe(root, { childList: true, subtree: true, characterData: true });
    }
})();
</script>
</body>
</html>
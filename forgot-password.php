<?php
// Include session configuration first
require_once 'includes/session_config.php';

// Include database connection
include 'includes/db_connection.php';
include 'includes/configuration.php';
include 'includes/settings.php';
include 'includes/utilities.php';
include 'includes/csrf_protection.php';

// Function to adjust color brightness (needed for styling)
function adjustBrightness($hex, $steps) {
    // Steps should be between -255 and 255. Negative = darker, positive = lighter
    $steps = max(-255, min(255, $steps));

    // Format the hex color string
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
    }

    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Adjust
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));

    // Convert back to hex
    $r_hex = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
    $g_hex = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
    $b_hex = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);

    return '#' . $r_hex . $g_hex . $b_hex;
}

// Set page title
$page = 'Forgot Password';

// Define app name variable
$appName = APP_NAME;

// Define company short name variable
$companyShortName = COMPANY_NAME;

// Define the $is_auth_page variable to prevent redirection loops
$is_auth_page = true;

// Get company logo from settings if available
$companyLogo = defined('COMPANY_LOGO') ? COMPANY_LOGO : 'resources/images/company_logo.png';
// Check if logo exists, if not fall back to favicon
$logoExists = file_exists(__DIR__ . '/' . $companyLogo);
$displayLogo = $logoExists ? $companyLogo : 'resources/images/favicon.svg';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (isset($_POST['csrf_token']) && verify_csrf_token($_POST['csrf_token'])) {
        
        // Check which form was submitted
        if (isset($_POST['request_reset'])) {
            // Password reset request form
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Check if the email exists in the database
                $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM employees WHERE email = ? AND login_access = '1'");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate a unique token and store it in the database with expiration time
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    
                    // Check if a reset token already exists for this user and update it, or insert a new one
                    $checkStmt = $pdo->prepare("SELECT id FROM password_resets WHERE employee_id = ?");
                    $checkStmt->execute([$user['id']]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        // Update existing token
                        $updateStmt = $pdo->prepare("UPDATE password_resets SET token = ?, expires_at = ? WHERE employee_id = ?");
                        $updateStmt->execute([$token, $expiry, $user['id']]);
                    } else {
                        // Insert new token
                        $insertStmt = $pdo->prepare("INSERT INTO password_resets (employee_id, token, expires_at) VALUES (?, ?, ?)");
                        $insertStmt->execute([$user['id'], $token, $expiry]);
                    }
                    
                    // Build the reset URL
                    $resetUrl = "http" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/forgot-password.php?token=" . $token;
                    
                    // Email content
                    $subject = $appName . " - Password Reset Request";
                    $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background-color: " . PRIMARY_COLOR . "; color: white; padding: 10px 20px; }
                            .content { padding: 20px; border: 1px solid #ddd; }
                            .button { display: inline-block; padding: 10px 20px; background-color: " . PRIMARY_COLOR . "; color: white; text-decoration: none; border-radius: 5px; }
                            .footer { font-size: 12px; color: #777; margin-top: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>" . $appName . " - Password Reset</h2>
                            </div>
                            <div class='content'>
                                <p>Hello " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ",</p>
                                <p>We received a request to reset your password for your account at " . $appName . ".</p>
                                <p>Please click the button below to set a new password:</p>
                                <p><a href='" . $resetUrl . "' class='button'>Reset Password</a></p>
                                <p>Alternatively, you can copy and paste the following link into your browser:</p>
                                <p>" . $resetUrl . "</p>
                                <p>This link will expire in 30 minutes for security reasons.</p>
                                <p>If you didn't request this password reset, please ignore this email or contact your system administrator if you have concerns.</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " " . $companyShortName . " - All rights reserved</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    // Set email headers
                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: " . SYSTEM_EMAIL . "\r\n";
                    
                    // Send the email
                    if (mail($user['email'], $subject, $message, $headers)) {
                        $_SESSION['success'] = "Password reset instructions have been sent to your email address. Please check your inbox and spam folder.";
                    } else {
                        $_SESSION['error'] = "Failed to send password reset email. Please contact the administrator.";
                    }
                } else {
                    // For security reasons, we still show a success message even if the email doesn't exist
                    $_SESSION['success'] = "If your email address exists in our database, you will receive a password reset link at your email address.";
                }
                
                // Redirect back to the forgot password page to prevent form resubmission
                header('Location: forgot-password.php');
                exit();
            } else {
                $_SESSION['error'] = "Please enter a valid email address.";
            }
        } elseif (isset($_POST['reset_password'])) {
            // Reset password form
            $token = $_POST['token'];
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Validate passwords
            if (strlen($password) < 8) {
                $_SESSION['error'] = "Password must be at least 8 characters long.";
            } elseif ($password !== $confirm_password) {
                $_SESSION['error'] = "Passwords do not match.";
            } else {
                // Check if token is valid and not expired
                $stmt = $pdo->prepare("
                    SELECT pr.employee_id, pr.expires_at 
                    FROM password_resets pr
                    WHERE pr.token = ? AND pr.expires_at > NOW()
                ");
                $stmt->execute([$token]);
                $reset = $stmt->fetch();
                
                if ($reset) {
                    // Update the user's password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?");
                    $updateStmt->execute([$hashedPassword, $reset['employee_id']]);
                    
                    // Delete the used token
                    $deleteStmt = $pdo->prepare("DELETE FROM password_resets WHERE employee_id = ?");
                    $deleteStmt->execute([$reset['employee_id']]);
                    
                    $_SESSION['success'] = "Your password has been reset successfully. You can now login with your new password.";
                    // Redirect to login page
                    header('Location: index.php');
                    exit();
                } else {
                    $_SESSION['error'] = "The password reset link is invalid or has expired. Please request a new one.";
                }
            }
        }
    } else {
        $_SESSION['error'] = "Invalid request. Please try again.";
    }
}

// Check if a token is provided in the URL for password reset
$showResetForm = false;
$tokenValid = false;
$token = '';

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if token is valid and not expired
    $stmt = $pdo->prepare("
        SELECT expires_at FROM password_resets 
        WHERE token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    
    if ($stmt->rowCount() > 0) {
        $showResetForm = true;
        $tokenValid = true;
    } else {
        $_SESSION['error'] = "The password reset link is invalid or has expired. Please request a new one.";
    }
}

// Generate a CSRF token for the forms
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $appName; ?> | Forgot Password</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#0d6efd">
    <meta name="description" content="Human Resource Management System">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="resources/images/icon-192x192.png">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="resources/images/favicon.png">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Custom Styling -->
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR; ?>;
            --primary-hover: <?php echo adjustBrightness(PRIMARY_COLOR, -10); ?>;
            --dark-color: #212529;
            --light-color: #f8f9fa;
        }

        .text-primary{
            color: <?php echo PRIMARY_COLOR; ?> !important;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa, #e4e8f0);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        body.dark-mode {
            background: linear-gradient(135deg, #212529, #343a40);
            color: var(--light-color);
        }
        
        .forgot-password-wrapper {
            width: 100%;
            max-width: 450px;
            padding: 1rem;
        }
        
        .forgot-password-card {
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .forgot-password-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.15);
        }
        
        .card-header-logo {
            padding: 2rem 1rem 1rem;
        }
        
        .form-control, .input-group-text {
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.6rem 1.5rem;
            border-radius: 50rem;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary:active {
            transform: translateY(0);
            box-shadow: none;
        }
        
        /* Company logo styling */
        .company-logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 10px;
        }
        
        /* Password strength indicator */
        .password-strength-meter {
            height: 5px;
            width: 100%;
            background-color: #e9ecef;
            margin-top: 0.5rem;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .password-strength-meter div {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 5px;
        }
        
        .strength-weak {
            background-color: #dc3545;
            width: 25% !important;
        }
        
        .strength-medium {
            background-color: #ffc107;
            width: 50% !important;
        }
        
        .strength-good {
            background-color: #28a745;
            width: 75% !important;
        }
        
        .strength-strong {
            background-color: #20c997;
            width: 100% !important;
        }
        
        /* Dark mode support */
        .dark-mode .forgot-password-card {
            background-color: #343a40;
            color: var(--light-color);
        }
        
        .dark-mode .form-control, 
        .dark-mode .input-group-text {
            background-color: #495057;
            border-color: #495057;
            color: var(--light-color);
        }
        
        .dark-mode .form-control::placeholder {
            color: #adb5bd;
        }
        
        .dark-mode .text-dark {
            color: var(--light-color) !important;
        }
        
        .dark-mode .text-muted {
            color: #adb5bd !important;
        }
        
        .theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1000;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background-color: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }
        
        .theme-toggle:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        
        .dark-mode .theme-toggle {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body>
    <!-- Theme Toggle Button -->
    <div class="theme-toggle" id="theme-toggle">
        <i class="fas fa-moon text-dark"></i>
    </div>

    <div class="forgot-password-wrapper">
        <!-- Forgot Password Card -->
        <div class="card border-0 forgot-password-card">
            <div class="card-header-logo text-center bg-transparent border-0">
                <a href="index.php" class="text-decoration-none">
                    <img src="<?php echo $displayLogo; ?>" alt="Company Logo" class="company-logo mb-2">
                    <h2 class="mb-0">
                        <span class="text-primary fw-bold"><?php echo $appName; ?></span>
                    </h2>
                    <p class="text-muted small">HR Management System</p>
                </a>
            </div>
            <div class="card-body px-4 py-4">
                <?php if ($showResetForm && $tokenValid): ?>
                <!-- Password Reset Form -->
                <h5 class="text-center mb-4">Reset Your Password</h5>
                <form action="forgot-password.php" method="post" id="password-reset-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-primary"></i>
                            </span>
                            <input type="password" class="form-control border-start-0" name="password" id="password" placeholder="New Password" required>
                        </div>
                        <div class="password-strength-meter mt-2">
                            <div id="password-strength-indicator"></div>
                        </div>
                        <small id="password-strength-text" class="form-text text-muted">Password must be at least 8 characters long</small>
                    </div>
                    
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-primary"></i>
                            </span>
                            <input type="password" class="form-control border-start-0" name="confirm_password" id="confirm-password" placeholder="Confirm Password" required>
                        </div>
                        <small id="password-match-text" class="form-text text-muted"></small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="reset_password" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Reset Password
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <!-- Request Password Reset Form -->
                <h5 class="text-center mb-4">Forgot Your Password?</h5>
                <p class="text-muted mb-4">Enter your email address below and we'll send you a link to reset your password.</p>
                
                <form action="forgot-password.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-primary"></i>
                            </span>
                            <input type="email" class="form-control border-start-0" name="email" placeholder="Email Address" required>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" name="request_reset" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                        </button>
                    </div>
                </form>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="index.php" class="text-decoration-none text-primary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 text-center py-3">
                <small class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo $companyShortName; ?> - All rights reserved</small>
            </div>
        </div>
    </div>

    <!-- REQUIRED SCRIPTS -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Notification Script for Alerts -->
    <script>
    function showNotification(type, message) {
        Swal.fire({
            icon: type,
            title: type.charAt(0).toUpperCase() + type.slice(1),
            text: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
    }

    // Show error message if exists
    <?php if(isset($_SESSION['error'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showNotification('error', '<?php echo addslashes($_SESSION['error']); ?>');
    });
    <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    // Show success message if exists
    <?php if(isset($_SESSION['success'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showNotification('success', '<?php echo addslashes($_SESSION['success']); ?>');
    });
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    </script>

    <!-- Password Strength Checker Script -->
    <script>
    $(document).ready(function() {
        const passwordInput = $('#password');
        const confirmPasswordInput = $('#confirm-password');
        const strengthIndicator = $('#password-strength-indicator');
        const strengthText = $('#password-strength-text');
        const passwordMatchText = $('#password-match-text');
        
        // Check password strength
        passwordInput.on('input', function() {
            const password = $(this).val();
            let strength = 0;
            
            // Length check
            if (password.length >= 8) {
                strength += 1;
            }
            
            // Contains uppercase
            if (/[A-Z]/.test(password)) {
                strength += 1;
            }
            
            // Contains number
            if (/[0-9]/.test(password)) {
                strength += 1;
            }
            
            // Contains special char
            if (/[^A-Za-z0-9]/.test(password)) {
                strength += 1;
            }
            
            // Update strength indicator
            strengthIndicator.removeClass('strength-weak strength-medium strength-good strength-strong');
            
            if (password.length === 0) {
                strengthIndicator.css('width', '0%');
                strengthText.text('Password must be at least 8 characters long');
                strengthText.removeClass('text-danger text-warning text-success');
                strengthText.addClass('text-muted');
            } else if (strength === 1) {
                strengthIndicator.addClass('strength-weak');
                strengthText.text('Weak password');
                strengthText.removeClass('text-muted text-warning text-success');
                strengthText.addClass('text-danger');
            } else if (strength === 2) {
                strengthIndicator.addClass('strength-medium');
                strengthText.text('Medium password');
                strengthText.removeClass('text-muted text-danger text-success');
                strengthText.addClass('text-warning');
            } else if (strength === 3) {
                strengthIndicator.addClass('strength-good');
                strengthText.text('Good password');
                strengthText.removeClass('text-muted text-danger text-warning');
                strengthText.addClass('text-success');
            } else if (strength === 4) {
                strengthIndicator.addClass('strength-strong');
                strengthText.text('Strong password');
                strengthText.removeClass('text-muted text-danger text-warning');
                strengthText.addClass('text-success');
            }
            
            // Check if passwords match
            checkPasswordsMatch();
        });
        
        // Check if passwords match
        confirmPasswordInput.on('input', checkPasswordsMatch);
        
        function checkPasswordsMatch() {
            const password = passwordInput.val();
            const confirmPassword = confirmPasswordInput.val();
            
            if (confirmPassword.length === 0) {
                passwordMatchText.text('');
                return;
            }
            
            if (password === confirmPassword) {
                passwordMatchText.text('Passwords match');
                passwordMatchText.removeClass('text-danger');
                passwordMatchText.addClass('text-success');
            } else {
                passwordMatchText.text('Passwords do not match');
                passwordMatchText.removeClass('text-success');
                passwordMatchText.addClass('text-danger');
            }
        }
    });
    </script>

    <!-- Dark Mode Toggle Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const themeToggleBtn = document.getElementById('theme-toggle');
        
        // Function to update theme based on preference
        function setTheme(isDark) {
            if (isDark) {
                document.body.classList.add('dark-mode');
                themeToggleBtn.innerHTML = '<i class="fas fa-sun text-warning"></i>';
                localStorage.setItem('dark-mode', 'true');
            } else {
                document.body.classList.remove('dark-mode');
                themeToggleBtn.innerHTML = '<i class="fas fa-moon text-dark"></i>';
                localStorage.setItem('dark-mode', 'false');
            }
        }
        
        // Check for saved theme preference or use system preference
        const savedTheme = localStorage.getItem('dark-mode');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        // Set initial theme
        setTheme(savedTheme === 'true' || (savedTheme === null && prefersDark));
        
        // Toggle theme on button click
        themeToggleBtn.addEventListener('click', function() {
            setTheme(!document.body.classList.contains('dark-mode'));
        });
    });
    </script>
</body>
</html>
<?php
// Include session configuration first
require_once 'includes/session_config.php';

// Include database connection
include 'includes/db_connection.php';
include 'includes/configuration.php';

// Check if user is already logged in and this is a direct page access (not a form submission)
if (isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
    // Determine where to redirect based on user role
    $dashboard = $_SESSION['user_role'] == '1' ? 'admin-dashboard.php' : 'employee-dashboard.php';
    header("Location: $dashboard");
    exit();
}

// Handling login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Fetch user from the database
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Password is correct
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['designation'] = $user['designation'];
        $_SESSION['fullName'] = $user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name'];
        $_SESSION['userImage'] = $user['user_image'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_access'] = $user['login_access'];

        // Check login access
        if ($user['login_access'] == '0') {
            $_SESSION['error'] = "Access Denied.";
        } else {
            // Redirect based on session or role
            if (isset($_SESSION['redirect_to'])) {
                $redirect_to = $_SESSION['redirect_to'];
                unset($_SESSION['redirect_to']); // Clear the session variable
                header("Location: " . $redirect_to);
                exit();
            } else {
                $dashboard = $user['role'] == '1' ? 'admin-dashboard.php' : 'employee-dashboard.php';
                header('Location: ' . $dashboard);
                exit();
            }
        }
    } else {
        // Invalid credentials
        $_SESSION['error'] = "Invalid email or password.";
    }
}

// Set page title
$page = 'Login';

// Define the $is_auth_page variable to prevent redirection loops in header.php
$is_auth_page = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $appName; ?> | Login</title>
    
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
    
    <!-- Custom Styling for Login Page -->
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-hover: #0b5ed7;
            --dark-color: #212529;
            --light-color: #f8f9fa;
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
        
        .login-wrapper {
            width: 100%;
            max-width: 400px;
            padding: 1rem;
        }
        
        .login-card {
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .login-card:hover {
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
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        /* Dark mode support */
        .dark-mode .login-card {
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

    <div class="login-wrapper">
        <!-- Login Card -->
        <div class="card border-0 login-card">
            <div class="card-header-logo text-center bg-transparent border-0">
                <a href="index.php" class="text-decoration-none">
                    <img src="resources/images/favicon.svg" alt="Logo" class="img-fluid mb-2" style="width: 60px;">
                    <h2 class="mb-0">
                        <span class="text-primary fw-bold"><?php echo $appName; ?></span>
                    </h2>
                    <p class="text-muted small">HR Management System</p>
                </a>
            </div>
            <div class="card-body px-4 py-4">
                <h5 class="text-center mb-4">Sign in to your account</h5>

                <!-- Login Form -->
                <form action="index.php" method="post">
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-primary"></i>
                            </span>
                            <input type="email" class="form-control border-start-0" name="email" placeholder="Email" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-primary"></i>
                            </span>
                            <input type="password" class="form-control border-start-0" name="password" placeholder="Password" required>
                        </div>
                    </div>
                    <div class="row mb-4 align-items-center">
                        <div class="col-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember Me</label>
                            </div>
                        </div>
                        <div class="col-6 text-end">
                            <button type="submit" class="btn btn-primary">
                                Sign In
                            </button>
                        </div>
                    </div>
                    <div class="text-center">
                        <a href="forgot-password.php" class="text-decoration-none text-primary">
                            <i class="fas fa-lock me-1"></i>Forgot password?
                        </a>
                    </div>
                </form>
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

    <!-- Notification Script for Login Errors -->
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

<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection first
include 'includes/db_connection.php';

// Debugging: Check database connection
if (!$pdo) {
    die("Database connection failed.");
}

// Redirect logged-in users to the dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: admin-dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Debugging: Log input values
    error_log("Login attempt: Email - $email");

    // Fetch user from the database
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Password is correct
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
                header('Location: ' . ($user['role'] == '1' ? 'admin-dashboard.php' : 'dashboard.php'));
                exit();
            }
        }
    } else {
        // Debugging: Log invalid credentials
        error_log("Invalid login attempt for email: $email");
        // Invalid credentials
        $_SESSION['error'] = "Invalid email or password.";
    }
}

// Set page title
$page = 'Login';

// Include header after all redirects
include 'includes/header.php';

// Display error message as a toast notification
if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    echo "<script>
        $(document).ready(function() {
            showErrorToast('$message');
        });
    </script>";
    unset($_SESSION['error']);
}
?>
<body class="hold-transition login-page">
<div class="login-box">
    <!-- Login Card -->
    <div class="card card-outline card-primary shadow-lg">
        <div class="card-header text-center">
            <a href="index.php" class="h1"><b>Attendance</b>Pro</a>
        </div>
        <div class="card-body">
            <p class="login-box-msg text-muted">Sign in to start your session</p>

            <!-- Login Form -->
            <form action="index.php" method="post">
                <div class="input-group mb-4">
                    <input type="email" class="form-control" name="email" placeholder="Email" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-4">
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-8">
                        <div class="icheck-primary">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember Me</label>
                        </div>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary btn-block rounded-pill">Sign In</button>
                    </div>
                </div>
            </form>

            <p class="mt-3 mb-1 text-center">
                <a href="forgot-password.html" class="text-primary">I forgot my password</a>
            </p>
        </div>
    </div>
</div>

<!-- Custom Styling -->
<style>
    .login-page {
        background: linear-gradient(135deg, #ffffff, #f8f9fa);
        color: #343a40;
    }
    .login-page.dark-mode {
        background: linear-gradient(135deg, #2c3e50, #34495e);
        color: #ecf0f1;
    }
    .login-box {
        width: 400px;
    }
    .card {
        border-radius: 15px;
        background-color: #ffffff;
        color: #343a40;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .dark-mode .card {
        background-color: #2c3e50;
        color: #ecf0f1;
    }
    .btn-primary {
        background-color: #2980b9;
        border-color: #2980b9;
    }
    .btn-primary:hover {
        background-color: #1f618d;
        border-color: #1a5276;
    }
    input:-webkit-autofill {
        -webkit-box-shadow: 0 0 0px 1000px #ffffff inset !important;
        -webkit-text-fill-color: #343a40 !important;
    }
    input:-webkit-autofill:focus {
        -webkit-box-shadow: 0 0 0px 1000px #ffffff inset !important;
        -webkit-text-fill-color: #343a40 !important;
    }
    .dark-mode input:-webkit-autofill {
        -webkit-box-shadow: 0 0 0px 1000px #34495e inset !important;
        -webkit-text-fill-color: #ecf0f1 !important;
    }
    .dark-mode input:-webkit-autofill:focus {
        -webkit-box-shadow: 0 0 0px 1000px #34495e inset !important;
        -webkit-text-fill-color: #ecf0f1 !important;
    }
    .input-group-text {
        background-color: #2980b9;
        color: #ffffff;
    }
    .dark-mode .input-group-text {
        background-color: #2980b9;
        color: #ffffff;
    }
    .text-muted {
        color: #6c757d !important;
    }
    .dark-mode .text-muted {
        color: #adb5bd !important;
    }
</style>

<!-- Scripts -->
<script src="../../plugins/jquery/jquery.min.js"></script>
<script src="../../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../../dist/js/adminlte.min.js"></script>

<!-- Dark Mode Script -->
<script>
$(document).ready(function() {
    // Function to update theme based on preference
    function setTheme(theme) {
        if (theme === 'dark-mode') {
            $('body').addClass('dark-mode');
        } else {
            $('body').removeClass('dark-mode');
        }
    }

    // Function to get system theme preference
    function getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark-mode' : '';
    }

    // Initialize theme on page load
    const systemTheme = getSystemTheme();
    setTheme(systemTheme);

    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addListener(function(e) {
        setTheme(e.matches ? 'dark-mode' : '');
    });
});
</script>
</body>
</html>

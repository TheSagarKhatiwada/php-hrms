<?php
// Include session configuration first
require_once 'includes/session_config.php';

// Include database connection and utilities
include 'includes/db_connection.php';
include 'includes/configuration.php';

// Ensure database is properly connected and configured
if (!checkDatabaseHealth()) {
    // Redirect to setup if database health check fails
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['REQUEST_URI']);
    $setup_url = $protocol . '://' . $host . rtrim($path, '/') . '/setup.php';
    header("Location: " . $setup_url);
    exit();
}

include 'includes/settings.php';
include 'includes/utilities.php'; // Add this line to include utilities.php

// Define the application name
$appName = get_setting('app_name', 'App Name');

// Fetch saved company short name
$companyShortName = get_setting('company_name', 'Company Short Name');

// Fetch saved company full name
$companyFullName = get_setting('company_full_name', 'Company Full Name');

// Fetch saved company logo URL
$companyLogoURL = get_setting('company_logo', 'resources\images\company_logo.png');

// Fetch saved Primary and secondary colors
$primaryColor = get_setting('company_primary_color', '#FFFFFF');
$secondaryColor = get_setting('company_secondary_color', '#000000');

// Function to adjust color brightness
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

// Check if user is already logged in and this is a direct page access (not a form submission)
if (isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
    // Determine where to redirect based on user role
    $dashboard = $_SESSION['user_role'] == '1' ? 'admin-dashboard.php' : 'dashboard.php';
    header("Location: $dashboard");
    exit();
}

// Handling login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_id = $_POST['login_id'];
    $password = $_POST['password'];

    // Fetch user from the database
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? OR emp_id = ?");
    $stmt->execute([$login_id, $login_id]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Password is correct - require geolocation coordinates from the client
        $lat = isset($_POST['lat']) ? trim($_POST['lat']) : null;
        $lon = isset($_POST['lon']) ? trim($_POST['lon']) : null;

        // Basic validation for coordinates
        if (!is_numeric($lat) || !is_numeric($lon)) {
            // Don't set session - require location access
            $_SESSION['error'] = 'Location permission required to login. Please allow location access in your browser and try again.';
        } else {
            // Set session variables now that we have coordinates
            $_SESSION['user_id'] = $user['emp_id'];
            $_SESSION['designation'] = $user['designation_id'];
            $_SESSION['fullName'] = $user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name'];
            $_SESSION['userImage'] = $user['user_image'];
            // Check which role field exists in the database and use it
            $_SESSION['user_role'] = isset($user['role']) ? $user['role'] : (isset($user['role_id']) ? $user['role_id'] : '0');
            $_SESSION['user_role_id'] = isset($user['role_id']) ? $user['role_id'] : (isset($user['role']) ? $user['role'] : '0'); // Add this for consistency
            $_SESSION['login_access'] = $user['login_access'];

            // Save last location to session meta
            if (!isset($_SESSION['meta'])) $_SESSION['meta'] = [];
            $_SESSION['meta']['last_location'] = ['lat' => $lat, 'lon' => $lon, 'ts' => time()];

            // Log the login activity including lat/lon
            try {
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $details = 'Browser: ' . $userAgent . ' | coords: ' . $lat . ',' . $lon;
                $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (:user_id, 'login', :details, :ip, NOW())");
                $logStmt->execute([
                    ':user_id' => $user['emp_id'],
                    ':details' => $details,
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
                ]);
            } catch (PDOException $e) {
                // Silently fail if logging fails
                error_log('Activity log error: ' . $e->getMessage());
            }

            // Check login access
            if ($user['login_access'] == '0') {
                // Clear the session since this user shouldn't be logged in
                session_unset();
                session_destroy();
                // Start a fresh session to be able to show the error message
                session_start();
                $_SESSION['error'] = "Access Denied. Your account is currently disabled. Please contact the administrator.";
                // Force reload of the page to clear the form
                header('Location: index.php');
                exit();
            } else {
                // Redirect based on session or role
                if (isset($_SESSION['redirect_to'])) {
                    $redirect_to = $_SESSION['redirect_to'];
                    unset($_SESSION['redirect_to']); // Clear the session variable
                    header("Location: " . append_sid($redirect_to));
                    exit();
                } else {
                    // Make the redirection code consistent with the session variable
                    $dashboard = $_SESSION['user_role'] == '1' ? 'admin-dashboard.php' : 'dashboard.php';
                    header('Location: ' . append_sid($dashboard));
                    exit();
                }
            }
        }
    } else {
        // Invalid credentials
        $_SESSION['error'] = "Invalid login ID or password.";
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
            --primary-color: <?php echo PRIMARY_COLOR; ?>;
            --primary-hover: <?php echo adjustBrightness(PRIMARY_COLOR, -10); ?>;
            --dark-color: #212529;
            --light-color: #f8f9fa;
        }

        .text-primary{
            color: <?php echo $primaryColor; ?> !important;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            /* Use CSS variables so theme (light/dark) can override background without hardcoding colors */
            background: var(--login-background, linear-gradient(135deg, var(--bg-light-start, #f5f7fa), var(--bg-light-end, #e4e8f0)));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        body.dark-mode {
            /* Dark theme fallback using variables */
            background: var(--login-background-dark, linear-gradient(135deg, var(--bg-dark-start, #212529), var(--bg-dark-end, #343a40)));
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
        
        /* Company logo styling */
        .company-logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 10px;
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
                    <img src="<?php echo $companyLogoURL; ?>" alt="Company Logo" class="company-logo mb-2">
                    <h2 class="mb-0">
                        <span class="text-primary fw-bold"><?php echo $appName; ?></span>
                    </h2>
                    <p class="text-muted small">HR Management System</p>
                </a>
            </div>
            <div class="card-body px-4 py-4">
                <h5 class="text-center mb-4">Sign in to your account</h5>

                <!-- Login Form -->
                <form id="loginForm" action="index.php" method="post">
                    <?php echo sid_field(); // Add session ID field ?>
                    <input type="hidden" name="lat" id="login_lat">
                    <input type="hidden" name="lon" id="login_lon">

                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-user text-primary"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="login_id" placeholder="Email or Employee ID" required>
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
                        <a href="<?php echo append_sid('forgot-password.php'); ?>" class="text-decoration-none text-primary">
                            <i class="fas fa-lock me-1"></i>Forgot password?
                        </a>
                    </div>
                    <div class="text-center mt-2">
                        <small class="text-muted">Location permission is required to sign in for security and attendance verification. <a href="#" data-bs-toggle="modal" data-bs-target="#locationHelpModal">Learn how to enable it</a>.</small>
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

    <script>
    // Ensure location permission is provided before submitting login
    (function () {
        const form = document.getElementById('loginForm');
        const latInput = document.getElementById('login_lat');
        const lonInput = document.getElementById('login_lon');

        function requestLocationAndSubmit(e) {
            e.preventDefault();

            if (latInput.value && lonInput.value) {
                form.submit();
                return;
            }

            if (!navigator.geolocation) {
                Swal.fire({ icon: 'error', title: 'Location not supported', text: 'Your browser does not support geolocation. Please enable it or use a supported browser.' });
                return;
            }

            Swal.fire({ title: 'Requesting location permission', text: 'Please allow location access to continue', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            navigator.geolocation.getCurrentPosition(function (pos) {
                latInput.value = pos.coords.latitude;
                lonInput.value = pos.coords.longitude;
                Swal.close();
                form.submit();
            }, function (err) {
                Swal.close();
                Swal.fire({ icon: 'error', title: 'Location required', text: 'Location permission is required to sign in. Please allow location access and try again.' });
            }, { enableHighAccuracy: true, timeout: 10000 });
        }

        if (form) {
            form.addEventListener('submit', requestLocationAndSubmit);
        }
    })();
    </script>

    <!-- Location Help Modal (available on the login page) -->
    <div class="modal fade" id="locationHelpModal" tabindex="-1" aria-labelledby="locationHelpModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="locationHelpModalLabel">Why we require location permission</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>We require your location to help secure your account and verify expected presence for attendance-related features. We only use approximate coordinates and do not share them without consent.</p>
            <h6>How to enable location</h6>
            <ul>
              <li><strong>Chrome (desktop/mobile):</strong> Click the padlock in the address bar → <em>Site settings</em> → <em>Location</em> → <em>Allow</em>.</li>
              <li><strong>Firefox (desktop):</strong> Click the site information icon → <em>Permissions</em> → <em>Location</em> → <em>Allow</em>.</li>
              <li><strong>Safari (macOS/iOS):</strong> Preferences → Websites (or Settings on iOS) → <em>Location</em> → <em>Allow</em>.</li>
              <li><strong>Edge:</strong> Click the lock → <em>Permissions for this site</em> → <em>Location</em> → <em>Allow</em>.</li>
            </ul>
            <p><strong>Note:</strong> Location requires HTTPS on most browsers and may be limited when the page is in the background. If you previously denied permission, you'll need to change it in your browser settings and then sign in again.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Small theme-aware modal CSS and runtime fallback for the login page -->
    <style>
      /* Keep this minimal to avoid duplicating the entire footer stylesheet */
      .modal-content { background-color: var(--modal-bg, #ffffff) !important; color: var(--modal-color, #212529) !important; }
      .modal a { color: var(--modal-link-color, #0d6efd) !important; }
      @media (prefers-color-scheme: dark) { .modal-content { background-color: rgba(30,34,38,0.98) !important; color: #f8f9fa !important; } .modal a { color: #6ea8fe !important; } }
      /* Ensure backdrop is dark enough when modal shows on dark pages */
      .modal-backdrop.show { background-color: rgba(0,0,0,0.6) !important; }
      /* extra class used by JS when page background is dark */
      .modal-content.modal-dark { background-color: rgba(30,34,38,0.98) !important; color: #f8f9fa !important; }
      .modal-content.modal-dark a { color: #6ea8fe !important; }
    </style>

    <script>
      // Fallback: when the modal opens, determine if page background is dark and apply modal-dark class
      (function () {
        function isColorDark(rgbStr) {
          try {
            const m = rgbStr.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*[0-9\.]+)?\)/);
            if (!m) return false;
            const r = parseInt(m[1],10), g = parseInt(m[2],10), b = parseInt(m[3],10);
            // Perceived luminance
            const lum = 0.2126*r + 0.7152*g + 0.0722*b;
            return lum < 140; // threshold; tweak if needed
          } catch (e) { return false; }
        }

        const modalEl = document.getElementById('locationHelpModal');
        if (!modalEl) return;

        modalEl.addEventListener('show.bs.modal', function () {
          // compute background color of body (or fallback to html)
          const bg = window.getComputedStyle(document.body).backgroundColor || window.getComputedStyle(document.documentElement).backgroundColor;
          if (isColorDark(bg)) {
            const mc = modalEl.querySelector('.modal-content');
            if (mc) mc.classList.add('modal-dark');
          }
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
          const mc = modalEl.querySelector('.modal-content');
          if (mc) mc.classList.remove('modal-dark');
        });
      })();
    </script>

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

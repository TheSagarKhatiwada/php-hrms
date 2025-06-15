<?php
/**
 * HRMS Setup Wizard
 * Web-based installation interface
 */

// Suppress deprecation warnings from third-party libraries
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Prevent direct access after installation
if (file_exists(__DIR__ . '/installation_completed.lock')) {
    die('Installation has already been completed. Remove the installation_completed.lock file to run setup again.');
}

session_start();

// Define include check to allow config.php inclusion
if (!defined('INCLUDE_CHECK')) {
    define('INCLUDE_CHECK', true);
}

require_once __DIR__ . '/includes/DatabaseInstaller.php';

$step = $_POST['step'] ?? $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle restart request
if (isset($_GET['restart']) || isset($_POST['restart'])) {
    session_destroy();
    session_start();
    $step = 1;
    $success = 'Setup restarted. Please begin again.';
}

// Debug information (comment out for production)
$debug = false; // Disabled - sessions are working correctly
if ($debug && $_POST) {
    $success .= "<br><small>Debug - Step: $step, POST: " . print_r($_POST, true) . "</small>";
}
if ($debug && $_SESSION) {
    $success .= "<br><small>Session: " . print_r($_SESSION, true) . "</small>";
}

// Get default database configuration from config.php if it exists
function getDefaultDbConfig() {
    // Define include check to allow config.php inclusion
    if (!defined('INCLUDE_CHECK')) {
        define('INCLUDE_CHECK', true);
    }
    
    $configFile = __DIR__ . '/includes/config.php';
    if (file_exists($configFile)) {
        require_once $configFile;
        return $DB_CONFIG ?? [
            'host' => 'localhost',
            'name' => 'hrms',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4'
        ];
    }
    return [
        'host' => 'localhost',
        'name' => 'hrms',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ];
}

$defaultConfig = getDefaultDbConfig();

// Handle form submissions
if ($_POST) {
    switch ($step) {
        case 2:
            // Database configuration
            $_SESSION['db_config'] = [
                'host' => $_POST['db_host'] ?? $defaultConfig['host'],
                'name' => $_POST['db_name'] ?? $defaultConfig['name'],
                'user' => $_POST['db_user'] ?? $defaultConfig['user'],
                'pass' => $_POST['db_pass'] ?? $defaultConfig['pass'],
                'charset' => 'utf8mb4'
            ];
            // Test connection
            $installer = new DatabaseInstaller($_SESSION['db_config']);
            if ($installer->testConnection()) {
                $_SESSION['success'] = 'Database connection successful!';
                header('Location: setup.php?step=3');
                exit;
            } else {
                $_SESSION['error'] = 'Database connection failed. Please check your settings.';
                header('Location: setup.php?step=2');
                exit;
            }
            break;
        case 3:
            // Admin user creation
            $_SESSION['admin_user'] = [
                'firstName' => $_POST['first_name'] ?? '',
                'lastName' => $_POST['last_name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'password' => $_POST['password'] ?? ''
            ];
            // Validate admin user data
            if (empty($_SESSION['admin_user']['firstName']) || 
                empty($_SESSION['admin_user']['lastName']) || 
                empty($_SESSION['admin_user']['email']) || 
                empty($_SESSION['admin_user']['password'])) {
                $_SESSION['error'] = 'All fields are required.';
                header('Location: setup.php?step=3');
                exit;
            } elseif (!filter_var($_SESSION['admin_user']['email'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'Please enter a valid email address.';
                header('Location: setup.php?step=3');
                exit;
            } elseif (strlen($_SESSION['admin_user']['password']) < 6) {
                $_SESSION['error'] = 'Password must be at least 6 characters long.';
                header('Location: setup.php?step=3');
                exit;
            } else {
                $_SESSION['success'] = 'Admin user data validated successfully!';
                header('Location: setup.php?step=4');
                exit;
            }
            break;
        case 4:
            // Run installation
            if (!isset($_SESSION['db_config']) || !isset($_SESSION['admin_user'])) {
                $_SESSION['error'] = 'Installation data missing. Session may have expired. <a href="?restart=1">Click here to start over</a>.';
                header('Location: setup.php?step=1');
                exit;
            }
            // Validate that we have all required data
            if (empty($_SESSION['db_config']['host']) || 
                empty($_SESSION['db_config']['name']) || 
                empty($_SESSION['admin_user']['firstName']) || 
                empty($_SESSION['admin_user']['email'])) {
                $_SESSION['error'] = 'Required configuration data is missing. <a href="?restart=1">Click here to start over</a>.';
                header('Location: setup.php?step=1');
                exit;
            }
            $installer = new DatabaseInstaller($_SESSION['db_config']);
            if ($installer->install($_SESSION['admin_user'])) {
                $_SESSION['success'] = 'Installation completed successfully!';
                // Create lock file
                file_put_contents(__DIR__ . '/installation_completed.lock', date('Y-m-d H:i:s'));
                // Clear session data
                unset($_SESSION['db_config']);
                unset($_SESSION['admin_user']);
                header('Location: setup.php?step=5');
                exit;
            } else {
                $_SESSION['error'] = 'Installation failed. Please check the logs for details.';
                header('Location: setup.php?step=4');
                exit;
            }            // No break needed due to redirect
    }
}

// Check requirements for step 1
$requirements = [];
if ($step == 1) {
    try {
        $installer = new DatabaseInstaller();
        $requirements = $installer->checkRequirements();
    } catch (Exception $e) {
        $error = 'Configuration Error: ' . $e->getMessage();
        error_log("Setup error: " . $e->getMessage(), 3, __DIR__ . '/debug_log.txt');
        
        // Create requirements array with proper structure for error state
        $requirements = [
            'config_file' => [
                'required' => 'Valid config.php',
                'current' => 'Error: ' . $e->getMessage(),
                'status' => false
            ],
            'php_version' => [
                'required' => '7.4.0',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
            ],
            'pdo' => [
                'required' => true,
                'current' => extension_loaded('pdo'),
                'status' => extension_loaded('pdo')
            ],
            'pdo_mysql' => [
                'required' => true,
                'current' => extension_loaded('pdo_mysql'),
                'status' => extension_loaded('pdo_mysql')
            ]
        ];
    }
}

// At the top of the HTML output, before displaying messages:
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS Setup Wizard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 50px auto;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            background: #dee2e6;
            color: #6c757d;
            font-weight: bold;
        }
        .step.active {
            background: #007bff;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .requirement-item:last-child {
            border-bottom: none;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pass {
            background: #d4edda;
            color: #155724;
        }
        .status-fail {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="setup-container">
            <div class="text-center mb-4">
                <h1 class="h2"><i class="fas fa-cogs me-2"></i>HRMS Setup Wizard</h1>
                <p class="text-muted">Welcome to the HRMS installation wizard. This will guide you through the setup process.</p>
            </div>
            
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?= $step >= 1 ? ($step == 1 ? 'active' : 'completed') : '' ?>">1</div>
                <div class="step <?= $step >= 2 ? ($step == 2 ? 'active' : 'completed') : '' ?>">2</div>
                <div class="step <?= $step >= 3 ? ($step == 3 ? 'active' : 'completed') : '' ?>">3</div>
                <div class="step <?= $step >= 4 ? ($step == 4 ? 'active' : 'completed') : '' ?>">4</div>
                <div class="step <?= $step >= 5 ? 'active' : '' ?>">5</div>
            </div>
              <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                <?php if ($step == 1 && strpos($error, 'missing') !== false): ?>
                <br><br>
                <a href="?restart=1" class="btn btn-warning btn-sm">
                    <i class="fas fa-redo me-2"></i>Restart Setup
                </a>
                <a href="test_session.php" class="btn btn-info btn-sm" target="_blank">
                    <i class="fas fa-bug me-2"></i>Test Sessions
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
            </div>
            <?php endif; ?>
            
            <!-- Debug panel (only show if there are issues) -->
            <?php if ($debug && ($error || $_GET['debug'] ?? false)): ?>
            <div class="alert alert-info">
                <h6>Debug Information:</h6>
                <small>
                    <strong>Current Step:</strong> <?= $step ?><br>
                    <strong>Session ID:</strong> <?= session_id() ?><br>
                    <strong>Session Status:</strong> <?= session_status() == PHP_SESSION_ACTIVE ? 'Active' : 'Inactive' ?><br>
                    <strong>DB Config Set:</strong> <?= isset($_SESSION['db_config']) ? 'Yes' : 'No' ?><br>
                    <strong>Admin User Set:</strong> <?= isset($_SESSION['admin_user']) ? 'Yes' : 'No' ?><br>
                </small>
                <div class="mt-2">
                    <a href="?restart=1" class="btn btn-warning btn-sm">
                        <i class="fas fa-redo me-2"></i>Restart Setup
                    </a>
                    <a href="test_session.php" class="btn btn-info btn-sm" target="_blank">
                        <i class="fas fa-bug me-2"></i>Test Sessions
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <?php if ($step == 1): ?>
                    <!-- Step 1: Requirements Check -->
                    <h3 class="card-title">System Requirements</h3>
                    <p class="text-muted">Please ensure your system meets the following requirements:</p>
                    
                    <div class="requirements-list">
                        <?php foreach ($requirements as $req => $info): ?>
                        <div class="requirement-item">                            <div>
                                <strong><?= ucwords(str_replace('_', ' ', $req)) ?></strong><br>
                                <small class="text-muted">
                                    Required: <?= is_bool($info['required'] ?? false) ? (($info['required'] ?? false) ? 'Yes' : 'No') : ($info['required'] ?? 'N/A') ?>
                                    | Current: <?= is_bool($info['current'] ?? false) ? (($info['current'] ?? false) ? 'Yes' : 'No') : ($info['current'] ?? 'N/A') ?>
                                </small>
                            </div>
                            <span class="status-badge <?= ($info['status'] ?? false) ? 'status-pass' : 'status-fail' ?>">
                                <?= ($info['status'] ?? false) ? 'PASS' : 'FAIL' ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4">
                        <?php
                        $allRequirementsMet = array_reduce($requirements, function($carry, $req) {
                            return $carry && $req['status'];
                        }, true);
                        ?>
                        
                        <?php if ($allRequirementsMet): ?>
                        <a href="?step=2" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i>Continue to Database Setup
                        </a>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Please resolve the failed requirements before continuing.
                        </div>
                        <button class="btn btn-secondary" onclick="location.reload()">
                            <i class="fas fa-refresh me-2"></i>Check Again
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php elseif ($step == 2): ?>                    <!-- Step 2: Database Configuration -->
                    <h3 class="card-title">Database Configuration</h3>
                    <p class="text-muted">Please enter your database connection details:</p>
                      <form method="POST" action="">
                        <input type="hidden" name="step" value="2">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="db_host" class="form-label">Database Host</label>                                <input type="text" class="form-control" id="db_host" name="db_host" 
                                       value="<?= htmlspecialchars($_SESSION['db_config']['host'] ?? $defaultConfig['host']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="db_name" class="form-label">Database Name</label>
                                <input type="text" class="form-control" id="db_name" name="db_name" 
                                       value="<?= htmlspecialchars($_SESSION['db_config']['name'] ?? $defaultConfig['name']) ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="db_user" class="form-label">Database Username</label>
                                <input type="text" class="form-control" id="db_user" name="db_user" 
                                       value="<?= htmlspecialchars($_SESSION['db_config']['user'] ?? $defaultConfig['user']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="db_pass" class="form-label">Database Password</label>
                                <input type="password" class="form-control" id="db_pass" name="db_pass" 
                                       value="<?= htmlspecialchars($_SESSION['db_config']['pass'] ?? $defaultConfig['pass']) ?>">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="?step=1" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-database me-2"></i>Test Connection
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($step == 3): ?>
                    <!-- Step 3: Admin User -->
                    <h3 class="card-title">Create Admin User</h3>
                    <p class="text-muted">Create the administrator account for your HRMS:</p>                    <form method="POST" action="">
                        <input type="hidden" name="step" value="3">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?= htmlspecialchars($_SESSION['admin_user']['firstName'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?= htmlspecialchars($_SESSION['admin_user']['lastName'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($_SESSION['admin_user']['email'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   minlength="6" required>
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="?step=2" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>Create Admin User
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($step == 4): ?>
                    <!-- Step 4: Installation -->
                    <h3 class="card-title">Ready to Install</h3>
                    <p class="text-muted">Review your settings and click install to begin the installation process:</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Database Settings</h5>
                            <ul class="list-unstyled">
                                <li><strong>Host:</strong> <?= htmlspecialchars($_SESSION['db_config']['host']) ?></li>
                                <li><strong>Database:</strong> <?= htmlspecialchars($_SESSION['db_config']['name']) ?></li>
                                <li><strong>Username:</strong> <?= htmlspecialchars($_SESSION['db_config']['user']) ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5>Admin User</h5>
                            <ul class="list-unstyled">
                                <li><strong>Name:</strong> <?= htmlspecialchars($_SESSION['admin_user']['firstName'] . ' ' . $_SESSION['admin_user']['lastName']) ?></li>
                                <li><strong>Email:</strong> <?= htmlspecialchars($_SESSION['admin_user']['email']) ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        The installation process will create the database schema, seed default data, and create your admin user.
                    </div>                    <form method="POST" action="">
                        <input type="hidden" name="step" value="4">
                        <div class="d-flex justify-content-between">
                            <a href="?step=3" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-play me-2"></i>Install HRMS
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($step == 5): ?>
                    <!-- Step 5: Completion -->
                    <div class="text-center">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        <h3 class="mt-3">Installation Complete!</h3>
                        <p class="text-muted">Your HRMS has been successfully installed and configured.</p>
                        
                        <div class="alert alert-success text-start">
                            <h5>What's Next?</h5>
                            <ul class="mb-0">
                                <li>You can now log in with your admin credentials</li>
                                <li>Configure your company settings</li>
                                <li>Add departments, branches, and employees</li>
                                <li>Start managing your HR processes</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning text-start">
                            <h5><i class="fas fa-security me-2"></i>Security Notice</h5>
                            <p class="mb-0">For security reasons, please delete or rename this setup.php file after installation.</p>
                        </div>
                        
                        <a href="index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

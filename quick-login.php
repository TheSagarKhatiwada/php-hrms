<?php
// Simple login test page
// Include session configuration first to ensure session is available
require_once 'includes/session_config.php';

if ($_POST && isset($_POST['username']) && isset($_POST['password'])) {
    // Simple login simulation for testing
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['username'] = $_POST['username'];
    echo "<h3>Login successful! Redirecting...</h3>";
    echo '<script>setTimeout(function(){ window.location.href = "periodic-report.php"; }, 2000);</script>';
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quick Login for Testing</title>
    <link rel="stylesheet" href="./plugins/bootstrap/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Quick Login for Testing</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label>Username:</label>
                                <input type="text" name="username" class="form-control" value="admin" required>
                            </div>
                            <div class="form-group">
                                <label>Password:</label>
                                <input type="password" name="password" class="form-control" value="admin" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Login</button>
                        </form>
                        
                        <hr>
                        <h6>Session Status:</h6>
                        <p>User ID: <?php echo $_SESSION['user_id'] ?? 'Not logged in'; ?></p>
                        <p>Role: <?php echo $_SESSION['role'] ?? 'Not set'; ?></p>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="periodic-report.php" class="btn btn-success">Go to Periodic Report</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

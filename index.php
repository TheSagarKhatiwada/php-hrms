<?php
$page = 'Login';
include 'includes/header.php';
include 'includes/db_connection.php';

// Redirect logged-in users to the dashboard
if (isset($_SESSION['user_id'])) {
  header("Location: admin-dashboard.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

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
            $error = "Access Denied.";
        } else {
            // Check if there is a URL to redirect to
            if (isset($_SESSION['redirect_to'])) {
                $redirect_to = $_SESSION['redirect_to'];
                // Clear the session variable
                unset($_SESSION['redirect_to']);
                // Redirect to the original page
                header("Location: " . $redirect_to);
                exit();
            } else {
                // Redirect based on role
                if ($user['role'] == '1') {
                    header('Location: admin-dashboard.php');
                }else {
                    header('Location: user-dashboard.php');
                }
                exit();
            }
          }               
    } else {
        // Password is incorrect, handle accordingly
        $error = "Login failed. Please try again.";
    }
}
?>
</head>
<body class="hold-transition login-page dark-mode">
<div class="login-box">
  <!-- /.login-logo -->
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <a href="../../index2.html" class="h1"><b>Attendance</b>Pro</a>
    </div>
    <div class="card-body">
      <p class="login-box-msg">Sign in to start your session</p>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form action="index.php" method="post">
        <div class="input-group mb-3">
          <input type="email" class="form-control" name="email" placeholder="Email" required>
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-envelope"></span>
            </div>
          </div>
        </div>
        <div class="input-group mb-3">
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
              <label for="remember">
                Remember Me
              </label>
            </div>
          </div>
          <!-- /.col -->
          <div class="col-4">
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
          </div>
          <!-- /.col -->
        </div>
      </form>

      <p class="mb-1">
        <a href="forgot-password.html">I forgot my password</a>
      </p>
    </div>
    <!-- /.card-body -->
  </div>
  <!-- /.card -->
</div>
<!-- /.login-box -->

<!-- jQuery -->
<script src="../../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="../../dist/js/adminlte.min.js"></script>
</body>
</html>

<?php
session_start();

/* =========
   Database Connection 
   ========= */
$dbHost = "localhost";
$dbUser = "root";       // change as needed
$dbPass = "";           // change as needed
$dbName = "hrms";

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

/* =========
   Logout Handler
   ========= */
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

/* =========
   Login Handler
   ========= */
$error = "";
if (isset($_POST['login'])) {
    // Retrieve form values
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Prepare and execute query
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password, $role);
        $stmt->fetch();
        // Use password_verify for security
        if (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
    $stmt->close();
}

/* =========
   File Upload & Data Import Handler (Admin Only)
   ========= */
$upload_msg = "";
if (isset($_POST['upload']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    if (isset($_FILES['attendance_file']) && $_FILES['attendance_file']['error'] == 0) {
        $fileName = $_FILES['attendance_file']['tmp_name'];
        $handle = fopen($fileName, "r");
        if ($handle) {
            $isFirstRow = true;
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                // Skip header row (assumes first nonempty line is header)
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }
                /*  
                    Assuming your file is tab-delimited, try to split by tab.
                    If the result is less than expected (7 columns), you may need to adjust the parsing.
                */
                $columns = explode("\t", $line);
                if (count($columns) < 7) {
                    // Fall back to splitting by any whitespace.
                    $columns = preg_split('/\s+/', $line);
                }
                
                // Ensure we have at least 7 columns
                if (count($columns) >= 7) {
                    // Map fields based on your sample data.
                    // [0] => No, [1] => Mchn, [2] => EnNo, [3] => Name, [4] => Mode, [5] => IOMd, [6] => DateTime
                    $mchn = trim($columns[1]);
                    $enno = trim($columns[2]);
                    $name = trim($columns[3]);
                    $mode = trim($columns[4]);
                    $iomd = trim($columns[5]);
                    $dt = trim($columns[6]);
                    
                    // Normalize the date/time string.
                    $datetime = date("Y-m-d H:i:s", strtotime($dt));
                    
                    // Insert record into the attendance table
                    $stmt = $conn->prepare("INSERT INTO attendance (mchn, enno, name, mode, iomd, datetime) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("ssssss", $mchn, $enno, $name, $mode, $iomd, $datetime);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            fclose($handle);
            $upload_msg = "File uploaded and processed successfully.";
        } else {
            $upload_msg = "Unable to open the file.";
        }
    } else {
        $upload_msg = "Error uploading file.";
    }
}

/* =========
   Report Generation Handler
   ========= */
$report_results = [];
if (isset($_GET['page']) && $_GET['page'] === 'reports') {
    // Default to current month and year if not set
    $month = isset($_POST['month']) ? $_POST['month'] : date('m');
    $year = isset($_POST['year']) ? $_POST['year'] : date('Y');
    
    if (isset($_POST['search'])) {
        // Build start and end dates for the selected month
        $start_date = "$year-$month-01";
        $end_date = date("Y-m-t", strtotime($start_date));
        
        $stmt = $conn->prepare("SELECT * FROM attendance WHERE datetime BETWEEN ? AND ? ORDER BY datetime ASC");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $report_results[] = $row;
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Simple HRMS System</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .error { color: red; }
    .success { color: green; }
    nav a { margin-right: 15px; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    table, th, td { border: 1px solid #666; }
    th, td { padding: 8px; text-align: left; }
  </style>
</head>
<body>

<?php
// If the user is not logged in, show the login form.
if (!isset($_SESSION['user_id'])) {
    ?>
    <h2>Login</h2>
    <?php if ($error): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post" action="index.php">
      <label>Username: <input type="text" name="username" required></label><br><br>
      <label>Password: <input type="password" name="password" required></label><br><br>
      <input type="submit" name="login" value="Login">
    </form>
    <?php
    exit;
}
?>

<!-- Navigation Menu -->
<nav>
  <strong>Welcome, <?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)</strong>
  | <a href="index.php">Home</a>
  <?php if ($_SESSION['role'] === 'admin'): ?>
    | <a href="index.php?page=upload">Upload Attendance File</a>
  <?php endif; ?>
  | <a href="index.php?page=reports">Monthly Reports</a>
  | <a href="index.php?action=logout">Logout</a>
</nav>

<hr>

<!-- Home/Dashboard -->
<?php
// Decide what to show based on the "page" parameter.
if (!isset($_GET['page']) || $_GET['page'] === 'home') {
    echo "<h2>Dashboard</h2>";
    echo "<p>Select an option from the menu.</p>";
}

// File Upload Page (only for admin)
if (isset($_GET['page']) && $_GET['page'] === 'upload' && $_SESSION['role'] === 'admin') {
    ?>
    <h2>Upload Attendance Data</h2>
    <?php if ($upload_msg): ?>
      <p class="<?= strpos($upload_msg, 'successfully') !== false ? 'success' : 'error'; ?>">
         <?= htmlspecialchars($upload_msg) ?>
      </p>
    <?php endif; ?>
    <form method="post" action="index.php?page=upload" enctype="multipart/form-data">
      <label>Select .txt file: <input type="file" name="attendance_file" accept=".txt" required></label><br><br>
      <input type="submit" name="upload" value="Upload">
    </form>
    <?php
}

// Monthly Reports Page
if (isset($_GET['page']) && $_GET['page'] === 'reports') {
    ?>
    <h2>Monthly Attendance Report</h2>
    <form method="post" action="index.php?page=reports">
      <label>Month (MM): <input type="text" name="month" value="<?= date('m') ?>" required></label>
      <label>Year (YYYY): <input type="text" name="year" value="<?= date('Y') ?>" required></label>
      <input type="submit" name="search" value="Search">
    </form>
    <?php
    if (isset($_POST['search'])) {
        if (count($report_results) > 0) {
            echo "<table>";
            echo "<tr>
                    <th>ID</th>
                    <th>Mchn</th>
                    <th>EnNo</th>
                    <th>Name</th>
                    <th>Mode</th>
                    <th>IOMd</th>
                    <th>DateTime</th>
                  </tr>";
            foreach ($report_results as $row) {
                echo "<tr>
                        <td>" . htmlspecialchars($row['id']) . "</td>
                        <td>" . htmlspecialchars($row['mchn']) . "</td>
                        <td>" . htmlspecialchars($row['enno']) . "</td>
                        <td>" . htmlspecialchars($row['name']) . "</td>
                        <td>" . htmlspecialchars($row['mode']) . "</td>
                        <td>" . htmlspecialchars($row['iomd']) . "</td>
                        <td>" . htmlspecialchars($row['datetime']) . "</td>
                      </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No attendance records found for the selected period.</p>";
        }
    }
}
?>

</body>
</html>

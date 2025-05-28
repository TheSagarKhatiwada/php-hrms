<?php
$page = 'periodic-report';
$home = './';

// Basic test for database connection
try {
    require_once 'includes/db_connection.php';
    echo "<h3>Database connection: SUCCESS</h3>";
    
    // Test branches query
    $branchQuery = "SELECT id, name FROM branches LIMIT 5";
    $stmt = $pdo->query($branchQuery);
    echo "<h3>Branches available:</h3>";
    while ($row = $stmt->fetch()) {
        echo "ID: {$row['id']}, Name: {$row['name']}<br>";
    }
    
} catch (Exception $e) {
    echo "<h3>Database connection: FAILED</h3>";
    echo "Error: " . $e->getMessage();
}

// Test session and permissions
echo "<h3>Session status:</h3>";
if (session_status() == PHP_SESSION_ACTIVE) {
    echo "Session active<br>";
    echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
    echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
} else {
    echo "Session not active<br>";
}

// Test basic HTML structure
?>
<!DOCTYPE html>
<html>
<head>
    <title>Periodic Report Debug</title>
    <link rel="stylesheet" href="<?php echo $home; ?>plugins/bootstrap/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Periodic Report Debug Page</h1>
        <p>This page tests the basic components needed for periodic-report.php</p>
        
        <form action="fetch-periodic-report-data.php" method="POST">
            <div class="form-group">
                <label for="reportDateRange">Date Range:</label>
                <input type="text" class="form-control" id="reportDateRange" name="reportDateRange" value="01/05/2025 - 28/05/2025">
            </div>
            
            <div class="form-group">
                <label for="empBranch">Branch:</label>
                <select class="form-control" id="empBranch" name="empBranch">
                    <option value="">All Branches</option>
                    <?php 
                    try {
                        $branchQuery = "SELECT id, name FROM branches";
                        $stmt = $pdo->query($branchQuery);
                        while ($row = $stmt->fetch()) {
                            echo "<option value='{$row['id']}'>{$row['name']}</option>";
                        }
                    } catch (Exception $e) {
                        echo "<option disabled>Error loading branches</option>";
                    }
                    ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Generate Report</button>
        </form>
    </div>
    
    <script src="<?php echo $home; ?>plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo $home; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

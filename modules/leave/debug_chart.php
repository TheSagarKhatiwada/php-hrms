<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Not logged in');
}

$user_id = $_SESSION['user_id'];
$employee_id = $user_id;
$current_year = date('Y');
$selected_year = $_GET['year'] ?? $current_year;

echo "<h2>Debug Chart Data</h2>";
echo "<p>Employee ID: $employee_id</p>";
echo "<p>Selected Year: $selected_year</p>";

// Get leave balance by leave type
$balance_sql = "SELECT 
    lt.id,
    lt.name as leave_type,
    lt.color,
    lt.days_allowed as allocated_days,
    COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END), 0) as used_days,
    COALESCE(SUM(CASE WHEN lr.status = 'pending' THEN lr.days_requested ELSE 0 END), 0) as pending_days,
    (lt.days_allowed - COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested ELSE 0 END), 0)) as remaining_days
    FROM leave_types lt
    LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id 
        AND lr.employee_id = ? 
        AND YEAR(lr.start_date) = ?
    GROUP BY lt.id, lt.name, lt.color, lt.days_allowed
    ORDER BY lt.name";

$balance_stmt = $pdo->prepare($balance_sql);
$balance_stmt->execute([$employee_id, $selected_year]);
$balance_result = $balance_stmt->fetchAll();

echo "<h3>Leave Types and Balances:</h3>";
echo "<table border='1'>";
echo "<tr><th>Type</th><th>Allocated</th><th>Used</th><th>Pending</th><th>Remaining</th></tr>";

$total_allocated = 0;
$total_used = 0;
$total_pending = 0;
$total_remaining = 0;

foreach ($balance_result as $balance) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($balance['leave_type']) . "</td>";
    echo "<td>" . $balance['allocated_days'] . "</td>";
    echo "<td>" . $balance['used_days'] . "</td>";
    echo "<td>" . $balance['pending_days'] . "</td>";
    echo "<td>" . $balance['remaining_days'] . "</td>";
    echo "</tr>";
    
    $total_allocated += $balance['allocated_days'];
    $total_used += $balance['used_days'];
    $total_pending += $balance['pending_days'];
    $total_remaining += $balance['remaining_days'];
}

echo "</table>";

echo "<h3>Totals:</h3>";
echo "<p>Total Allocated: $total_allocated</p>";
echo "<p>Total Used: $total_used</p>";
echo "<p>Total Pending: $total_pending</p>";
echo "<p>Total Remaining: $total_remaining</p>";

echo "<h3>Chart Data Array:</h3>";
echo "<p>[" . $total_used . ", " . $total_pending . ", " . $total_remaining . "]</p>";

// Test chart HTML
echo "<h3>Test Chart:</h3>";
echo '<div style="width: 400px; height: 400px;">
    <canvas id="testChart"></canvas>
</div>';

echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>';
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById("testChart");
    if (ctx) {
        new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: ["Used", "Pending", "Available"],
                datasets: [{
                    data: [' . $total_used . ', ' . $total_pending . ', ' . $total_remaining . '],
                    backgroundColor: ["#28a745", "#ffc107", "#007bff"]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
        console.log("Test chart created with data:", [' . $total_used . ', ' . $total_pending . ', ' . $total_remaining . ']);
    } else {
        console.error("Canvas not found");
    }
});
</script>';
?>

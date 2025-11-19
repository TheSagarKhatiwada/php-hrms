<?php
require_once '../../includes/db_connection.php';

try {
    // Check existing tasks
    echo "<h3>Current tasks in database:</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as total, task_type, assigned_to FROM tasks GROUP BY task_type, assigned_to");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "<p>No tasks found in database.</p>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>Task Type</th><th>Assigned To</th><th>Count</th></tr>";
        foreach($results as $row) {
            echo "<tr>";
            echo "<td>" . ($row['task_type'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['assigned_to'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['total'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check if we have any available tasks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks WHERE assigned_to IS NULL");
    $available = $stmt->fetchColumn();
    echo "<p><strong>Available tasks (unassigned): " . $available . "</strong></p>";
    
    // Show some sample tasks
    echo "<h3>Sample tasks:</h3>";
    $stmt = $pdo->query("SELECT id, title, task_type, assigned_to, assigned_by FROM tasks LIMIT 5");
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tasks)) {
        echo "<p>No tasks to display.</p>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Title</th><th>Type</th><th>Assigned To</th><th>Assigned By</th></tr>";
        foreach($tasks as $task) {
            echo "<tr>";
            echo "<td>" . $task['id'] . "</td>";
            echo "<td>" . htmlspecialchars($task['title']) . "</td>";
            echo "<td>" . ($task['task_type'] ?? 'NULL') . "</td>";
            echo "<td>" . ($task['assigned_to'] ?? 'NULL') . "</td>";
            echo "<td>" . ($task['assigned_by'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>

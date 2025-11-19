<?php
require_once 'includes/db_connection.php';

echo "=== LATEST TASKS IN DATABASE ===\n";
$stmt = $pdo->query('SELECT id, title, description, category, assigned_to, assigned_by, priority, due_date, created_at FROM tasks ORDER BY id DESC LIMIT 5');

if ($stmt && $stmt->rowCount() > 0) {
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Task ID: " . $row['id'] . "\n";
        echo "  Title: " . $row['title'] . "\n";
        echo "  Category: " . $row['category'] . "\n";
        echo "  Assigned to: " . $row['assigned_to'] . "\n";
        echo "  Assigned by: " . $row['assigned_by'] . "\n";
        echo "  Priority: " . $row['priority'] . "\n";
        echo "  Due Date: " . $row['due_date'] . "\n";
        echo "  Created: " . $row['created_at'] . "\n";
        echo "  ---\n";
    }
} else {
    echo "No tasks found.\n";
}

echo "\n=== TASK CATEGORIES ===\n";
$stmt = $pdo->query('SELECT * FROM task_categories ORDER BY id');
if ($stmt && $stmt->rowCount() > 0) {
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Description: " . $row['description'] . "\n";
    }
} else {
    echo "No categories found.\n";
}
?>

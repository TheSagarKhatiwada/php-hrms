<?php
require_once '../../includes/db_connection.php';

try {
    // Check if task_categories table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'task_categories'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "<h3>✅ Table task_categories exists</h3>";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE task_categories");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Table Structure:</h4>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach($columns as $col) {
            echo "<tr>";
            echo "<td>" . $col['Field'] . "</td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . $col['Null'] . "</td>";
            echo "<td>" . $col['Key'] . "</td>";
            echo "<td>" . $col['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show existing data
        $stmt = $pdo->query("SELECT * FROM task_categories");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Existing Categories (" . count($categories) . "):</h4>";
        if (empty($categories)) {
            echo "<p>No categories found.</p>";
        } else {
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Name</th><th>Description</th></tr>";
            foreach($categories as $cat) {
                echo "<tr>";
                echo "<td>" . $cat['id'] . "</td>";
                echo "<td>" . htmlspecialchars($cat['name']) . "</td>";
                echo "<td>" . htmlspecialchars($cat['description']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<h3>❌ Table task_categories does NOT exist</h3>";
        echo "<p>The table needs to be created.</p>";
        
        // Create the table
        echo "<h4>Creating task_categories table...</h4>";
        $createSQL = "
            CREATE TABLE task_categories (
                id INT(11) NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_name (name)
            )
        ";
        
        if ($pdo->exec($createSQL)) {
            echo "<p>✅ Table task_categories created successfully!</p>";
            echo "<p><a href='task-categories.php'>Go to Task Categories page</a></p>";
        } else {
            echo "<p>❌ Failed to create table.</p>";
        }
    }
    
} catch(Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>

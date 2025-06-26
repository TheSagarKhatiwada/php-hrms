<?php
require_once '../../includes/db_connection.php';

try {
    echo "<h2>Checking task_categories table</h2>";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'task_categories'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "<p>✅ task_categories table exists.</p>";
        
        // Show table structure
        echo "<h3>Table Structure:</h3>";
        $stmt = $pdo->query("DESCRIBE task_categories");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show existing records
        echo "<h3>Existing Categories:</h3>";
        $stmt = $pdo->query("SELECT * FROM task_categories ORDER BY name");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($categories)) {
            echo "<p>No categories found.</p>";
        } else {
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Name</th><th>Description</th><th>Created</th></tr>";
            foreach($categories as $cat) {
                echo "<tr>";
                echo "<td>" . $cat['id'] . "</td>";
                echo "<td>" . htmlspecialchars($cat['name']) . "</td>";
                echo "<td>" . htmlspecialchars($cat['description']) . "</td>";
                echo "<td>" . $cat['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<p>❌ task_categories table does NOT exist!</p>";
        echo "<p>Creating task_categories table...</p>";
        
        $sql = "CREATE TABLE task_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql);
        echo "<p>✅ task_categories table created successfully!</p>";
    }
    
    // Test inserting a category
    echo "<h3>Testing Category Insert:</h3>";
    try {
        $stmt = $pdo->prepare("INSERT INTO task_categories (name, description) VALUES (?, ?)");
        $stmt->execute(['Test Category', 'This is a test category']);
        echo "<p>✅ Test category inserted successfully!</p>";
        
        // Get the inserted category
        $stmt = $pdo->query("SELECT * FROM task_categories WHERE name = 'Test Category'");
        $testCat = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Inserted category: " . htmlspecialchars($testCat['name']) . " - " . htmlspecialchars($testCat['description']) . "</p>";
        
        // Delete test category
        $pdo->exec("DELETE FROM task_categories WHERE name = 'Test Category'");
        echo "<p>Test category deleted.</p>";
        
    } catch (Exception $e) {
        echo "<p>❌ Error inserting test category: " . $e->getMessage() . "</p>";
    }
    
} catch(Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
table { border-collapse: collapse; margin: 10px 0; }
table, th, td { border: 1px solid #ddd; padding: 8px; }
th { background-color: #f2f2f2; }
</style>

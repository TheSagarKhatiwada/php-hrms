<?php
require_once 'includes/db_connection.php';

try {
    echo "=== CHECKING TASK CATEGORIES TABLE ===\n\n";
    
    // Check for task_categories table
    $stmt = $pdo->query("SHOW TABLES LIKE 'task_categories'");
    if ($stmt->rowCount() > 0) {
        echo "✅ task_categories table exists\n";
        
        // Show structure
        $stmt = $pdo->query("DESCRIBE task_categories");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nTable structure:\n";
        foreach ($columns as $column) {
            echo "  {$column['Field']} - {$column['Type']} - {$column['Null']}\n";
        }
        
        // Show existing categories
        $stmt = $pdo->query("SELECT * FROM task_categories ORDER BY name");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nExisting categories (" . count($categories) . "):\n";
        foreach ($categories as $cat) {
            echo "  ID: {$cat['id']} - {$cat['name']}";
            if (!empty($cat['description'])) {
                echo " - {$cat['description']}";
            }
            echo "\n";
        }
        
    } else {
        echo "❌ task_categories table does NOT exist\n";
        echo "Creating task_categories table...\n";
        
        $createSQL = "
        CREATE TABLE task_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT NULL,
            color VARCHAR(7) DEFAULT '#007bff',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        if ($pdo->exec($createSQL)) {
            echo "✅ task_categories table created successfully!\n";
            
            // Insert default categories
            $defaultCategories = [
                ['Development', 'Software development and programming tasks', '#28a745'],
                ['Testing', 'Quality assurance and testing tasks', '#ffc107'],
                ['Documentation', 'Documentation and technical writing', '#17a2b8'],
                ['Marketing', 'Marketing and promotional activities', '#e83e8c'],
                ['HR', 'Human resources and administrative tasks', '#6f42c1'],
                ['Sales', 'Sales and customer relations', '#fd7e14'],
                ['Support', 'Customer support and technical assistance', '#20c997'],
                ['Management', 'Management and supervision tasks', '#6c757d'],
                ['Training', 'Training and education activities', '#198754'],
                ['Research', 'Research and analysis tasks', '#0d6efd']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO task_categories (name, description, color) VALUES (?, ?, ?)");
            foreach ($defaultCategories as $category) {
                $stmt->execute($category);
                echo "  ✅ Added: {$category[0]}\n";
            }
            
            echo "\n✅ Default categories added successfully!\n";
        } else {
            echo "❌ Failed to create task_categories table\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

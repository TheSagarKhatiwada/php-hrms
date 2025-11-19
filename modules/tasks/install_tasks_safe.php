<?php
// Simplified database installation script for task management module
require_once '../../includes/db_connection.php';

try {
    echo "<h2>Installing Task Management Tables...</h2>";
    
    // Drop existing tables first
    $tables = ['task_history', 'task_attachments', 'task_comments', 'tasks'];
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS $table");
        echo "<p>Dropped table: $table (if existed)</p>";
    }
    
    // Create tasks table WITHOUT foreign key constraints first
    $sql_tasks = "CREATE TABLE tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        assigned_by INT NOT NULL,
        assigned_to INT NOT NULL,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'in_progress', 'completed', 'cancelled', 'on_hold') DEFAULT 'pending',
        due_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        progress INT DEFAULT 0,
        category VARCHAR(100),
        attachments TEXT,
        notes TEXT,
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_assigned_by (assigned_by),
        INDEX idx_status (status),
        INDEX idx_due_date (due_date)
    )";

    // Create task comments table
    $sql_comments = "CREATE TABLE task_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        employee_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_task_id (task_id)
    )";

    // Create task attachments table
    $sql_attachments = "CREATE TABLE task_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT,
        uploaded_by INT NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_task_id (task_id)
    )";

    // Create task history table for tracking changes
    $sql_history = "CREATE TABLE task_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        employee_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        old_value TEXT,
        new_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_task_id (task_id)
    )";

    // Execute table creation
    $pdo->exec($sql_tasks);
    echo "<p style='color: green;'>✓ Tasks table created successfully.</p>";
    
    $pdo->exec($sql_comments);
    echo "<p style='color: green;'>✓ Task comments table created successfully.</p>";
    
    $pdo->exec($sql_attachments);
    echo "<p style='color: green;'>✓ Task attachments table created successfully.</p>";
    
    $pdo->exec($sql_history);
    echo "<p style='color: green;'>✓ Task history table created successfully.</p>";

    // Now add foreign key constraints if possible
    echo "<h3>Adding Foreign Key Constraints...</h3>";
    
    try {
        // Add foreign keys to tasks table
        $pdo->exec("ALTER TABLE tasks ADD CONSTRAINT fk_tasks_assigned_by FOREIGN KEY (assigned_by) REFERENCES employees(emp_id) ON DELETE CASCADE");
        echo "<p style='color: green;'>✓ Added foreign key: tasks.assigned_by -> employees.emp_id</p>";
        
        $pdo->exec("ALTER TABLE tasks ADD CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (assigned_to) REFERENCES employees(emp_id) ON DELETE CASCADE");
        echo "<p style='color: green;'>✓ Added foreign key: tasks.assigned_to -> employees.emp_id</p>";
        
        // Add foreign keys to comments table
        $pdo->exec("ALTER TABLE task_comments ADD CONSTRAINT fk_comments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE");
        echo "<p style='color: green;'>✓ Added foreign key: task_comments.task_id -> tasks.id</p>";
        
        $pdo->exec("ALTER TABLE task_comments ADD CONSTRAINT fk_comments_employee FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE");
        echo "<p style='color: green;'>✓ Added foreign key: task_comments.employee_id -> employees.emp_id</p>";
        
        // Add foreign keys to attachments table
        $pdo->exec("ALTER TABLE task_attachments ADD CONSTRAINT fk_attachments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE");
        echo "<p style='color: green;'>✓ Added foreign key: task_attachments.task_id -> tasks.id</p>";
        
        $pdo->exec("ALTER TABLE task_attachments ADD CONSTRAINT fk_attachments_employee FOREIGN KEY (uploaded_by) REFERENCES employees(emp_id) ON DELETE CASCADE");
        echo "<p style='color: green;'>✓ Added foreign key: task_attachments.uploaded_by -> employees.emp_id</p>";
        
        // Add foreign keys to history table
        $pdo->exec("ALTER TABLE task_history ADD CONSTRAINT fk_history_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE");
        echo "<p style='color: green;'>✓ Added foreign key: task_history.task_id -> tasks.id</p>";
        
        $pdo->exec("ALTER TABLE task_history ADD CONSTRAINT fk_history_employee FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE");
        echo "<p style='color: green;'>✓ Added foreign key: task_history.employee_id -> employees.emp_id</p>";
        
    } catch (PDOException $fk_error) {
        echo "<p style='color: orange;'>⚠ Foreign key constraints could not be added: " . $fk_error->getMessage() . "</p>";
        echo "<p>Tables created successfully but without foreign key constraints. The system will still work correctly.</p>";
    }

    echo "<h2 style='color: green;'>✅ Task management module installed successfully!</h2>";
    echo "<p><a href='index.php'>Go to Task Management Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error installing task management module: " . $e->getMessage() . "</p>";
}
?>

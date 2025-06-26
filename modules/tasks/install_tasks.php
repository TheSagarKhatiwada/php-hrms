<?php
// Database installation script for task management module
require_once '../../includes/db_connection.php';

try {
    // Create tasks table
    $sql_tasks = "CREATE TABLE IF NOT EXISTS tasks (
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
        FOREIGN KEY (assigned_by) REFERENCES employees(emp_id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES employees(emp_id) ON DELETE CASCADE,
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_assigned_by (assigned_by),
        INDEX idx_status (status),
        INDEX idx_due_date (due_date)
    )";

    // Create task comments table
    $sql_comments = "CREATE TABLE IF NOT EXISTS task_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        employee_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE,
        INDEX idx_task_id (task_id)
    )";

    // Create task attachments table
    $sql_attachments = "CREATE TABLE IF NOT EXISTS task_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT,
        uploaded_by INT NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES employees(emp_id) ON DELETE CASCADE,
        INDEX idx_task_id (task_id)
    )";

    // Create task history table for tracking changes
    $sql_history = "CREATE TABLE IF NOT EXISTS task_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        employee_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        old_value TEXT,
        new_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE,
        INDEX idx_task_id (task_id)
    )";

    // Execute table creation
    $pdo->exec($sql_tasks);
    echo "Tasks table created successfully.\n";
    
    $pdo->exec($sql_comments);
    echo "Task comments table created successfully.\n";
    
    $pdo->exec($sql_attachments);
    echo "Task attachments table created successfully.\n";
    
    $pdo->exec($sql_history);
    echo "Task history table created successfully.\n";

    echo "Task management module installed successfully!";
    
} catch (PDOException $e) {
    echo "Error installing task management module: " . $e->getMessage();
}
?>

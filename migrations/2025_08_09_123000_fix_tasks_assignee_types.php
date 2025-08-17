<?php
/**
 * Migration: fix_tasks_assignee_types
 * Description: Align tasks.assigned_by/assigned_to with employees.emp_id (VARCHAR(20)) and allow NULL for assigned_to
 * Created: 2025-08-09 12:30:00
 */

return [
    'up' => function($pdo) {
        // Detect current column types
        $types = [];
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM tasks WHERE Field IN ('assigned_by','assigned_to')");
            foreach ($stmt as $row) { $types[$row['Field']] = strtolower($row['Type']); }
        } catch (Throwable $e) {}

        // Modify to VARCHAR(20)
        try { $pdo->exec("ALTER TABLE tasks MODIFY COLUMN assigned_by VARCHAR(20) NOT NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE tasks MODIFY COLUMN assigned_to VARCHAR(20) NULL"); } catch (Throwable $e) {}

        // Add indexes if missing (no IF NOT EXISTS in older MySQL)
        try { $pdo->exec("CREATE INDEX idx_tasks_assigned_by ON tasks(assigned_by)"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE INDEX idx_tasks_assigned_to ON tasks(assigned_to)"); } catch (Throwable $e) {}
    },
    'down' => function($pdo) {
        // No-op downgrade to avoid data loss
    }
];

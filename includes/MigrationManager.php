<?php
/**
 * Database Migration Manager
 * Handles database schema updates and migrations
 */

class MigrationManager {
    private $pdo;
    private $migrationPath;
    private $logFile;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? $this->getDatabaseConnection();
        $this->migrationPath = __DIR__ . '/../migrations';
        $this->logFile = __DIR__ . '/../logs/migrations.log';
        $this->ensureDirectories();
        $this->ensureMigrationsTable();
    }
    
    /**
     * Get database connection
     */
    private function getDatabaseConnection() {
        require_once __DIR__ . '/db_connection.php';
        global $pdo;
        return $pdo;
    }
    
    /**
     * Ensure required directories exist
     */
    private function ensureDirectories() {
        if (!is_dir($this->migrationPath)) {
            mkdir($this->migrationPath, 0755, true);
        }
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Ensure migrations table exists
     */
    private function ensureMigrationsTable() {
        if (!$this->pdo) {
            return;
        }
        
        try {
            $sql = "CREATE TABLE IF NOT EXISTS db_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration_name VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                execution_time DECIMAL(10,3) DEFAULT NULL,
                INDEX idx_migration_name (migration_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            $this->log("Failed to create migrations table: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Log migration activity
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
    
    /**
     * Get all migration files
     */
    public function getMigrationFiles() {
        $files = glob($this->migrationPath . '/*.php');
        sort($files);
        return array_map('basename', $files);
    }
    
    /**
     * Get executed migrations
     */
    public function getExecutedMigrations() {
        if (!$this->pdo) {
            return [];
        }
        
        try {
            $stmt = $this->pdo->query("SELECT migration_name FROM db_migrations ORDER BY executed_at");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            $this->log("Failed to get executed migrations: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Get pending migrations
     */
    public function getPendingMigrations() {
        $allMigrations = $this->getMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();
        
        return array_diff($allMigrations, $executedMigrations);
    }
    
    /**
     * Execute a single migration
     */
    public function executeMigration($migrationFile) {
        if (!$this->pdo) {
            $this->log("No database connection available", 'ERROR');
            return false;
        }
        
        $migrationPath = $this->migrationPath . '/' . $migrationFile;
        
        if (!file_exists($migrationPath)) {
            $this->log("Migration file not found: $migrationFile", 'ERROR');
            return false;
        }
        
        try {
            $this->log("Executing migration: $migrationFile");
            $startTime = microtime(true);
            
            // Include the migration file
            $migration = include $migrationPath;
            
            // Execute the up() method if it exists
            if (is_array($migration) && isset($migration['up']) && is_callable($migration['up'])) {
                $this->pdo->beginTransaction();
                
                try {
                    call_user_func($migration['up'], $this->pdo);
                    $this->pdo->commit();
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    throw $e;
                }
            } else {
                $this->log("Invalid migration format: $migrationFile", 'ERROR');
                return false;
            }
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 3);
            
            // Record the migration
            $stmt = $this->pdo->prepare("
                INSERT INTO db_migrations (migration_name, executed_at, execution_time) 
                VALUES (?, NOW(), ?)
            ");
            $stmt->execute([$migrationFile, $executionTime]);
            
            $this->log("Migration completed successfully: $migrationFile ({$executionTime}ms)");
            return true;
        } catch (Exception $e) {
            $this->log("Migration failed: $migrationFile - " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Rollback a migration
     */
    public function rollbackMigration($migrationFile) {
        if (!$this->pdo) {
            $this->log("No database connection available", 'ERROR');
            return false;
        }
        
        $migrationPath = $this->migrationPath . '/' . $migrationFile;
        
        if (!file_exists($migrationPath)) {
            $this->log("Migration file not found: $migrationFile", 'ERROR');
            return false;
        }
        
        try {
            $this->log("Rolling back migration: $migrationFile");
            
            // Include the migration file
            $migration = include $migrationPath;
            
            // Execute the down() method if it exists
            if (is_array($migration) && isset($migration['down']) && is_callable($migration['down'])) {
                $this->pdo->beginTransaction();
                
                try {
                    call_user_func($migration['down'], $this->pdo);
                    $this->pdo->commit();
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    throw $e;
                }
            } else {
                $this->log("No rollback method found for migration: $migrationFile", 'WARNING');
                return false;
            }
            
            // Remove from migrations table
            $stmt = $this->pdo->prepare("DELETE FROM db_migrations WHERE migration_name = ?");
            $stmt->execute([$migrationFile]);
            
            $this->log("Migration rolled back successfully: $migrationFile");
            return true;
        } catch (Exception $e) {
            $this->log("Migration rollback failed: $migrationFile - " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Run all pending migrations
     */
    public function migrate() {
        $pendingMigrations = $this->getPendingMigrations();
        
        if (empty($pendingMigrations)) {
            $this->log("No pending migrations to execute");
            return true;
        }
        
        $this->log("Found " . count($pendingMigrations) . " pending migrations");
        
        $success = true;
        foreach ($pendingMigrations as $migration) {
            if (!$this->executeMigration($migration)) {
                $success = false;
                break;
            }
        }
        
        if ($success) {
            $this->log("All migrations executed successfully");
        } else {
            $this->log("Migration process stopped due to error", 'ERROR');
        }
        
        return $success;
    }
    
    /**
     * Get migration status
     */
    public function getStatus() {
        $allMigrations = $this->getMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();
        $pendingMigrations = $this->getPendingMigrations();
        
        return [
            'total' => count($allMigrations),
            'executed' => count($executedMigrations),
            'pending' => count($pendingMigrations),
            'migrations' => [
                'all' => $allMigrations,
                'executed' => $executedMigrations,
                'pending' => $pendingMigrations
            ]
        ];
    }
    
    /**
     * Create a new migration file
     */
    public function createMigration($name, $description = '') {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $filepath = $this->migrationPath . '/' . $filename;
        
        $template = $this->getMigrationTemplate($name, $description);
        
        if (file_put_contents($filepath, $template)) {
            $this->log("Migration file created: $filename");
            return $filename;
        } else {
            $this->log("Failed to create migration file: $filename", 'ERROR');
            return false;
        }
    }
    
    /**
     * Get migration template
     */
    private function getMigrationTemplate($name, $description) {
        $className = $this->toCamelCase($name);
        
        return "<?php
/**
 * Migration: $name
 * Description: $description
 * Created: " . date('Y-m-d H:i:s') . "
 */

return [
    'up' => function(\$pdo) {
        // Add your migration logic here
        // Example:
        // \$pdo->exec(\"CREATE TABLE example (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))\");
        
        // For schema changes:
        // \$pdo->exec(\"ALTER TABLE table_name ADD COLUMN new_column VARCHAR(255)\");
        
        // For data migrations:
        // \$stmt = \$pdo->prepare(\"INSERT INTO table_name (column) VALUES (?)\");
        // \$stmt->execute(['value']);
    },
    
    'down' => function(\$pdo) {
        // Add your rollback logic here
        // Example:
        // \$pdo->exec(\"DROP TABLE IF EXISTS example\");
        
        // For schema rollbacks:
        // \$pdo->exec(\"ALTER TABLE table_name DROP COLUMN new_column\");
        
        // For data rollbacks:
        // \$pdo->exec(\"DELETE FROM table_name WHERE condition\");
    }
];
";
    }
    
    /**
     * Convert string to CamelCase
     */
    private function toCamelCase($string) {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
    
    /**
     * Check if database needs migration
     */
    public function needsMigration() {
        return count($this->getPendingMigrations()) > 0;
    }
    
    /**
     * Get migration history
     */
    public function getHistory($limit = 50) {
        if (!$this->pdo) {
            return [];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT migration_name, executed_at, execution_time 
                FROM db_migrations 
                ORDER BY executed_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->log("Failed to get migration history: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Validate migration file
     */
    public function validateMigration($migrationFile) {
        $migrationPath = $this->migrationPath . '/' . $migrationFile;
        
        if (!file_exists($migrationPath)) {
            return ['valid' => false, 'error' => 'Migration file not found'];
        }
        
        try {
            $migration = include $migrationPath;
            
            if (!is_array($migration)) {
                return ['valid' => false, 'error' => 'Migration must return an array'];
            }
            
            if (!isset($migration['up']) || !is_callable($migration['up'])) {
                return ['valid' => false, 'error' => 'Migration must have an "up" callable'];
            }
            
            if (!isset($migration['down']) || !is_callable($migration['down'])) {
                return ['valid' => false, 'error' => 'Migration must have a "down" callable'];
            }
            
            return ['valid' => true];
        } catch (Exception $e) {
            return ['valid' => false, 'error' => 'Error validating migration: ' . $e->getMessage()];
        }
    }
}

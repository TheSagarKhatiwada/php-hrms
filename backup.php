<?php
/**
 * Database Backup and Restore Utility
 * Handles database backups and restoration for HRMS
 */

require_once __DIR__ . '/includes/db_connection.php';

class DatabaseBackup {
    private $pdo;
    private $backupPath;
    private $logFile;
    private $progressFile;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->backupPath = __DIR__ . '/db_backup';
        $this->logFile = __DIR__ . '/logs/backup.log';
        $this->progressFile = __DIR__ . '/logs/backup_progress.json';
        $this->ensureDirectories();
        
        // Set memory limit and execution time
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes
    }
    
    /**
     * Ensure backup directories exist
     */
    private function ensureDirectories() {
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
      /**
     * Log backup operations
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
     * Update backup progress
     */
    private function updateProgress($current, $total, $message = '', $operation = 'backup') {
        $progress = [
            'operation' => $operation,
            'current' => $current,
            'total' => $total,
            'percentage' => round(($current / $total) * 100, 2),
            'message' => $message,
            'timestamp' => time()
        ];
        
        file_put_contents($this->progressFile, json_encode($progress), LOCK_EX);
        
        if (php_sapi_name() === 'cli') {
            echo "\rProgress: {$progress['percentage']}% - $message";
        }
    }
    
    /**
     * Get current progress
     */
    public function getProgress() {
        if (file_exists($this->progressFile)) {
            $progress = json_decode(file_get_contents($this->progressFile), true);
            // Clear progress if older than 10 minutes
            if (time() - $progress['timestamp'] > 600) {
                unlink($this->progressFile);
                return null;
            }
            return $progress;
        }
        return null;
    }
    
    /**
     * Clear progress tracking
     */
    private function clearProgress() {
        if (file_exists($this->progressFile)) {
            unlink($this->progressFile);
        }
    }
      /**
     * Create database backup
     */
    public function createBackup($filename = null) {
        if (!$filename) {
            $filename = 'hrms_backup_' . date('Y_m_d_H_i_s') . '.sql';
        }
        
        $backupFile = $this->backupPath . '/' . $filename;
        
        try {
            $this->clearProgress();
            $this->log("Starting database backup: $filename");
            
            // Get database configuration
            require_once __DIR__ . '/includes/config.php';
            global $DB_CONFIG;
            
            // Get all tables
            $tables = [];
            $stmt = $this->pdo->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            $totalTables = count($tables);
            $this->updateProgress(0, $totalTables, 'Initializing backup...', 'backup');
            
            // Start backup file
            $backup = "-- HRMS Database Backup\n";
            $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $backup .= "-- Database: {$DB_CONFIG['name']}\n\n";
            $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            $currentTable = 0;
            foreach ($tables as $table) {
                $currentTable++;
                $this->updateProgress($currentTable, $totalTables, "Backing up table: $table", 'backup');
                $this->log("Backing up table: $table");
                
                // Get table structure
                $stmt = $this->pdo->query("SHOW CREATE TABLE `$table`");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $backup .= "-- Table structure for table `$table`\n";
                $backup .= "DROP TABLE IF EXISTS `$table`;\n";
                $backup .= $row['Create Table'] . ";\n\n";
                
                // Get table data
                $stmt = $this->pdo->query("SELECT * FROM `$table`");
                $rowCount = 0;
                
                if ($stmt->rowCount() > 0) {
                    $backup .= "-- Dumping data for table `$table`\n";
                    $backup .= "LOCK TABLES `$table` WRITE;\n";
                    
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        if ($rowCount === 0) {
                            $columns = array_keys($row);
                            $backup .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
                        } else {
                            $backup .= ",\n";
                        }
                        
                        $values = array_map(function($value) {
                            return $value === null ? 'NULL' : $this->pdo->quote($value);
                        }, array_values($row));
                        
                        $backup .= '(' . implode(', ', $values) . ')';
                        $rowCount++;
                    }
                    
                    if ($rowCount > 0) {
                        $backup .= ";\n";
                    }
                    
                    $backup .= "UNLOCK TABLES;\n\n";
                }
                
                $this->log("Table $table backed up ($rowCount rows)");
            }
            
            $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // Write backup file
            if (file_put_contents($backupFile, $backup)) {
                $fileSize = filesize($backupFile);
                $this->log("Backup completed successfully: $filename (" . $this->formatBytes($fileSize) . ")");
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $backupFile,
                    'size' => $fileSize,
                    'tables' => count($tables)
                ];
            } else {
                $this->log("Failed to write backup file: $backupFile", 'ERROR');
                return ['success' => false, 'error' => 'Failed to write backup file'];
            }
            
        } catch (Exception $e) {
            $this->log("Backup failed: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Restore database from backup
     */    public function restoreBackup($filename) {
        $backupFile = $this->backupPath . '/' . $filename;
        
        if (!file_exists($backupFile)) {
            $this->log("Backup file not found: $backupFile", 'ERROR');
            return ['success' => false, 'error' => 'Backup file not found'];
        }
        
        try {
            $this->clearProgress();
            $this->log("Starting database restore: $filename");
            
            $this->updateProgress(0, 100, 'Reading backup file...', 'restore');
            
            $sql = file_get_contents($backupFile);
            
            if (empty($sql)) {
                $this->log("Backup file is empty or corrupted", 'ERROR');
                return ['success' => false, 'error' => 'Backup file is empty or corrupted'];
            }
            
            $this->updateProgress(10, 100, 'Parsing SQL statements...', 'restore');
            
            // Split SQL into statements
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && 
                           !preg_match('/^\/\*/', $stmt) && 
                           !preg_match('/^\-\-/', $stmt);
                }
            );
            
            $totalStatements = count($statements);
            $this->updateProgress(20, 100, "Preparing to execute $totalStatements SQL statements...", 'restore');
            
            $this->pdo->beginTransaction();
            
            $executedStatements = 0;
            foreach ($statements as $index => $statement) {
                if (!empty($statement)) {
                    try {
                        $this->pdo->exec($statement);
                        $executedStatements++;
                        
                        // Update progress every 10 statements or at key milestones
                        if ($executedStatements % 10 === 0 || $executedStatements === $totalStatements) {
                            $progress = 20 + (($executedStatements / $totalStatements) * 70); // 20-90%
                            $this->updateProgress($progress, 100, "Executed $executedStatements of $totalStatements statements...", 'restore');
                        }
                    } catch (PDOException $e) {
                        // Log the error but continue with other statements
                        $this->log("Error executing statement: " . $e->getMessage(), 'WARNING');
                    }
                }
            }
            
            $this->updateProgress(95, 100, 'Finalizing transaction...', 'restore');
            $this->pdo->commit();
            
            $this->updateProgress(100, 100, 'Database restore completed successfully!', 'restore');
            
            $this->log("Database restore completed: $executedStatements statements executed");
            
            // Clear progress after a brief delay
            sleep(1);
            $this->clearProgress();
            
            return [
                'success' => true,
                'filename' => $filename,
                'statements' => $executedStatements
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->clearProgress();
            $this->log("Restore failed: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * List available backups
     */
    public function listBackups() {
        $backups = [];
        $files = glob($this->backupPath . '/*.sql');
        
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'filepath' => $file,
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'age' => $this->getFileAge($file)
            ];
        }
        
        // Sort by date (newest first)
        usort($backups, function($a, $b) {
            return filemtime($b['filepath']) - filemtime($a['filepath']);
        });
        
        return $backups;
    }
    
    /**
     * Delete backup file
     */
    public function deleteBackup($filename) {
        $backupFile = $this->backupPath . '/' . $filename;
        
        if (!file_exists($backupFile)) {
            return ['success' => false, 'error' => 'Backup file not found'];
        }
        
        if (unlink($backupFile)) {
            $this->log("Backup deleted: $filename");
            return ['success' => true];
        } else {
            $this->log("Failed to delete backup: $filename", 'ERROR');
            return ['success' => false, 'error' => 'Failed to delete backup file'];
        }
    }
    
    /**
     * Clean old backups
     */
    public function cleanOldBackups($keepDays = 30) {
        $backups = $this->listBackups();
        $deleted = 0;
        $cutoffTime = time() - ($keepDays * 24 * 60 * 60);
        
        foreach ($backups as $backup) {
            if (filemtime($backup['filepath']) < $cutoffTime) {
                if ($this->deleteBackup($backup['filename'])['success']) {
                    $deleted++;
                }
            }
        }
        
        $this->log("Cleaned $deleted old backups (older than $keepDays days)");
        return ['success' => true, 'deleted' => $deleted];
    }
      /**
     * Format bytes to human readable format
     */
    public function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get file age in human readable format
     */
    private function getFileAge($file) {
        $age = time() - filemtime($file);
        
        if ($age < 60) {
            return $age . ' seconds ago';
        } elseif ($age < 3600) {
            return floor($age / 60) . ' minutes ago';
        } elseif ($age < 86400) {
            return floor($age / 3600) . ' hours ago';
        } else {
            return floor($age / 86400) . ' days ago';
        }
    }
      /**
     * Schedule automatic backup
     */
    public function scheduleBackup($interval = 'daily') {
        // This would typically integrate with system cron or Windows Task Scheduler
        $this->log("Backup scheduling not implemented yet - use system cron/task scheduler");
        return ['success' => false, 'error' => 'Scheduling not implemented'];
    }
}

// CLI Usage
if (php_sapi_name() === 'cli' && basename($_SERVER['PHP_SELF']) === 'backup.php') {
    $backup = new DatabaseBackup();
    
    $command = $argv[1] ?? 'help';
    
    switch ($command) {
        case 'create':
            $result = $backup->createBackup();
            if ($result['success']) {
                echo "Backup created successfully: {$result['filename']}\n";
                echo "Size: " . $backup->formatBytes($result['size']) . "\n";
                echo "Tables: {$result['tables']}\n";
            } else {
                echo "Backup failed: {$result['error']}\n";
                exit(1);
            }
            break;
            
        case 'restore':
            $filename = $argv[2] ?? null;
            if (!$filename) {
                echo "Usage: php backup.php restore <filename>\n";
                exit(1);
            }
            
            $result = $backup->restoreBackup($filename);
            if ($result['success']) {
                echo "Restore completed successfully: {$result['filename']}\n";
                echo "Statements executed: {$result['statements']}\n";
            } else {
                echo "Restore failed: {$result['error']}\n";
                exit(1);
            }
            break;
            
        case 'list':
            $backups = $backup->listBackups();
            if (empty($backups)) {
                echo "No backups found.\n";
            } else {
                echo "Available backups:\n";
                foreach ($backups as $b) {
                    echo sprintf("  %-40s %10s %s\n", 
                        $b['filename'], 
                        $backup->formatBytes($b['size']), 
                        $b['age']
                    );
                }
            }
            break;
            
        case 'clean':
            $days = $argv[2] ?? 30;
            $result = $backup->cleanOldBackups($days);
            echo "Cleaned {$result['deleted']} old backups\n";
            break;
            
        default:
            echo "HRMS Database Backup Utility\n";
            echo "Usage: php backup.php [command] [options]\n\n";
            echo "Commands:\n";
            echo "  create              Create a new backup\n";
            echo "  restore <filename>  Restore from backup\n";
            echo "  list                List available backups\n";
            echo "  clean [days]        Clean backups older than X days (default: 30)\n";
            echo "  help                Show this help\n";
            break;
    }
}
?>

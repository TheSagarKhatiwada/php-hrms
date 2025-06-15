<?php
/**
 * HRMS Database Maintenance Script
 * Comprehensive database management tool
 */

require_once __DIR__ . '/includes/DatabaseInstaller.php';
require_once __DIR__ . '/includes/MigrationManager.php';
require_once __DIR__ . '/backup.php';

class HRMSMaintenance {
    private $installer;
    private $migrationManager;
    private $backup;
    
    public function __construct() {
        $this->installer = new DatabaseInstaller();
        $this->migrationManager = new MigrationManager();
        $this->backup = new DatabaseBackup();
    }
    
    /**
     * Show main menu
     */
    public function showMenu() {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║                    HRMS Database Maintenance                 ║\n";
        echo "╠══════════════════════════════════════════════════════════════╣\n";
        echo "║  Installation & Setup:                                      ║\n";
        echo "║    1. Install HRMS Database                                 ║\n";
        echo "║    2. Check Installation Status                             ║\n";
        echo "║    3. Verify Installation                                   ║\n";
        echo "║                                                             ║\n";
        echo "║  Migrations:                                                ║\n";
        echo "║    4. Run Migrations                                        ║\n";
        echo "║    5. Migration Status                                      ║\n";
        echo "║    6. Create New Migration                                  ║\n";
        echo "║    7. Rollback Migration                                    ║\n";
        echo "║                                                             ║\n";
        echo "║  Backup & Restore:                                          ║\n";
        echo "║    8. Create Backup                                         ║\n";
        echo "║    9. List Backups                                          ║\n";
        echo "║   10. Restore Backup                                        ║\n";
        echo "║   11. Clean Old Backups                                     ║\n";
        echo "║                                                             ║\n";
        echo "║  Maintenance:                                               ║\n";
        echo "║   12. Optimize Database                                     ║\n";
        echo "║   13. Check Database Health                                 ║\n";
        echo "║   14. Repair Database                                       ║\n";
        echo "║                                                             ║\n";
        echo "║    0. Exit                                                  ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";
        echo "\nEnter your choice (0-14): ";
    }
    
    /**
     * Handle menu selection
     */
    public function handleChoice($choice) {
        switch ($choice) {
            case 1:
                $this->installDatabase();
                break;
            case 2:
                $this->checkInstallation();
                break;
            case 3:
                $this->verifyInstallation();
                break;
            case 4:
                $this->runMigrations();
                break;
            case 5:
                $this->migrationStatus();
                break;
            case 6:
                $this->createMigration();
                break;
            case 7:
                $this->rollbackMigration();
                break;
            case 8:
                $this->createBackup();
                break;
            case 9:
                $this->listBackups();
                break;
            case 10:
                $this->restoreBackup();
                break;
            case 11:
                $this->cleanBackups();
                break;
            case 12:
                $this->optimizeDatabase();
                break;
            case 13:
                $this->checkDatabaseHealth();
                break;
            case 14:
                $this->repairDatabase();
                break;
            case 0:
                echo "Goodbye!\n";
                exit(0);
            default:
                echo "Invalid choice. Please try again.\n";
        }
    }
    
    /**
     * Install database
     */
    private function installDatabase() {
        echo "\n--- HRMS Database Installation ---\n";
        
        if ($this->installer->isInstalled()) {
            echo "HRMS is already installed.\n";
            echo "Would you like to reinstall? (y/N): ";
            $confirm = trim(fgets(STDIN));
            if (strtolower($confirm) !== 'y') {
                return;
            }
        }
        
        // Get admin user details
        echo "Enter admin user details:\n";
        echo "First Name: ";
        $firstName = trim(fgets(STDIN));
        echo "Last Name: ";
        $lastName = trim(fgets(STDIN));
        echo "Email: ";
        $email = trim(fgets(STDIN));
        echo "Password: ";
        $password = trim(fgets(STDIN));
        
        $adminData = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'password' => $password
        ];
        
        echo "\nStarting installation...\n";
        if ($this->installer->install($adminData)) {
            echo "✓ Installation completed successfully!\n";
        } else {
            echo "✗ Installation failed. Check logs for details.\n";
        }
    }
    
    /**
     * Check installation status
     */
    private function checkInstallation() {
        echo "\n--- Installation Status ---\n";
        
        $requirements = $this->installer->checkRequirements();
        foreach ($requirements as $req => $info) {
            $status = $info['status'] ? '✓' : '✗';
            echo "$status $req: {$info['current']}\n";
        }
        
        if ($this->installer->testConnection()) {
            echo "✓ Database connection: OK\n";
            
            if ($this->installer->connect() && $this->installer->isInstalled()) {
                echo "✓ HRMS installation: Found\n";
            } else {
                echo "✗ HRMS installation: Not found\n";
            }
        } else {
            echo "✗ Database connection: Failed\n";
        }
    }
    
    /**
     * Verify installation
     */
    private function verifyInstallation() {
        echo "\n--- Installation Verification ---\n";
        
        require_once __DIR__ . '/verify-installation.php';
        $verifier = new InstallationVerifier();
        $results = $verifier->verify();
        echo $verifier->generateReport('cli');
    }
    
    /**
     * Run migrations
     */
    private function runMigrations() {
        echo "\n--- Running Migrations ---\n";
        
        $pending = $this->migrationManager->getPendingMigrations();
        if (empty($pending)) {
            echo "No pending migrations found.\n";
            return;
        }
        
        echo "Found " . count($pending) . " pending migrations:\n";
        foreach ($pending as $migration) {
            echo "  - $migration\n";
        }
        
        echo "\nProceed with migration? (y/N): ";
        $confirm = trim(fgets(STDIN));
        if (strtolower($confirm) === 'y') {
            if ($this->migrationManager->migrate()) {
                echo "✓ All migrations completed successfully!\n";
            } else {
                echo "✗ Migration failed. Check logs for details.\n";
            }
        }
    }
    
    /**
     * Show migration status
     */
    private function migrationStatus() {
        echo "\n--- Migration Status ---\n";
        
        $status = $this->migrationManager->getStatus();
        echo "Total migrations: {$status['total']}\n";
        echo "Executed: {$status['executed']}\n";
        echo "Pending: {$status['pending']}\n";
        
        if (!empty($status['migrations']['pending'])) {
            echo "\nPending migrations:\n";
            foreach ($status['migrations']['pending'] as $migration) {
                echo "  - $migration\n";
            }
        }
    }
    
    /**
     * Create new migration
     */
    private function createMigration() {
        echo "\n--- Create Migration ---\n";
        echo "Migration name: ";
        $name = trim(fgets(STDIN));
        echo "Description (optional): ";
        $description = trim(fgets(STDIN));
        
        $filename = $this->migrationManager->createMigration($name, $description);
        if ($filename) {
            echo "✓ Migration created: $filename\n";
        } else {
            echo "✗ Failed to create migration\n";
        }
    }
    
    /**
     * Rollback migration
     */
    private function rollbackMigration() {
        echo "\n--- Rollback Migration ---\n";
        
        $executed = $this->migrationManager->getExecutedMigrations();
        if (empty($executed)) {
            echo "No migrations to rollback.\n";
            return;
        }
        
        echo "Recent migrations:\n";
        $recent = array_slice(array_reverse($executed), 0, 10);
        foreach ($recent as $i => $migration) {
            echo ($i + 1) . ". $migration\n";
        }
        
        echo "\nEnter migration number to rollback (1-" . count($recent) . "): ";
        $choice = (int)trim(fgets(STDIN));
        
        if ($choice >= 1 && $choice <= count($recent)) {
            $migration = $recent[$choice - 1];
            echo "Rollback migration: $migration? (y/N): ";
            $confirm = trim(fgets(STDIN));
            
            if (strtolower($confirm) === 'y') {
                if ($this->migrationManager->rollbackMigration($migration)) {
                    echo "✓ Migration rolled back successfully!\n";
                } else {
                    echo "✗ Rollback failed. Check logs for details.\n";
                }
            }
        } else {
            echo "Invalid choice.\n";
        }
    }
    
    /**
     * Create backup
     */
    private function createBackup() {
        echo "\n--- Create Backup ---\n";
        
        $result = $this->backup->createBackup();
        if ($result['success']) {
            echo "✓ Backup created: {$result['filename']}\n";
            echo "  Size: " . $this->formatBytes($result['size']) . "\n";
            echo "  Tables: {$result['tables']}\n";
        } else {
            echo "✗ Backup failed: {$result['error']}\n";
        }
    }
    
    /**
     * List backups
     */
    private function listBackups() {
        echo "\n--- Available Backups ---\n";
        
        $backups = $this->backup->listBackups();
        if (empty($backups)) {
            echo "No backups found.\n";
            return;
        }
        
        printf("%-40s %10s %20s\n", "Filename", "Size", "Created");
        echo str_repeat("-", 72) . "\n";
        
        foreach ($backups as $backup) {
            printf("%-40s %10s %20s\n", 
                substr($backup['filename'], 0, 40),
                $this->formatBytes($backup['size']),
                $backup['date']
            );
        }
    }
    
    /**
     * Restore backup
     */
    private function restoreBackup() {
        echo "\n--- Restore Backup ---\n";
        
        $backups = $this->backup->listBackups();
        if (empty($backups)) {
            echo "No backups found.\n";
            return;
        }
        
        echo "Available backups:\n";
        foreach ($backups as $i => $backup) {
            echo ($i + 1) . ". {$backup['filename']} ({$backup['date']})\n";
        }
        
        echo "\nEnter backup number to restore (1-" . count($backups) . "): ";
        $choice = (int)trim(fgets(STDIN));
        
        if ($choice >= 1 && $choice <= count($backups)) {
            $backup = $backups[$choice - 1];
            echo "Restore backup: {$backup['filename']}? (y/N): ";
            $confirm = trim(fgets(STDIN));
            
            if (strtolower($confirm) === 'y') {
                $result = $this->backup->restoreBackup($backup['filename']);
                if ($result['success']) {
                    echo "✓ Backup restored successfully!\n";
                    echo "  Statements executed: {$result['statements']}\n";
                } else {
                    echo "✗ Restore failed: {$result['error']}\n";
                }
            }
        } else {
            echo "Invalid choice.\n";
        }
    }
    
    /**
     * Clean old backups
     */
    private function cleanBackups() {
        echo "\n--- Clean Old Backups ---\n";
        echo "Keep backups newer than how many days? (default: 30): ";
        $days = trim(fgets(STDIN));
        $days = empty($days) ? 30 : (int)$days;
        
        $result = $this->backup->cleanOldBackups($days);
        echo "✓ Cleaned {$result['deleted']} old backups\n";
    }
    
    /**
     * Optimize database
     */
    private function optimizeDatabase() {
        echo "\n--- Optimize Database ---\n";
        
        try {
            require_once __DIR__ . '/includes/db_connection.php';
            global $pdo;
            
            // Get all tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "Optimizing " . count($tables) . " tables...\n";
            
            foreach ($tables as $table) {
                $pdo->exec("OPTIMIZE TABLE `$table`");
                echo "✓ Optimized: $table\n";
            }
            
            echo "✓ Database optimization completed!\n";
        } catch (Exception $e) {
            echo "✗ Optimization failed: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Check database health
     */
    private function checkDatabaseHealth() {
        echo "\n--- Database Health Check ---\n";
        
        try {
            require_once __DIR__ . '/includes/db_connection.php';
            global $pdo;
            
            // Check connection
            echo "✓ Database connection: OK\n";
            
            // Check table status
            $stmt = $pdo->query("SHOW TABLE STATUS");
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalSize = 0;
            $issues = 0;
            
            foreach ($tables as $table) {
                $size = $table['Data_length'] + $table['Index_length'];
                $totalSize += $size;
                
                if ($table['Engine'] === null) {
                    echo "⚠ Table {$table['Name']}: Missing engine\n";
                    $issues++;
                }
            }
            
            echo "✓ Total tables: " . count($tables) . "\n";
            echo "✓ Database size: " . $this->formatBytes($totalSize) . "\n";
            echo "✓ Issues found: $issues\n";
            
            // Check for orphaned records (basic check)
            $orphanChecks = [
                'employees with invalid branch' => "SELECT COUNT(*) FROM employees e LEFT JOIN branches b ON e.branch = b.id WHERE b.id IS NULL AND e.branch IS NOT NULL",
                'employees with invalid designation' => "SELECT COUNT(*) FROM employees e LEFT JOIN designations d ON e.designation = d.id WHERE d.id IS NULL AND e.designation IS NOT NULL"
            ];
            
            foreach ($orphanChecks as $check => $sql) {
                $stmt = $pdo->query($sql);
                $count = $stmt->fetchColumn();
                if ($count > 0) {
                    echo "⚠ Found $count $check\n";
                    $issues++;
                } else {
                    echo "✓ No $check\n";
                }
            }
            
            if ($issues === 0) {
                echo "\n✓ Database health: GOOD\n";
            } else {
                echo "\n⚠ Database health: NEEDS ATTENTION ($issues issues)\n";
            }
            
        } catch (Exception $e) {
            echo "✗ Health check failed: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Repair database
     */
    private function repairDatabase() {
        echo "\n--- Repair Database ---\n";
        
        try {
            require_once __DIR__ . '/includes/db_connection.php';
            global $pdo;
            
            // Get all tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "Repairing " . count($tables) . " tables...\n";
            
            foreach ($tables as $table) {
                $stmt = $pdo->query("REPAIR TABLE `$table`");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['Msg_type'] === 'status' && $result['Msg_text'] === 'OK') {
                    echo "✓ Repaired: $table\n";
                } else {
                    echo "⚠ $table: {$result['Msg_text']}\n";
                }
            }
            
            echo "✓ Database repair completed!\n";
        } catch (Exception $e) {
            echo "✗ Repair failed: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Format bytes
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Main menu loop
     */
    public function run() {
        while (true) {
            $this->showMenu();
            $choice = (int)trim(fgets(STDIN));
            $this->handleChoice($choice);
            
            if ($choice !== 0) {
                echo "\nPress Enter to continue...";
                fgets(STDIN);
            }
        }
    }
}

// Run if called directly from CLI
if (php_sapi_name() === 'cli' && basename($_SERVER['PHP_SELF']) === 'maintenance.php') {
    $maintenance = new HRMSMaintenance();
    $maintenance->run();
}
?>

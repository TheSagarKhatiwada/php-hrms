<?php
/**
 * Database Installer Class
 * Handles first-time database setup and installation
 */

class DatabaseInstaller {
    private $config;
    private $pdo;
    private $logFile;
      public function __construct($config = null) {
        $this->config = $config ?? $this->getDefaultConfig();
        $this->logFile = __DIR__ . '/../logs/installation.log';
        $this->ensureLogDirectory();
    }
    
    /**
     * Get database configuration from centralized config file
     */
    private function getDefaultConfig() {
        // Define include check to allow config.php inclusion
        if (!defined('INCLUDE_CHECK')) {
            define('INCLUDE_CHECK', true);
        }
        
        $configFile = __DIR__ . '/config.php';        if (!file_exists($configFile)) {
            throw new Exception("Database configuration file not found at: $configFile. Please ensure config.php exists with proper database configuration.");
        }
          // Clear any existing configuration
        $DB_CONFIG = null;
        
        // Include the config file
        require_once $configFile;
        
        // Try multiple approaches to get the configuration
        $config = null;
        
        // Method 1: Try the function approach
        if (function_exists('getDBConfig')) {
            $config = getDBConfig();
        }
        
        // Method 2: Try local variable
        if (!$config && isset($DB_CONFIG) && is_array($DB_CONFIG)) {
            $config = $DB_CONFIG;
        }
        
        // Method 3: Try global variable
        if (!$config) {
            global $DB_CONFIG;
            if (isset($DB_CONFIG) && is_array($DB_CONFIG)) {
                $config = $DB_CONFIG;
            }
        }
        
        // Method 4: Try GLOBALS array
        if (!$config && isset($GLOBALS['DB_CONFIG']) && is_array($GLOBALS['DB_CONFIG'])) {
            $config = $GLOBALS['DB_CONFIG'];
        }
        
        // If still no config, throw error
        if (!$config || !is_array($config)) {
            throw new Exception("Invalid database configuration in config.php. Please ensure \$DB_CONFIG array is properly defined.");
        }
          // Validate required fields
        $required_fields = ['host', 'name', 'user', 'pass'];
        foreach ($required_fields as $field) {
            if (!isset($config[$field])) {
                throw new Exception("Missing required database configuration field: $field");
            }
        }
        
        return $config;
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Log installation progress
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also output to console if running from CLI
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
    
    /**
     * Check system requirements
     */
    public function checkRequirements() {
        $requirements = [];
        
        // Check PHP version
        $requirements['php_version'] = [
            'required' => '7.4.0',
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
        ];
        
        // Check PDO extension
        $requirements['pdo'] = [
            'required' => true,
            'current' => extension_loaded('pdo'),
            'status' => extension_loaded('pdo')
        ];
        
        // Check PDO MySQL driver
        $requirements['pdo_mysql'] = [
            'required' => true,
            'current' => extension_loaded('pdo_mysql'),
            'status' => extension_loaded('pdo_mysql')
        ];
          // Check memory limit
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        $requirements['memory_limit'] = [
            'required' => '128M',
            'current' => $memoryLimit,
            'status' => $memoryLimitBytes >= $this->convertToBytes('128M')
        ];
        
        // Check mbstring extension (recommended but not required)
        $requirements['mbstring'] = [
            'required' => 'Recommended',
            'current' => extension_loaded('mbstring') ? 'Available' : 'Not Available',
            'status' => true // Always pass since it's not required
        ];
        
        return $requirements;
    }
    
    /**
     * Convert memory limit to bytes
     */
    private function convertToBytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int) $value;
        
        switch($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $dsn = "mysql:host={$this->config['host']};charset={$this->config['charset']}";
            $pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            
            $this->log("Database connection test successful");
            return true;
        } catch (PDOException $e) {
            $this->log("Database connection test failed: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Create database if it doesn't exist
     */
    public function createDatabase() {
        try {
            $dsn = "mysql:host={$this->config['host']};charset={$this->config['charset']}";
            $pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            $dbName = $this->config['name'];
            $sql = "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
            $pdo->exec($sql);
            
            $this->log("Database '$dbName' created successfully");
            return true;
        } catch (PDOException $e) {
            $this->log("Failed to create database: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Connect to the HRMS database
     */
    public function connect() {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['name']};charset={$this->config['charset']}";
            $this->pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            
            $this->log("Connected to HRMS database successfully");
            return true;
        } catch (PDOException $e) {
            $this->log("Failed to connect to HRMS database: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
      /**
     * Check if database is already installed
     */
    public function isInstalled() {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            // Check if migrations table exists and has records
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'db_migrations'");
            if ($stmt->rowCount() === 0) {
                return false;
            }
            
            // Check if initial migration exists (using correct column name)
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM db_migrations WHERE migration = 'initial_schema'");
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
      /**
     * Install the database schema
     */
    public function installSchema() {
        if (!$this->pdo) {
            $this->log("No database connection available", 'ERROR');
            return false;
        }
        
        try {            $this->log("Starting database schema installation");
            
            // Use the proper schema file for installation
            $schemaFile = __DIR__ . '/../schema/hrms_schema.sql';
            
            if (!file_exists($schemaFile)) {
                $this->log("Schema file not found: $schemaFile", 'ERROR');
                $this->log("Please ensure the schema file exists in the schema directory", 'ERROR');
                return false;
            }
            
            // Use a more robust method to read the SQL file
            $sql = $this->readSqlFile($schemaFile);
            if ($sql === false) {
                $this->log("Failed to read SQL file", 'ERROR');
                return false;
            }
              // Parse and execute SQL statements
            $statements = $this->parseSqlStatements($sql);
            $statementCount = 0;
            $errorCount = 0;
            
            $this->log("Found " . count($statements) . " SQL statements to execute");
            
            // Disable foreign key checks during installation
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $this->pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
            
            foreach ($statements as $index => $statement) {
                $statement = trim($statement);
                if (empty($statement)) {
                    continue;
                }
                
                try {
                    $this->pdo->exec($statement);
                    $statementCount++;
                    
                    // Log progress for major operations
                    if (stripos($statement, 'CREATE TABLE') === 0) {
                        preg_match('/CREATE TABLE `?(\w+)`?/i', $statement, $matches);
                        $tableName = $matches[1] ?? 'unknown';
                        $this->log("Created table: $tableName");
                    } elseif (stripos($statement, 'INSERT INTO') === 0) {
                        preg_match('/INSERT INTO `?(\w+)`?/i', $statement, $matches);
                        $tableName = $matches[1] ?? 'unknown';
                        $this->log("Inserted data into: $tableName");
                    }
                    
                } catch (PDOException $e) {
                    $errorCount++;
                    $errorMsg = $e->getMessage();
                    
                    // Only log significant errors, skip "already exists" warnings
                    if (strpos($errorMsg, 'already exists') === false && 
                        strpos($errorMsg, 'Duplicate entry') === false) {
                        $this->log("Error executing statement #$index: $errorMsg", 'ERROR');
                        $this->log("Statement: " . substr($statement, 0, 200) . "...", 'ERROR');
                        
                        // For critical errors, stop installation
                        if (strpos($errorMsg, 'syntax error') !== false || 
                            strpos($errorMsg, 'Unknown table') !== false) {
                            $this->log("Critical error encountered, stopping installation", 'ERROR');
                            return false;
                        }
                    }
                }
            }
            
            // Re-enable foreign key checks
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $this->log("Schema installation completed. Executed $statementCount statements with $errorCount errors");
            
            $this->log("Database schema installed successfully");
            return true;
        } catch (Exception $e) {
            $this->log("Failed to install schema: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Read SQL file with proper encoding handling
     */
    private function readSqlFile($filePath) {
        // Try multiple methods to read the file properly
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            return false;
        }
          // Remove BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        // Check for encoding issues only if mbstring extension is available
        if (extension_loaded('mbstring')) {
            if (!mb_check_encoding($content, 'UTF-8')) {
                // Try to convert from different encodings
                $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'UTF-16LE', 'UTF-16BE'];
                foreach ($encodings as $encoding) {
                    $converted = mb_convert_encoding($content, 'UTF-8', $encoding);
                    if (mb_check_encoding($converted, 'UTF-8')) {
                        $content = $converted;
                        break;
                    }
                }
            }
        } else {
            // Fallback: Simple encoding detection and conversion
            // Check if content contains non-ASCII characters that might indicate encoding issues
            if (!preg_match('//u', $content)) {
                // Content is not valid UTF-8, try to convert from common encodings
                if (function_exists('iconv')) {
                    $encodings = ['ISO-8859-1', 'Windows-1252'];
                    foreach ($encodings as $encoding) {
                        $converted = @iconv($encoding, 'UTF-8//IGNORE', $content);
                        if ($converted !== false) {
                            $content = $converted;
                            break;
                        }
                    }
                } else {
                    // Last resort: just remove non-UTF-8 characters
                    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
                }
            }
        }
        
        // Remove any null bytes that might cause issues
        $content = str_replace("\x00", '', $content);
        
        return $content;
    }    /**
     * Parse SQL content into individual statements
     */
    private function parseSqlStatements($sql) {
        $statements = [];
        
        // Remove comments and normalize line endings
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        
        // Split by semicolons - use a simpler approach
        $parts = explode(';', $sql);
        
        if ($parts === false) {
            $this->log("Failed to split SQL statements", 'ERROR');
            return [];
        }
        
        foreach ($parts as $part) {
            $statement = trim($part);
            
            // Skip empty statements
            if (empty($statement)) {
                continue;
            }
            
            // Skip SET statements and other MySQL-specific commands we don't need
            if (preg_match('/^\s*(SET|START TRANSACTION|COMMIT|AUTOCOMMIT)/i', $statement)) {
                continue;
            }
            
            $statements[] = $statement;
        }
        
        return $statements;
    }
      /**
     * Create the migrations table for tracking database changes
     */
    public function createMigrationsTable() {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            // Check if table already exists with correct structure
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'db_migrations'");
            if ($stmt->rowCount() > 0) {
                $this->log("Migrations table already exists");
                
                // Record the initial schema installation
                $this->recordMigration('initial_schema');
                
                return true;
            }
            
            // Create table if it doesn't exist (though it should from schema)
            $sql = "CREATE TABLE IF NOT EXISTS `db_migrations` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `migration` varchar(255) NOT NULL,
                `batch` int unsigned NOT NULL,
                `applied_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `migration_name` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $this->pdo->exec($sql);
            
            // Record the initial schema installation
            $this->recordMigration('initial_schema');
            
            $this->log("Migrations table created successfully");
            return true;
        } catch (PDOException $e) {
            $this->log("Failed to create migrations table: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
      /**
     * Record a migration in the tracking table
     */
    public function recordMigration($name, $description = '') {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            // Use the correct column name 'migration' instead of 'migration_name'
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO db_migrations (migration, batch, applied_at) VALUES (?, 1, NOW())");
            $stmt->execute([$name]);
            return true;
        } catch (PDOException $e) {
            $this->log("Failed to record migration: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Seed default data
     */
    public function seedDefaultData() {
        if (!$this->pdo) {
            $this->log("No database connection available", 'ERROR');
            return false;
        }
        
        try {
            $this->log("Seeding default data");
            
            // Insert default roles
            $this->pdo->exec("INSERT IGNORE INTO roles (id, name, description) VALUES 
                (1, 'Admin', 'System Administrator'),
                (2, 'Manager', 'Department Manager'),
                (3, 'Employee', 'Regular Employee')");
            
            // Insert default permissions
            $permissions = [
                ['manage_employees', 'Manage Employees'],
                ['view_reports', 'View Reports'],
                ['manage_attendance', 'Manage Attendance'],
                ['manage_assets', 'Manage Assets'],
                ['system_settings', 'System Settings']
            ];
            
            foreach ($permissions as $permission) {
                $this->pdo->prepare("INSERT IGNORE INTO permissions (name, description) VALUES (?, ?)")
                    ->execute($permission);
            }
            
            // Insert default role permissions (Admin gets all permissions)
            $this->pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) 
                SELECT 1, id FROM permissions");
              // Insert default branch (using correct column structure)
            $this->pdo->exec("INSERT IGNORE INTO branches (id, name, created_at, updated_at) VALUES 
                (1, 'Main Office', NOW(), NOW())");
            
            // Insert default department
            $this->pdo->exec("INSERT IGNORE INTO departments (id, name, description) VALUES 
                (1, 'Administration', 'Administrative Department')");
            
            // Insert default designation
            $this->pdo->exec("INSERT IGNORE INTO designations (id, title, description, department_id) VALUES 
                (1, 'System Administrator', 'System Administrator', 1)");
            
            // Insert default settings
            $defaultSettings = [
                ['app_name', 'HRMS Pro'],
                ['company_name', 'Your Company Name'],
                ['company_address', '123 Main Street, City, Country'],
                ['company_phone', '+1234567890'],
                ['company_primary_color', '#007bff'],
                ['company_secondary_color', '#6c757d'],
                ['company_work_hour', '9:00 AM - 5:00 PM'],
                ['timezone', 'UTC']
            ];
            
            foreach ($defaultSettings as $setting) {
                $this->pdo->prepare("INSERT IGNORE INTO settings (setting_key, value, created_at, modified_at) VALUES (?, ?, NOW(), NOW())")
                    ->execute($setting);
            }
            
            $this->log("Default data seeded successfully");
            return true;
        } catch (Exception $e) {
            $this->log("Failed to seed default data: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Create admin user
     */
    public function createAdminUser($firstName, $lastName, $email, $password) {
        if (!$this->pdo) {
            $this->log("No database connection available", 'ERROR');
            return false;
        }
        
        try {
            $this->log("Creating admin user");
            
            // Check if admin user already exists
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ? OR role_id = 1");
            $stmt->execute([$email]);
            
            if ($stmt->fetchColumn() > 0) {
                $this->log("Admin user already exists", 'WARNING');
                return true;
            }
            
            // Generate employee ID
            $empId = 'EMP001';
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert admin user
            $stmt = $this->pdo->prepare("
                INSERT INTO employees (
                    emp_id, first_name, last_name, email, password, 
                    role_id, designation, branch, join_date, login_access
                ) VALUES (?, ?, ?, ?, ?, 1, 1, 1, CURDATE(), 1)
            ");
            
            $result = $stmt->execute([
                $empId, $firstName, $lastName, $email, $hashedPassword
            ]);
            
            if ($result) {
                $this->log("Admin user created successfully: $email");
                return true;
            } else {
                $this->log("Failed to create admin user", 'ERROR');
                return false;
            }
        } catch (Exception $e) {
            $this->log("Failed to create admin user: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Record installation in migrations table
     */
    public function recordInstallation() {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO db_migrations (migration_name, executed_at) 
                VALUES ('initial_schema', NOW())
            ");
            $stmt->execute();
            
            $this->log("Installation recorded in migrations table");
            return true;
        } catch (Exception $e) {
            $this->log("Failed to record installation: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Run complete installation
     */
    public function install($adminData = null) {
        $this->log("Starting HRMS installation");
        
        // Check requirements
        $requirements = $this->checkRequirements();
        foreach ($requirements as $req => $info) {
            if (!$info['status']) {
                $this->log("Requirement check failed: $req", 'ERROR');
                return false;
            }
        }
        
        // Test connection
        if (!$this->testConnection()) {
            return false;
        }
        
        // Create database
        if (!$this->createDatabase()) {
            return false;
        }
        
        // Connect to database
        if (!$this->connect()) {
            return false;
        }
        
        // Check if already installed
        if ($this->isInstalled()) {
            $this->log("HRMS is already installed", 'WARNING');
            return true;        }
        
        // Install schema
        if (!$this->installSchema()) {
            return false;
        }
        
        // Create migrations table to track future updates
        if (!$this->createMigrationsTable()) {
            return false;
        }
        
        // Seed default data
        if (!$this->seedDefaultData()) {
            return false;
        }
        
        // Create admin user if provided
        if ($adminData) {
            if (!$this->createAdminUser(
                $adminData['firstName'],
                $adminData['lastName'],
                $adminData['email'],
                $adminData['password']
            )) {
                return false;
            }
        }
        
        // Record installation
        if (!$this->recordInstallation()) {
            return false;
        }
        
        $this->log("HRMS installation completed successfully");
        return true;
    }
    
    /**
     * Get installation log
     */
    public function getInstallationLog() {
        if (file_exists($this->logFile)) {
            return file_get_contents($this->logFile);
        }
        return '';
    }
    
    /**
     * Debug function to test config file loading
     */
    public static function testConfigFile() {
        if (!defined('INCLUDE_CHECK')) {
            define('INCLUDE_CHECK', true);
        }
        
        $configFile = __DIR__ . '/config.php';
        
        $result = [
            'file_exists' => file_exists($configFile),
            'file_readable' => is_readable($configFile),
            'config_loaded' => false,
            'db_config_found' => false,
            'db_config_valid' => false,
            'error' => null
        ];
        
        if (!$result['file_exists']) {
            $result['error'] = "Config file not found at: $configFile";
            return $result;
        }
        
        if (!$result['file_readable']) {
            $result['error'] = "Config file exists but is not readable";
            return $result;
        }
          try {
            // Clear any previous DB_CONFIG
            unset($GLOBALS['DB_CONFIG']);
            $DB_CONFIG = null;
            
            // Include the config file
            include $configFile;
            $result['config_loaded'] = true;
            
            // Try multiple methods to get the configuration
            $config = null;
            
            if (function_exists('getDBConfig')) {
                $config = getDBConfig();
            } elseif (isset($DB_CONFIG)) {
                $config = $DB_CONFIG;
            } elseif (isset($GLOBALS['DB_CONFIG'])) {
                $config = $GLOBALS['DB_CONFIG'];
            }
            
            // Check if DB_CONFIG is defined
            if ($config && is_array($config)) {
                $result['db_config_found'] = true;
                
                // Validate the config structure
                if (isset($config['host']) && 
                    isset($config['name']) && 
                    isset($config['user']) && 
                    isset($config['pass'])) {
                    $result['db_config_valid'] = true;
                } else {
                    $result['error'] = "DB_CONFIG is not properly structured";
                }
            } else {
                $result['error'] = "DB_CONFIG variable not found after including config.php";
            }
            
        } catch (Exception $e) {
            $result['error'] = "Exception loading config: " . $e->getMessage();
        }
        
        return $result;
    }
}

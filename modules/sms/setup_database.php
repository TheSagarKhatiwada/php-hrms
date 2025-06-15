<?php
/**
 * Simple Database Setup Script
 * Alternative to migration when PDO MySQL extension is not available
 */

echo "=== SMS Module Database Setup ===\n\n";

// Load database configuration from centralized config file
function createDatabaseConnection() {
    // Load configuration from main config file
    $config_file = __DIR__ . '/../../includes/config.php';
    if (!file_exists($config_file)) {
        echo "Configuration error: Database configuration file not found at: $config_file\n";
        echo "Please ensure includes/config.php exists with proper database configuration.\n";
        return null;
    }
    
    require_once $config_file;
    
    try {
        $dsn = "mysql:host={$DB_CONFIG['host']};dbname={$DB_CONFIG['name']};charset={$DB_CONFIG['charset']}";
        $pdo = new PDO($dsn, $DB_CONFIG['user'], $DB_CONFIG['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        echo "Database connection failed: " . $e->getMessage() . "\n";
        return null;
    }
}

// Test database connection
echo "1. Testing database connection...\n";
$pdo = createDatabaseConnection();

if ($pdo) {
    echo "✓ Database connection successful!\n\n";
    
    echo "2. Creating SMS tables...\n";
    
    try {
        // Create SMS configuration table
        echo "   - Creating sms_config table...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(100) NOT NULL UNIQUE,
                config_value TEXT NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Create SMS logs table
        echo "   - Creating sms_logs table...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone_number VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                sender VARCHAR(50) NOT NULL,
                status ENUM('pending', 'sent', 'failed', 'delivered') DEFAULT 'pending',
                message_id VARCHAR(100),
                response_data JSON,
                cost DECIMAL(10,4) DEFAULT 0.0000,
                employee_id INT DEFAULT NULL,
                template_id INT DEFAULT NULL,
                campaign_id INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_phone (phone_number),
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            )
        ");
        
        // Create SMS templates table
        echo "   - Creating sms_templates table...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                variables JSON,
                category ENUM('attendance', 'payroll', 'general', 'alerts', 'reminders') DEFAULT 'general',
                is_active BOOLEAN DEFAULT TRUE,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
          // Create SMS campaigns table
        echo "   - Creating sms_campaigns table...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(200) NOT NULL,
                description TEXT,
                template_id INT,
                recipient_type ENUM('all', 'department', 'designation', 'custom') DEFAULT 'custom',
                recipient_criteria JSON,
                status ENUM('draft', 'scheduled', 'sending', 'completed', 'failed') DEFAULT 'draft',
                scheduled_at TIMESTAMP NULL,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                total_recipients INT DEFAULT 0,
                sent_count INT DEFAULT 0,
                failed_count INT DEFAULT 0,
                total_cost DECIMAL(10,4) DEFAULT 0.0000,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Create SMS sender identities table
        echo "   - Creating sms_sender_identities table...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_sender_identities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identity VARCHAR(11) NOT NULL UNIQUE,
                description VARCHAR(200),
                is_default BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        echo "✓ All SMS tables created successfully!\n\n";
        
        echo "3. Inserting default configuration...\n";        // Insert default SMS configuration
        $defaultConfigs = [
            ['api_token', '', 'SparrowSMS API Token (Required - Get from sparrowsms.com dashboard)'],
            ['sender_identity', '', 'Sender Identity provided by SparrowSMS (Required)'],
            ['api_endpoint', 'https://api.sparrowsms.com/v2/sms/', 'SparrowSMS API Endpoint URL']
        ];
        
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO sms_config (config_key, config_value, description) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($defaultConfigs as $config) {
            $stmt->execute($config);
        }
        
        echo "✓ Default configuration inserted!\n\n";
        
        echo "4. Inserting default templates...\n";
        
        // Insert default SMS templates
        $defaultTemplates = [
            [
                'Attendance Reminder',
                'Daily Attendance Reminder',
                'Hi {employee_name}, please don\'t forget to mark your attendance today. Thank you!',
                '["employee_name"]',
                'attendance'
            ],
            [
                'Attendance Summary',
                'Daily Attendance Summary',
                'Hi {employee_name}, your attendance for {date}: Check-in: {check_in_time}, Check-out: {check_out_time}. Total hours: {total_hours}',
                '["employee_name", "date", "check_in_time", "check_out_time", "total_hours"]',
                'attendance'
            ],
            [
                'Leave Request Approved',
                'Leave Request Approved',
                'Hi {employee_name}, your leave request for {leave_dates} has been approved. Enjoy your time off!',
                '["employee_name", "leave_dates"]',
                'general'
            ],
            [
                'Birthday Wishes',
                'Birthday Wishes',
                'Happy Birthday {employee_name}! Wishing you a wonderful year ahead. Best regards from {company_name} team!',
                '["employee_name", "company_name"]',
                'general'
            ],
            [
                'Meeting Reminder',
                'Meeting Reminder',
                'Hi {employee_name}, reminder for the meeting "{meeting_title}" scheduled at {meeting_time} on {meeting_date}.',
                '["employee_name", "meeting_title", "meeting_time", "meeting_date"]',
                'reminders'
            ]
        ];
        
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO sms_templates (name, subject, message, variables, category) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($defaultTemplates as $template) {
            $stmt->execute($template);
        }
        
        echo "✓ Default templates inserted!\n\n";
        
        echo "5. Inserting default sender identities...\n";
        
        // Insert default approved sender identities
        $defaultIdentities = [
            ['HRMS', 'Default HRMS System Identity', true, true, 'approved'],
            ['COMPANY', 'Company Name Identity', false, true, 'approved'],
            ['ALERT', 'Alert Messages Identity', false, true, 'approved'],
            ['NOTICE', 'Notice Messages Identity', false, true, 'approved']
        ];
        
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO sms_sender_identities (identity, description, is_default, is_active, approval_status) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($defaultIdentities as $identity) {
            $stmt->execute($identity);
        }
        
        echo "✓ Default sender identities inserted!\n\n";
        
        echo "🎉 SMS Module setup completed successfully!\n\n";
        echo "Next steps:\n";
        echo "1. Visit SMS Configuration: http://localhost/php-hrms/modules/sms/sms-config.php\n";
        echo "2. Enter your Sparrow SMS API credentials\n";
        echo "3. Test SMS functionality\n";
        echo "4. Check SMS Dashboard: http://localhost/php-hrms/modules/sms/sms-dashboard.php\n";
        
    } catch (PDOException $e) {
        echo "❌ Database setup failed: " . $e->getMessage() . "\n";
        echo "\nTry running this SQL manually in phpMyAdmin or MySQL console:\n";
        echo "-- Run the SQL commands from migration_fixed.php --\n";
    }
    
} else {
    echo "❌ Cannot connect to database. Please check:\n";
    echo "1. MySQL service is running\n";
    echo "2. Database credentials are correct\n";
    echo "3. Database exists (create 'hrms_db' if needed)\n";
    echo "4. PDO MySQL extension is installed\n\n";
    
    echo "Alternative setup:\n";
    echo "1. Copy the SQL commands from migration_fixed.php\n";
    echo "2. Run them manually in phpMyAdmin\n";
    echo "3. Test with: php test_standalone.php\n";
}

echo "\n=== Setup Complete ===\n";
?>

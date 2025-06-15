<?php
/**
 * SMS Module Database Migration
 * Creates tables for SMS configuration, logs, and templates
 */

class SMSMigration {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function up() {
        try {
            // Create SMS configuration table
            $this->pdo->exec("
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
            $this->pdo->exec("
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
                    INDEX idx_created (created_at),
                    INDEX idx_employee (employee_id),
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
                )
            ");
            
            // Create SMS templates table
            $this->pdo->exec("
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
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                )
            ");
            
            // Create SMS campaigns table
            $this->pdo->exec("
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
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (template_id) REFERENCES sms_templates(id) ON DELETE SET NULL,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                )
            ");            // Insert default SMS configuration
            $defaultConfigs = [
                ['api_token', '', 'SparrowSMS API Token (Required - Get from sparrowsms.com dashboard)'],
                ['sender_identity', '', 'Sender Identity provided by SparrowSMS (Required)'],
                ['api_endpoint', 'https://api.sparrowsms.com/v2/sms/', 'SparrowSMS API Endpoint URL']
            ];
            
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO sms_config (config_key, config_value, description) 
                VALUES (?, ?, ?)
            ");
            
            foreach ($defaultConfigs as $config) {
                $stmt->execute($config);
            }
            
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
                    'Leave Request Rejected',
                    'Leave Request Rejected',
                    'Hi {employee_name}, your leave request for {leave_dates} has been rejected. Please contact HR for more details.',
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
                    'Payroll Alert',
                    'Salary Credited',
                    'Hi {employee_name}, your salary for {month} has been credited to your account. Amount: Rs. {amount}',
                    '["employee_name", "month", "amount"]',
                    'payroll'
                ]
            ];
            
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO sms_templates (name, subject, message, variables, category) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($defaultTemplates as $template) {
                $stmt->execute($template);
            }
            
            echo "SMS tables created successfully!\n";
            return true;
            
        } catch (Exception $e) {
            echo "Error creating SMS tables: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function down() {
        try {
            $this->pdo->exec("DROP TABLE IF EXISTS sms_campaigns");
            $this->pdo->exec("DROP TABLE IF EXISTS sms_logs");
            $this->pdo->exec("DROP TABLE IF EXISTS sms_templates");
            $this->pdo->exec("DROP TABLE IF EXISTS sms_config");
            
            echo "SMS tables dropped successfully!\n";
            return true;
            
        } catch (Exception $e) {
            echo "Error dropping SMS tables: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Run migration if executed directly
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    require_once __DIR__ . '/../includes/db_connection.php';
    
    $migration = new SMSMigration($pdo);
    
    if (isset($_GET['action']) && $_GET['action'] === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
?>

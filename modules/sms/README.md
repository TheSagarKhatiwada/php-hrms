# SMS Management System - Documentation

## 📋 Overview
Complete SMS management system integrated with Sparrow SMS API for the PHP HRMS application. Supports both single and bulk SMS sending with comprehensive logging and monitoring capabilities.

## 🚀 Features
- ✅ **Unified SMS Interface** - Single textarea for both individual and bulk SMS
- ✅ **Sparrow SMS API Integration** - Complete integration with all API endpoints
- ✅ **Smart Phone Number Validation** - Nepal phone number format validation
- ✅ **Real-time Credit Monitoring** - Check SMS credit balance
- ✅ **Comprehensive Logging** - Database and file-based logging
- ✅ **Message Templates** - Quick message templates for common scenarios
- ✅ **Reusable Components** - Modular SMS modal component
- ✅ **Error Handling** - Detailed error messages and debugging information

## 📁 File Structure
```
modules/sms/
├── SparrowSMS.php           # Core SMS API service class
├── sms-config.php           # SMS configuration management
├── sms-dashboard.php        # Main SMS sending interface
├── sms-logs.php            # SMS logs viewer and management
├── sms-templates.php       # SMS templates management
├── export-sms-logs.php     # Export SMS logs functionality
├── setup_database.php      # Database setup script
├── migration.php           # Database migration script
└── logs/                   # SMS operation logs directory

includes/
└── sms-modal.php           # Reusable SMS modal component
```

## 🔧 Configuration
1. **API Setup**: Configure your Sparrow SMS API token in SMS Configuration
2. **Sender Name**: Set your default SMS sender name
3. **IP Whitelisting**: Ensure your server IP is whitelisted in Sparrow SMS account

## 🎯 Core Components

### SparrowSMS Class
Main API integration class with methods:
- `sendSMS($to, $text, $from)` - Send single SMS
- `sendBulkSMS($recipients, $text, $from)` - Send bulk SMS
- `checkCredit()` - Check SMS credit balance
- `getSMSStatus($messageId)` - Get SMS delivery status
- `getSMSLogs()` - Retrieve SMS logs from API

### SMS Modal Component
Reusable modal component (`includes/sms-modal.php`) with:
- Phone number validation
- Character counter
- Message templates
- Loading states
- Error handling

## 📊 Database Schema
```sql
-- SMS Configuration Table
CREATE TABLE sms_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Required Configuration Fields for SparrowSMS API
-- api_token: Your API token from sparrowsms.com dashboard
-- sender_identity: Sender identity provided by SparrowSMS
-- api_endpoint: SparrowSMS API endpoint (https://api.sparrowsms.com/v2/sms/)
-- sms_enabled: Enable/Disable SMS functionality (0=Disabled, 1=Enabled)

-- SMS Logs Table
CREATE TABLE sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20),
    message TEXT,
    sender VARCHAR(20),
    status ENUM('sent', 'failed', 'pending'),
    message_id VARCHAR(100),
    response_data JSON,
    cost DECIMAL(10,4) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SMS Templates Table
CREATE TABLE sms_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    message TEXT,
    category VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 🔧 Configuration

### Required SparrowSMS API Fields
Based on [SparrowSMS API Documentation](https://docs.sparrowsms.com/sms/outgoing_sendsms/):

1. **api_token** (Required): Your API token from SparrowSMS dashboard
2. **sender_identity** (Required): Sender identity provided by SparrowSMS  
3. **api_endpoint**: API endpoint URL (default: https://api.sparrowsms.com/v2/sms/)

### Getting Started
1. Sign up at [SparrowSMS Dashboard](https://web.sparrowsms.com/)
2. Get your API token from the dashboard
3. Get your approved sender identity from SparrowSMS
4. Update the configuration in SMS settings page
5. Enable SMS functionality

## 🔍 Usage Examples

### Single SMS
```php
$sms = new SparrowSMS();
$response = $sms->sendSMS('9771234567890', 'Hello World!', 'YourSender');
```

### Bulk SMS
```php
$sms = new SparrowSMS();
$recipients = ['9771234567890', '9779876543210'];
$response = $sms->sendBulkSMS($recipients, 'Bulk message', 'HRMS');
```

### Check Credit
```php
$sms = new SparrowSMS();
$credit = $sms->checkCredit();
echo "Available credits: " . $credit['credit_balance'];
```

## 🛠 Troubleshooting

### Common Issues
1. **Invalid Token**: Ensure API token is valid and IP is whitelisted
2. **HTTP 301**: Fixed - endpoints now use trailing slashes
3. **Database Errors**: Run migration script to update schema
4. **Phone Format**: Numbers should be in format 977XXXXXXXXX

### Error Codes
- **1002**: Invalid API Token
- **1001**: Insufficient SMS credits
- **1003**: IP not whitelisted

## 📈 Monitoring
- **File Logs**: Check `modules/sms/logs/sms_YYYY_MM_DD.log`
- **Database Logs**: Query `sms_logs` table
- **Dashboard**: View statistics in SMS Dashboard

## 🔒 Security
- API tokens are stored securely in database
- Phone numbers are validated before sending
- All SMS operations are logged for audit trail
- CSRF protection on all forms

## 📞 Support
For Sparrow SMS API support:
- Website: https://sparrowsms.com
- Documentation: https://docs.sparrowsms.com
- Token Management: https://web.sparrowsms.com/token/

---
*Last updated: June 6, 2025*
*Version: 1.0.0*

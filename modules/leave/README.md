# Leave Management Module

A comprehensive leave management system for the PHP HRMS application that handles employee leave requests, approvals, and administration.

## Features

### Core Functionality
- **Leave Request Management**: Submit, view, edit, and cancel leave requests
- **Approval Workflow**: Multi-level approval process with comments and rejection reasons
- **Leave Balance Tracking**: Real-time tracking of leave balances with visual indicators
- **Calendar Integration**: Calendar view of leave requests and company-wide leave overview
- **Reports & Analytics**: Comprehensive reporting with export capabilities
- **Email Notifications**: Automated email notifications for all leave activities

### User Roles & Permissions
- **Employee**: Submit requests, view own requests, check balances
- **Supervisor**: Approve/reject requests, view team requests
- **HR**: Manage leave types, view all requests, generate reports
- **Admin**: Full access to all leave management features

### Leave Types
- Annual Leave
- Sick Leave
- Casual Leave
- Maternity Leave
- Paternity Leave
- Custom leave types with configurable settings

## File Structure

```
modules/leave/
├── index.php              # Main dashboard
├── request.php            # Leave application form
├── my-requests.php        # Employee's leave history
├── requests.php           # Admin/HR request management
├── view.php               # Detailed request view
├── approve.php            # Request approval interface
├── reject.php             # Request rejection interface
├── cancel-request.php     # AJAX request cancellation
├── balance.php            # Leave balance tracking
├── types.php              # Leave type management
├── calendar.php           # Calendar view
├── reports.php            # Analytics and reporting
├── export-reports.php     # Report export functionality
├── config.php             # Module configuration
├── notifications.php      # Email notification system
├── navigation.php         # Navigation integration
└── README.md             # This file
```

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Existing PHP HRMS system
- Email server configuration (for notifications)

### Database Setup

The Leave Module uses the following existing database tables:
- `employees` - Employee information
- `departments` - Department structure
- `leave_requests` - Leave request records
- `leave_types` - Leave type definitions
- `leave_balances` - Employee leave balances (if implemented)

### Configuration

1. **Module Configuration**
   Edit `config.php` to customize:
   - Default leave balances
   - Notification settings
   - File upload settings
   - Permission configurations

2. **Email Configuration**
   Update the email settings in `notifications.php`:
   ```php
   $this->from_email = 'hrms@yourcompany.com';
   $this->from_name = 'Your Company HRMS';
   ```

3. **Navigation Integration**
   Include the leave module in your main navigation by adding to your header/sidebar:
   ```php
   include_once 'modules/leave/navigation.php';
   echo getLeaveModuleMenuHtml($_SESSION['role'], $_SERVER['REQUEST_URI']);
   ```

## Usage

### For Employees

1. **Applying for Leave**
   - Navigate to "Apply for Leave"
   - Select leave type and dates
   - Provide reason and submit request
   - Track request status in "My Requests"

2. **Checking Leave Balance**
   - View current leave balances
   - See leave history and usage patterns
   - Track remaining days for each leave type

3. **Managing Requests**
   - View all submitted requests
   - Cancel pending requests
   - Download request details

### For Administrators

1. **Managing Requests**
   - View all leave requests with filtering options
   - Bulk approve/reject multiple requests
   - Add comments and reasons for decisions

2. **Leave Type Management**
   - Create and configure leave types
   - Set default balances and rules
   - Manage leave type colors and settings

3. **Reporting**
   - Generate comprehensive leave reports
   - Export data to Excel/PDF formats
   - Analyze leave trends and patterns

## API Endpoints

### AJAX Endpoints
- `cancel-request.php` - Cancel leave request
- `export-reports.php` - Export report data

### Notification System
- Automatic email notifications for all leave activities
- Configurable email templates
- Support for reminder notifications

## Customization

### Adding New Leave Types
1. Access "Leave Types" from the admin menu
2. Click "Add New Leave Type"
3. Configure settings like days allowed, color coding, and approval requirements

### Custom Email Templates
Edit the `$email_templates` array in `config.php`:
```php
$email_templates['custom_template'] = [
    'subject' => 'Your Subject Here',
    'body' => 'Your email body with {variables}'
];
```

### Permission Customization
Modify the `$leave_permissions` array in `config.php` to adjust role-based access:
```php
$leave_permissions['custom_role'] = [
    PERMISSION_VIEW_OWN_REQUESTS,
    PERMISSION_CREATE_REQUESTS
];
```

## Security Features

- **Input Validation**: All inputs are validated and sanitized
- **SQL Injection Prevention**: Prepared statements used throughout
- **Session Management**: Secure session handling
- **Role-based Access Control**: Granular permission system
- **CSRF Protection**: Protection against cross-site request forgery

## Troubleshooting

### Common Issues

1. **Email Notifications Not Working**
   - Check PHP mail configuration
   - Verify SMTP settings
   - Check email logs in `logs/email.log`

2. **Permission Denied Errors**
   - Verify user role assignments
   - Check permission configurations in `config.php`
   - Ensure database user has proper privileges

3. **Calendar Not Loading**
   - Check JavaScript console for errors
   - Verify database date formats
   - Ensure proper timezone settings

### Debug Mode
Enable debug mode by setting in `config.php`:
```php
define('LEAVE_MODULE_DEBUG', true);
```

## Performance Optimization

### Database Optimization
- Add indexes on frequently queried columns:
  ```sql
  CREATE INDEX idx_leave_requests_employee_date ON leave_requests(employee_id, start_date);
  CREATE INDEX idx_leave_requests_status ON leave_requests(status);
  ```

### Caching
- Implement caching for leave balances
- Cache frequently accessed leave types
- Use session caching for user permissions

## Integration with Main HRMS

### Dashboard Widget
Add leave overview to main dashboard:
```php
include_once 'modules/leave/navigation.php';
echo getLeaveModuleDashboardWidget($_SESSION['user_id'], $_SESSION['role']);
```

### Notification Integration
Include leave notifications in main notification system:
```php
$leave_notifications = getLeaveNotifications($_SESSION['user_id'], $_SESSION['role']);
```

## Backup and Maintenance

### Regular Maintenance
- Clean up old notification logs
- Archive completed leave requests
- Update leave balances for new year

### Backup Procedures
- Include leave module files in system backup
- Backup leave-related database tables
- Preserve configuration settings

## Support and Updates

### Version Information
- **Current Version**: 1.0.0
- **Compatibility**: PHP HRMS v1.0+
- **Last Updated**: June 2025

### Getting Help
- Check the troubleshooting section
- Review error logs in `logs/` directory
- Contact system administrator for technical issues

## License
This Leave Management Module is part of the PHP HRMS system and follows the same licensing terms.

## Changelog

### Version 1.0.0 (June 2025)
- Initial release
- Complete leave management functionality
- Email notification system
- Comprehensive reporting
- Calendar integration
- Multi-role support
- Export capabilities
- Mobile-responsive design

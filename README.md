# PHP HRMS - Human Resource Management System

## Project Overview

PHP HRMS is a comprehensive web-based Human Resource Management System designed to streamline and automate various HR operations. Built using PHP with a MySQL/MariaDB database backend, this system provides an integrated platform for managing employees, attendance, leave requests, tasks, assets, SMS notifications, and more.

## System Architecture

### Technology Stack
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: 
  - HTML5, CSS3, JavaScript (ES6+)
  - Bootstrap 5.x (Responsive UI Framework)
  - Font Awesome (Icons)
  - jQuery (DOM manipulation)
- **PDF Generation**: TCPDF Library
- **File Upload**: Dropzone.js
- **Authentication**: Session-based authentication with CSRF protection

### Directory Structure

```
php-hrms/
├── modules/              # Feature modules
│   ├── employees/       # Employee management
│   ├── attendance/      # Attendance tracking & scheduling
│   ├── leave/          # Leave request & accrual management
│   ├── tasks/          # Task assignment & tracking
│   ├── assets/         # Asset & maintenance management
│   ├── sms/            # SMS messaging integration
│   └── salary/         # Salary components & topics
├── includes/            # Core system files
│   ├── db_connection.php       # Database connection
│   ├── session_config.php      # Session management
│   ├── header.php              # Page header
│   ├── sidebar.php             # Navigation sidebar
│   ├── footer.php              # Page footer
│   ├── utilities.php           # Helper functions
│   ├── csrf_protection.php     # Security helpers
│   └── report-templates/       # PDF report templates
├── api/                 # API endpoints
├── migrations/          # Database migrations
├── schema/             # Database schema files
├── resources/          # Static assets (images, uploads)
├── plugins/            # Third-party libraries
├── scripts/            # Utility scripts
└── docs/               # Documentation
```

## Core Modules

### 1. Employee Management
**Location**: `modules/employees/`

**Features**:
- Employee profile creation and management
- Employee photo upload with cropping
- Hierarchical organization (supervisor/subordinate)
- Department and designation assignment
- Branch management and transfers
- Login access control
- Contact information management
- Exit/termination processing

**Key Files**:
- `add-employee.php` - New employee registration
- `employees.php` - Employee listing
- `employee-viewer.php` - Detailed employee view
- `edit-employee.php` - Employee profile editing
- `schedule-overrides.php` - Individual schedule management

**Database Tables**:
- `employees` - Core employee data
- `departments` - Department structure
- `designations` - Job positions/titles
- `branches` - Office/branch locations
- `employee_branch_transfers` - Transfer history

### 2. Attendance Management
**Location**: `modules/attendance/`

**Features**:
- Biometric device integration (machine ID based)
- Manual attendance recording
- Bulk attendance upload (TXT/CSV files)
- Attendance editing and deletion
- Schedule override system
- Shift template assignment
- Grace period management
- Attendance reporting

**Key Files**:
- `attendance.php` - Main attendance dashboard
- `upload-attendance.php` - Bulk upload interface
- `record_attendance.php` - Manual entry (web-based)
- `schedule-overrides.php` - Schedule exception management
- `edit-attendance.php` - Modify existing records

**Database Tables**:
- `attendance_logs` - Attendance records
- `shift_templates` - Predefined work shifts
- `employee_shift_assignments` - Employee shift mapping
- `employee_schedule_overrides` - Temporary schedule changes

**Schedule System**:
- Default work hours per employee
- Shift templates with start/end times
- Open-ended schedule overrides (NULL end_date support)
- Partial time overrides (start_time OR end_time)
- Priority-based resolution (latest override wins)

### 3. Leave Management
**Location**: `modules/leave/`

**Features**:
- Leave request submission
- Multi-level approval workflow
- Leave balance tracking
- Accrual system (monthly/annual)
- Carry-forward support
- Leave calendar visualization
- Leave type configuration
- Comprehensive reporting
- Half-day leave support

**Key Files**:
- `index.php` - Leave dashboard
- `my-requests.php` - Employee leave history
- `calendar.php` - Leave calendar view
- `accrual.php` - Accrual processing
- `reports.php` - Leave analytics
- `config.php` - Leave system configuration

**Database Tables**:
- `leave_requests` - Leave applications
- `leave_types` - Leave categories (Annual, Sick, Casual, etc.)
- `leave_balances` - Employee leave balances
- `leave_accruals` - Monthly accrual records

**Accrual System**:
- Monthly automatic accrual processing
- Pro-rata calculation for new employees
- Carry-forward rules per leave type
- Balance synchronization
- Audit trail maintenance

### 4. Task Management
**Location**: `modules/tasks/`

**Features**:
- Task creation and assignment
- Priority levels (Low, Medium, High, Urgent)
- Status tracking (Pending, In Progress, Completed)
- Task categories
- Progress monitoring
- Department-level tasks
- Team task visibility
- Task reassignment (Admin)

**Key Files**:
- `index.php` - Task dashboard
- `tasks.php` - Task listing
- `create-task.php` - New task creation
- `view_task.php` - Task details
- `all-tasks.php` - Admin task overview
- `task-categories.php` - Category management

**Database Tables**:
- `tasks` - Task records
- `task_categories` - Task classification
- `task_status` - Status definitions

**Task Types**:
- **Assigned**: Direct employee assignment
- **Department**: Open to department members
- **Team**: Visible to team members

### 5. Asset Management
**Location**: `modules/assets/`

**Features**:
- Asset registration and tracking
- Maintenance scheduling
- Maintenance history
- Asset assignment to employees
- Asset categories
- Warranty tracking
- Disposal management

**Key Files**:
- `manage_assets.php` - Asset catalog
- `manage_maintenance.php` - Maintenance operations
- `add_asset.php` - Asset registration

**Database Tables**:
- `assets` - Asset inventory
- `asset_maintenance` - Maintenance records

### 6. SMS Management
**Location**: `modules/sms/`

**Features**:
- Sparrow SMS API integration
- Single and bulk SMS sending
- SMS templates
- Delivery status tracking
- Credit balance monitoring
- Comprehensive logging
- Reusable modal component

**Key Files**:
- `sms-dashboard.php` - SMS sending interface
- `sms-logs.php` - Delivery history
- `sms-templates.php` - Template management
- `sms-config.php` - API configuration
- `SparrowSMS.php` - API service class

**Database Tables**:
- `sms_logs` - SMS delivery records
- `sms_config` - API credentials & settings
- `sms_templates` - Message templates

**Configuration Requirements**:
- Sparrow SMS API token
- Sender identity (approved by provider)
- Server IP whitelisting

### 7. Reporting System
**Location**: `includes/report-templates/`

**Features**:
- Monthly attendance reports
- PDF export functionality
- Employee-wise summaries
- Leave analytics
- Custom date range reporting
- Multi-branch support

**Report Templates**:
- `monthly-attendance.php` - Attendance summary
- Various leave reports
- Employee reports

## Database Schema Overview

### Core Tables

**employees**
- Primary employee data (ID, name, contact, dates)
- Work schedule (start/end times)
- Biometric integration (mach_id)
- Organizational linkage (supervisor, department, branch)
- Login and access control
- Employment status tracking

**attendance_logs**
- Attendance punch records
- Machine serial number (mach_sn) for duplicate prevention
- Employee and machine ID mapping
- Check-in time stamps
- Entry method tracking (device/manual/web)

**leave_requests**
- Leave application details
- Date range and duration
- Approval workflow status
- Reviewer information
- Attachment support
- Soft delete capability

**leave_balances**
- Year-wise leave allocations
- Used, pending, and remaining days
- Carry-forward amounts
- Automatic synchronization with accruals

**tasks**
- Task information and assignments
- Priority and status tracking
- Progress percentage
- Assignor and assignee linkage
- Due date management

**branches**
- Office/branch locations
- Branch-specific settings

**departments**
- Organizational units
- Department-level operations

**designations**
- Job titles and positions
- Role definitions

## Key Features & Functionality

### Authentication & Authorization
- Session-based authentication
- Role-based access control (Admin, HR, Employee)
- Permission system for granular access
- CSRF protection on forms
- Password reset functionality
- Session timeout management

### Employee Hierarchy
- Supervisor-subordinate relationships
- Department structure
- Multi-branch support
- Organizational chart visualization

### Attendance Processing
- Duplicate detection via `mach_sn`
- Automatic employee ID mapping
- Schedule resolution with overrides
- Grace period support
- Manual attendance with approval
- Attendance editing with audit trail

### Leave Accrual System
- Monthly automatic processing
- Pro-rata calculation for mid-year joiners
- Balance updates post-accrual
- Eligible leave types configuration
- Carry-forward rules

### Notification System
- Email notifications for key events
- SMS integration for alerts
- Real-time notifications (via database)
- Welcome messages for new employees

### Backup & Maintenance
- Database backup system
- Schema export functionality
- Migration framework
- System validation tools

## API Integration

### Sparrow SMS API
**Endpoint**: `https://api.sparrowsms.com/v2/sms/`

**Features**:
- Text message sending
- Credit balance check
- Delivery status tracking
- Error handling and logging

**Authentication**: Token-based (Bearer)

**Response Handling**:
- Success/failure detection
- Message ID tracking
- Cost calculation
- Response data storage (JSON)

## Configuration Files

### Database Connection
**File**: `includes/db_connection.php`
- PDO-based connection
- Error mode configuration
- Transaction support

### Session Management
**File**: `includes/session_config.php`
- Session initialization
- Lifetime configuration
- Security settings

### CSRF Protection
**File**: `includes/csrf_protection.php`
- Token generation
- Token validation
- Form integration

### Utilities
**File**: `includes/utilities.php`
- Helper functions (is_admin, has_permission)
- Date formatting
- String manipulation
- Data validation

## Security Features

1. **Input Validation**
   - Prepared statements (PDO)
   - Data sanitization
   - Type enforcement

2. **Access Control**
   - Session validation
   - Role verification
   - Permission checks

3. **CSRF Protection**
   - Token-based validation
   - Form-level protection

4. **Password Security**
   - Hashed storage
   - Secure reset mechanism

5. **File Upload Security**
   - Type validation
   - Size limits
   - Secure storage paths

## Migration System

**Location**: `migrations/`

**Structure**:
```php
return [
    'up' => function($pdo) {
        // Forward migration
    },
    'down' => function($pdo) {
        // Rollback (optional)
    }
];
```

**Execution**: Run via `run_migration.php`

**Key Migrations**:
- Employee work time fields
- Schedule override tables
- Leave system tables
- Branch transfer tracking
- Task schema updates

## Installation & Setup

### System Requirements
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- PDO PHP extension
- GD library (for image processing)
- mbstring extension

### Installation Steps

1. **Clone/Download** the project files
2. **Import** database schema from `schema/database_schema.sql`
3. **Configure** database connection in `includes/db_connection.php`
4. **Set** file permissions:
   - `uploads/` - writable (755)
   - `resources/userimg/uploads/` - writable
   - `db_backup/` - writable
   - `logs/` - writable
5. **Run** migrations: `php run_migration.php`
6. **Access** setup page: `setup.php`
7. **Create** admin account
8. **Configure** system settings

### Post-Installation

1. Configure branches and departments
2. Set up designations
3. Configure leave types
4. Set up SMS integration (if required)
5. Import employees
6. Upload initial attendance data

## Usage Guidelines

### For Administrators

1. **Employee Management**
   - Add employees via `modules/employees/add-employee.php`
   - Assign supervisors and departments
   - Configure work schedules
   - Manage branch transfers

2. **Attendance**
   - Upload bulk attendance files
   - Review and edit records
   - Generate reports
   - Monitor schedule overrides

3. **Leave Management**
   - Process leave requests
   - Run monthly accrual
   - Configure leave types
   - Generate analytics

4. **Task Management**
   - Create and assign tasks
   - Monitor progress
   - Reassign tasks
   - Manage categories

### For Employees

1. **Profile**
   - View personal information
   - Update contact details (if permitted)

2. **Attendance**
   - Record manual attendance (if enabled)
   - View attendance history

3. **Leave**
   - Apply for leave
   - Check leave balance
   - Track request status

4. **Tasks**
   - View assigned tasks
   - Update task progress
   - Complete tasks

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Verify credentials in `db_connection.php`
   - Check database server status
   - Ensure PDO extension is enabled

2. **File Upload Issues**
   - Check directory permissions
   - Verify PHP upload limits
   - Review file type restrictions

3. **Session Problems**
   - Check session configuration
   - Verify session directory permissions
   - Review session timeout settings

4. **SMS Delivery Failures**
   - Verify API credentials
   - Check credit balance
   - Ensure IP whitelisting
   - Review error logs

## Support & Maintenance

### Log Files
- `error_log.txt` - PHP errors
- `logs/` - Application logs
- `modules/sms/logs/` - SMS operation logs

### Database Maintenance
- Regular backups via `backup.php`
- Schema exports for version control
- Migration tracking

### Performance Optimization
- Database indexing
- Query optimization
- Session cleanup
- Log rotation

## Future Enhancements

Potential areas for expansion:
- Payroll processing integration
- Performance appraisal module
- Recruitment and onboarding
- Training and development tracking
- Document management
- Mobile application
- REST API development
- Real-time notifications (WebSockets)
- Advanced analytics dashboard
- Multi-language support

## Credits & Attribution

### Third-Party Libraries
- **Bootstrap 5** - UI Framework
- **TCPDF** - PDF Generation
- **Dropzone.js** - File Uploads
- **jQuery** - DOM Manipulation
- **Font Awesome** - Icon Library

### API Services
- **Sparrow SMS** - SMS Gateway Provider

## License

[Specify your license here]

## Contact & Support

For technical support or inquiries, please refer to your organization's IT support channels.

---

**Document Version**: 1.0  
**Last Updated**: November 27, 2025  
**Prepared For**: PHP HRMS Project Documentation

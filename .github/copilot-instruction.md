# PHP HRMS Application - Development Guidelines

## 🏗️ Architecture Overview

This PHP HRMS (Human Resource Management System) follows a structured MVC-like architecture with the following key principles which can run smoothly in linux based server;

### Core Architecture
- **Frontend**: Vanilla PHP with Bootstrap 5 UI framework
- **Backend**: Pure PHP 8+ with PDO for database operations
- **Database**: MySQL/MariaDB with proper foreign key relationships
- **Session Management**: Custom secure session handling
- **Security**: CSRF protection, input validation, and role-based access control

## 📋 Key Building Principles

### 1. **Employee Identifier System**
- **PRIMARY KEY**: Use `emp_id` (VARCHAR(20)) as the unique employee identifier throughout the system
- **NEVER** use integer `id` columns for employee references
- **Employee ID Format**: `{branch_id}{sequential_number}` (e.g., "101", "102", "201")
- **All foreign keys** referencing employees must use `emp_id` type VARCHAR(20)

### 2. **Database Schema Standards**

**IMPORTANT**: Always refer to `schema/database_schema.sql` for the complete and authoritative database schema. The schema file contains the official table structures, relationships, and constraints.

Key principles for database schema:
- **Employee Primary Key**: `emp_id` VARCHAR(20) - unique employee identifier
- **No integer ID references**: All employee relationships use `emp_id` 
- **Foreign Key Pattern**: All tables referencing employees must use `emp_id` type VARCHAR(20)
- **Schema Updates**: Any schema changes must be reflected in `schema/database_schema.sql`

```sql
-- Refer to schema/database_schema.sql for complete schema
-- Key employee-related tables that MUST use emp_id:
-- employees (primary table with emp_id as PRIMARY KEY)
-- attendance_logs (emp_id references employees.emp_id)
-- notifications (emp_id references employees.emp_id)  
-- leave_requests (emp_id references employees.emp_id)
-- asset_assignments (emp_id references employees.emp_id)

-- Example foreign key constraint pattern:
ALTER TABLE table_name 
ADD CONSTRAINT fk_table_emp_id 
FOREIGN KEY (emp_id) REFERENCES employees(emp_id) 
ON DELETE CASCADE;
```

**Schema Maintenance**: 
- Update `schema/database_schema.sql` when making structural changes
- Ensure all foreign keys use proper `emp_id` references
- Run schema validation after major changes

### 3. **File Structure Standards**
```
includes/
├── config.php                 # Central database configuration
├── db_connection.php          # PDO connection with error handling
├── session_config.php         # Secure session management
├── utilities.php              # Common utility functions
├── notification_helpers.php   # Notification system functions
├── hierarchy_helpers.php      # Employee hierarchy functions
├── header.php                 # Common page header
├── footer.php                 # Common page footer
├── sidebar.php               # Navigation sidebar
└── services/
    └── NotificationService.php # Notification service class

modules/
├── leave/                     # Leave management module
├── attendance/               # Attendance tracking module
└── assets/                   # Asset management module
```

### 4. **Session and Authentication**
```php
// Always include session config first
require_once 'includes/session_config.php';
require_once 'includes/utilities.php';

// Use standardized authentication checks
if (!is_admin()) {
    header('Location: dashboard.php');
    exit();
}

// Session data uses emp_id
$_SESSION['user_id'] = $emp_id; // VARCHAR emp_id, not integer
$_SESSION['user_role'] = $role_id;
```

### 5. **Database Connection Pattern**
```php
// Always use centralized config
require_once 'includes/db_connection.php';

// Use prepared statements for all queries
$stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
$stmt->execute([$emp_id]);

// Use emp_id in all employee-related queries
$stmt = $pdo->prepare("INSERT INTO attendance_logs (emp_id, date, time_in) VALUES (?, ?, ?)");
```

## 🔧 Development Standards

### 1. **Employee ID Generation**
```php
// Generate new employee ID based on branch
$stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE branch = ? ORDER BY emp_id DESC LIMIT 1");
$stmt->execute([$branchId]);
$lastEmployee = $stmt->fetch();

if ($lastEmployee) {
    $lastId = $lastEmployee['emp_id'];
    $numberPart = (int)substr($lastId, strlen($branchId));
    $nextNumber = $numberPart + 1;
    $empId = $branchId . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
} else {
    $empId = $branchId . '01';
}
```

### 2. **Notification System**
```php
// Use emp_id for all notifications
notify_employee($emp_id, 'action_type');
notify_system('Title', 'Message', 'success', true);

// NotificationService uses emp_id
$notificationService = new NotificationService($pdo);
$notificationService->sendNotification($emp_id, $title, $message, $type);
```

### 3. **Form Validation**
```php
// Always validate and sanitize input
$emp_id = filter_input(INPUT_POST, 'emp_id', FILTER_SANITIZE_STRING);
$first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);

// Use prepared statements with parameter binding
$stmt = $pdo->prepare("UPDATE employees SET first_name = ? WHERE emp_id = ?");
$stmt->execute([$first_name, $emp_id]);
```

### 4. **Error Handling**
```php
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $_SESSION['success'] = "Operation completed successfully!";
} catch (PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    error_log("Database error: " . $e->getMessage());
}

// Always redirect after POST operations
header("Location: page.php?_nocache=" . time());
exit();
```

### 5. **Security Best Practices**
```php
// CSRF Protection
require_once 'includes/csrf_protection.php';

// Role-based access control
if (!has_permission('manage_employees')) {
    http_response_code(403);
    die('Access denied');
}

// Input validation
if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $emp_id)) {
    $_SESSION['error'] = "Invalid employee ID format";
    header('Location: employees.php');
    exit();
}
```

## 🗄️ Database Schema & Migration Guidelines

### Schema Reference
- **Authoritative Source**: `schema/database_schema.sql` contains the complete database schema
- **Always check schema file** before making database-related changes
- **Update schema file** when making structural modifications
- **Version control**: All schema changes must be committed to the repository

### Adding New Features
1. **Review existing schema** in `schema/database_schema.sql`
2. **Plan changes** to maintain `emp_id` consistency  
3. **Always use emp_id** for employee references (never integer IDs)
4. **Create migration scripts** for schema changes
5. **Update schema file** with new structures
6. **Test migrations** on development data
7. **Update all related queries** to use new columns

### Schema Validation
```sql
-- Verify all employee references use emp_id
SELECT 
    TABLE_NAME, 
    COLUMN_NAME, 
    REFERENCED_TABLE_NAME, 
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE REFERENCED_TABLE_NAME = 'employees' 
AND TABLE_SCHEMA = 'hrms';

-- Should show emp_id references, not id references
```

### Foreign Key Standards
```sql
-- Always reference emp_id for employee relationships
-- Pattern from schema/database_schema.sql:
ALTER TABLE new_table 
ADD CONSTRAINT fk_new_table_emp_id 
FOREIGN KEY (emp_id) REFERENCES employees(emp_id) 
ON DELETE CASCADE;
```

## ⚠️ Critical Database Schema Notes

**CONFIRMED**: The current implementation uses:
- **Employee Primary Key**: `emp_id` VARCHAR(20) (NOT `id`)
- **Table Names**: Use lowercase table names consistently (`assetassignments`, `fixedassets`, `employees`)
- **Column Names**: Use lowercase column names (`first_name`, `last_name`, NOT `First_Name`, `Last_Name`)
- **Foreign Key Pattern**: All employee references use `emp_id` VARCHAR(20)

**Recent Migration Lessons**:
- Always verify actual database schema before making assumptions
- Use `DESCRIBE table_name` to check actual column names and types
- Table name casing matters for cross-platform compatibility
- Employee queries must use `emp_id` for joins and references

### Verified Schema Patterns:
```sql
-- Employees table structure (confirmed):
-- Primary Key: emp_id VARCHAR(20)
-- Columns: first_name, last_name, middle_name (all lowercase)

-- Asset assignments pattern (confirmed):
-- EmployeeID VARCHAR(20) references employees.emp_id
-- Table name: assetassignments (lowercase)

-- Correct JOIN syntax:
SELECT e.first_name, e.last_name 
FROM employees e 
JOIN assetassignments aa ON e.emp_id = aa.EmployeeID
```

## 🧪 Testing Guidelines

### Before Code Changes
1. **Backup database** before structural changes
2. **Test all CRUD operations** for affected modules
3. **Verify foreign key relationships** work correctly
4. **Test user permissions** and role-based access

### After Code Changes
1. **Run manual tests** on all affected features
2. **Check error logs** for any issues
3. **Verify session handling** works correctly
4. **Test notification system** functionality

## 📁 Module Development Pattern

### Creating New Modules
```php
<?php
// Standard module header
require_once 'includes/session_config.php';
require_once 'includes/utilities.php';

$page = 'module_name';

// Permission check
if (!has_permission('module_permission')) {
    header('Location: dashboard.php');
    exit();
}

include 'includes/db_connection.php';

// Module logic here...

require_once 'includes/header.php';
?>

<!-- HTML content -->

<?php require_once 'includes/footer.php'; ?>
```

## 🔄 Deployment Checklist

### Pre-deployment
- [ ] **Verify schema file** `schema/database_schema.sql` is up to date
- [ ] Update `includes/config.php` with production database credentials
- [ ] Set `ENVIRONMENT` to 'production'
- [ ] Remove all test/debug files
- [ ] Verify all file permissions are secure
- [ ] Test all critical user flows
- [ ] **Validate database schema** matches `schema/database_schema.sql`

### Post-deployment
- [ ] Verify database connection works
- [ ] **Check schema integrity** - all tables match schema file
- [ ] Test login and session handling
- [ ] Check all employee operations (add/edit/view)
- [ ] Verify notification system functionality
- [ ] Test attendance tracking features
- [ ] **Confirm emp_id consistency** across all modules

## ⚠️ Common Pitfalls to Avoid

1. **Never use integer `id` for employee references** - Always use `emp_id` VARCHAR(20)
2. **Don't bypass session configuration** - Always include `session_config.php` first
3. **Avoid direct SQL queries** - Always use prepared statements with PDO
4. **Don't forget CSRF protection** on forms that modify data
5. **Never commit database credentials** to version control
6. **Don't skip input validation** - Always sanitize and validate user input
7. **Avoid mixing authentication methods** - Use the standardized utility functions

## 🎯 Key Success Metrics

- All features work with `emp_id` as the primary employee identifier
- No "Unknown column" errors in logs
- Session handling is secure and consistent
- All database operations use prepared statements
- Notification system functions correctly
- Role-based permissions are properly enforced

# HRMS Database Schema

This directory contains the clean installation schema for the PHP HRMS system.

## Files

- `hrms_schema.sql` - Complete database schema with all required tables and default data

## Schema Overview

The schema includes all tables required by the HRMS application:

### Core Tables
- `users` - User accounts and authentication
- `employees` - Employee records with all necessary fields (including `emp_id`, `designation`, `branch`, etc.)
- `departments` - Company departments
- `designations` - Job positions/designations
- `branches` - Company branches/locations

### Attendance & Leaves
- `attendance` - Daily attendance records
- `attendance_logs` - Raw attendance machine data
- `leaves` - Leave applications
- `leave_requests` - Leave requests with full workflow support
- `leave_types` - Types of leave (uses `is_active` field for compatibility)
- `holidays` - Company holidays

### System Tables
- `settings` - System configuration (uses `setting_key` column as required by the code)
- `notifications` - User notifications system
- `permissions` - System permissions
- `roles` - User roles
- `role_permissions` - Role-permission mappings

### Assets Management
- `assetcategories` - Asset categories (IT, Furniture, Vehicles, etc.)
- `fixedassets` - Fixed assets inventory with details
- `assetassignments` - Asset assignments to employees
- `assetmaintenance` - Asset maintenance records and schedules

### SMS Management
- `sms_config` - SMS system configuration (SparrowSMS API)
- `sms_logs` - SMS delivery logs and history
- `sms_templates` - Predefined SMS message templates
- `sms_campaigns` - Bulk SMS campaign management
- `sms_sender_identities` - Approved sender identities

### Board Management
- `board_of_directors` - Board members
- `board_positions` - Board position types
- `board_committees` - Board committees
- `board_committee_members` - Committee membership
- `board_meetings` - Board meeting records

### Migration Tables
- `migrations` - Database migration history
- `db_migrations` - Installation migration tracking

## Key Features

1. **Complete Schema**: All 27 tables referenced in the application code are included
2. **SMS Integration**: Full SMS management system with templates, campaigns, and logging
3. **Proper Column Names**: Uses correct column names as expected by the PHP code
4. **Foreign Key Constraints**: All relationships properly defined
5. **Default Data**: Includes essential default data for immediate use
6. **Sample Admin User**: Ready-to-use admin account for initial access
7. **Compatibility**: Supports both new and legacy column names where needed

## Default Admin User

The schema includes a default administrator account:
- **Username**: admin@hrms.local (or EMP001)
- **Email**: admin@hrms.local  
- **Password**: admin123
- **Employee ID**: EMP001
- **Role**: System Administrator
- **Login Access**: Enabled

**Important**: The login uses the email address or employee ID, not a separate username.
Please change the password after first login for security.

## Installation

The schema is automatically installed by the DatabaseInstaller class during setup. Installation includes:
- Table creation with proper indexes and constraints
- Default data insertion for all reference tables
- Foreign key relationship setup
- Initial system configuration

## Verification

After installation, verify these key elements:
- Settings table uses `setting_key` column (not `key_name`)
- Leave types table has `is_active` field 
- Employees table includes `emp_id`, `designation`, `branch` fields
- All 22 application tables are created
- Default data is populated

## Recent Updates (2025-06-15)

### Phase 1: Core Schema Fix
- ✅ Fixed settings table to use `setting_key` column
- ✅ Added all missing tables: `notifications`, `leave_requests`, `branches`, `permissions`, etc.
- ✅ Updated employees table with all required fields (`emp_id`, `designation`, `branch`, etc.)
- ✅ Added `attendance_logs` table for machine data
- ✅ Fixed SQL parsing in DatabaseInstaller
- ✅ Verified complete installation process works

### Phase 2: SMS Integration & Admin User
- ✅ Added complete SMS management system (5 tables)
- ✅ Included SMS templates for common scenarios
- ✅ Added default SMS configuration
- ✅ Created sample admin user (username: admin, password: admin123)
- ✅ Updated schema to 27 total tables

### Ready for Production
- All application features now have database support
- No more "table doesn't exist" errors
- Admin account ready for immediate access
- SMS system ready for configuration
- User roles (admin, hr, manager, employee)
- System settings (company info, working hours, etc.)

### Installation

The schema is automatically installed when running the HRMS setup wizard. The installation process:

1. Creates all required tables with proper indexes and constraints
2. Sets up foreign key relationships
3. Inserts default data and settings
4. Configures initial system parameters

### Database Requirements

- MySQL 5.7+ or MariaDB 10.2+
- UTF8MB4 character set support
- InnoDB storage engine
- Foreign key constraint support

## Customization

After installation, you can:
- Add additional departments and designations
- Configure leave types and policies
- Customize system settings
- Add company-specific holidays

For database updates and migrations, see the `migrations/` directory.

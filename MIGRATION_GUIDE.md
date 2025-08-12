# PHP HRMS to Laravel & Inertia Migration Guide

## Overview
This document provides a comprehensive guide for migrating the PHP HRMS system to Laravel with Inertia.js and Vue 3.

## Project Structure

### Current PHP Structure
```
php-hrms/
├── modules/
│   ├── employees/
│   ├── attendance/
│   ├── reports/
│   ├── tasks/
│   ├── leave/
│   ├── sms/
│   └── assets/
├── includes/
├── plugins/
└── ...
```

### New Laravel Structure
```
php-hrms-laravel/
├── app/
│   ├── Http/Controllers/
│   ├── Models/
│   └── Services/
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   ├── Employees/
│   │   │   ├── Attendance/
│   │   │   └── Reports/
│   │   └── Components/
│   └── views/
└── routes/
```

## Migration Steps

### 1. Environment Setup

#### Prerequisites
- PHP 8.1 or higher
- Composer
- Node.js 16+ and npm
- MySQL 8.0+

#### PHP Extensions Required
Ensure these extensions are enabled in your php.ini:
```ini
extension=fileinfo
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=tokenizer
extension=xml
extension=ctype
extension=json
extension=bcmath
```

#### Laravel Project Creation
```bash
cd d:\wwwroot
composer create-project laravel/laravel php-hrms-laravel
cd php-hrms-laravel
```

#### Install Inertia.js
```bash
# Server-side
composer require inertiajs/inertia-laravel

# Client-side
npm install @inertiajs/vue3
npm install vue@next @vitejs/plugin-vue
```

### 2. Database Migration

#### Copy Migration Files
Copy the migration files from `d:\wwwroot\php-hrms\laravel-migrations\` to `database/migrations/`:

1. `001_create_branches_table.php`
2. `002_create_departments_table.php`
3. `003_create_designations_table.php`
4. `004_create_employees_table.php`
5. `005_create_attendance_logs_table.php`
6. `006_create_leave_types_table.php`
7. `007_create_leave_requests_table.php`
8. `008_create_task_categories_table.php`
9. `009_create_tasks_table.php`

#### Run Migrations
```bash
php artisan migrate
```

#### Data Migration Script
Create a data migration script to transfer existing data:

```php
<?php
// database/seeders/DataMigrationSeeder.php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataMigrationSeeder extends Seeder
{
    public function run()
    {
        // Connect to old database
        $oldDB = DB::connection('old_mysql');
        
        // Migrate branches
        $branches = $oldDB->table('branches')->get();
        foreach ($branches as $branch) {
            DB::table('branches')->insert([
                'id' => $branch->id,
                'name' => $branch->name,
                'branch_code' => $branch->branch_code,
                'address' => $branch->address,
                'contact_email' => $branch->contact_email,
                'contact_phone' => $branch->contact_phone,
                'status' => $branch->status,
                'created_at' => $branch->created_at,
                'updated_at' => $branch->updated_at,
            ]);
        }
        
        // Migrate other tables similarly...
    }
}
```

### 3. Model Setup

#### Copy Model Files
Copy the model files from `d:\wwwroot\php-hrms\laravel-models\` to `app/Models/`:

- `Branch.php`
- `Department.php`
- `Designation.php`
- `Employee.php`
- `AttendanceLog.php`
- `LeaveType.php`
- `LeaveRequest.php`
- `TaskCategory.php`
- `Task.php`

### 4. Controller Setup

#### Copy Controller Files
Copy the controller files from `d:\wwwroot\php-hrms\laravel-controllers\` to `app/Http/Controllers/`:

- `EmployeeController.php`
- `AttendanceController.php`
- (Create additional controllers for other modules)

#### Create Additional Controllers
```bash
php artisan make:controller LeaveController --resource
php artisan make:controller TaskController --resource
php artisan make:controller ReportController
```

### 5. Frontend Setup

#### Configure Inertia
Update `app/Http/Kernel.php`:
```php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \App\Http\Middleware\HandleInertiaRequests::class,
    ],
];
```

Create Inertia middleware:
```bash
php artisan inertia:middleware
```

#### Vite Configuration
Update `vite.config.js`:
```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
```

#### Copy Vue Components
Copy the Vue components from `d:\wwwroot\php-hrms\laravel-vue-components\` to `resources/js/Pages/`:

- `Employees/Index.vue`
- `Employees/Create.vue`
- `Attendance/Index.vue`

### 6. Routes Configuration

#### Web Routes
Create routes in `routes/web.php`:
```php
<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Employee routes
    Route::resource('employees', EmployeeController::class);
    Route::get('employees/department/{department}/designations', [EmployeeController::class, 'getDesignationsByDepartment']);
    
    // Attendance routes
    Route::resource('attendance', AttendanceController::class);
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
    Route::get('attendance-report', [AttendanceController::class, 'report'])->name('attendance.report');
});
```

### 7. Assets and Styling

#### Install CSS Framework
```bash
npm install tailwindcss @tailwindcss/forms
```

#### Configure Tailwind CSS
Create `tailwind.config.js`:
```javascript
module.exports = {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
```

### 8. Authentication Setup

#### Install Laravel Breeze with Inertia
```bash
composer require laravel/breeze --dev
php artisan breeze:install vue
npm install && npm run dev
```

### 9. File Storage Configuration

#### Configure Storage
Update `config/filesystems.php` for profile pictures and documents:
```php
'disks' => [
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
        'throw' => false,
    ],
],
```

Create storage link:
```bash
php artisan storage:link
```

### 10. Testing and Validation

#### Create Feature Tests
```bash
php artisan make:test EmployeeTest
php artisan make:test AttendanceTest
```

#### Run Tests
```bash
php artisan test
```

### 11. Performance Optimization

#### Enable Caching
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Database Optimization
- Add database indexes
- Optimize queries with eager loading
- Use database transactions for bulk operations

### 12. Deployment Checklist

#### Production Environment
1. Set `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false`
3. Configure proper database credentials
4. Set up proper file permissions
5. Configure web server (Apache/Nginx)
6. Set up SSL certificate
7. Configure backup system

#### Build Assets
```bash
npm run build
```

## Module-by-Module Migration Priority

### Phase 1: Core Modules (Completed)
- ✅ Employees
- ✅ Attendance
- ⏳ Reports (Basic structure)

### Phase 2: Business Logic
- Leave Management
- Task Management
- Department/Branch Management

### Phase 3: Advanced Features
- SMS Notifications
- Asset Management
- Advanced Reporting
- User Permissions & Roles

### Phase 4: Integration & Optimization
- API Development
- Mobile App Integration
- Performance Optimization
- Security Enhancements

## Key Benefits of Migration

### Technical Improvements
- **Modern PHP Framework**: Laravel 10+ with latest PHP features
- **Better Architecture**: MVC pattern with clear separation of concerns
- **Enhanced Security**: Built-in CSRF protection, SQL injection prevention
- **Improved Performance**: Query optimization, caching, and modern practices
- **Scalability**: Better structure for future growth

### Developer Experience
- **Modern Frontend**: Vue 3 with Composition API
- **Better Tooling**: Vite for fast development builds
- **Code Organization**: Clear file structure and naming conventions
- **Testing**: Built-in testing framework and tools

### Business Benefits
- **Maintainability**: Easier to maintain and update
- **Extensibility**: Simple to add new features
- **Performance**: Faster page loads and better user experience
- **Mobile Ready**: Responsive design with modern UI components

## Troubleshooting

### Common Issues

#### PHP Extension Missing
```
Problem: league/flysystem-local requires ext-fileinfo
Solution: Enable fileinfo extension in php.ini
```

#### Memory Limit Issues
```
Problem: Allowed memory size exhausted
Solution: Increase memory_limit in php.ini or use ini_set()
```

#### Permission Issues
```
Problem: storage/ directory not writable
Solution: Set proper permissions (755 for directories, 644 for files)
```

## Next Steps

1. **Complete Laravel Setup**: Resolve PHP extension issues and complete project creation
2. **Data Migration**: Create and run data migration scripts
3. **Module Development**: Complete remaining modules (Leave, Tasks, Reports)
4. **Testing**: Comprehensive testing of all functionality
5. **Deployment**: Set up production environment
6. **Training**: User training on new interface

## Maintenance

### Regular Tasks
- **Security Updates**: Keep Laravel and dependencies updated
- **Database Backup**: Regular automated backups
- **Performance Monitoring**: Monitor application performance
- **Code Quality**: Regular code reviews and refactoring

### Long-term Improvements
- **API Development**: Create REST API for mobile app
- **Advanced Reporting**: Business intelligence features
- **Integration**: Third-party service integrations
- **Mobile App**: Native mobile application development

---

**Created**: December 2024  
**Version**: 1.0  
**Status**: In Progress  
**Contact**: Development Team

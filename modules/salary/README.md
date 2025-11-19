# Salary module

This folder contains the initial skeleton for a Salary module.

Files added:
- `index.php` - simple entry page for the salary module
- `actions.php` - minimal CRUD endpoints for salary topics (list/add/delete) and a placeholder for components

Migration added:
- `migrations/2025_10_30_101000_create_salary_tables.php` - creates `salary_topics`, `salary_components`, `employee_salaries`, and `salary_deductions` tables.

How to run migrations locally (PowerShell):

```powershell
# from project root
php scripts/run_migrations.php
```

Notes:
- This is a starter scaffold. Implement UI and permission checks consistent with your project patterns.
- Make a backup of the database before running migrations on production.

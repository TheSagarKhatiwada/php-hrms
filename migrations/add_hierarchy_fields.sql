-- Add hierarchical fields to employees table
-- This will enable proper company hierarchy with reporting relationships

-- Add supervisor_id to create reporting relationships
ALTER TABLE employees 
ADD COLUMN supervisor_id INT(11) NULL AFTER role_id,
ADD CONSTRAINT employees_supervisor_fk 
    FOREIGN KEY (supervisor_id) REFERENCES employees(id) ON DELETE SET NULL;

-- Add department field as integer (currently it's varchar)
-- First backup existing data
ALTER TABLE employees 
ADD COLUMN department_id INT(11) NULL AFTER designation;

-- Update department_id with proper department IDs
-- You'll need to run this after the migration:
-- UPDATE employees e 
-- JOIN departments d ON d.id = e.branch 
-- SET e.department_id = d.id 
-- WHERE e.branch IS NOT NULL;

-- Add foreign key constraint for department
ALTER TABLE employees 
ADD CONSTRAINT employees_department_fk 
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

-- Add indexes for better performance
CREATE INDEX idx_employees_supervisor ON employees(supervisor_id);
CREATE INDEX idx_employees_department ON employees(department_id);
CREATE INDEX idx_employees_role ON employees(role_id);

-- Create a view for organizational hierarchy
CREATE OR REPLACE VIEW employee_hierarchy AS
SELECT 
    e.id,
    e.emp_id,
    e.first_name,
    e.last_name,
    CONCAT(e.first_name, ' ', e.last_name) as full_name,
    e.designation,
    e.role_id,
    r.name as role_name,
    e.supervisor_id,
    CONCAT(s.first_name, ' ', s.last_name) as supervisor_name,
    e.department_id,
    d.name as department_name,
    d.manager_id as department_manager_id,
    CONCAT(dm.first_name, ' ', dm.last_name) as department_manager_name,
    e.branch,
    b.name as branch_name,
    e.join_date,
    e.exit_date
FROM employees e
LEFT JOIN employees s ON e.supervisor_id = s.id
LEFT JOIN roles r ON e.role_id = r.id
LEFT JOIN departments d ON e.department_id = d.id
LEFT JOIN employees dm ON d.manager_id = dm.id
LEFT JOIN branches b ON e.branch = b.id
WHERE e.exit_date IS NULL
ORDER BY d.name, e.supervisor_id, e.first_name;

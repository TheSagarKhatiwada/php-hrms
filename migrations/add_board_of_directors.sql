-- Add Board of Directors as the top level of organizational hierarchy
-- This creates a proper organizational structure with Board at the top

-- Create a new table for Board of Directors positions
CREATE TABLE IF NOT EXISTS board_positions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    position_name VARCHAR(100) NOT NULL,
    position_level INT NOT NULL DEFAULT 1,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_position (position_name)
);

-- Insert default board positions
INSERT INTO board_positions (position_name, position_level, description) VALUES
('Chairman of the Board', 1, 'Highest position in the organization, leads the Board of Directors'),
('Vice Chairman', 2, 'Second in command of the Board of Directors'),
('Board Member', 3, 'Member of the Board of Directors'),
('Independent Director', 3, 'Independent member of the Board of Directors'),
('Executive Director', 4, 'Board member who is also part of company management');

-- Add board_position_id to employees table to track board positions
ALTER TABLE employees 
ADD COLUMN board_position_id INT(11) NULL AFTER role_id,
ADD CONSTRAINT employees_board_position_fk 
    FOREIGN KEY (board_position_id) REFERENCES board_positions(id) ON DELETE SET NULL;

-- Create index for board position
CREATE INDEX idx_employees_board_position ON employees(board_position_id);

-- Add organizational level field to better structure hierarchy
ALTER TABLE employees 
ADD COLUMN organizational_level INT DEFAULT 5 AFTER board_position_id;

-- Update organizational levels:
-- Level 1: Board of Directors (Chairman, Vice Chairman)
-- Level 2: Board of Directors (Other Members)
-- Level 3: C-Level Executives (CEO, COO, CFO, etc.)
-- Level 4: Vice Presidents / Senior Management
-- Level 5: Department Heads / Directors
-- Level 6: Managers
-- Level 7: Team Leads / Supervisors
-- Level 8: Senior Staff
-- Level 9: Staff
-- Level 10: Junior Staff / Interns

-- Create a view for complete organizational hierarchy including board
CREATE OR REPLACE VIEW complete_organizational_hierarchy AS
SELECT 
    e.id,
    e.emp_id,
    e.first_name,
    e.last_name,
    CONCAT(e.first_name, ' ', e.last_name) as full_name,
    e.designation,
    e.role_id,
    r.name as role_name,
    e.board_position_id,
    bp.position_name as board_position,
    bp.position_level as board_level,
    e.organizational_level,
    e.supervisor_id,
    CONCAT(s.first_name, ' ', s.last_name) as supervisor_name,
    e.department_id,
    d.name as department_name,
    d.manager_id as department_manager_id,
    CONCAT(dm.first_name, ' ', dm.last_name) as department_manager_name,
    e.branch,
    b.name as branch_name,
    e.join_date,
    e.status,
    CASE 
        WHEN e.board_position_id IS NOT NULL THEN 'Board of Directors'
        WHEN e.organizational_level <= 3 THEN 'Executive Leadership'
        WHEN e.organizational_level <= 5 THEN 'Senior Management'
        WHEN e.organizational_level <= 7 THEN 'Middle Management'
        ELSE 'Staff'
    END as hierarchy_category
FROM employees e
LEFT JOIN roles r ON e.role_id = r.id
LEFT JOIN board_positions bp ON e.board_position_id = bp.id
LEFT JOIN employees s ON e.supervisor_id = s.id
LEFT JOIN departments d ON e.department_id = d.id
LEFT JOIN employees dm ON d.manager_id = dm.id
LEFT JOIN branches b ON e.branch = b.id
ORDER BY 
    CASE WHEN e.board_position_id IS NOT NULL THEN bp.position_level ELSE 999 END,
    e.organizational_level,
    e.department_id,
    e.designation;

-- Function to get all subordinates (including board hierarchy)
DELIMITER //
CREATE OR REPLACE FUNCTION get_all_subordinates(employee_id INT) 
RETURNS TEXT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE result TEXT DEFAULT '';
    DECLARE done INT DEFAULT FALSE;
    DECLARE temp_id INT;
    DECLARE temp_name VARCHAR(255);
    
    DECLARE subordinate_cursor CURSOR FOR
        WITH RECURSIVE subordinates AS (
            SELECT id, CONCAT(first_name, ' ', last_name) as name, supervisor_id, 1 as level
            FROM employees 
            WHERE supervisor_id = employee_id
            
            UNION ALL
            
            SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) as name, e.supervisor_id, s.level + 1
            FROM employees e
            INNER JOIN subordinates s ON e.supervisor_id = s.id
            WHERE s.level < 10  -- Prevent infinite recursion
        )
        SELECT id, name FROM subordinates ORDER BY level, name;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN subordinate_cursor;
    
    read_loop: LOOP
        FETCH subordinate_cursor INTO temp_id, temp_name;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        IF result = '' THEN
            SET result = temp_name;
        ELSE
            SET result = CONCAT(result, ', ', temp_name);
        END IF;
    END LOOP;
    
    CLOSE subordinate_cursor;
    
    RETURN result;
END//
DELIMITER ;

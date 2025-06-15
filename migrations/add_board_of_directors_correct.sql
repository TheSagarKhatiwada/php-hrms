-- Create proper Board of Directors system - SEPARATE from employees
-- Board members are external governance, not internal employees

-- Create Board of Directors table (completely separate from employees)
CREATE TABLE IF NOT EXISTS board_of_directors (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    title VARCHAR(100) NOT NULL, -- Chairman, Vice Chairman, etc.
    position_level INT NOT NULL DEFAULT 1,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    bio TEXT,
    photo VARCHAR(255),
    appointment_date DATE,
    term_end_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    is_independent TINYINT(1) DEFAULT 0, -- Independent vs Executive Director
    expertise_areas TEXT, -- Finance, Technology, Legal, etc.
    company_affiliations TEXT,
    qualifications TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample board positions/titles
INSERT INTO board_of_directors (first_name, last_name, title, position_level, email, appointment_date, is_active, is_independent, expertise_areas) VALUES
('John', 'Smith', 'Chairman of the Board', 1, 'chairman@company.com', '2024-01-01', 1, 1, 'Corporate Governance, Strategy'),
('Sarah', 'Johnson', 'Vice Chairman', 2, 'vice.chairman@company.com', '2024-01-01', 1, 1, 'Finance, Risk Management'),
('Robert', 'Williams', 'Independent Director', 3, 'r.williams@external.com', '2024-01-01', 1, 1, 'Technology, Innovation'),
('Maria', 'Garcia', 'Independent Director', 3, 'maria.garcia@external.com', '2024-01-01', 1, 1, 'Legal, Compliance'),
('David', 'Brown', 'Executive Director', 4, 'david.brown@company.com', '2024-01-01', 1, 0, 'Operations, Management');

-- Create Board Committees table
CREATE TABLE IF NOT EXISTS board_committees (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    committee_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert standard board committees
INSERT INTO board_committees (committee_name, description) VALUES
('Audit Committee', 'Oversees financial reporting and internal controls'),
('Compensation Committee', 'Reviews executive compensation and benefits'),
('Governance Committee', 'Ensures proper corporate governance practices'),
('Risk Committee', 'Monitors and manages enterprise risks'),
('Strategy Committee', 'Reviews long-term strategic planning');

-- Create Board Committee Memberships (many-to-many relationship)
CREATE TABLE IF NOT EXISTS board_committee_members (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    board_member_id INT(11) NOT NULL,
    committee_id INT(11) NOT NULL,
    role VARCHAR(50) DEFAULT 'Member', -- Chairman, Member
    appointed_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (board_member_id) REFERENCES board_of_directors(id) ON DELETE CASCADE,
    FOREIGN KEY (committee_id) REFERENCES board_committees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (board_member_id, committee_id)
);

-- Create Board Meetings table
CREATE TABLE IF NOT EXISTS board_meetings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    meeting_title VARCHAR(200) NOT NULL,
    meeting_date DATE NOT NULL,
    meeting_time TIME,
    location VARCHAR(200),
    agenda TEXT,
    minutes TEXT,
    status ENUM('Scheduled', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    created_by INT(11), -- Employee who created the meeting
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Board Meeting Attendance
CREATE TABLE IF NOT EXISTS board_meeting_attendance (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT(11) NOT NULL,
    board_member_id INT(11) NOT NULL,
    attendance_status ENUM('Present', 'Absent', 'Excused') DEFAULT 'Present',
    notes TEXT,
    FOREIGN KEY (meeting_id) REFERENCES board_meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (board_member_id) REFERENCES board_of_directors(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (meeting_id, board_member_id)
);

-- Create view for board hierarchy (separate from employee hierarchy)
CREATE OR REPLACE VIEW board_hierarchy_view AS
SELECT 
    b.id,
    CONCAT(b.first_name, ' ', b.last_name) as full_name,
    b.title,
    b.position_level,
    b.email,
    b.phone,
    b.appointment_date,
    b.term_end_date,
    b.is_independent,
    b.expertise_areas,
    CASE 
        WHEN b.is_independent = 1 THEN 'Independent Director'
        ELSE 'Executive Director'
    END as director_type,
    CASE 
        WHEN b.position_level = 1 THEN 'Board Leadership'
        WHEN b.position_level = 2 THEN 'Board Leadership'
        ELSE 'Board Member'
    END as hierarchy_level
FROM board_of_directors b
WHERE b.is_active = 1
ORDER BY b.position_level, b.title, b.last_name;

-- Create view for complete organizational structure (Board + Employees)
CREATE OR REPLACE VIEW complete_organizational_structure AS
-- Board of Directors (Top Level)
SELECT 
    CONCAT('BOARD_', b.id) as unique_id,
    'Board of Directors' as entity_type,
    CONCAT(b.first_name, ' ', b.last_name) as full_name,
    b.title as position,
    b.position_level as level,
    0 as org_level, -- Board is above all employee levels
    NULL as supervisor_id,
    NULL as department_id,
    b.email,
    b.phone,
    'Board Member' as category
FROM board_of_directors b
WHERE b.is_active = 1

UNION ALL

-- Employees (Below Board Level)
SELECT 
    CONCAT('EMP_', e.id) as unique_id,
    'Employee' as entity_type,
    CONCAT(e.first_name, ' ', e.last_name) as full_name,
    e.designation as position,
    CASE WHEN e.supervisor_id IS NULL THEN 1 ELSE 2 END as level, -- CEO/Top = 1, others = 2+
    COALESCE(e.supervisor_id, 1) as org_level,
    e.supervisor_id,
    e.department_id,
    e.email,
    e.phone,
    CASE 
        WHEN e.supervisor_id IS NULL THEN 'Executive Leadership'
        WHEN d.name IS NOT NULL THEN 'Department Staff'
        ELSE 'Staff'
    END as category
FROM employees e
LEFT JOIN departments d ON e.department_id = d.id
WHERE e.exit_date IS NULL

ORDER BY level, org_level, position;

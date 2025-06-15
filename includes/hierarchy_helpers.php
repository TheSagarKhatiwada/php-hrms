<?php
/**
 * Hierarchy Helper Functions
 * Contains utility functions for organizational hierarchy management
 */

/**
 * Get all subordinates of a given employee (recursive)
 * @param PDO $pdo Database connection
 * @param int $employeeId Employee ID
 * @return array List of subordinate employee IDs
 */
function getSubordinates($pdo, $employeeId) {
    $subordinates = [];
    
    // Get direct subordinates
    $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE supervisor_id = ? AND exit_date IS NULL");
    $stmt->execute([$employeeId]);
    $directSubordinates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($directSubordinates as $subordinateId) {
        $subordinates[] = $subordinateId;
        // Recursively get subordinates of subordinates
        $subordinates = array_merge($subordinates, getSubordinates($pdo, $subordinateId));
    }
    
    return $subordinates;
}

/**
 * Get hierarchy path from employee to top level
 * @param PDO $pdo Database connection
 * @param int $employeeId Employee ID
 * @return array Hierarchy path (bottom to top)
 */
function getHierarchyPath($pdo, $employeeId) {
    $path = [];
    $currentEmployeeId = $employeeId;
    
    while ($currentEmployeeId) {
        $stmt = $pdo->prepare("
            SELECT id, supervisor_id, CONCAT(first_name, ' ', last_name) as full_name, 
                   designation, role_id
            FROM employees 
            WHERE id = ? AND exit_date IS NULL
        ");
        $stmt->execute([$currentEmployeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) break;
        
        $path[] = $employee;
        $currentEmployeeId = $employee['supervisor_id'];
    }
    
    return $path;
}

/**
 * Check if an employee can supervise another employee (prevent circular hierarchy)
 * @param PDO $pdo Database connection
 * @param int $supervisorId Potential supervisor ID
 * @param int $employeeId Employee ID
 * @return bool True if valid, false if would create circular hierarchy
 */
function canSupervise($pdo, $supervisorId, $employeeId) {
    if ($supervisorId == $employeeId) {
        return false; // Can't supervise self
    }
    
    // Check if the proposed supervisor is in the subordinate chain of the employee
    $subordinates = getSubordinates($pdo, $employeeId);
    return !in_array($supervisorId, $subordinates);
}

/**
 * Get team members for a supervisor
 * @param PDO $pdo Database connection
 * @param int $supervisorId Supervisor ID
 * @param bool $includeIndirect Include indirect subordinates
 * @return array Team members
 */
function getTeamMembers($pdo, $supervisorId, $includeIndirect = false) {
    if ($includeIndirect) {
        $subordinateIds = getSubordinates($pdo, $supervisorId);
        if (empty($subordinateIds)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($subordinateIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT e.*, d.title as designation_title, dept.name as department_name
            FROM employees e
            LEFT JOIN designations d ON e.designation = d.id
            LEFT JOIN departments dept ON e.department_id = dept.id
            WHERE e.id IN ($placeholders) AND e.exit_date IS NULL
            ORDER BY e.first_name, e.last_name
        ");
        $stmt->execute($subordinateIds);
    } else {
        $stmt = $pdo->prepare("
            SELECT e.*, d.title as designation_title, dept.name as department_name
            FROM employees e
            LEFT JOIN designations d ON e.designation = d.id
            LEFT JOIN departments dept ON e.department_id = dept.id
            WHERE e.supervisor_id = ? AND e.exit_date IS NULL
            ORDER BY e.first_name, e.last_name
        ");
        $stmt->execute([$supervisorId]);
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get department hierarchy structure
 * @param PDO $pdo Database connection
 * @return array Department hierarchy with employee counts
 */
function getDepartmentHierarchy($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            CONCAT(dm.first_name, ' ', dm.last_name) as manager_name,
            COUNT(e.id) as employee_count
        FROM departments d
        LEFT JOIN employees dm ON d.manager_id = dm.id
        LEFT JOIN employees e ON d.id = e.department_id AND e.exit_date IS NULL
        GROUP BY d.id
        ORDER BY d.name
    ");
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate hierarchy breadcrumb for an employee
 * @param PDO $pdo Database connection
 * @param int $employeeId Employee ID
 * @return string HTML breadcrumb
 */
function generateHierarchyBreadcrumb($pdo, $employeeId) {
    $path = getHierarchyPath($pdo, $employeeId);
    $breadcrumb = [];
    
    // Reverse to show top to bottom
    $path = array_reverse($path);
    
    foreach ($path as $index => $employee) {
        if ($index === count($path) - 1) {
            // Current employee (active)
            $breadcrumb[] = '<span class="breadcrumb-item active">' . htmlspecialchars($employee['full_name']) . '</span>';
        } else {
            // Supervisor (clickable)
            $breadcrumb[] = '<a href="employee-viewer.php?empId=' . $employee['id'] . '" class="breadcrumb-item">' . htmlspecialchars($employee['full_name']) . '</a>';
        }
    }
    
    return '<nav aria-label="breadcrumb"><ol class="breadcrumb">' . implode('', $breadcrumb) . '</ol></nav>';
}

/**
 * Check hierarchy permissions for leave approvals
 * @param PDO $pdo Database connection
 * @param int $approverId Approver ID
 * @param int $requesterId Requester ID
 * @return bool True if approver can approve requests from requester
 */
function canApproveLeave($pdo, $approverId, $requesterId) {
    // Admin and HR can approve any leave
    $stmt = $pdo->prepare("SELECT role_id FROM employees WHERE id = ?");
    $stmt->execute([$approverId]);
    $approverRole = $stmt->fetchColumn();
    
    if ($approverRole == 1 || $approverRole == 2) { // Admin or HR roles
        return true;
    }
    
    // Check if approver is in the hierarchy chain above the requester
    $hierarchyPath = getHierarchyPath($pdo, $requesterId);
    foreach ($hierarchyPath as $pathEmployee) {
        if ($pathEmployee['id'] == $approverId) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get organization statistics
 * @param PDO $pdo Database connection
 * @return array Organization statistics
 */
function getOrganizationStats($pdo) {
    $stats = [];
    
    // Total active employees
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE exit_date IS NULL");
    $stats['total_employees'] = $stmt->fetchColumn();
    
    // Employees by level
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE supervisor_id IS NULL AND exit_date IS NULL");
    $stats['top_level'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE supervisor_id IS NOT NULL AND exit_date IS NULL");
    $stats['subordinates'] = $stmt->fetchColumn();
    
    // Average hierarchy depth
    $depths = [];
    $stmt = $pdo->query("SELECT id FROM employees WHERE exit_date IS NULL");
    $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($employees as $empId) {
        $path = getHierarchyPath($pdo, $empId);
        $depths[] = count($path);
    }
    
    $stats['avg_depth'] = !empty($depths) ? round(array_sum($depths) / count($depths), 1) : 0;
    $stats['max_depth'] = !empty($depths) ? max($depths) : 0;
    
    // Department distribution
    $stmt = $pdo->query("
        SELECT d.name, COUNT(e.id) as employee_count 
        FROM departments d 
        LEFT JOIN employees e ON d.id = e.department_id AND e.exit_date IS NULL 
        GROUP BY d.id 
        ORDER BY employee_count DESC
    ");
    $stats['department_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

/**
 * Update hierarchy when employee leaves/exits
 * @param PDO $pdo Database connection
 * @param int $employeeId Employee ID who is leaving
 * @param int|null $newSupervisorId New supervisor for subordinates (null = no supervisor)
 * @return bool Success status
 */
function updateHierarchyOnExit($pdo, $employeeId, $newSupervisorId = null) {
    try {
        $pdo->beginTransaction();
        
        // Update all direct subordinates
        $stmt = $pdo->prepare("UPDATE employees SET supervisor_id = ? WHERE supervisor_id = ?");
        $stmt->execute([$newSupervisorId, $employeeId]);
        
        // Update department if employee was department manager
        $stmt = $pdo->prepare("UPDATE departments SET manager_id = ? WHERE manager_id = ?");
        $stmt->execute([$newSupervisorId, $employeeId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollback();
        return false;
    }
}

/**
 * Get all board members
 * @param PDO $pdo Database connection
 * @return array List of board members with their positions
 */
function getBoardMembers($pdo) {
    $stmt = $pdo->prepare("
        SELECT e.id, e.emp_id, e.first_name, e.last_name, 
               CONCAT(e.first_name, ' ', e.last_name) as full_name,
               e.designation, e.organizational_level,
               bp.position_name, bp.position_level, bp.description
        FROM employees e
        JOIN board_positions bp ON e.board_position_id = bp.id
        WHERE e.exit_date IS NULL
        ORDER BY bp.position_level, bp.position_name, e.first_name
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if employee is board member
 * @param PDO $pdo Database connection
 * @param int $employeeId Employee ID
 * @return bool True if employee is board member
 */
function isBoardMember($pdo, $employeeId) {
    $stmt = $pdo->prepare("SELECT board_position_id FROM employees WHERE id = ? AND exit_date IS NULL");
    $stmt->execute([$employeeId]);
    $result = $stmt->fetchColumn();
    return !empty($result);
}

/**
 * Get board position details for an employee
 * @param PDO $pdo Database connection
 * @param int $employeeId Employee ID
 * @return array|null Board position details or null if not board member
 */
function getBoardPosition($pdo, $employeeId) {
    $stmt = $pdo->prepare("
        SELECT bp.position_name, bp.position_level, bp.description
        FROM employees e
        JOIN board_positions bp ON e.board_position_id = bp.id
        WHERE e.id = ? AND e.exit_date IS NULL
    ");
    $stmt->execute([$employeeId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get organizational hierarchy including board structure
 * @param PDO $pdo Database connection
 * @return array Complete organizational hierarchy
 */
function getCompleteOrganizationalHierarchy($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            e.id, e.emp_id, e.first_name, e.last_name,
            CONCAT(e.first_name, ' ', e.last_name) as full_name,
            e.designation, e.supervisor_id, e.department_id, 
            e.board_position_id, e.organizational_level,
            bp.position_name as board_position,
            bp.position_level as board_level,
            d.name as department_name,
            CASE 
                WHEN e.board_position_id IS NOT NULL THEN 'Board of Directors'
                WHEN e.organizational_level <= 3 THEN 'Executive Leadership'
                WHEN e.organizational_level <= 5 THEN 'Senior Management'
                WHEN e.organizational_level <= 7 THEN 'Middle Management'
                ELSE 'Staff'
            END as hierarchy_category
        FROM employees e
        LEFT JOIN board_positions bp ON e.board_position_id = bp.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.exit_date IS NULL
        ORDER BY 
            CASE WHEN e.board_position_id IS NOT NULL THEN bp.position_level ELSE 999 END,
            COALESCE(e.organizational_level, 10),
            e.supervisor_id IS NULL DESC,
            e.supervisor_id,
            e.first_name
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========= CORRECTED BOARD OF DIRECTORS FUNCTIONS =========
// These functions work with the separate board_of_directors table

/**
 * Get all board members from separate Board of Directors table
 * @param PDO $pdo Database connection
 * @return array List of board members
 */
function getBoardMembersCorrect($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, position, qualification, expertise, 
                   appointment_date, contact_email, contact_phone, status
            FROM board_of_directors
            WHERE status = 'active'
            ORDER BY 
                CASE position
                    WHEN 'Chairman' THEN 1
                    WHEN 'Vice Chairman' THEN 2
                    WHEN 'Executive Director' THEN 3
                    WHEN 'Independent Director' THEN 4
                    ELSE 5
                END, name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Check if someone is a board member by email (separate from employees)
 * @param PDO $pdo Database connection
 * @param string $email Email to check
 * @return bool True if board member
 */
function isBoardMemberByEmail($pdo, $email) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM board_of_directors 
            WHERE contact_email = ? AND status = 'active'
        ");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get board position details by ID
 * @param PDO $pdo Database connection
 * @param int $boardMemberId Board member ID
 * @return array|null Board position details
 */
function getBoardPositionCorrect($pdo, $boardMemberId) {
    try {
        $stmt = $pdo->prepare("
            SELECT name, position, qualification, expertise, appointment_date
            FROM board_of_directors
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$boardMemberId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get complete organizational structure (Board + Employees)
 * @param PDO $pdo Database connection
 * @return array Complete hierarchy with board and employees separated
 */
function getCompleteOrganizationalStructure($pdo) {
    $structure = [];
    
    // Get Board of Directors (Governance Layer)
    $structure['board'] = getBoardMembersCorrect($pdo);
    
    // Get Employee Structure (Operations Layer)
    $stmt = $pdo->prepare("
        SELECT 
            e.id, e.emp_id, e.first_name, e.last_name,
            CONCAT(e.first_name, ' ', e.last_name) as full_name,
            e.designation, e.supervisor_id, e.department_id, 
            d.name as department_name,
            des.title as designation_title,
            r.name as role_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN designations des ON e.designation = des.id
        LEFT JOIN roles r ON e.role_id = r.id
        WHERE e.exit_date IS NULL
        ORDER BY e.supervisor_id IS NULL DESC, e.supervisor_id, e.first_name
    ");
    $stmt->execute();
    $structure['employees'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $structure;
}
?>

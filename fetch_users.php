<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/date_preferences.php';
require_once __DIR__ . '/includes/db_connection.php';

// Optional parameters: branch, date_from, date_to
$branchId = isset($_POST['branch']) ? trim($_POST['branch']) : '';
$dateFrom = isset($_POST['date_from']) ? trim($_POST['date_from']) : '';
$dateTo = isset($_POST['date_to']) ? trim($_POST['date_to']) : '';

try {
    // Build WHERE clauses depending on supplied filters
    $where = [];
    $params = [];
    if ($branchId !== '') {
        $where[] = 'e.branch = :branch';
        $params[':branch'] = $branchId;
    }


    // If both date_from and date_to provided, include employees who were employed
    // within the date range (join_date <= dateTo AND (exit_date IS NULL OR exit_date >= dateFrom))
    // OR who have any attendance records in that date range.
    if ($dateFrom !== '' && $dateTo !== '') {
        // Use unique parameter names because some PDO drivers reject repeated named params
        $where[] = "((e.join_date <= :dateTo1 AND (e.exit_date IS NULL OR e.exit_date >= :dateFrom1)) OR e.emp_id IN (SELECT emp_id FROM attendance_logs WHERE date BETWEEN :dateFrom2 AND :dateTo2))";
        $params[':dateFrom1'] = $dateFrom;
        $params[':dateTo1'] = $dateTo;
        $params[':dateFrom2'] = $dateFrom;
        $params[':dateTo2'] = $dateTo;
    } else {
        // default behavior: only return currently active employees
        $where[] = "e.status = 'active'";
    }

    $whereSql = '';
    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    // Prepare the query to fetch employees and their branch name
        $sql = "SELECT e.emp_id, 
                  CONCAT(e.first_name, 
                      CASE WHEN e.middle_name IS NOT NULL AND e.middle_name != '' 
                        THEN CONCAT(' ', e.middle_name, ' ') 
                        ELSE ' ' 
                      END, 
                      e.last_name) AS full_name, 
                  COALESCE(d.title, 'Not Assigned') AS designation,
                  e.date_of_birth,
                  b.name
                FROM employees e
                LEFT JOIN branches b ON e.branch = b.id
                LEFT JOIN designations d ON e.designation_id = d.id
                $whereSql
                ORDER BY e.first_name, e.last_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

            // Fetch all employees
            $employees = $stmt->fetchAll();
            if ($employees) {
                // Loop through and create an option for each employee with branch name
                foreach ($employees as $employee) {
                    $formattedBirthday = (!empty($employee['date_of_birth']) && $employee['date_of_birth'] !== '0000-00-00')
                        ? hrms_format_preferred_date($employee['date_of_birth'], 'F j')
                        : '';
                    $birthdayAttr = ($formattedBirthday && $formattedBirthday !== '-')
                        ? ' data-formatted-birthday="' . htmlspecialchars($formattedBirthday, ENT_QUOTES, 'UTF-8') . '"'
                        : '';

                    $empId = htmlspecialchars($employee['emp_id'], ENT_QUOTES, 'UTF-8');
                    $fullName = htmlspecialchars($employee['full_name'], ENT_QUOTES, 'UTF-8');
                    $designation = htmlspecialchars($employee['designation'], ENT_QUOTES, 'UTF-8');

                    echo "<option value=\"{$empId}\"{$birthdayAttr}>{$fullName} - {$designation}</option>";
                }
            } else {
                echo "<option value=''>No employees found</option>";
            }
} catch (PDOException $e) {
    echo "<option value=''>Error fetching employees: {$e->getMessage()}</option>";
}
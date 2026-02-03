<?php
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/date_preferences.php';
require_once __DIR__ . '/../../includes/db_connection.php';

$branchId = isset($_POST['branch']) ? trim($_POST['branch']) : '';
$dateFrom = isset($_POST['date_from']) ? trim($_POST['date_from']) : '';
$dateTo = isset($_POST['date_to']) ? trim($_POST['date_to']) : '';

if ($branchId === '') {
    echo "<option value=''>Select Employee</option>";
    exit;
}

if ($dateFrom === '') {
    $dateFrom = date('Y-m-d', strtotime('-30 days'));
}
if ($dateTo === '') {
    $dateTo = date('Y-m-d');
}

try {
    $sql = "SELECT e.emp_id,
                   CONCAT(e.first_name,
                          CASE WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
                               THEN CONCAT(' ', e.middle_name, ' ')
                               ELSE ' '
                          END,
                          e.last_name) AS full_name,
                   COALESCE(d.title, 'Not Assigned') AS designation
            FROM employees e
            LEFT JOIN designations d ON e.designation_id = d.id
            WHERE e.branch = :branch
              AND e.status = 'active'
              AND (e.mach_id_not_applicable IS NULL OR e.mach_id_not_applicable = 0)
              AND (e.join_date IS NULL OR e.join_date <= :dateTo)
              AND (e.exit_date IS NULL OR e.exit_date >= :dateFrom)
            ORDER BY e.first_name, e.last_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':branch' => $branchId,
        ':dateFrom' => $dateFrom,
        ':dateTo' => $dateTo
    ]);

    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$employees) {
        echo "<option value=''>No employees found</option>";
        exit;
    }

    foreach ($employees as $employee) {
        $empId = htmlspecialchars($employee['emp_id'], ENT_QUOTES, 'UTF-8');
        $fullName = htmlspecialchars($employee['full_name'], ENT_QUOTES, 'UTF-8');
        $designation = htmlspecialchars($employee['designation'], ENT_QUOTES, 'UTF-8');
        echo "<option value=\"{$empId}\">{$fullName} - {$designation}</option>";
    }
} catch (PDOException $e) {
    echo "<option value=''>Error fetching employees: {$e->getMessage()}</option>";
}

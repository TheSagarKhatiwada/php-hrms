<?php
header('Content-Type: application/json');
// Use unified session (even though this endpoint doesn't require auth, keeps consistency)
require_once '../includes/session_config.php';
require_once '../includes/db_connection.php';

// NOTE: The employees table in the rest of the codebase uses columns `emp_id` (employee code/id)
// and `branch` (foreign key to branches.id). Previous version referenced `employee_id` and
// `branch_id`, which caused SQL errors and resulted in an empty array being returned.

$branch = $_POST['branch'] ?? '';
try {
  if($branch==='') {
    $stmt = $pdo->query("SELECT emp_id, CONCAT(first_name,' ',last_name) AS name FROM employees ORDER BY first_name, last_name LIMIT 500");
  } else {
    $stmt = $pdo->prepare("SELECT emp_id, CONCAT(first_name,' ',last_name) AS name FROM employees WHERE branch = ? ORDER BY first_name, last_name LIMIT 500");
    $stmt->execute([$branch]);
  }
  $out = [];
  while($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $out[] = $r; }
  echo json_encode($out);
} catch(Exception $e) {
  // On error return an empty list (frontend handles gracefully)
  echo json_encode([]);
}

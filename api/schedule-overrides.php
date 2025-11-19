<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/csrf_protection.php';
require_once __DIR__ . '/../includes/utilities.php';

// Simple JSON API for schedule overrides (create/update/delete/list)
// Authentication and permission checks
if (!is_admin() && !has_permission('manage_schedule_overrides')) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
try {
    if ($method === 'GET') {
        // Return list of overrides (simple list) including creator info
        $stmt = $pdo->query("SELECT o.*, e.first_name, e.middle_name, e.last_name, e.branch AS emp_branch, b.name AS branch_name,
               creator.first_name AS creator_first, creator.middle_name AS creator_middle, creator.last_name AS creator_last, o.created_at AS override_created_at
            FROM employee_schedule_overrides o
            JOIN employees e ON o.emp_id = e.emp_id
            LEFT JOIN branches b ON e.branch = b.id
            LEFT JOIN employees creator ON o.created_by = creator.emp_id
            ORDER BY o.start_date DESC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    // For POST requests expect form data or JSON
    if ($method === 'POST') {
        // CSRF check if form token present
        if (!empty($_POST) || !empty($_FILES)) {
            verify_csrf_post();
        } else {
            // try JSON body: validate X-CSRF-Token header if present
            $headers = getallheaders();
            if (isset($headers['X-CSRF-Token'])) {
                verify_csrf_header($headers['X-CSRF-Token']);
            }
        }

        // Accept JSON or form
        $input = $_POST;
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json)) $input = array_merge($input, $json);

        $override_id = $input['override_id'] ?? null;
        $emp_ids = $input['emp_id'] ?? [];
        if (!is_array($emp_ids)) $emp_ids = [$emp_ids];
        $start_date = $input['start_date'] ?? null;
        $end_date = $input['end_date'] ?? null;
        $work_start_time = $input['work_start_time'] ?? null;
        $work_end_time = $input['work_end_time'] ?? null;
        $reason = $input['reason'] ?? null;

        if ($override_id) {
            // update single override
            $sql = "UPDATE employee_schedule_overrides SET emp_id = ?, start_date = ?, end_date = ?, work_start_time = ?, work_end_time = ?, reason = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $emp_single = $emp_ids[0] ?? null;
            $stmt->execute([$emp_single, $start_date, $end_date, $work_start_time, $work_end_time, $reason, $override_id]);
            echo json_encode(['ok' => true, 'updated' => (int)$stmt->rowCount()]);
            exit;
        }

        // Insert for multiple employees
        $sql = "INSERT INTO employee_schedule_overrides (emp_id, start_date, end_date, work_start_time, work_end_time, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $created = 0;
        foreach ($emp_ids as $eid) {
            if ($eid === '__all__') continue;
            $stmt->execute([$eid, $start_date, $end_date, $work_start_time, $work_end_time, $reason, $_SESSION['user_id'] ?? 'admin']);
            $created += 1;
        }
        echo json_encode(['ok' => true, 'created' => $created]);
        exit;
    }

    // Delete via DELETE (or POST with delete flag)
    if ($method === 'DELETE') {
        $json = json_decode(file_get_contents('php://input'), true);
        $override_id = $json['override_id'] ?? null;
        if (!$override_id) { http_response_code(400); echo json_encode(['error' => 'override_id required']); exit; }
        $stmt = $pdo->prepare("DELETE FROM employee_schedule_overrides WHERE id = ?");
        $stmt->execute([$override_id]);
        echo json_encode(['ok' => true, 'deleted' => (int)$stmt->rowCount()]);
        exit;
    }

    // Unknown method
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// helper for header token verification
function verify_csrf_header($token) {
    // If your csrf_protection.php provides this helper replace accordingly; fallback to session compare
    if (function_exists('verify_csrf_token')) {
        if (!verify_csrf_token($token)) { http_response_code(403); echo json_encode(['error' => 'CSRF token invalid']); exit; }
    } else {
        if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $token) { http_response_code(403); echo json_encode(['error' => 'CSRF token invalid']); exit; }
    }
}

?>

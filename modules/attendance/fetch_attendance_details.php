<?php
require_once '../../includes/db_connection.php';

if (isset($_POST['id'])) {
    try {
        $id = $_POST['id'];
        
    $stmt = $pdo->prepare("SELECT a.*, e.first_name, e.middle_name, e.last_name, e.designation, e.user_image, b.name as branch_name 
                  FROM attendance_logs a 
                  INNER JOIN employees e ON a.emp_id = e.emp_id 
                  INNER JOIN branches b ON e.branch = b.id 
                  WHERE a.id = :id");
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            // Process the manual reason with support for both '||' and '|'
            $raw = (string)$record['manual_reason'];
            $reasonCode = trim($raw);
            $remarks = '';
            if (strpos($raw, '||') !== false) {
                [$reasonCode, $remarks] = array_map('trim', explode('||', $raw, 2));
            } elseif (strpos($raw, '|') !== false) {
                [$reasonCode, $remarks] = array_map('trim', explode('|', $raw, 2));
            }
            $record['reason_code'] = $reasonCode;
            $record['remarks'] = $remarks;
            
            echo json_encode([
                'status' => 'success',
                'data' => $record
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Record not found'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No ID provided'
    ]);
}
?>
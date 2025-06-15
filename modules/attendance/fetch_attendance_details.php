<?php
require_once '../../includes/db_connection.php';

if (isset($_POST['id'])) {
    try {
        $id = $_POST['id'];
        
        $stmt = $pdo->prepare("SELECT a.*, e.first_name, e.middle_name, e.last_name, e.designation, e.user_image, b.name as branch_name 
                              FROM attendance_logs a 
                              INNER JOIN employees e ON a.emp_Id = e.emp_id 
                              INNER JOIN branches b ON e.branch = b.id 
                              WHERE a.id = :id");
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            // Process the manual reason
            $parts = explode(' || ', $record['manual_reason']);
            $record['reason_code'] = trim($parts[0]);
            $record['remarks'] = isset($parts[1]) ? trim($parts[1]) : '';
            
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
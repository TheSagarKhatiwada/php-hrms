<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/reason_helpers.php';

// Only allow logged-in users
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$currentUserId = $_SESSION['user_id'];

// Fetch requests for the current user
try {
    $stmt = $pdo->prepare("
        SELECT 
            id, request_date, request_time, reason_label, reason_code, remarks, status, created_at, review_notes
        FROM attendance_requests 
        WHERE requested_by = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$currentUserId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data for display
    $data = [];
    foreach ($requests as $req) {
        $data[] = [
            'id' => $req['id'],
            'date' => date('M d, Y', strtotime($req['request_date'])),
            'time' => date('h:i A', strtotime($req['request_time'])),
            'reason' => $req['reason_label'] ?: $req['reason_code'],
            'remarks' => $req['remarks'],
            'status' => ucfirst($req['status']),
            'status_class' => getStatusClass($req['status']),
            'submitted' => date('M d, Y h:i A', strtotime($req['created_at'])),
            'notes' => $req['review_notes']
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    error_log('Fetch my requests failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch requests']);
}

function getStatusClass($status) {
    switch ($status) {
        case 'approved': return 'bg-success';
        case 'rejected': return 'bg-danger';
        case 'pending': return 'bg-warning text-dark';
        default: return 'bg-secondary';
    }
}

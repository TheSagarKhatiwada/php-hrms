<?php
session_start();
require_once '../includes/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Handle filtering (same as sms-logs.php)
$whereClause = "WHERE 1=1";
$params = [];

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $whereClause .= " AND status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $whereClause .= " AND DATE(created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $whereClause .= " AND DATE(created_at) <= ?";
    $params[] = $_GET['date_to'];
}

if (isset($_GET['phone']) && !empty($_GET['phone'])) {
    $whereClause .= " AND phone_number LIKE ?";
    $params[] = '%' . $_GET['phone'] . '%';
}

// Get SMS logs for export
$query = "
    SELECT 
        l.id,
        l.phone_number,
        l.message,
        l.sender,
        l.status,
        l.message_id,
        l.cost,
        l.created_at,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        e.employee_id as emp_id
    FROM sms_logs l 
    LEFT JOIN employees e ON l.employee_id = e.emp_id 
    $whereClause 
    ORDER BY l.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$smsLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = 'sms_logs_' . date('Y_m_d_H_i_s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create CSV output
$output = fopen('php://output', 'w');

// CSV headers
fputcsv($output, [
    'ID',
    'Date & Time',
    'Phone Number',
    'Employee Name',
    'Employee ID',
    'Message',
    'Sender',
    'Status',
    'Message ID',
    'Cost (Rs.)',
    'Message Length'
]);

// CSV data
foreach ($smsLogs as $log) {
    fputcsv($output, [
        $log['id'],
        date('Y-m-d H:i:s', strtotime($log['created_at'])),
        $log['phone_number'],
        $log['employee_name'] ?: 'External',
        $log['emp_id'] ?: '',
        $log['message'],
        $log['sender'],
        ucfirst($log['status']),
        $log['message_id'] ?: '',
        number_format($log['cost'], 4),
        strlen($log['message'])
    ]);
}

fclose($output);
exit();
?>

<?php
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/SparrowSMS.php';

header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != '1') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_identities':
            $stmt = $pdo->query("
                SELECT * FROM sms_sender_identities 
                ORDER BY is_default DESC, identity ASC
            ");
            $identities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'identities' => $identities
            ]);
            break;
            
        case 'add_identity':
            $identity = trim($_POST['identity'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($identity)) {
                throw new Exception('Identity name is required');
            }
            
            if (strlen($identity) > 11) {
                throw new Exception('Identity name cannot exceed 11 characters');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO sms_sender_identities (identity, description, is_active, approval_status) 
                VALUES (?, ?, 1, 'approved')
            ");
            $stmt->execute([$identity, $description]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Sender identity added successfully'
            ]);
            break;
            
        case 'update_identity':
            $id = intval($_POST['id'] ?? 0);
            $identity = trim($_POST['identity'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $is_active = intval($_POST['is_active'] ?? 1);
            
            if ($id <= 0) {
                throw new Exception('Invalid identity ID');
            }
            
            if (empty($identity)) {
                throw new Exception('Identity name is required');
            }
            
            if (strlen($identity) > 11) {
                throw new Exception('Identity name cannot exceed 11 characters');
            }
            
            $stmt = $pdo->prepare("
                UPDATE sms_sender_identities 
                SET identity = ?, description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$identity, $description, $is_active, $id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Sender identity updated successfully'
            ]);
            break;
            
        case 'set_default':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid identity ID');
            }
            
            // Remove default from all identities
            $pdo->exec("UPDATE sms_sender_identities SET is_default = 0");
            
            // Set new default
            $stmt = $pdo->prepare("UPDATE sms_sender_identities SET is_default = 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Default sender identity updated successfully'
            ]);
            break;
            
        case 'delete_identity':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid identity ID');
            }
            
            // Check if it's the default identity
            $stmt = $pdo->prepare("SELECT is_default FROM sms_sender_identities WHERE id = ?");
            $stmt->execute([$id]);
            $identity = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($identity && $identity['is_default']) {
                throw new Exception('Cannot delete the default sender identity');
            }
            
            $stmt = $pdo->prepare("DELETE FROM sms_sender_identities WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Sender identity deleted successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

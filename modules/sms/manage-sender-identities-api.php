<?php
/**
 * API-based Sender Identity Management
 * This API validates sender identities against Sparrow SMS API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/SparrowSMS.php';

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

try {
    $sms = new SparrowSMS();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'get_identities':
            echo json_encode([
                'success' => true,
                'identities' => $sms->getApprovedSenderIdentities()
            ]);
            break;

        case 'add_identity':
            $identity = $_POST['identity'] ?? '';
            $description = $_POST['description'] ?? '';
            
            if (empty($identity)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Sender identity is required'
                ]);
                break;
            }
            
            $result = $sms->addCustomSenderIdentity($identity, $description);
            echo json_encode($result);
            break;

        case 'remove_identity':
            $identity = $_POST['identity'] ?? '';
            
            if (empty($identity)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Sender identity is required'
                ]);
                break;
            }
            
            $result = $sms->removeSenderIdentity($identity);
            echo json_encode($result);
            break;

        case 'refresh_identities':
            $identities = $sms->refreshSenderIdentities();
            echo json_encode([
                'success' => true,
                'message' => 'Sender identities refreshed successfully',
                'identities' => $identities
            ]);
            break;

        case 'validate_identity':
            $identity = $_POST['identity'] ?? '';
            
            if (empty($identity)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Sender identity is required'
                ]);
                break;
            }
            
            $isValid = $sms->validateSenderIdentity($identity);
            echo json_encode([
                'success' => true,
                'valid' => $isValid,
                'message' => $isValid ? 
                    'Sender identity is approved in your Sparrow SMS account' : 
                    'Sender identity is not approved in your Sparrow SMS account'
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

<?php
header('Content-Type: application/json');
require_once 'includes/db_connection.php';

// Check if user is logged in and has admin role
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != '1') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get the request body
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    switch ($data['type']) {
        case 'add':
            $stmt = $pdo->prepare("INSERT INTO fixedassets (AssetName, CategoryID, PurchaseDate, PurchaseCost, AssetsDescription, Status, AssetImage, AssetSerial) 
                                 VALUES (:assetName, :categoryId, :purchaseDate, :purchaseCost, :description, :status, :imagePath, :serialNumber)");
            $stmt->execute([
                ':assetName' => $data['data']['AssetName'],
                ':categoryId' => $data['data']['CategoryID'],
                ':purchaseDate' => $data['data']['PurchaseDate'],
                ':purchaseCost' => $data['data']['PurchaseCost'],
                ':description' => $data['data']['AssetsDescription'],
                ':status' => $data['data']['Status'],
                ':imagePath' => $data['data']['AssetImage'] ?? '',
                ':serialNumber' => $data['data']['AssetSerial']
            ]);
            break;
            
        case 'update':
            $stmt = $pdo->prepare("UPDATE fixedassets 
                                 SET AssetName = :assetName,
                                     CategoryID = :categoryId,
                                     PurchaseDate = :purchaseDate,
                                     PurchaseCost = :purchaseCost,
                                     AssetsDescription = :description,
                                     Status = :status,
                                     AssetImage = :imagePath
                                 WHERE AssetID = :assetId");
            $stmt->execute([
                ':assetId' => $data['data']['AssetID'],
                ':assetName' => $data['data']['AssetName'],
                ':categoryId' => $data['data']['CategoryID'],
                ':purchaseDate' => $data['data']['PurchaseDate'],
                ':purchaseCost' => $data['data']['PurchaseCost'],
                ':description' => $data['data']['AssetsDescription'],
                ':status' => $data['data']['Status'],
                ':imagePath' => $data['data']['AssetImage'] ?? ''
            ]);
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM fixedassets WHERE AssetID = :assetId");
            $stmt->execute([':assetId' => $data['data']['AssetID']]);
            break;
            
        default:
            throw new Exception('Invalid operation type');
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 
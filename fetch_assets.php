<?php
// Include database connection
require_once 'includes/db_connection.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get category ID from POST request
if (!isset($_POST['categoryId'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Category ID is required']);
    exit();
}

$categoryId = $_POST['categoryId'];

try {
    // Debug - log the category ID
    file_put_contents('debug_log.txt', "Fetching assets for category ID: $categoryId\n", FILE_APPEND);
    
    // Use lowercase table name consistently - this is what your database actually uses
    $stmt = $pdo->prepare("SELECT 
                            a.AssetID,
                            a.AssetName,
                            a.AssetSerial AS Serial,
                            DATE_FORMAT(a.PurchaseDate, '%d %b %Y') AS PurchaseDate,
                            a.Status,
                            a.PurchaseCost,
                            a.AssetCondition,
                            a.AssetLocation,
                            a.AssetImage
                          FROM fixedassets a
                          WHERE a.CategoryID = :categoryId
                          ORDER BY a.AssetName");

    $stmt->execute([':categoryId' => $categoryId]);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug - log the query and results
    file_put_contents('debug_log.txt', "SQL Query: " . $stmt->queryString . "\n", FILE_APPEND);
    file_put_contents('debug_log.txt', "Found " . count($assets) . " assets\n", FILE_APPEND);
    file_put_contents('debug_log.txt', "Assets data: " . json_encode($assets) . "\n", FILE_APPEND);

    // Return JSON response with data object format for DataTables
    header('Content-Type: application/json');
    echo json_encode(['data' => $assets]);
} catch (PDOException $e) {
    // Log error to file
    file_put_contents('debug_log.txt', "Database error: " . $e->getMessage() . "\n", FILE_APPEND);
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error fetching assets: ' . $e->getMessage()]);
}
?>
<?php
ob_start(); // Start output buffering 
$page = 'Manage Assets';

// Include utilities for role check functions
require_once 'includes/session_config.php';
require_once 'includes/utilities.php';

// Use the standardized role check function - both checks combined into one
if (!is_admin()) {
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $assetName = $_POST['assetName'];
            $categoryId = $_POST['categoryId'];
            $purchaseDate = $_POST['purchaseDate'];
            $purchaseCost = $_POST['purchaseCost'];
            $description = $_POST['description'];
            $status = "Available"; // Set default status as Available
            
            try {
                // Get category details
                $stmt = $pdo->prepare("SELECT CategoryName, CategoryShortCode FROM AssetCategories WHERE CategoryID = :categoryId");
                $stmt->execute([':categoryId' => $categoryId]);
                $category = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get the last serial number for this category
                $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(AssetSerial, '-', -1) AS UNSIGNED)) as last_number 
                                      FROM fixedassets 
                                      WHERE AssetSerial LIKE :categoryCode");
                $stmt->execute([':categoryCode' => $category['CategoryShortCode'] . '-%']);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Generate new serial number with 2-digit leading zeros
                $nextNumber = ($result['last_number'] ?? 0) + 1;
                $serialNumber = $category['CategoryShortCode'] . '-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
                
                // Handle image upload
                $imagePath = ''; // Empty path for default icon
                if (isset($_FILES['assetImage']) && $_FILES['assetImage']['error'] == 0) {
                    $uploadDir = 'resources/assetsimages/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileExtension = strtolower(pathinfo($_FILES['assetImage']['name'], PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png'];
                    
                    if (in_array($fileExtension, $allowedExtensions)) {
                        $fileName = uniqid('asset_') . '.' . $fileExtension;
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['assetImage']['tmp_name'], $targetPath)) {
                            $imagePath = $targetPath;
                        }
                    }
                }
                
                $stmt = $pdo->prepare("INSERT INTO fixedassets (AssetName, CategoryID, PurchaseDate, PurchaseCost, AssetsDescription, Status, AssetImage, AssetSerial) 
                                    VALUES (:assetName, :categoryId, :purchaseDate, :purchaseCost, :description, :status, :imagePath, :serialNumber)");
                $stmt->execute([
                    ':assetName' => $assetName,
                    ':categoryId' => $categoryId,
                    ':purchaseDate' => $purchaseDate,
                    ':purchaseCost' => $purchaseCost,
                    ':description' => $description,
                    ':status' => $status,
                    ':imagePath' => $imagePath,
                    ':serialNumber' => $serialNumber
                ]);
                
                $_SESSION['success'] = "Asset added successfully! Serial Number: " . $serialNumber;
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error adding asset: " . $e->getMessage();
            }
        } 
        elseif ($action == 'edit') {
            $assetId = $_POST['assetId'];
            $assetName = $_POST['assetName'];
            $categoryId = $_POST['categoryId'];
            $purchaseDate = $_POST['purchaseDate'];
            $purchaseCost = $_POST['purchaseCost'];
            $warrantyEndDate = $_POST['warrantyEndDate'];
            $assetCondition = $_POST['assetCondition'];
            $assetLocation = $_POST['assetLocation'];
            $description = $_POST['description'];
            $currentImage = $_POST['currentImage'];
            
            try {
                // Get current asset details including serial number
                $stmt = $pdo->prepare("SELECT AssetSerial FROM fixedassets WHERE AssetID = :assetId");
                $stmt->execute([':assetId' => $assetId]);
                $currentAsset = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // If no serial number exists, generate one
                if (empty($currentAsset['AssetSerial'])) {
                    // Get category details
                    $stmt = $pdo->prepare("SELECT CategoryShortCode FROM AssetCategories WHERE CategoryID = :categoryId");
                    $stmt->execute([':categoryId' => $categoryId]);
                    $category = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get the last serial number for this category
                    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(AssetSerial, '-', -1) AS UNSIGNED)) as last_number 
                                          FROM fixedassets 
                                          WHERE AssetSerial LIKE :categoryCode");
                    $stmt->execute([':categoryCode' => $category['CategoryShortCode'] . '-%']);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Generate new serial number with 2-digit leading zeros
                    $nextNumber = ($result['last_number'] ?? 0) + 1;
                    $serialNumber = $category['CategoryShortCode'] . '-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
                } else {
                    $serialNumber = $currentAsset['AssetSerial'];
                }
                
                // Handle image upload
                $imagePath = $currentImage; // Keep current image by default
                if (isset($_FILES['assetImage']) && $_FILES['assetImage']['error'] == 0) {
                    $uploadDir = 'resources/assetsimages/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileExtension = strtolower(pathinfo($_FILES['assetImage']['name'], PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png'];
                    
                    if (in_array($fileExtension, $allowedExtensions)) {
                        $fileName = uniqid('asset_') . '.' . $fileExtension;
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['assetImage']['tmp_name'], $targetPath)) {
                            // Delete old image if it's not the default image
                            if ($currentImage != 'resources/assetsimages/default-asset.png' && file_exists($currentImage)) {
                                unlink($currentImage);
                            }
                            $imagePath = $targetPath;
                        }
                    }
                }
                
                $stmt = $pdo->prepare("UPDATE fixedassets SET 
                                    AssetName = :assetName,
                                    CategoryID = :categoryId,
                                    PurchaseDate = :purchaseDate,
                                    PurchaseCost = :purchaseCost,
                                    WarrantyEndDate = :warrantyEndDate,
                                    AssetCondition = :assetCondition,
                                    AssetLocation = :assetLocation,
                                    AssetsDescription = :description,
                                    AssetImage = :imagePath,
                                    AssetSerial = :serialNumber
                                    WHERE AssetID = :assetId");
                $stmt->execute([
                    ':assetId' => $assetId,
                    ':assetName' => $assetName,
                    ':categoryId' => $categoryId,
                    ':purchaseDate' => $purchaseDate,
                    ':purchaseCost' => $purchaseCost,
                    ':warrantyEndDate' => $warrantyEndDate,
                    ':assetCondition' => $assetCondition,
                    ':assetLocation' => $assetLocation,
                    ':description' => $description,
                    ':imagePath' => $imagePath,
                    ':serialNumber' => $serialNumber
                ]);
                
                $_SESSION['success'] = "Asset updated successfully!";
                if (empty($currentAsset['AssetSerial'])) {
                    $_SESSION['success'] .= " Serial Number assigned: " . $serialNumber;
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error updating asset: " . $e->getMessage();
            }
        }
        elseif ($action == 'delete') {
            $assetId = $_POST['assetId'];
            
            try {
                // Get the image path before deleting
                $stmt = $pdo->prepare("SELECT AssetImage FROM fixedassets WHERE AssetID = :assetId");
                $stmt->execute([':assetId' => $assetId]);
                $imagePath = $stmt->fetchColumn();
                
                // Delete the asset
                $stmt = $pdo->prepare("DELETE FROM fixedassets WHERE AssetID = :assetId");
                $stmt->execute([':assetId' => $assetId]);
                
                // Delete the image file if it's not the default image
                if ($imagePath != 'resources/assetsimages/default-asset.png' && file_exists($imagePath)) {
                    unlink($imagePath);
                }
                
                $_SESSION['success'] = "Asset deleted successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error deleting asset: " . $e->getMessage();
            }
        }
        
        // Redirect to prevent form resubmission
        header("Location: manage_assets.php");
        exit();
    }
}

// Fetch all assets with their categories
try {
    $stmt = $pdo->query("SELECT a.*, c.CategoryName 
                        FROM fixedassets a 
                        LEFT JOIN AssetCategories c ON a.CategoryID = c.CategoryID 
                        ORDER BY a.AssetName");
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch categories for dropdown
    $stmt = $pdo->query("SELECT CategoryID, CategoryName FROM AssetCategories ORDER BY CategoryName");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching data: " . $e->getMessage();
    $assets = [];
    $categories = [];
}
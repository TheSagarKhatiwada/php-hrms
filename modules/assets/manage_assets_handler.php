<?php
ob_start(); // Start output buffering 
$page = 'Manage Assets';

// Include utilities for role check functions
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';
require_once '../../includes/csrf_protection.php';
require_once __DIR__ . '/../../includes/services/AssetService.php';

// Use the standardized role/permission check
if (!is_admin() && !has_permission('manage_assets')) {
    header('Location: ../../index.php');
    exit();
}

$assetService = new AssetService($pdo);
$csrf_token = generate_csrf_token();
$requestContext = [
    'user_id' => $_SESSION['user_id'] ?? null,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
];
$allowedConditions = ['Excellent', 'Good', 'Fair', 'Poor'];

$normalizeCondition = static function (?string $value, array $allowed, ?string $fallback = null): ?string {
    if ($value === null) {
        return $fallback;
    }
    $normalized = trim($value);
    if ($normalized === '') {
        return $fallback;
    }
    $normalized = ucfirst(strtolower($normalized));
    return in_array($normalized, $allowed, true) ? $normalized : $fallback;
};

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? null;
    if (!$token || !verify_csrf_token($token)) {
        $_SESSION['error'] = 'Invalid request token. Please refresh and try again.';
        header('Location: manage_assets.php');
        exit();
    }

    $action = $_POST['action'];

    try {
        if ($action === 'add') {
            $condition = $normalizeCondition($_POST['assetCondition'] ?? null, $allowedConditions, 'Good');
            $assetService->createAsset([
                'assetName' => $_POST['assetName'] ?? '',
                'categoryId' => $_POST['categoryId'] ?? null,
                'productSerial' => $_POST['productSerial'] ?? null,
                'purchaseDate' => $_POST['purchaseDate'] ?? null,
                'purchaseCost' => $_POST['purchaseCost'] ?? null,
                'warrantyEndDate' => $_POST['warrantyEndDate'] ?? null,
                'assetCondition' => $condition,
                'assetLocation' => $_POST['assetLocation'] ?? '',
                'description' => $_POST['description'] ?? '',
                'status' => 'Available',
            ], $_FILES['assetImage'] ?? null, $requestContext);

            $_SESSION['success'] = 'Asset added successfully.';
        } elseif ($action === 'edit') {
            $assetId = (int)($_POST['assetId'] ?? 0);
            $condition = $normalizeCondition($_POST['assetCondition'] ?? null, $allowedConditions, null);
            $updated = $assetService->updateAsset($assetId, [
                'assetName' => $_POST['assetName'] ?? null,
                'categoryId' => $_POST['categoryId'] ?? null,
                'productSerial' => $_POST['productSerial'] ?? null,
                'purchaseDate' => $_POST['purchaseDate'] ?? null,
                'purchaseCost' => $_POST['purchaseCost'] ?? null,
                'warrantyEndDate' => $_POST['warrantyEndDate'] ?? null,
                'assetCondition' => $condition,
                'assetLocation' => $_POST['assetLocation'] ?? null,
                'description' => $_POST['description'] ?? null,
                'status' => $_POST['status'] ?? null,
            ], $_FILES['assetImage'] ?? null, $requestContext);

            $_SESSION['success'] = 'Asset updated successfully. Serial: ' . ($updated['AssetSerial'] ?? '');
        } elseif ($action === 'delete') {
            $assetId = (int)($_POST['assetId'] ?? 0);
            $assetService->softDeleteAsset($assetId, array_merge($requestContext, [
                'reason' => 'Deleted via assets UI',
            ]));
            $_SESSION['success'] = 'Asset deleted successfully.';
        } elseif ($action === 'restore') {
            $assetId = (int)($_POST['assetId'] ?? 0);
            $assetService->restoreAsset($assetId, $requestContext);
            $_SESSION['success'] = 'Asset restored successfully.';
        }
    } catch (Throwable $e) {
        $logMessage = sprintf('[%s] Asset %s failed: %s%s', date('c'), $action, $e->getMessage(), PHP_EOL);
        error_log($logMessage, 3, __DIR__ . '/error_log.txt');
        $_SESSION['error'] = 'Asset operation failed: ' . $e->getMessage();
    }

    header('Location: manage_assets.php');
    exit();
}

// Fetch categories for dropdowns/filters
try {
    $stmt = $pdo->query("SELECT CategoryID, CategoryName FROM assetcategories ORDER BY CategoryName");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching categories: ' . $e->getMessage();
    $categories = [];
}
<?php
/**
 * AssetService
 * Centralized helper for asset CRUD, filtering, uploads, and audit logging.
 */

require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../utilities.php';
require_once __DIR__ . '/../validation.php';

class AssetService
{
    private PDO $pdo;
    private string $projectRoot;
    private string $uploadDir;
    private string $publicUploadPath;
    private ?bool $softDeleteSupported = null;

    public function __construct(?PDO $connection = null)
    {
        if ($connection instanceof PDO) {
            $this->pdo = $connection;
        } else {
            global $pdo;
            if (!($pdo instanceof PDO)) {
                throw new RuntimeException('Database connection is not available');
            }
            $this->pdo = $pdo;
        }

        $this->projectRoot = dirname(__DIR__, 2);
        $this->uploadDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'assets';
        $this->publicUploadPath = 'uploads/assets';
        $this->ensureUploadDirectory();
    }

    /**
     * Return paginated list of assets with optional filters.
     */
    public function listAssets(array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = (int)($params['per_page'] ?? 25);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $search = trim($params['search'] ?? '');
        $status = trim($params['status'] ?? '');
        $categoryId = $params['category'] ?? '';
        $orderColumn = $this->resolveSortColumn($params['order'] ?? 'purchaseDate');
        $direction = strtoupper($params['direction'] ?? 'DESC');
        $direction = $direction === 'ASC' ? 'ASC' : 'DESC';
        $includeDeleted = $this->toBool($params['include_deleted'] ?? false);
        $onlyDeleted = $this->toBool($params['only_deleted'] ?? false);
        if ($onlyDeleted) {
            $includeDeleted = true;
        }

        $baseSql = ' FROM fixedassets a LEFT JOIN assetcategories c ON a.CategoryID = c.CategoryID WHERE 1=1';
        if ($this->supportsSoftDeletes()) {
            if ($onlyDeleted) {
                $baseSql .= ' AND a.deleted_at IS NOT NULL';
            } elseif (!$includeDeleted) {
                $baseSql .= ' AND a.deleted_at IS NULL';
            }
        }
        $conditions = [];
        $bindings = [];

        if ($search !== '') {
            $conditions[] = '(a.AssetName LIKE :search OR a.AssetSerial LIKE :search OR a.ProductSerial LIKE :search OR c.CategoryName LIKE :search)';
            $bindings[':search'] = '%' . $search . '%';
        }

        if ($status !== '') {
            $conditions[] = 'a.Status = :status';
            $bindings[':status'] = $status;
        }

        if ($categoryId !== '' && $categoryId !== null) {
            $conditions[] = 'a.CategoryID = :categoryId';
            $bindings[':categoryId'] = (int)$categoryId;
        }

        $whereSql = $conditions ? (' AND ' . implode(' AND ', $conditions)) : '';

        // Total assets (without filters)
        if ($this->supportsSoftDeletes()) {
            if ($onlyDeleted) {
                $total = (int)$this->pdo->query('SELECT COUNT(*) FROM fixedassets WHERE deleted_at IS NOT NULL')->fetchColumn();
            } elseif ($includeDeleted) {
                $total = (int)$this->pdo->query('SELECT COUNT(*) FROM fixedassets')->fetchColumn();
            } else {
                $total = (int)$this->pdo->query('SELECT COUNT(*) FROM fixedassets WHERE deleted_at IS NULL')->fetchColumn();
            }
        } else {
            $total = (int)$this->pdo->query('SELECT COUNT(*) FROM fixedassets')->fetchColumn();
        }

        // Filtered count
        $countStmt = $this->pdo->prepare('SELECT COUNT(*)' . $baseSql . $whereSql);
        foreach ($bindings as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $countStmt->bindValue($key, $value, $paramType);
        }
        $countStmt->execute();
        $filtered = (int)$countStmt->fetchColumn();

        // Data query
        $dataSql = 'SELECT a.*, c.CategoryName, c.CategoryShortCode' . $baseSql . $whereSql . " ORDER BY {$orderColumn} {$direction} LIMIT :limit OFFSET :offset";
        $dataStmt = $this->pdo->prepare($dataSql);
        foreach ($bindings as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $dataStmt->bindValue($key, $value, $paramType);
        }
        $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $rows,
            'meta' => [
                'total' => $total,
                'filtered' => $filtered,
                'page' => $page,
                'per_page' => $perPage,
                'showing_deleted' => $onlyDeleted,
            ],
        ];
    }

    /**
     * Create new asset and return persisted record.
     */
    public function createAsset(array $payload, ?array $file = null, array $context = []): array
    {
        $data = $this->normalizePayload($payload);

        $this->pdo->beginTransaction();
        try {
            $serial = $this->generateSerial($data['CategoryID']);
            $imagePath = $this->handleImageUpload($file);

            $stmt = $this->pdo->prepare('INSERT INTO fixedassets (
                AssetName, CategoryID, PurchaseDate, PurchaseCost, WarrantyEndDate,
                AssetCondition, AssetLocation, AssetsDescription, Status, AssetImage, AssetSerial, ProductSerial
            ) VALUES (
                :name, :category, :purchaseDate, :purchaseCost, :warrantyEnd,
                :condition, :location, :description, :status, :imagePath, :serial, :productSerial
            )');

            $stmt->execute([
                ':name' => $data['AssetName'],
                ':category' => $data['CategoryID'],
                ':purchaseDate' => $data['PurchaseDate'],
                ':purchaseCost' => $data['PurchaseCost'],
                ':warrantyEnd' => $data['WarrantyEndDate'],
                ':condition' => $data['AssetCondition'],
                ':location' => $data['AssetLocation'],
                ':description' => $data['AssetsDescription'],
                ':status' => $data['Status'],
                ':imagePath' => $imagePath ?? '',
                ':serial' => $serial,
                ':productSerial' => $data['ProductSerial'] ?? null,
            ]);

            $assetId = (int)$this->pdo->lastInsertId();
            $this->logAudit($assetId, 'asset_created', [
                'summary' => 'Asset created',
                'after' => array_merge($data, ['AssetSerial' => $serial, 'AssetImage' => $imagePath]),
            ], $context);

            $this->pdo->commit();
            return $this->findAsset($assetId);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update existing asset.
     */
    public function updateAsset(int $assetId, array $payload, ?array $file = null, array $context = []): array
    {
        $existing = $this->findAsset($assetId);
        if (!$existing) {
            throw new InvalidArgumentException('Asset not found');
        }

        $data = $this->normalizePayload($payload, false);
        $imagePath = $existing['AssetImage'];
        $newImage = $this->handleImageUpload($file);
        if ($newImage) {
            $this->deleteImage($imagePath);
            $imagePath = $newImage;
        }

        $fields = [
            'AssetName' => $data['AssetName'] ?? $existing['AssetName'],
            'CategoryID' => $data['CategoryID'] ?? $existing['CategoryID'],
            'PurchaseDate' => $data['PurchaseDate'] ?? $existing['PurchaseDate'],
            'PurchaseCost' => $data['PurchaseCost'] ?? $existing['PurchaseCost'],
            'WarrantyEndDate' => array_key_exists('WarrantyEndDate', $data) ? $data['WarrantyEndDate'] : $existing['WarrantyEndDate'],
            'AssetCondition' => $data['AssetCondition'] ?? $existing['AssetCondition'],
            'AssetLocation' => $data['AssetLocation'] ?? $existing['AssetLocation'],
            'AssetsDescription' => $data['AssetsDescription'] ?? $existing['AssetsDescription'],
            'Status' => $data['Status'] ?? $existing['Status'],
            'AssetImage' => $imagePath,
            'ProductSerial' => array_key_exists('ProductSerial', $data) ? $data['ProductSerial'] : ($existing['ProductSerial'] ?? null),
        ];

        // Ensure serial exists
        $serial = $existing['AssetSerial'];
        if (empty($serial)) {
            $serial = $this->generateSerial($fields['CategoryID']);
        }

        $stmt = $this->pdo->prepare('UPDATE fixedassets SET
            AssetName = :name,
            CategoryID = :category,
            PurchaseDate = :purchaseDate,
            PurchaseCost = :purchaseCost,
            WarrantyEndDate = :warrantyEnd,
            AssetCondition = :condition,
            AssetLocation = :location,
            AssetsDescription = :description,
            Status = :status,
            AssetImage = :imagePath,
            AssetSerial = :serial,
            ProductSerial = :productSerial
            WHERE AssetID = :assetId');

        $stmt->execute([
            ':name' => $fields['AssetName'],
            ':category' => $fields['CategoryID'],
            ':purchaseDate' => $fields['PurchaseDate'],
            ':purchaseCost' => $fields['PurchaseCost'],
            ':warrantyEnd' => $fields['WarrantyEndDate'],
            ':condition' => $fields['AssetCondition'],
            ':location' => $fields['AssetLocation'],
            ':description' => $fields['AssetsDescription'],
            ':status' => $fields['Status'],
            ':imagePath' => $imagePath ?? '',
            ':serial' => $serial,
            ':productSerial' => $fields['ProductSerial'],
            ':assetId' => $assetId,
        ]);

        $this->logAudit($assetId, 'asset_updated', [
            'summary' => 'Asset updated',
            'before' => $existing,
            'after' => array_merge($fields, ['AssetSerial' => $serial]),
        ], $context);

        return $this->findAsset($assetId);
    }

    /**
     * Soft delete asset.
     */
    public function softDeleteAsset(int $assetId, array $context = []): void
    {
        $existing = $this->findAsset($assetId);
        if (!$existing) {
            throw new InvalidArgumentException('Asset not found');
        }

        $reason = trim($context['reason'] ?? 'Manual delete');
        $userId = $context['user_id'] ?? null;

        if ($this->supportsSoftDeletes()) {
            $stmt = $this->pdo->prepare('UPDATE fixedassets SET deleted_at = NOW(), deleted_by = :user, deleted_reason = :reason WHERE AssetID = :id');
            $stmt->execute([
                ':user' => $userId,
                ':reason' => $reason,
                ':id' => $assetId,
            ]);

            $this->logAudit($assetId, 'asset_deleted', [
                'summary' => $reason,
                'before' => $existing,
            ], $context);
        } else {
            $stmt = $this->pdo->prepare('DELETE FROM fixedassets WHERE AssetID = :id');
            $stmt->execute([':id' => $assetId]);

            $this->logAudit($assetId, 'asset_deleted_permanent', [
                'summary' => $reason,
                'before' => $existing,
            ], $context);
        }
    }

    public function restoreAsset(int $assetId, array $context = []): array
    {
        if (!$this->supportsSoftDeletes()) {
            throw new RuntimeException('Soft delete columns not available on this installation');
        }

        $existing = $this->findAsset($assetId, true);
        if (!$existing || empty($existing['deleted_at'])) {
            throw new InvalidArgumentException('Asset is not deleted or does not exist');
        }

        $stmt = $this->pdo->prepare('UPDATE fixedassets SET deleted_at = NULL, deleted_by = NULL, deleted_reason = NULL WHERE AssetID = :id');
        $stmt->execute([':id' => $assetId]);

        $this->logAudit($assetId, 'asset_restored', [
            'summary' => 'Asset restored from archive',
            'before' => $existing,
        ], $context);

        return $this->findAsset($assetId);
    }

    private function resolveSortColumn(string $column): string
    {
        $map = [
            'name' => 'a.AssetName',
            'status' => 'a.Status',
            'cost' => 'a.PurchaseCost',
            'category' => 'c.CategoryName',
            'purchaseDate' => 'a.PurchaseDate',
        ];
        return $map[$column] ?? 'a.PurchaseDate';
    }

    private function normalizePayload(array $payload, bool $requireAll = true): array
    {
        $data = [];

        $name = $payload['assetName'] ?? $payload['AssetName'] ?? null;
        if ($name !== null || $requireAll) {
            $name = trim((string)$name);
            if ($requireAll && $name === '') {
                throw new InvalidArgumentException('Asset name is required');
            }
            if ($name !== null) {
                $data['AssetName'] = $name;
            }
        }

        $category = $payload['categoryId'] ?? $payload['CategoryID'] ?? null;
        if ($category !== null || $requireAll) {
            $categoryId = (int)$category;
            if ($requireAll && $categoryId <= 0) {
                throw new InvalidArgumentException('Category is required');
            }
            if ($category !== null) {
                $data['CategoryID'] = $categoryId;
            }
        }

        $purchaseDate = $payload['purchaseDate'] ?? $payload['PurchaseDate'] ?? null;
        if ($purchaseDate !== null || $requireAll) {
            $normalized = $purchaseDate ? $this->normalizeDate($purchaseDate) : null;
            if ($requireAll && !$normalized) {
                throw new InvalidArgumentException('Purchase date is invalid');
            }
            if ($purchaseDate !== null) {
                $data['PurchaseDate'] = $normalized;
            }
        }

        $purchaseCost = $payload['purchaseCost'] ?? $payload['PurchaseCost'] ?? null;
        if ($purchaseCost !== null || $requireAll) {
            $cost = $purchaseCost !== null ? (float)$purchaseCost : null;
            if ($requireAll && ($cost === null || $cost < 0)) {
                throw new InvalidArgumentException('Purchase cost is invalid');
            }
            if ($purchaseCost !== null) {
                $data['PurchaseCost'] = $cost;
            }
        }

        $warranty = $payload['warrantyEndDate'] ?? $payload['WarrantyEndDate'] ?? null;
        if ($warranty !== null) {
            $data['WarrantyEndDate'] = $warranty ? $this->normalizeDate($warranty) : null;
        } elseif ($requireAll) {
            $data['WarrantyEndDate'] = null;
        }

        $condition = $payload['assetCondition'] ?? $payload['AssetCondition'] ?? null;
        if ($condition !== null || $requireAll) {
            $allowed = ['Excellent', 'Good', 'Fair', 'Poor'];
            $condition = $condition ? ucfirst(strtolower($condition)) : null;
            if ($requireAll && !in_array($condition, $allowed, true)) {
                $condition = 'Good';
            }
            if ($condition !== null) {
                $data['AssetCondition'] = in_array($condition, $allowed, true) ? $condition : 'Good';
            }
        }

        $location = $payload['assetLocation'] ?? $payload['AssetLocation'] ?? null;
        if ($location !== null || $requireAll) {
            $location = $location !== null ? trim((string)$location) : null;
            if ($requireAll && $location === '') {
                throw new InvalidArgumentException('Location is required');
            }
            if ($location !== null) {
                $data['AssetLocation'] = $location;
            }
        }

        $productSerial = $payload['productSerial'] ?? $payload['ProductSerial'] ?? null;
        if ($productSerial !== null) {
            $data['ProductSerial'] = trim((string)$productSerial);
        } elseif ($requireAll) {
            $data['ProductSerial'] = null;
        }

        $description = $payload['description'] ?? $payload['AssetsDescription'] ?? null;
        if ($description !== null) {
            $data['AssetsDescription'] = trim((string)$description);
        } elseif ($requireAll) {
            $data['AssetsDescription'] = '';
        }

        $status = $payload['status'] ?? $payload['Status'] ?? null;
        if ($status !== null || $requireAll) {
            $status = $status !== null ? trim((string)$status) : 'Available';
            $data['Status'] = $status === '' ? 'Available' : $status;
        }

        return $data;
    }

    private function normalizeDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d', $timestamp);
    }

    private function generateSerial(int $categoryId): string
    {
        $stmt = $this->pdo->prepare('SELECT CategoryShortCode FROM assetcategories WHERE CategoryID = :id');
        $stmt->execute([':id' => $categoryId]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$category || empty($category['CategoryShortCode'])) {
            throw new InvalidArgumentException('Category short code missing. Please update the category.');
        }

        $shortCode = $category['CategoryShortCode'];
        $stmt = $this->pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(AssetSerial, '-', -1) AS UNSIGNED)) as last_number FROM fixedassets WHERE AssetSerial LIKE :code AND CategoryID = :categoryId");
        $stmt->execute([
            ':code' => $shortCode . '-%',
            ':categoryId' => $categoryId,
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNumber = (int)($result['last_number'] ?? 0) + 1;

        return $shortCode . '-' . str_pad((string)$nextNumber, 3, '0', STR_PAD_LEFT);
    }

    private function handleImageUpload(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $savedPath = secure_file_upload($file, $this->uploadDir, $allowed, 2 * 1024 * 1024);
        if (!$savedPath) {
            throw new RuntimeException('Invalid asset image upload');
        }

        $filename = basename($savedPath);
        return $this->publicUploadPath . '/' . $filename;
    }

    private function deleteImage(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        $absolute = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function findAsset(int $assetId, bool $withDeleted = false): ?array
    {
        $deletedConstraint = '';
        if ($this->supportsSoftDeletes() && !$withDeleted) {
            $deletedConstraint = ' AND a.deleted_at IS NULL';
        }

        $stmt = $this->pdo->prepare('SELECT a.*, c.CategoryName, c.CategoryShortCode FROM fixedassets a LEFT JOIN assetcategories c ON a.CategoryID = c.CategoryID WHERE a.AssetID = :id' . $deletedConstraint);
        $stmt->execute([':id' => $assetId]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        return $asset ?: null;
    }

    private function logAudit(?int $assetId, string $action, array $details = [], array $context = []): void
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO asset_audit (asset_id, action, summary, before_snapshot, after_snapshot, performed_by, ip_address) VALUES (:asset, :action, :summary, :before, :after, :user, :ip)');
            $stmt->execute([
                ':asset' => $assetId,
                ':action' => $action,
                ':summary' => $details['summary'] ?? '',
                ':before' => isset($details['before']) ? json_encode($details['before']) : null,
                ':after' => isset($details['after']) ? json_encode($details['after']) : null,
                ':user' => $context['user_id'] ?? ($_SESSION['user_id'] ?? null),
                ':ip' => $context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            ]);
        } catch (Throwable $e) {
            error_log('Asset audit log failed: ' . $e->getMessage(), 3, $this->projectRoot . '/error_log.txt');
        }
    }

    private function ensureUploadDirectory(): void
    {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    private function supportsSoftDeletes(): bool
    {
        if ($this->softDeleteSupported !== null) {
            return $this->softDeleteSupported;
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM fixedassets LIKE 'deleted_at'");
            $this->softDeleteSupported = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->softDeleteSupported = false;
        }

        return $this->softDeleteSupported;
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        $normalized = strtolower((string)$value);
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

<?php
require __DIR__ . '/../includes/db_connection.php';
$code = 'view_all_branch_attendance';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "DELETE rp FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE p.name = :code";
$stmt = $pdo->prepare($sql);
$stmt->execute([':code' => $code]);
echo 'Deleted rows: ' . $stmt->rowCount() . PHP_EOL;

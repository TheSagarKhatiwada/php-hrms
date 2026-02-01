<?php
require_once 'includes/db_connection.php';

try {
    $stmt = $pdo->query("DESCRIBE salary_components");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

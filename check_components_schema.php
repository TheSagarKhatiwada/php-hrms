<?php
require_once 'includes/db_connection.php';

try {
    $stmt = $pdo->query("SHOW CREATE TABLE salary_components");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($row);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

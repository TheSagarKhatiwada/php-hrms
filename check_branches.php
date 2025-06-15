<?php
require_once 'includes/db_connection.php';

echo "=== CHECKING BRANCHES TABLE ===\n";
$stmt = $pdo->query('SELECT * FROM branches');
$branches = $stmt->fetchAll();

if (empty($branches)) {
    echo "❌ No branches found in the table!\n";
} else {
    echo "✅ Found " . count($branches) . " branches:\n";
    foreach ($branches as $branch) {
        echo "ID: {$branch['id']}, Name: {$branch['name']}\n";
    }
}

echo "\n=== CHECKING DESIGNATIONS TABLE ===\n";
$stmt = $pdo->query('SELECT * FROM designations');
$designations = $stmt->fetchAll();

if (empty($designations)) {
    echo "❌ No designations found in the table!\n";
} else {
    echo "✅ Found " . count($designations) . " designations:\n";
    foreach ($designations as $designation) {
        echo "ID: {$designation['id']}, Title: {$designation['title']}\n";
    }
}

echo "\n=== CHECKING ROLES TABLE ===\n";
$stmt = $pdo->query('SELECT * FROM roles');
$roles = $stmt->fetchAll();

if (empty($roles)) {
    echo "❌ No roles found in the table!\n";
} else {
    echo "✅ Found " . count($roles) . " roles:\n";
    foreach ($roles as $role) {
        echo "ID: {$role['id']}, Name: {$role['name']}\n";
    }
}
?>

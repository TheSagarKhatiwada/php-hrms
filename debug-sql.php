<?php
// Debug SQL parsing
error_reporting(E_ALL);
ini_set('display_errors', 1);

$schemaFile = __DIR__ . '/schema/hrms_schema.sql';
echo "Reading SQL file: $schemaFile\n";

if (!file_exists($schemaFile)) {
    echo "ERROR: Schema file not found!\n";
    exit(1);
}

$sql = file_get_contents($schemaFile);
echo "File size: " . strlen($sql) . " bytes\n";

// Parse SQL statements like the installer does
$statements = [];

// Remove comments and normalize line endings
$sql = preg_replace('/--.*$/m', '', $sql);
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
$sql = str_replace(["\r\n", "\r"], "\n", $sql);

// Split by semicolons but be careful about semicolons in strings
$parts = preg_split('/;(?=(?:[^\'"]|[\'"][^\'"]*[\'"])*$)/', $sql);

foreach ($parts as $part) {
    $statement = trim($part);
    
    // Skip empty statements
    if (empty($statement)) {
        continue;
    }
    
    // Skip SET statements and other MySQL-specific commands we don't need
    if (preg_match('/^\s*(SET|START TRANSACTION|COMMIT|AUTOCOMMIT)/i', $statement)) {
        continue;
    }
    
    $statements[] = $statement;
}

echo "Found " . count($statements) . " SQL statements\n";

if (count($statements) > 0) {
    echo "First statement preview:\n";
    echo substr($statements[0], 0, 200) . "...\n";
    
    echo "\nAll statements:\n";
    foreach ($statements as $i => $stmt) {
        echo ($i + 1) . ". " . substr(trim($stmt), 0, 80) . "...\n";
    }
} else {
    echo "No statements found. File content preview:\n";
    echo substr($sql, 0, 500) . "...\n";
}
?>

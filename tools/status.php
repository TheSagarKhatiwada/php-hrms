<?php
require_once __DIR__ . '/../includes/MigrationManager.php';
$mm = new MigrationManager();
$status = $mm->getStatus();
print_r($status);

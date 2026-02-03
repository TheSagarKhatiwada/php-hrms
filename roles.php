<?php
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/utilities.php';

if (!is_logged_in()) {
    header('Location: index.php');
    exit();
}

$_SESSION['info'] = 'Role management now lives on the Roles & Permissions page.';
header('Location: permissions.php');
exit();
?>

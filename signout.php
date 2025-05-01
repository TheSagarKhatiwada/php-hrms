<?php
// Include session configuration before starting any session
require_once 'includes/session_config.php';

session_start();

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?>
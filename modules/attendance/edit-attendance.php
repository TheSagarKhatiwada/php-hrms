<?php
session_start();

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Location: attendance.php');
    exit();
}

// Redirect to attendance.php with the edit parameter
// The edit modal will be handled by the attendance.php page
header('Location: attendance.php?edit=' . urlencode($_GET['id']));
exit();

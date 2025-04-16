<?php
$servername = "localhost"; // Change this to your server name
$username = "root"; // Change this to your database username
$password = ""; // Change this to your database password
$dbname = "hrms"; // Change this to your database name

try {
    $dsn = "mysql:host=$servername;dbname=$dbname";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Log the error message to a file (optional)
    error_log($e->getMessage(), 3, 'error_log.txt');

    // Display a generic error message to the user
    die("Connection failed. Please try again later.");
}
?>
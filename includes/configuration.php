<?php
// Define the base URL
$home = 'http://hrms.localhost/'; // Updated base URL

// Ensure timezone is loaded before any date usage
require_once __DIR__ . '/settings.php';

// Get the current date and time in the defined timezone
$currentDateTime = date('Y-m-d H:i:s');
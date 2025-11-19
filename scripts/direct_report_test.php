<?php
/**
 * Script to test report generator directly by requiring it and simulating a POST request
 */
// First initialize all includes the report generator expects
require_once __DIR__ . '/../includes/session_config.php';  // First include this since report needs it
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings.php';

// Simulate a POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'report_type' => 'daily',
    'selected_date' => '2025-10-29',  // During override period
    'branch_id' => '',
    'emp_id' => '101'                 // Employee with override
];

// Now require the report generator
require_once __DIR__ . '/../api/generate-attendance-report.php';
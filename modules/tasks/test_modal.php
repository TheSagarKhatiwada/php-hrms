<?php
// Simple test page to verify modal functionality
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once 'task_helpers.php';

// Mock user session for testing
$_SESSION['user_id'] = 1;

$current_user_id = $_SESSION['user_id'];

// Get user details
$stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
$stmt->execute([$current_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found. Please check your database.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Task Modal</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Test Task Modal</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="fas fa-plus me-2"></i>Test Create Task Modal
        </button>
        
        <div id="console-log" class="mt-4">
            <h3>Console Output:</h3>
            <pre id="console-output" style="background: #f8f9fa; padding: 1rem; border-radius: 0.375rem;"></pre>
        </div>
    </div>

    <?php 
    // Include the create task modal
    require_once 'create_task_modal.php';
    ?>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Capture console logs and display them on the page
    (function() {
        const originalLog = console.log;
        const originalWarn = console.warn;
        const originalError = console.error;
        const output = document.getElementById('console-output');
        
        function addToOutput(type, message) {
            const timestamp = new Date().toLocaleTimeString();
            output.textContent += `[${timestamp}] ${type}: ${message}\n`;
            output.scrollTop = output.scrollHeight;
        }
        
        console.log = function(...args) {
            originalLog.apply(console, args);
            addToOutput('LOG', args.join(' '));
        };
        
        console.warn = function(...args) {
            originalWarn.apply(console, args);
            addToOutput('WARN', args.join(' '));
        };
        
        console.error = function(...args) {
            originalError.apply(console, args);
            addToOutput('ERROR', args.join(' '));
        };
    })();
    </script>
</body>
</html>

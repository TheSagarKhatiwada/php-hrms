<?php
/**
 * Leave Module Testing Script
 * Comprehensive test suite for the Leave Management System
 */

session_start();
include_once '../../includes/config.php';
include_once '../../includes/db_connection.php';
include_once '../../includes/utilities.php';

// Check if user is logged in and is admin/HR
if (!isset($_SESSION['user_id']) || !is_admin()) {
    header("Location: ../../index.php");
    exit();
}

$test_results = [];
$errors = [];

// Test 1: Database Tables
function testDatabaseTables($pdo) {
    global $test_results, $errors;
    
    $tables = ['leave_requests', 'leave_types', 'leave_balances'];
    $table_results = [];
    
    foreach ($tables as $table) {
        $query = "SHOW TABLES LIKE '$table'";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $table_results[$table] = 'EXISTS';
            
            // Check table structure
            $structure_query = "DESCRIBE $table";
            $structure_stmt = $pdo->prepare($structure_query);
            $structure_stmt->execute();
            $columns = [];
            while ($row = $structure_stmt->fetch()) {
                $columns[] = $row['Field'];
            }
            $table_results[$table . '_columns'] = $columns;
        } else {
            $table_results[$table] = 'MISSING';
            $errors[] = "Table $table is missing";
        }
    }
    
    $test_results['database_tables'] = $table_results;
    return count($errors) == 0;
}

// Test 2: Leave Types Data
function testLeaveTypesData($pdo) {
    global $test_results, $errors;
    
    $query = "SELECT COUNT(*) as count FROM leave_types WHERE status = 'active'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $count = $stmt->fetch()['count'];
    
    $test_results['leave_types_count'] = $count;
    
    if ($count == 0) {
        $errors[] = "No active leave types found";
        return false;
    }
    
    return true;
}

// Test 3: Module Files
function testModuleFiles() {
    global $test_results, $errors;
    
    $required_files = [
        'index.php',
        'request.php',
        'my-requests.php',
        'requests.php',
        'balance.php',
        'types.php',
        'calendar.php',
        'reports.php',
        'approve.php',
        'reject.php',
        'view.php',
        'cancel-request.php',
        'notifications.php',
        'config.php'
    ];
    
    $file_results = [];
    
    foreach ($required_files as $file) {
        if (file_exists($file)) {
            $file_results[$file] = 'EXISTS';
        } else {
            $file_results[$file] = 'MISSING';
            $errors[] = "File $file is missing";
        }
    }
    
    $test_results['module_files'] = $file_results;
    return count($errors) == 0;
}

// Test 4: Configuration
function testConfiguration() {
    global $test_results, $errors;
    
    $config_tests = [
        'LEAVE_MODULE_VERSION' => defined('LEAVE_MODULE_VERSION'),
        'DEFAULT_LEAVE_BALANCE_ANNUAL' => defined('DEFAULT_LEAVE_BALANCE_ANNUAL'),
        'SEND_EMAIL_NOTIFICATIONS' => defined('SEND_EMAIL_NOTIFICATIONS'),
        'ALLOWED_FILE_TYPES' => defined('ALLOWED_FILE_TYPES')
    ];
    
    $test_results['configuration'] = $config_tests;
    
    foreach ($config_tests as $constant => $exists) {
        if (!$exists) {
            $errors[] = "Configuration constant $constant is not defined";
        }
    }
    
    return count($errors) == 0;
}

// Test 5: Employee Leave Balances
function testLeaveBalances($pdo) {
    global $test_results, $errors;
    
    $year = date('Y');
    $query = "SELECT COUNT(*) as count FROM leave_balances WHERE year = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$year]);
    $count = $stmt->fetch()['count'];
    
    $test_results['leave_balances_count'] = $count;
    
    // Get employee count
    $emp_query = "SELECT COUNT(*) as count FROM employees WHERE status = 'active'";
    $emp_stmt = $pdo->prepare($emp_query);
    $emp_stmt->execute();
    $emp_count = $emp_stmt->fetch()['count'];
    
    $test_results['active_employees_count'] = $emp_count;
    
    if ($count == 0 && $emp_count > 0) {
        $errors[] = "No leave balances found for current year. Run setup.php to create initial balances.";
        return false;
    }
    
    return true;
}

// Test 6: Sample Leave Request
function testLeaveRequestFlow($pdo) {
    global $test_results, $errors;
    
    // Check if we can create a sample request (without actually creating it)
    $user_id = $_SESSION['user_id'];
    
    // Get user's leave types
    $types_query = "SELECT id FROM leave_types WHERE status = 'active' LIMIT 1";
    $types_stmt = $pdo->prepare($types_query);
    $types_stmt->execute();
    
    if ($types_stmt->rowCount() > 0) {
        $leave_type = $types_stmt->fetch();
        $test_results['sample_request_possible'] = true;
    } else {
        $test_results['sample_request_possible'] = false;
        $errors[] = "Cannot create sample request - no leave types available";
        return false;
    }
    
    return true;
}

// Run all tests
include_once 'config.php';

$all_passed = true;

$all_passed &= testDatabaseTables($pdo);
$all_passed &= testLeaveTypesData($pdo);
$all_passed &= testModuleFiles();
$all_passed &= testConfiguration();
$all_passed &= testLeaveBalances($pdo);
$all_passed &= testLeaveRequestFlow($pdo);

include_once '../../includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Leave Module Testing</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Leave Module</a></li>
                        <li class="breadcrumb-item active">Testing</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Overall Status -->
            <div class="row">
                <div class="col-12">
                    <div class="alert <?php echo $all_passed ? 'alert-success' : 'alert-warning'; ?> alert-dismissible">
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        <h5><i class="icon fas <?php echo $all_passed ? 'fa-check' : 'fa-exclamation-triangle'; ?>"></i> 
                            <?php echo $all_passed ? 'All Tests Passed!' : 'Some Issues Found'; ?>
                        </h5>
                        <?php if (!$all_passed): ?>
                            <p>Please review the test results below and fix any issues.</p>
                        <?php else: ?>
                            <p>Your Leave Module is ready to use!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Errors -->
            <?php if (count($errors) > 0): ?>
                <div class="card card-danger">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-exclamation-circle"></i> Issues Found</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <?php foreach ($errors as $error): ?>
                                <li><i class="fas fa-times text-danger"></i> <?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Test Results -->
            <div class="row">
                <!-- Database Tests -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-database"></i> Database Tests</h3>
                        </div>
                        <div class="card-body">
                            <h6>Tables:</h6>
                            <ul class="list-group list-group-flush">
                                <?php foreach (['leave_requests', 'leave_types', 'leave_balances'] as $table): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?php echo $table; ?></span>
                                        <span class="badge <?php echo $test_results['database_tables'][$table] == 'EXISTS' ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $test_results['database_tables'][$table]; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <h6 class="mt-3">Data:</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Active Leave Types</span>
                                    <span class="badge badge-info"><?php echo $test_results['leave_types_count']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Active Employees</span>
                                    <span class="badge badge-info"><?php echo $test_results['active_employees_count']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Leave Balances (<?php echo date('Y'); ?>)</span>
                                    <span class="badge badge-info"><?php echo $test_results['leave_balances_count']; ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- File Tests -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-file-code"></i> Module Files</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php 
                                $file_chunks = array_chunk($test_results['module_files'], ceil(count($test_results['module_files']) / 2), true);
                                foreach ($file_chunks as $chunk_index => $chunk): 
                                ?>
                                    <div class="col-6">
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($chunk as $file => $status): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                                                    <span style="font-size: 0.8em;"><?php echo $file; ?></span>
                                                    <span class="badge <?php echo $status == 'EXISTS' ? 'badge-success' : 'badge-danger'; ?> badge-sm">
                                                        <?php echo $status == 'EXISTS' ? '✓' : '✗'; ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuration & Functionality Tests -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cog"></i> Configuration</h3>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($test_results['configuration'] as $config => $status): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?php echo $config; ?></span>
                                        <span class="badge <?php echo $status ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $status ? 'DEFINED' : 'MISSING'; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-play-circle"></i> Functionality</h3>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Leave Request Flow</span>
                                    <span class="badge <?php echo $test_results['sample_request_possible'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $test_results['sample_request_possible'] ? 'READY' : 'NOT READY'; ?>
                                    </span>
                                </li>
                            </ul>
                            
                            <?php if ($all_passed): ?>
                                <div class="mt-3">
                                    <h6>Quick Actions:</h6>
                                    <div class="btn-group-vertical w-100">
                                        <a href="index.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                                        </a>
                                        <a href="request.php" class="btn btn-success btn-sm">
                                            <i class="fas fa-plus"></i> Test Leave Request
                                        </a>
                                        <a href="setup.php" class="btn btn-warning btn-sm">
                                            <i class="fas fa-cog"></i> Setup Leave Balances
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Details -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> Test Details</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <pre><?php echo json_encode($test_results, JSON_PRETTY_PRINT); ?></pre>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once '../../includes/footer.php'; ?>

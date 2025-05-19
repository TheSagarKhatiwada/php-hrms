<?php
/**
 * Database Migration Manager
 * 
 * This script manages database migrations for the HRMS application
 * It can be run from the command line or through a web interface
 */

// Check for PDO extension
if (!extension_loaded('pdo')) {
    $error_message = "Error: PDO extension is not loaded. Please enable it in your php.ini file.";
    $error_message .= "\nRun 'php migration_check.php' for more detailed information.";
    
    if (php_sapi_name() === 'cli') {
        die($error_message . "\n");
    } else {
        die($error_message);
    }
}

// Check for PDO MySQL driver
if (!extension_loaded('pdo_mysql')) {
    $error_message = "Error: PDO MySQL driver is not loaded. Please enable it in your php.ini file.";
    $error_message .= "\nRun 'php migration_check.php' for more detailed information.";
    
    if (php_sapi_name() === 'cli') {
        die($error_message . "\n");
    } else {
        die($error_message);
    }
}

// Include necessary files
require_once __DIR__ . '/includes/db_connection.php';

// Check for database connection
if (!isset($pdo) || !$pdo) {
    $error_message = "Error: Database connection failed. Please check your database configuration.";
    $error_message .= "\nRun 'php migration_check.php' for more detailed information.";
    
    if (php_sapi_name() === 'cli') {
        die($error_message . "\n");
    } else {
        die($error_message);
    }
}

// Set up variables
$migrations_dir = __DIR__ . '/migrations';
$is_cli = php_sapi_name() === 'cli';

// Function to get all migrations from the directory
function get_available_migrations($dir) {
    $migrations = [];
    $files = scandir($dir);
    
    foreach ($files as $file) {
        // Skip migration tracking table and non-SQL files
        if ($file === '.' || $file === '..' || $file === 'migrations_table.sql' || !preg_match('/^\d{4}_.*\.sql$/', $file)) {
            continue;
        }
        
        $migrations[] = $file;
    }
    
    // Sort migrations by version number
    sort($migrations);
    return $migrations;
}

// Function to get already applied migrations
function get_applied_migrations($pdo) {
    try {
        // Check if migrations table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'db_migrations'");
        if ($stmt->rowCount() === 0) {
            // Create migrations table first
            $migration_table_sql = file_get_contents($GLOBALS['migrations_dir'] . '/migrations_table.sql');
            $pdo->exec($migration_table_sql);
            return [];
        }
        
        // Get the list of applied migrations
        $stmt = $pdo->query("SELECT migration FROM db_migrations ORDER BY id");
        $applied = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $applied[] = $row['migration'];
        }
        return $applied;
    } catch (PDOException $e) {
        die("Error getting applied migrations: " . $e->getMessage());
    }
}

// Function to get the current batch number
function get_current_batch($pdo) {
    try {
        $stmt = $pdo->query("SELECT MAX(batch) as current_batch FROM db_migrations");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['current_batch'] ?? 0);
    } catch (PDOException $e) {
        die("Error getting current batch: " . $e->getMessage());
    }
}

// Function to apply a migration
function apply_migration($pdo, $migration, $batch) {
    try {
        $pdo->beginTransaction();
        
        // Run the migration SQL
        $sql = file_get_contents($GLOBALS['migrations_dir'] . '/' . $migration);
        $pdo->exec($sql);
        
        // Record the migration
        $stmt = $pdo->prepare("INSERT INTO db_migrations (migration, batch) VALUES (?, ?)");
        $stmt->execute([$migration, $batch]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "Error applying migration {$migration}: " . $e->getMessage() . "\n";
        return false;
    }
}

// Function to roll back a batch of migrations
function rollback_migrations($pdo, $batch = null) {
    try {
        // If no batch is specified, get the latest batch
        if ($batch === null) {
            $batch = get_current_batch($pdo);
        }
        
        // Get migrations from the specified batch
        $stmt = $pdo->prepare("SELECT migration FROM db_migrations WHERE batch = ? ORDER BY id DESC");
        $stmt->execute([$batch]);
        
        $rolled_back = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $migration = $row['migration'];
            
            // Get the down SQL
            $sql = file_get_contents($GLOBALS['migrations_dir'] . '/' . $migration);
            // Extract the down SQL between -- DOWN and -- END DOWN
            if (preg_match('/-- DOWN(.*?)-- END DOWN/s', $sql, $matches)) {
                $down_sql = trim($matches[1]);
                
                // Execute the down SQL
                $pdo->beginTransaction();
                $pdo->exec($down_sql);
                
                // Remove the migration record
                $delete = $pdo->prepare("DELETE FROM db_migrations WHERE migration = ?");
                $delete->execute([$migration]);
                
                $pdo->commit();
                $rolled_back[] = $migration;
            } else {
                echo "No rollback SQL found for {$migration}\n";
            }
        }
        
        return $rolled_back;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "Error rolling back migrations: " . $e->getMessage() . "\n";
        return [];
    }
}

// Check if the migrations table exists, if not create it
function ensure_migrations_table_exists($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'db_migrations'");
        if ($stmt->rowCount() === 0) {
            $sql = file_get_contents($GLOBALS['migrations_dir'] . '/migrations_table.sql');
            $pdo->exec($sql);
            echo "Created migrations table.\n";
        }
    } catch (PDOException $e) {
        die("Error creating migrations table: " . $e->getMessage());
    }
}

// Function to create a new migration file
function create_migration($name) {
    global $migrations_dir;
    
    $version = date('YmdHis');
    $filename = $version . '_' . preg_replace('/[^a-z0-9_]/i', '_', strtolower($name)) . '.sql';
    $filepath = $migrations_dir . '/' . $filename;
    
    $template = <<<EOT
-- Migration: {$name}
-- Created at: {$version}

-- UP
-- Your schema changes go here

-- END UP

-- DOWN
-- How to reverse the changes (if possible)

-- END DOWN
EOT;

    if (file_put_contents($filepath, $template)) {
        return $filename;
    }
    
    return false;
}

// Main function to run migrations
function run_migrations($pdo) {
    global $migrations_dir;
    
    // Ensure migrations table exists
    ensure_migrations_table_exists($pdo);
    
    // Get available and applied migrations
    $available = get_available_migrations($migrations_dir);
    $applied = get_applied_migrations($pdo);
    
    // Calculate migrations to apply
    $to_apply = array_diff($available, $applied);
    
    if (empty($to_apply)) {
        return ["status" => "success", "message" => "No new migrations to apply."];
    }
    
    // Get the next batch number
    $batch = get_current_batch($pdo) + 1;
    
    // Apply each migration
    $applied_count = 0;
    $errors = [];
    
    foreach ($to_apply as $migration) {
        echo "Applying migration: {$migration}\n";
        if (apply_migration($pdo, $migration, $batch)) {
            $applied_count++;
        } else {
            $errors[] = $migration;
        }
    }
    
    if (empty($errors)) {
        return [
            "status" => "success", 
            "message" => "Applied {$applied_count} migrations successfully."
        ];
    } else {
        return [
            "status" => "error", 
            "message" => "Applied {$applied_count} migrations with errors.",
            "errors" => $errors
        ];
    }
}

// Check how the script is being run (CLI or web)
if ($is_cli) {
    // Running from CLI
    if ($argc < 2) {
        echo "Usage: php db_migrator.php [command] [options]\n";
        echo "Commands:\n";
        echo "  migrate           Run all pending migrations\n";
        echo "  rollback          Rollback the latest batch of migrations\n";
        echo "  create [name]     Create a new migration\n";
        echo "  status            Show migration status\n";
        exit(1);
    }
    
    $command = $argv[1];
    
    switch ($command) {
        case 'migrate':
            $result = run_migrations($pdo);
            echo $result['message'] . "\n";
            break;
            
        case 'rollback':
            $rolled_back = rollback_migrations($pdo);
            echo "Rolled back " . count($rolled_back) . " migrations.\n";
            break;
            
        case 'create':
            if ($argc < 3) {
                echo "Error: Migration name required.\n";
                echo "Usage: php db_migrator.php create [name]\n";
                exit(1);
            }
            $name = $argv[2];
            $file = create_migration($name);
            if ($file) {
                echo "Created migration: {$file}\n";
            } else {
                echo "Error creating migration.\n";
            }
            break;
            
        case 'status':
            $available = get_available_migrations($migrations_dir);
            $applied = get_applied_migrations($pdo);
            $pending = array_diff($available, $applied);
            
            echo "Applied migrations: " . count($applied) . "\n";
            foreach ($applied as $migration) {
                echo "  ✓ {$migration}\n";
            }
            
            echo "Pending migrations: " . count($pending) . "\n";
            foreach ($pending as $migration) {
                echo "  ○ {$migration}\n";
            }
            break;
            
        default:
            echo "Unknown command: {$command}\n";
            exit(1);
    }
} else {
    // Running from web
    // Only run if admin is logged in
    session_start();
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin privileges required.']);
        exit;
    }
    
    // Handle AJAX requests
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        switch ($_POST['action']) {
            case 'migrate':
                $result = run_migrations($pdo);
                echo json_encode($result);
                break;
                
            case 'rollback':
                $rolled_back = rollback_migrations($pdo);
                echo json_encode([
                    'status' => 'success',
                    'message' => "Rolled back " . count($rolled_back) . " migrations.",
                    'rolledBack' => $rolled_back
                ]);
                break;
                
            case 'create':
                if (!isset($_POST['name']) || empty($_POST['name'])) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Migration name is required.'
                    ]);
                    break;
                }
                
                $file = create_migration($_POST['name']);
                if ($file) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => "Created migration: {$file}",
                        'file' => $file
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Error creating migration file.'
                    ]);
                }
                break;
                
            case 'status':
                $available = get_available_migrations($migrations_dir);
                $applied = get_applied_migrations($pdo);
                $pending = array_diff($available, $applied);
                
                echo json_encode([
                    'status' => 'success',
                    'applied' => $applied,
                    'pending' => $pending
                ]);
                break;
                
            default:
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Unknown action.'
                ]);
        }
        exit;
    }
    
    // Display the migration management interface
    include('includes/header.php');
?>
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Database Migration Manager</h1>
        </div>
        
        <div class="row">
            <div class="col-lg-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Migration Status</h6>
                        <div class="dropdown no-arrow">
                            <button id="refreshStatus" class="btn btn-sm btn-primary shadow-sm">
                                <i class="fas fa-sync fa-sm text-white-50"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="migrationStatus">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Run Migrations</h6>
                    </div>
                    <div class="card-body">
                        <p>Apply all pending database migrations.</p>
                        <button id="runMigrations" class="btn btn-success btn-icon-split">
                            <span class="icon text-white-50">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                            <span class="text">Run Migrations</span>
                        </button>
                        <div id="migrateResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Rollback Migrations</h6>
                    </div>
                    <div class="card-body">
                        <p>Rollback the most recent batch of migrations.</p>
                        <button id="rollbackMigrations" class="btn btn-warning btn-icon-split">
                            <span class="icon text-white-50">
                                <i class="fas fa-arrow-left"></i>
                            </span>
                            <span class="text">Rollback Last Batch</span>
                        </button>
                        <div id="rollbackResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Create New Migration</h6>
                    </div>
                    <div class="card-body">
                        <form id="createMigrationForm" class="form-inline">
                            <div class="form-group mb-2 mr-2">
                                <label for="migrationName" class="sr-only">Migration Name</label>
                                <input type="text" class="form-control" id="migrationName" placeholder="Migration Name (e.g., add_users_table)" required>
                            </div>
                            <button type="submit" class="btn btn-primary mb-2">Create Migration</button>
                        </form>
                        <div id="createResult" class="mt-3"></div>
                        <div class="mt-4">
                            <h6 class="font-weight-bold">Migration File Format:</h6>
                            <pre><code>-- Migration: Example
-- Created at: 20250506120000

-- UP
-- Your schema changes go here

-- END UP

-- DOWN
-- How to reverse the changes (if possible)

-- END DOWN</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            // Function to load migration status
            function loadMigrationStatus() {
                $.post('db_migrator.php', {action: 'status'}, function(response) {
                    if (response.status === 'success') {
                        let html = '<div class="table-responsive">';
                        html += '<table class="table table-bordered" width="100%" cellspacing="0">';
                        html += '<thead><tr><th>Status</th><th>Migration</th></tr></thead>';
                        html += '<tbody>';
                        
                        // Applied migrations
                        if (response.applied.length > 0) {
                            $.each(response.applied, function(i, migration) {
                                html += '<tr><td><span class="badge badge-success">Applied</span></td><td>' + migration + '</td></tr>';
                            });
                        }
                        
                        // Pending migrations
                        if (response.pending.length > 0) {
                            $.each(response.pending, function(i, migration) {
                                html += '<tr><td><span class="badge badge-warning">Pending</span></td><td>' + migration + '</td></tr>';
                            });
                        }
                        
                        if (response.applied.length === 0 && response.pending.length === 0) {
                            html += '<tr><td colspan="2" class="text-center">No migrations found.</td></tr>';
                        }
                        
                        html += '</tbody></table></div>';
                        $('#migrationStatus').html(html);
                    } else {
                        $('#migrationStatus').html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                }).fail(function() {
                    $('#migrationStatus').html('<div class="alert alert-danger">Error connecting to server.</div>');
                });
            }
            
            // Load initial status
            loadMigrationStatus();
            
            // Refresh status
            $('#refreshStatus').click(function() {
                $('#migrationStatus').html('Loading...');
                loadMigrationStatus();
            });
            
            // Run migrations
            $('#runMigrations').click(function() {
                $(this).prop('disabled', true);
                $('#migrateResult').html('<div class="alert alert-info">Running migrations...</div>');
                
                $.post('db_migrator.php', {action: 'migrate'}, function(response) {
                    if (response.status === 'success') {
                        $('#migrateResult').html('<div class="alert alert-success">' + response.message + '</div>');
                    } else {
                        $('#migrateResult').html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                    $('#runMigrations').prop('disabled', false);
                    loadMigrationStatus();
                }).fail(function() {
                    $('#migrateResult').html('<div class="alert alert-danger">Error connecting to server.</div>');
                    $('#runMigrations').prop('disabled', false);
                });
            });
            
            // Rollback migrations
            $('#rollbackMigrations').click(function() {
                if (!confirm('Are you sure you want to rollback the last batch of migrations?')) {
                    return;
                }
                
                $(this).prop('disabled', true);
                $('#rollbackResult').html('<div class="alert alert-info">Rolling back migrations...</div>');
                
                $.post('db_migrator.php', {action: 'rollback'}, function(response) {
                    if (response.status === 'success') {
                        $('#rollbackResult').html('<div class="alert alert-success">' + response.message + '</div>');
                    } else {
                        $('#rollbackResult').html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                    $('#rollbackMigrations').prop('disabled', false);
                    loadMigrationStatus();
                }).fail(function() {
                    $('#rollbackResult').html('<div class="alert alert-danger">Error connecting to server.</div>');
                    $('#rollbackMigrations').prop('disabled', false);
                });
            });
            
            // Create migration
            $('#createMigrationForm').submit(function(e) {
                e.preventDefault();
                
                const name = $('#migrationName').val();
                if (!name) {
                    $('#createResult').html('<div class="alert alert-danger">Migration name is required.</div>');
                    return;
                }
                
                $('#createResult').html('<div class="alert alert-info">Creating migration...</div>');
                
                $.post('db_migrator.php', {action: 'create', name: name}, function(response) {
                    if (response.status === 'success') {
                        $('#createResult').html('<div class="alert alert-success">' + response.message + '</div>');
                        $('#migrationName').val('');
                    } else {
                        $('#createResult').html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                    loadMigrationStatus();
                }).fail(function() {
                    $('#createResult').html('<div class="alert alert-danger">Error connecting to server.</div>');
                });
            });
        });
    </script>
<?php
    include('includes/footer.php');
}
?>
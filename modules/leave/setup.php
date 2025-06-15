<?php
session_start();
include_once '../../includes/config.php';
include_once '../../includes/db_connection.php';
include_once '../../includes/utilities.php';

// Check if user is logged in and is admin/HR
if (!isset($_SESSION['user_id']) || !is_admin()) {
    header("Location: ../../index.php");
    exit();
}

if ($_POST && isset($_POST['setup_balances'])) {
    try {
        // Get all active employees
        $employees_query = "SELECT id FROM employees WHERE status = 'active'";
        $employees_stmt = $pdo->prepare($employees_query);
        $employees_stmt->execute();
        $employees = $employees_stmt->fetchAll();
        
        // Get all active leave types
        $types_query = "SELECT id, days_allowed FROM leave_types WHERE status = 'active'";
        $types_stmt = $pdo->prepare($types_query);
        $types_stmt->execute();
        $leave_types = $types_stmt->fetchAll();
        
        $year = date('Y');
        $balances_created = 0;
        
        foreach ($employees as $employee) {
            foreach ($leave_types as $type) {
                // Check if balance already exists
                $check_query = "SELECT id FROM leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?";
                $check_stmt = $pdo->prepare($check_query);
                $check_stmt->execute([$employee['id'], $type['id'], $year]);
                
                if ($check_stmt->rowCount() == 0) {
                    // Create balance record
                    $insert_query = "INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days) VALUES (?, ?, ?, ?, 0, ?)";
                    $insert_stmt = $pdo->prepare($insert_query);
                    if ($insert_stmt->execute([$employee['id'], $type['id'], $year, $type['days_allowed'], $type['days_allowed']])) {
                        $balances_created++;
                    }
                }
            }
        }
        
        $message = "Setup completed! Created $balances_created leave balance records.";
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

include_once '../../includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Leave Module Setup</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Leave Module</a></li>
                        <li class="breadcrumb-item active">Setup</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cog"></i> Initial Setup</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h5>Create Initial Leave Balances</h5>
                            <p class="text-muted">
                                This will create leave balance records for all active employees based on the configured leave types. 
                                If balances already exist for an employee and leave type, they will not be duplicated.
                            </p>
                              <h6>Current Leave Types:</h6>
                            <ul class="list-group list-group-flush">
                                <?php
                                $types_query = "SELECT name, days_allowed, color FROM leave_types WHERE status = 'active' ORDER BY name";
                                $types_stmt = $pdo->prepare($types_query);
                                $types_stmt->execute();
                                $leave_types_display = $types_stmt->fetchAll();
                                foreach ($leave_types_display as $type):
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>
                                            <span class="badge" style="background-color: <?php echo $type['color']; ?>; color: white;">
                                                <?php echo htmlspecialchars($type['name']); ?>
                                            </span>
                                        </span>
                                        <span class="badge badge-primary"><?php echo $type['days_allowed']; ?> days</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Ready to Setup?</h5>
                                    <p class="card-text">Click the button below to create initial leave balances for all employees.</p>
                                    <form method="POST">
                                        <button type="submit" name="setup_balances" class="btn btn-primary btn-lg">
                                            <i class="fas fa-play"></i> Setup Leave Balances
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <a href="index.php" class="btn btn-secondary btn-block">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Current Statistics -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-bar"></i> Current Statistics</h3>
                </div>
                <div class="card-body">
                    <div class="row">                        <?php
                        // Get current statistics
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
                        $stmt->execute();
                        $employees_count = $stmt->fetch()['count'];
                        
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_types WHERE status = 'active'");
                        $stmt->execute();
                        $leave_types_count = $stmt->fetch()['count'];
                        
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_balances WHERE year = ?");
                        $stmt->execute([date('Y')]);
                        $balances_count = $stmt->fetch()['count'];
                        
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE YEAR(created_at) = ?");
                        $stmt->execute([date('Y')]);
                        $requests_count = $stmt->fetch()['count'];
                        ?>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3><?php echo $employees_count; ?></h3>
                                    <p>Active Employees</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3><?php echo $leave_types_count; ?></h3>
                                    <p>Leave Types</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-tags"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3><?php echo $balances_count; ?></h3>
                                    <p>Balance Records</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-chart-pie"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3><?php echo $requests_count; ?></h3>
                                    <p>Requests This Year</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once '../../includes/footer.php'; ?>

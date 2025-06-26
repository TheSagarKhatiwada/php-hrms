<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Task Modal</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Debug Task Modal</h1>
        <button type="button" class="btn btn-primary" id="testModalBtn" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="fas fa-plus me-2"></i>Open Task Modal
        </button>
        
        <div class="mt-4">
            <h3>Debug Information:</h3>
            <div id="debug-info" style="background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; white-space: pre-line;"></div>
        </div>
    </div>

    <!-- Simplified Task Modal -->
    <div class="modal fade" id="createTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Create New Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="createTaskForm" method="POST" action="./create_task_handler.php" onsubmit="return false;">
                    <div class="modal-body">
                        <div id="taskFormAlert"></div>
                        
                        <div class="mb-3">
                            <label for="task_title" class="form-label">Task Title *</label>
                            <input type="text" class="form-control" id="task_title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="task_assigned_to" class="form-label">Assign To *</label>
                            <select class="form-select" id="task_assigned_to" name="assigned_to" required>
                                <option value="">Select assignee...</option>
                                <option value="1">Test User</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="task_priority" class="form-label">Priority</label>
                            <select class="form-select" id="task_priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="createTaskBtn">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function debugLog(message) {
        const timestamp = new Date().toLocaleTimeString();
        const debugDiv = document.getElementById('debug-info');
        debugDiv.textContent += `[${timestamp}] ${message}\n`;
        console.log(message);
    }

    $(document).ready(function() {
        debugLog('Document ready - jQuery loaded: ' + (typeof jQuery !== 'undefined'));
        debugLog('Bootstrap loaded: ' + (typeof bootstrap !== 'undefined'));
        
        // Test modal functionality
        $('#createTaskModal').on('shown.bs.modal', function() {
            debugLog('Modal opened successfully');
        });
        
        $('#createTaskModal').on('hidden.bs.modal', function() {
            debugLog('Modal closed');
        });
        
        // Handle create task button click
        $('#createTaskBtn').on('click', function(e) {
            debugLog('Create task button clicked');
            
            const title = $('#task_title').val().trim();
            const assignedTo = $('#task_assigned_to').val();
            
            debugLog('Title: "' + title + '"');
            debugLog('Assigned to: "' + assignedTo + '"');
            
            if (!title) {
                debugLog('ERROR: Title is required');
                $('#taskFormAlert').html('<div class="alert alert-warning">Please enter a task title.</div>');
                return;
            }
            
            if (!assignedTo) {
                debugLog('ERROR: Assignee is required');
                $('#taskFormAlert').html('<div class="alert alert-warning">Please select who to assign this task to.</div>');
                return;
            }
            
            debugLog('Starting AJAX request...');
            
            const submitBtn = $(this);
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Creating...');
            
            $.ajax({
                url: './create_task_handler.php',
                method: 'POST',
                data: $('#createTaskForm').serialize(),
                dataType: 'json',
                success: function(response) {
                    debugLog('AJAX Success: ' + JSON.stringify(response));
                    
                    if (response && response.success) {
                        $('#taskFormAlert').html('<div class="alert alert-success">' + response.message + '</div>');
                        $('#createTaskForm')[0].reset();
                        setTimeout(() => $('#createTaskModal').modal('hide'), 2000);
                    } else {
                        $('#taskFormAlert').html('<div class="alert alert-danger">' + (response.message || 'Unknown error') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    debugLog('AJAX Error: ' + error);
                    debugLog('Status: ' + xhr.status);
                    debugLog('Response: ' + xhr.responseText);
                    $('#taskFormAlert').html('<div class="alert alert-danger">Request failed: ' + error + '</div>');
                },
                complete: function() {
                    debugLog('AJAX Complete');
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });
        
        debugLog('Event handlers attached');
    });
    </script>
</body>
</html>

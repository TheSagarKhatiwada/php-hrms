<?php
// This file contains the reusable task creation modal
// Should be included after the main content and before footer

// Get employees for assignment dropdown if not already loaded
if (!isset($assignable_employees)) {
    try {
        // Get current user's subordinates and colleagues
        $assignable_employees = getAssignableEmployees($pdo, $current_user_id);
    } catch (Exception $e) {
        $assignable_employees = [];
    }
}

// Get task categories if not already loaded
if (!isset($task_categories)) {
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT category FROM tasks WHERE category IS NOT NULL AND category != '' ORDER BY category");
        $stmt->execute();
        $task_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $task_categories = ['Development', 'Marketing', 'HR', 'Sales', 'Support', 'Management'];
    }
}
?>

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="createTaskModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Create New Task
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createTaskForm" method="POST" action="./create_task_handler.php" novalidate onsubmit="return false;">
                <div class="modal-body">
                    <div id="taskFormAlert"></div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="task_title" class="form-label fw-bold">
                                <i class="fas fa-tasks me-1"></i>Task Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="task_title" name="title" 
                                   placeholder="Enter task title..." required maxlength="255">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="task_description" class="form-label fw-bold">
                                <i class="fas fa-align-left me-1"></i>Description
                            </label>
                            <textarea class="form-control" id="task_description" name="description" 
                                      rows="4" placeholder="Enter task description..."></textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="task_type" class="form-label fw-bold">
                                <i class="fas fa-list me-1"></i>Task Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="task_type" name="task_type" required>
                                <option value="assigned">📋 Direct Assignment - Assign to specific person</option>
                                <option value="open">🌟 Open Task - Anyone can assign to themselves</option>
                                <option value="department">🏢 Department Task - For my department members</option>
                            </select>
                        </div>
                    </div>

                    <div class="row" id="assignment_row">
                        <div class="col-md-6 mb-3" id="assigned_to_field">
                            <label for="task_assigned_to" class="form-label fw-bold">
                                <i class="fas fa-user me-1"></i>Assign To <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="task_assigned_to" name="assigned_to">
                                <option value="">Select assignee...</option>
                                <option value="<?php echo $current_user_id; ?>">
                                    👤 Myself (<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>)
                                </option>
                                <?php if (!empty($assignable_employees)): ?>
                                    <?php 
                                    $current_dept_employees = [];
                                    $subordinates = [];
                                    $others = [];
                                    
                                    foreach ($assignable_employees as $employee) {
                                        if ($employee['emp_id'] == $current_user_id) continue;
                                        
                                        if ($employee['is_subordinate']) {
                                            $subordinates[] = $employee;
                                        } elseif ($employee['department_id'] == $user['department_id']) {
                                            $current_dept_employees[] = $employee;
                                        } else {
                                            $others[] = $employee;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if (!empty($subordinates)): ?>
                                        <optgroup label="👥 My Subordinates">
                                            <?php foreach ($subordinates as $employee): ?>
                                                <option value="<?php echo $employee['emp_id']; ?>">
                                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                    <?php if ($employee['designation_title']): ?>
                                                        (<?php echo htmlspecialchars($employee['designation_title']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($current_dept_employees)): ?>
                                        <optgroup label="🏢 My Department">
                                            <?php foreach ($current_dept_employees as $employee): ?>
                                                <option value="<?php echo $employee['emp_id']; ?>">
                                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                    <?php if ($employee['designation_title']): ?>
                                                        (<?php echo htmlspecialchars($employee['designation_title']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($others)): ?>
                                        <optgroup label="👤 Other Employees">
                                            <?php foreach ($others as $employee): ?>
                                                <option value="<?php echo $employee['emp_id']; ?>">
                                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                    <?php if ($employee['designation_title']): ?>
                                                        (<?php echo htmlspecialchars($employee['designation_title']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3" id="target_info" style="display:none;">
                            <label class="form-label fw-bold">
                                <i class="fas fa-info-circle me-1"></i>Target Information
                            </label>
                            <div class="alert alert-info mb-0">
                                <div id="target_description"></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="task_priority" class="form-label fw-bold">
                                <i class="fas fa-flag me-1"></i>Priority
                            </label>
                            <select class="form-select" id="task_priority" name="priority">
                                <option value="low">🟢 Low</option>
                                <option value="medium" selected>🟡 Medium</option>
                                <option value="high">🟠 High</option>
                                <option value="urgent">🔴 Urgent</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="task_due_date" class="form-label fw-bold">
                                <i class="fas fa-calendar me-1"></i>Due Date
                            </label>
                            <input type="date" class="form-control" id="task_due_date" name="due_date" 
                                   min="<?php echo date('Y-m-d'); ?>">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>Leave empty if no specific deadline
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="task_category" class="form-label fw-bold">
                                <i class="fas fa-tag me-1"></i>Category
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="task_category" name="category" 
                                       placeholder="Enter or select category..." list="categoryList">
                                <datalist id="categoryList">
                                    <?php foreach ($task_categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                    </div>

                    <!-- Optional: Add attachment support -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="task_notes" class="form-label fw-bold">
                                <i class="fas fa-sticky-note me-1"></i>Additional Notes
                            </label>
                            <textarea class="form-control" id="task_notes" name="notes" 
                                      rows="2" placeholder="Any additional notes or instructions..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="createTaskBtn">
                        <i class="fas fa-plus me-1"></i>Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#createTaskModal .modal-dialog {
    max-width: 800px;
}

#createTaskModal .form-label {
    color: #495057;
    margin-bottom: 0.5rem;
}

#createTaskModal .form-control,
#createTaskModal .form-select {
    border-radius: 0.375rem;
    border: 1px solid #ced4da;
    padding: 0.75rem 1rem;
}

#createTaskModal .form-control:focus,
#createTaskModal .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
}

#createTaskModal .input-group .form-control {
    border-right: 0;
}

#createTaskModal .btn {
    padding: 0.75rem 1.5rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    #createTaskModal .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
}
</style>

<script>
// Ensure this script runs after all dependencies are loaded
(function() {
    let modalInitialized = false;
    
    function initializeTaskModal() {
        // Prevent double initialization
        if (modalInitialized) {
            return;
        }
        
        // Check if jQuery and Bootstrap are available
        if (typeof jQuery === 'undefined') {
            console.warn('jQuery not loaded, retrying in 100ms...');
            setTimeout(initializeTaskModal, 100);
            return;
        }
        
        if (typeof bootstrap === 'undefined') {
            console.warn('Bootstrap not loaded, retrying in 100ms...');
            setTimeout(initializeTaskModal, 100);
            return;
        }
        
        $(document).ready(function() {
            console.log('Task modal initializing...');
            modalInitialized = true;
            
            // Debug: Check if modal elements exist
            console.log('Modal elements check:');
            console.log('- Modal div:', $('#createTaskModal').length);
            console.log('- Form:', $('#createTaskForm').length);
            console.log('- Title input:', $('#task_title').length);
            console.log('- Assigned to select:', $('#task_assigned_to').length);
            console.log('- Create button:', $('#createTaskBtn').length);
            
            // Remove any existing event handlers to prevent duplicates
            $('#createTaskForm').off('submit.taskModal');
            $('#createTaskBtn').off('click.taskModal');
            $('#createTaskModal').off('hidden.bs.modal.taskModal');
            $('#task_priority').off('change.taskModal');
            
            // Handle create task button click
            $('#createTaskBtn').on('click.taskModal', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Create task button clicked');
                
                const form = $('#createTaskForm');
                const submitBtn = $(this);
                const originalText = submitBtn.html();
                
                // Clear any previous alerts
                $('#taskFormAlert').empty();
                
                // Get form values directly
                const title = $.trim($('#task_title').val() || '');
                const taskType = $('#task_type').val() || 'assigned';
                const assignedTo = $('#task_assigned_to').val() || '';
                
                console.log('Form validation:');
                console.log('- Title:', JSON.stringify(title));
                console.log('- Task type:', JSON.stringify(taskType));
                console.log('- Assigned to:', JSON.stringify(assignedTo));
                console.log('- Form data:', form.serialize());
                
                // Simple validation
                if (title === '') {
                    console.log('Validation failed: Empty title');
                    $('#taskFormAlert').html(`
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>Please enter a task title.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    $('#task_title').focus();
                    return false;
                }
                
                // Only validate assigned_to for direct assignment tasks
                if (taskType === 'assigned' && assignedTo === '') {
                    console.log('Validation failed: No assignee selected for direct assignment');
                    $('#taskFormAlert').html(`
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>Please select who to assign this task to.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    $('#task_assigned_to').focus();
                    return false;
                }
                
                console.log('Validation passed, submitting...');
                
                // Disable form
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Creating...');
                form.find('input, select, textarea').prop('disabled', true);
                
                // Submit via AJAX
                $.ajax({
                    url: 'create_task_handler.php',
                    method: 'POST',
                    data: form.serialize() + '&debug=1',
                    dataType: 'json',
                    timeout: 15000,
                    beforeSend: function() {
                        console.log('Sending AJAX request to: create_task_handler.php');
                        console.log('Form data:', form.serialize());
                        console.log('Current location:', window.location.href);
                    }
                })
                .done(function(response) {
                    console.log('AJAX Success:', response);
                    if (response && response.success) {
                        $('#taskFormAlert').html(`
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>${response.message || 'Task created successfully!'}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `);
                        form[0].reset();
                        setTimeout(function() {
                            $('#createTaskModal').modal('hide');
                            location.reload();
                        }, 1500);
                    } else {
                        $('#taskFormAlert').html(`
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>${response ? response.message : 'Unknown error occurred'}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `);
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('AJAX Error Details:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error,
                        readyState: xhr.readyState
                    });
                    
                    let errorMsg = 'Network error occurred. ';
                    if (xhr.status === 404) {
                        errorMsg = 'Handler file not found (404). ';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Server error (500). ';
                    } else if (xhr.status === 403) {
                        errorMsg = 'Access denied (403). ';
                    }
                    
                    $('#taskFormAlert').html(`
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>${errorMsg}Status: ${xhr.status}. Please try again.
                            <br><small>Details: ${error}</small>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                })
                .always(function() {
                    submitBtn.prop('disabled', false).html(originalText);
                    form.find('input, select, textarea').prop('disabled', false);
                });
                
                return false;
            });
            
            // Reset form when modal is hidden
            $('#createTaskModal').on('hidden.bs.modal.taskModal', function() {
                const form = $('#createTaskForm')[0];
                if (form) {
                    form.reset();
                }
                $('#taskFormAlert').empty();
                
                // Re-enable all form elements
                $('#createTaskForm').find('input, select, textarea, button').prop('disabled', false);
                $('#createTaskBtn').html('<i class="fas fa-plus me-1"></i>Create Task');
            });
            
            // Auto-set due date to next week when priority is urgent
            $('#task_priority').on('change.taskModal', function() {
                if ($(this).val() === 'urgent' && !$('#task_due_date').val()) {
                    const nextWeek = new Date();
                    nextWeek.setDate(nextWeek.getDate() + 7);
                    $('#task_due_date').val(nextWeek.toISOString().split('T')[0]);
                }
            });
            
            // Handle task type changes
            $('#task_type').on('change.taskModal', function() {
                const taskType = $(this).val();
                const assignedToField = $('#assigned_to_field');
                const targetInfo = $('#target_info');
                const targetDescription = $('#target_description');
                const assignedToSelect = $('#task_assigned_to');
                
                console.log('Task type changed to:', taskType);
                
                switch(taskType) {
                    case 'assigned':
                        assignedToField.show();
                        targetInfo.hide();
                        assignedToSelect.prop('required', true);
                        assignedToSelect.find('option[value=""]').text('Select assignee...');
                        break;
                        
                    case 'open':
                        assignedToField.hide();
                        targetInfo.show();
                        assignedToSelect.prop('required', false);
                        assignedToSelect.val('');
                        targetDescription.html(`
                            <strong>🌟 Open Task</strong><br>
                            Anyone with access can assign this task to themselves from the task lists.
                        `);
                        break;
                        
                    case 'department':
                        assignedToField.hide();
                        targetInfo.show();
                        assignedToSelect.prop('required', false);
                        assignedToSelect.val('');
                        targetDescription.html(`
                            <strong>🏢 Department Task</strong><br>
                            Members of your department can assign this task to themselves from their team task list.
                        `);
                        break;
                }
            });
            
            // Initialize task type handling
            $('#task_type').trigger('change.taskModal');
            
            console.log('Task modal initialized successfully');
        });
    }
    
    // Start initialization
    initializeTaskModal();
})();

// Function to refresh task lists (can be called from different pages)
function refreshTaskList() {
    // Check if we're on a page with DataTables and refresh them
    if (typeof $.fn.DataTable !== 'undefined') {
        // Refresh all DataTables on the page
        $.fn.dataTable.tables({ visible: true, api: true }).ajax.reload();
    }
    
    // For pages without DataTables, just reload the page
    setTimeout(function() {
        location.reload();
    }, 500);
}
</script>

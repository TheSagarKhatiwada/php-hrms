<?php
require_once __DIR__ . '/../../includes/db_connection.php';

// Set page title for navigation
$page = 'SMS Templates';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/SparrowSMS.php';

$sms = new SparrowSMS();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {                case 'create_template':
                    $stmt = $pdo->prepare("
                        INSERT INTO sms_templates (name, subject, content, variables, category, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    $variables = array_filter(explode(',', $_POST['variables']));
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['subject'],
                        $_POST['message'],
                        json_encode($variables),
                        $_POST['category'],
                        $_SESSION['user_id']
                    ]);
                    
                    $_SESSION['success'] = "SMS template created successfully!";
                    break;
                      case 'update_template':
                    $stmt = $pdo->prepare("
                        UPDATE sms_templates 
                        SET name = ?, subject = ?, content = ?, variables = ?, category = ?, is_active = ?
                        WHERE id = ?
                    ");
                    
                    $variables = array_filter(explode(',', $_POST['variables']));
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['subject'],
                        $_POST['message'],
                        json_encode($variables),
                        $_POST['category'],
                        isset($_POST['is_active']) ? 1 : 0,
                        $_POST['template_id']
                    ]);
                    
                    $_SESSION['success'] = "SMS template updated successfully!";
                    break;
                    
                case 'delete_template':
                    $stmt = $pdo->prepare("DELETE FROM sms_templates WHERE id = ?");
                    $stmt->execute([$_POST['template_id']]);
                    
                    $_SESSION['success'] = "SMS template deleted successfully!";
                    break;
                    
                case 'send_template':
                    $templateId = $_POST['template_id'];
                    $phoneNumber = $_POST['phone_number'];
                    $variables = $_POST['variables'] ?? [];
                    
                    // Get template
                    $stmt = $pdo->prepare("SELECT * FROM sms_templates WHERE id = ?");
                    $stmt->execute([$templateId]);
                    $template = $stmt->fetch(PDO::FETCH_ASSOC);
                      if ($template) {
                        $message = $template['content'];
                        $templateVars = json_decode($template['variables'], true) ?? [];
                        
                        // Replace variables in message
                        foreach ($templateVars as $var) {
                            $placeholder = '{' . $var . '}';
                            $value = $variables[$var] ?? '';
                            $message = str_replace($placeholder, $value, $message);
                        }
                        
                        // Send SMS
                        $result = $sms->sendSMS($phoneNumber, $message);
                        
                        if ($result['success']) {                            $_SESSION['success'] = "SMS sent successfully using template!";
                        } else {
                            $_SESSION['error'] = "Failed to send SMS: " . $result['error'];
                        }
                    } else {
                        $_SESSION['error'] = "Template not found!";
                    }
                    break;
            }
        } catch (PDOException $e) {            $_SESSION['error'] = "Database error: " . $e->getMessage();        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get all templates
try {
    // First check if table exists
    $tableExists = false;
    try {
        $checkStmt = $pdo->query("SHOW TABLES LIKE 'sms_templates'");
        $tableExists = $checkStmt->rowCount() > 0;
    } catch (PDOException $e) {
        $tableExists = false;
    }
    
    if (!$tableExists) {
        $templates = [];
        $_SESSION['error'] = "SMS templates table not found. Please run the SMS module setup first.";
    } else {
        $stmt = $pdo->prepare("
            SELECT t.*, e.first_name as created_by_name
            FROM sms_templates t 
            LEFT JOIN employees e ON t.created_by = e.emp_id 
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $templates = [];
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

// Get template categories
$categories = ['attendance', 'payroll', 'general', 'alerts', 'reminders'];
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-file-alt me-2"></i>SMS Templates</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                    <i class="fas fa-plus me-1"></i>Create Template
                </button>
                <a href="sms-dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                </a>
            </div>
        </div>
    </div>            <!-- Templates Grid -->
            <div class="row">
                <?php foreach ($templates as $template): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 <?php echo $template['is_active'] ? '' : 'opacity-75'; ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">
                                <?php echo htmlspecialchars($template['name']); ?>
                            </h6>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="editTemplate(<?php echo $template['id']; ?>)">
                                            <i class="fas fa-edit me-2"></i>Edit
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="#" onclick="deleteTemplate(<?php echo $template['id']; ?>)">
                                            <i class="fas fa-trash me-2"></i>Delete
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <span class="badge bg-<?php 
                                    echo match($template['category']) {
                                        'attendance' => 'primary',
                                        'payroll' => 'success',
                                        'alerts' => 'warning',
                                        'reminders' => 'info',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($template['category']); ?>
                                </span>
                                <?php if (!$template['is_active']): ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </div>
                            
                            <h6 class="card-subtitle mb-2 text-muted">
                                <?php echo htmlspecialchars($template['subject']); ?>
                            </h6>
                            
                            <p class="card-text small">
                                <?php echo htmlspecialchars(substr($template['content'], 0, 100)) . (strlen($template['content']) > 100 ? '...' : ''); ?>
                            </p>
                            
                            <?php
                            $variables = json_decode($template['variables'], true) ?? [];
                            if (!empty($variables)):
                            ?>                            <div class="mb-2">
                                <small class="text-muted">Variables:</small><br>
                                <?php foreach ($variables as $var): ?>
                                    <span class="badge bg-secondary me-1">{<?php echo $var; ?>}</span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <small class="text-muted">
                                Created: <?php echo date('M d, Y', strtotime($template['created_at'])); ?>
                                <?php if ($template['created_by_name']): ?>
                                    by <?php echo htmlspecialchars($template['created_by_name']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-primary flex-fill" 
                                        onclick="useTemplate(<?php echo $template['id']; ?>)">
                                    <i class="fas fa-paper-plane me-1"></i>Use Template
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                        onclick="previewTemplate(<?php echo $template['id']; ?>)">
                                    <i class="fas fa-eye me-1"></i>Preview
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($templates)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No SMS Templates Found</h5>
                <p class="text-muted">Create your first SMS template to get started.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                    <i class="fas fa-plus me-1"></i>Create First Template
                </button>            </div>
            <?php endif; ?>
</div>

<!-- Create/Edit Template Modal -->
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalTitle">Create SMS Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="templateForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="templateAction" value="create_template">
                    <input type="hidden" name="template_id" id="templateId">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="templateName" class="form-label">Template Name</label>
                                <input type="text" class="form-control" name="name" id="templateName" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="templateCategory" class="form-label">Category</label>
                                <select name="category" id="templateCategory" class="form-select" required>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category; ?>"><?php echo ucfirst($category); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="templateSubject" class="form-label">Subject/Title</label>
                        <input type="text" class="form-control" name="subject" id="templateSubject" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="templateMessage" class="form-label">Message Content</label>
                        <textarea class="form-control" name="message" id="templateMessage" rows="5" maxlength="160" required></textarea>
                        <small class="form-text text-muted">
                            <span id="templateCharCount">0</span>/160 characters
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="templateVariables" class="form-label">Variables (comma-separated)</label>
                        <input type="text" class="form-control" name="variables" id="templateVariables" 
                               placeholder="employee_name, date, time">
                        <small class="form-text text-muted">
                            Use these in your message as {variable_name}. Example: {employee_name}, {date}
                        </small>
                    </div>
                    
                    <div class="form-check" id="activeCheckContainer" style="display: none;">
                        <input class="form-check-input" type="checkbox" name="is_active" id="templateActive" checked>
                        <label class="form-check-label" for="templateActive">
                            Active
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="templateSubmitBtn">Create Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Use Template Modal -->
<div class="modal fade" id="useTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send SMS using Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="useTemplateForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="send_template">
                    <input type="hidden" name="template_id" id="useTemplateId">
                    
                    <div class="mb-3">
                        <label for="usePhoneNumber" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone_number" id="usePhoneNumber" 
                               placeholder="9841234567" required>
                    </div>
                    
                    <div id="templateVariablesContainer">
                        <!-- Variables will be loaded here -->
                    </div>
                      <div class="mb-3">
                        <label class="form-label">Preview:</label>
                        <div class="border p-3">
                            <div id="messagePreview">Select a template to see preview</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i>Send SMS
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Template Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Template Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
const templates = <?php echo json_encode($templates); ?>;

// Character counter for template message
document.getElementById('templateMessage').addEventListener('input', function() {
    const charCount = this.value.length;
    document.getElementById('templateCharCount').textContent = charCount;
    
    if (charCount > 160) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

function editTemplate(templateId) {
    const template = templates.find(t => t.id == templateId);
    if (!template) return;
    
    document.getElementById('templateModalTitle').textContent = 'Edit SMS Template';
    document.getElementById('templateAction').value = 'update_template';
    document.getElementById('templateSubmitBtn').textContent = 'Update Template';
    document.getElementById('templateId').value = templateId;
      document.getElementById('templateName').value = template.name;
    document.getElementById('templateSubject').value = template.subject;
    document.getElementById('templateMessage').value = template.content;
    document.getElementById('templateCategory').value = template.category;
    
    const variables = JSON.parse(template.variables || '[]');
    document.getElementById('templateVariables').value = variables.join(', ');
    
    document.getElementById('templateActive').checked = template.is_active == '1';    document.getElementById('activeCheckContainer').style.display = 'block';
    
    // Update character count
    document.getElementById('templateCharCount').textContent = template.content.length;
    
    new bootstrap.Modal(document.getElementById('createTemplateModal')).show();
}

function deleteTemplate(templateId) {
    if (confirm('Are you sure you want to delete this template?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_template">
            <input type="hidden" name="template_id" value="${templateId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function useTemplate(templateId) {
    const template = templates.find(t => t.id == templateId);
    if (!template) return;
    
    document.getElementById('useTemplateId').value = templateId;
    
    const variables = JSON.parse(template.variables || '[]');
    const container = document.getElementById('templateVariablesContainer');
    
    container.innerHTML = '';
    variables.forEach(variable => {
        container.innerHTML += `
            <div class="mb-3">
                <label for="var_${variable}" class="form-label">${variable.replace('_', ' ').toUpperCase()}</label>
                <input type="text" class="form-control template-variable" 
                       name="variables[${variable}]" id="var_${variable}" 
                       placeholder="Enter ${variable}" onchange="updatePreview()">
            </div>
        `;
    });
    
    updatePreview();
    new bootstrap.Modal(document.getElementById('useTemplateModal')).show();
}

function updatePreview() {
    const templateId = document.getElementById('useTemplateId').value;
    const template = templates.find(t => t.id == templateId);
    if (!template) return;
    
    let message = template.content;
    const variableInputs = document.querySelectorAll('.template-variable');
    
    variableInputs.forEach(input => {
        const variable = input.name.match(/\[(.*?)\]/)[1];
        const placeholder = `{${variable}}`;
        const value = input.value || `{${variable}}`;
        message = message.replace(new RegExp(placeholder, 'g'), value);
    });
    
    document.getElementById('messagePreview').textContent = message;
}

function previewTemplate(templateId) {
    const template = templates.find(t => t.id == templateId);
    if (!template) return;
    
    const variables = JSON.parse(template.variables || '[]');
    
    const previewHtml = `
        <div class="mb-3">
            <h6>Template: ${template.name}</h6>
            <p class="text-muted">${template.subject}</p>
        </div>        <div class="mb-3">
            <label class="form-label">Message:</label>
            <div class="border p-3 font-monospace">
                ${template.content}
            </div>
        </div>
        ${variables.length > 0 ? `
        <div class="mb-3">
            <label class="form-label">Available Variables:</label>
            <div>
                ${variables.map(v => `<span class="badge bg-primary me-1">{${v}}</span>`).join('')}
            </div>
        </div>` : ''}        <div class="mb-3">
            <small class="text-muted">
                Category: <span class="badge bg-secondary">${template.category}</span> | 
                Length: ${template.content.length} characters |
                Status: <span class="badge bg-${template.is_active == '1' ? 'success' : 'danger'}">
                    ${template.is_active == '1' ? 'Active' : 'Inactive'}
                </span>
            </small>
        </div>
    `;
    
    document.getElementById('previewContent').innerHTML = previewHtml;
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}

// Reset form when modal is hidden
document.getElementById('createTemplateModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('templateForm').reset();
    document.getElementById('templateModalTitle').textContent = 'Create SMS Template';
    document.getElementById('templateAction').value = 'create_template';
    document.getElementById('templateSubmitBtn').textContent = 'Create Template';
    document.getElementById('templateId').value = '';
    document.getElementById('activeCheckContainer').style.display = 'none';
    document.getElementById('templateCharCount').textContent = '0';
});
</script>

<?php require_once '../../includes/footer.php'; ?>

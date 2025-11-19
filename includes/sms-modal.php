<!-- Reusable SMS Modal Component -->
<?php
// Initialize SMS service to get sender identities
require_once __DIR__ . '/../modules/sms/SparrowSMS.php';
$smsService = new SparrowSMS();
$identities = $smsService->getSenderIdentities();
$hasMultipleIdentities = count($identities) > 1;
$defaultIdentity = $smsService->getDefaultSenderIdentity();
?>

<div class="modal fade" id="sendSMSModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-paper-plane me-2"></i>Send SMS
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="sendSMSForm">
                    <div class="mb-3">
                        <label for="smsTo" class="form-label">Phone Number(s)</label>
                        <textarea class="form-control" id="smsTo" name="to" rows="3" 
                                  placeholder="For single SMS: 9841234567&#10;&#10;For multiple SMS:&#10;9841234567&#10;9779841234568&#10;9779841234569&#10;&#10;Or comma-separated: 9841234567, 9779841234568"></textarea>
                        <small class="form-text text-muted">
                            <strong>Single SMS:</strong> Enter one phone number<br>
                            <strong>Multiple SMS:</strong> Enter multiple numbers (one per line or comma-separated)
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="smsFrom" class="form-label">Sender Identity</label>
                        <?php if ($hasMultipleIdentities): ?>
                            <!-- Show dropdown when multiple identities are available -->
                            <select class="form-control" id="smsFrom" name="from">
                                <?php foreach ($identities as $identity): ?>
                                    <option value="<?php echo htmlspecialchars($identity['identity']); ?>" 
                                            <?php echo !empty($identity['is_default']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($identity['identity']); ?>
                                        <?php if (!empty($identity['description'])): ?>
                                            - <?php echo htmlspecialchars($identity['description']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($identity['is_default'])): ?>
                                            (Default)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>Select from approved sender identities
                            </small>
                        <?php else: ?>
                            <!-- Show text when only one identity is available -->
                            <?php $singleIdentity = $identities[0]; ?>
                            <div class="form-control">
                                <strong><?php echo htmlspecialchars($singleIdentity['identity']); ?></strong>
                                <?php if (!empty($singleIdentity['description'])): ?>
                                    - <?php echo htmlspecialchars($singleIdentity['description']); ?>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="smsFrom" name="from" value="<?php echo htmlspecialchars($singleIdentity['identity']); ?>">
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>Using the only available sender identity
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="smsMessage" class="form-label">Message</label>
                        <textarea class="form-control" id="smsMessage" name="message" 
                                  rows="4" maxlength="160" required></textarea>
                        <small class="form-text text-muted">
                            <span id="charCount">0</span>/160 characters
                        </small>
                    </div>
                    <div class="mb-3" id="templateSection" style="display: none;">
                        <label class="form-label">Quick Templates</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertTemplate('meeting')">
                                Meeting Reminder
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertTemplate('leave')">
                                Leave Approved
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertTemplate('attendance')">
                                Attendance Alert
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="sendSMS()">
                    <i class="fas fa-paper-plane me-1"></i>Send SMS
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// SMS Modal Templates
const smsTemplates = {
    meeting: "Meeting scheduled for [DATE] at [TIME]. Please attend. -HRMS",
    leave: "Your leave request has been approved for [DATE]. -HRMS", 
    attendance: "Please ensure to mark your attendance on time. -HRMS"
};

// Character counter
document.getElementById('smsMessage').addEventListener('input', function() {
    const charCount = this.value.length;
    document.getElementById('charCount').textContent = charCount;
    
    if (charCount > 160) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// Insert template text
function insertTemplate(templateKey) {
    const template = smsTemplates[templateKey];
    if (template) {
        document.getElementById('smsMessage').value = template;
        document.getElementById('smsMessage').dispatchEvent(new Event('input'));
    }
}

// Show/hide templates based on page
function toggleSMSTemplates(show = true) {
    const templateSection = document.getElementById('templateSection');
    if (templateSection) {
        templateSection.style.display = show ? 'block' : 'none';
    }
}

// Pre-fill phone number (useful for employee-specific pages)
function prefillSMSNumber(phoneNumber) {
    document.getElementById('smsTo').value = phoneNumber;
}

// Pre-fill message (useful for notification pages)
function prefillSMSMessage(message) {
    document.getElementById('smsMessage').value = message;
    document.getElementById('smsMessage').dispatchEvent(new Event('input'));
}

// Reset modal form
function resetSMSForm() {
    const form = document.getElementById('sendSMSForm');
    form.reset();
    document.getElementById('charCount').textContent = '0';
    document.getElementById('smsTo').classList.remove('is-invalid');
    document.getElementById('smsMessage').classList.remove('is-invalid');
}

// Main SMS sending function
function sendSMS() {
    const form = document.getElementById('sendSMSForm');
    const formData = new FormData(form);
    
    // Validate inputs
    const message = document.getElementById('smsMessage').value.trim();
    const phoneNumbers = document.getElementById('smsTo').value.trim();
    
    if (!message) {
        document.getElementById('smsMessage').classList.add('is-invalid');
        alert('Please enter a message');
        return;
    }
    
    if (!phoneNumbers) {
        document.getElementById('smsTo').classList.add('is-invalid');
        alert('Please enter at least one phone number');
        return;
    }
    
    // Parse phone numbers (split by newlines or commas)
    const numbers = phoneNumbers.split(/[,\n]/).map(n => n.trim()).filter(n => n);
    
    if (numbers.length === 0) {
        document.getElementById('smsTo').classList.add('is-invalid');
        alert('Please enter valid phone numbers');
        return;
    }
    
    // Clear validation classes
    document.getElementById('smsMessage').classList.remove('is-invalid');
    document.getElementById('smsTo').classList.remove('is-invalid');
    
    // Add parsed numbers to form data
    formData.append('phone_numbers', JSON.stringify(numbers));
    formData.append('action', 'send_sms');
    
    // Show loading state (find the send button reliably instead of relying on global event)
    const modal = document.getElementById('sendSMSModal');
    const sendButton = modal ? modal.querySelector('button.btn-primary') : null;
    const originalText = sendButton ? sendButton.innerHTML : 'Send SMS';
    if (sendButton) {
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
        sendButton.disabled = true;
    }
    
    // Determine the endpoint based on current page
    const endpoint = getSMSEndpoint();
    
    fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin', // include cookies so PHP session is sent with the request
        body: formData
    })
    .then(async response => {
        const text = await response.text();
        // Try to parse as JSON; if server prepended HTML, attempt to extract trailing JSON object
        try {
            const data = JSON.parse(text);
            return { ok: true, data };
        } catch (e) {
            // Fallback: extract last JSON object in the response (useful when HTML is prepended)
            const jsonMatch = text.match(/\{[\s\S]*\}\s*$/);
            if (jsonMatch) {
                try {
                    const data = JSON.parse(jsonMatch[0]);
                    console.warn('Extracted JSON from mixed HTML response');
                    return { ok: true, data };
                } catch (e2) {
                    // fall through
                }
            }
            return { ok: false, text };
        }
    })
    .then(result => {
        if (result.ok && result.data) {
            const data = result.data;
            if (data.success) {
                let message = 'SMS sent successfully!';
                if (data.results && data.results.length > 1) {
                    const successful = data.results.filter(r => r.success).length;
                    const failed = data.results.length - successful;
                    message = `SMS sending completed: ${successful} sent successfully, ${failed} failed`;
                }

                // Show success message
                showSMSAlert(message, 'success');

                // Close modal and reset form
                bootstrap.Modal.getInstance(document.getElementById('sendSMSModal')).hide();
                resetSMSForm();

                // Refresh page if we're on SMS logs or dashboard
                if (window.location.pathname.includes('sms-')) {
                    setTimeout(() => location.reload(), 1000);
                }
                return;
            }

            // Backend returned JSON but with success=false
            const errMsg = data.error || JSON.stringify(data);
            showSMSAlert('Failed to send SMS: ' + errMsg, 'error');
        } else {
            // Non-JSON or HTML response (likely an error page or redirect). Show a friendly message and log raw text to console
            const text = result.text || '';
            console.error('Non-JSON response from SMS endpoint:', text);
            let short = text.trim().substring(0, 500);
            // If it looks like an HTML page, show a concise message
            if (short.startsWith('<!DOCTYPE') || short.startsWith('<html') || short.includes('<html')) {
                showSMSAlert('Server returned an unexpected HTML response. Check server logs or session/authentication.', 'error');
            } else {
                showSMSAlert('Unexpected response from server: ' + short, 'error');
            }
        }
    })
    .catch(error => {
        showSMSAlert('Network error: ' + (error.message || error), 'error');
    })
    .finally(() => {
        // Restore button state
        if (sendButton) {
            sendButton.innerHTML = originalText;
            sendButton.disabled = false;
        }
    });
}

// Get the appropriate SMS endpoint based on current page
function getSMSEndpoint() {
    const path = window.location.pathname;
    
    if (path.includes('/modules/sms/')) {
        return 'sms-dashboard.php';
    } else {
        return '/php-hrms/modules/sms/sms-dashboard.php';
    }
}

// Show alert messages
function showSMSAlert(message, type = 'info') {
    if (typeof Swal !== 'undefined') {
        // Use SweetAlert if available
        Swal.fire({
            title: type === 'success' ? 'Success!' : 'Error!',
            text: message,
            icon: type === 'success' ? 'success' : 'error',
            timer: 3000,
            showConfirmButton: false
        });
    } else {
        // Fallback to regular alert
        alert(message);
    }
}

// Initialize modal when it's shown
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('sendSMSModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function() {
            resetSMSForm();
            // Show templates by default, pages can override this
            toggleSMSTemplates(true);
        });
    }
});
</script>

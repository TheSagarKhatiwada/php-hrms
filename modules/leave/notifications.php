<?php
/**
 * Leave Module Email Notification System
 * Handles sending email notifications for leave requests
 */

include_once 'config.php';

class LeaveEmailNotifier {
    private $connection;
    private $from_email;
    private $from_name;
    
    public function __construct($db_connection) {
        $this->connection = $db_connection;
        $this->from_email = 'hrms@company.com'; // Configure as needed
        $this->from_name = 'HRMS System';
    }
    
    /**
     * Send notification when leave request is submitted
     */
    public function sendRequestSubmittedNotification($request_id) {
        if (!SEND_EMAIL_NOTIFICATIONS || !EMAIL_ON_REQUEST_SUBMIT) {
            return false;
        }
        
        // Get request details
        $request = $this->getRequestDetails($request_id);
        if (!$request) return false;
        
        // Get supervisor/HR emails
        $recipients = $this->getApprovalRecipients($request['employee_id'], $request['department_id']);
        
        $template = $GLOBALS['email_templates']['request_submitted'];
        
        $subject = $this->replaceTemplateVariables($template['subject'], $request);
        $body = $this->replaceTemplateVariables($template['body'], $request);
        
        foreach ($recipients as $recipient) {
            $this->sendEmail($recipient['email'], $subject, $body, $recipient['name']);
        }
        
        return true;
    }
    
    /**
     * Send notification when leave request is approved
     */
    public function sendRequestApprovedNotification($request_id, $approved_by_id, $comments = '') {
        if (!SEND_EMAIL_NOTIFICATIONS || !EMAIL_ON_REQUEST_APPROVED) {
            return false;
        }
        
        $request = $this->getRequestDetails($request_id);
        if (!$request) return false;
        
        // Get approver details
        $approver = $this->getEmployeeDetails($approved_by_id);
        $request['approved_by'] = $approver['first_name'] . ' ' . $approver['last_name'];
        $request['approval_comments'] = $comments;
        
        $template = $GLOBALS['email_templates']['request_approved'];
        
        $subject = $this->replaceTemplateVariables($template['subject'], $request);
        $body = $this->replaceTemplateVariables($template['body'], $request);
        
        // Send to employee
        $this->sendEmail($request['employee_email'], $subject, $body, $request['employee_name']);
        
        return true;
    }
    
    /**
     * Send notification when leave request is rejected
     */
    public function sendRequestRejectedNotification($request_id, $rejected_by_id, $rejection_reason) {
        if (!SEND_EMAIL_NOTIFICATIONS || !EMAIL_ON_REQUEST_REJECTED) {
            return false;
        }
        
        $request = $this->getRequestDetails($request_id);
        if (!$request) return false;
        
        // Get rejector details
        $rejector = $this->getEmployeeDetails($rejected_by_id);
        $request['rejected_by'] = $rejector['first_name'] . ' ' . $rejector['last_name'];
        $request['rejection_reason'] = $rejection_reason;
        
        $template = $GLOBALS['email_templates']['request_rejected'];
        
        $subject = $this->replaceTemplateVariables($template['subject'], $request);
        $body = $this->replaceTemplateVariables($template['body'], $request);
        
        // Send to employee
        $this->sendEmail($request['employee_email'], $subject, $body, $request['employee_name']);
        
        return true;
    }
    
    /**
     * Send notification when leave request is cancelled
     */
    public function sendRequestCancelledNotification($request_id) {
        if (!SEND_EMAIL_NOTIFICATIONS || !EMAIL_ON_REQUEST_CANCELLED) {
            return false;
        }
        
        $request = $this->getRequestDetails($request_id);
        if (!$request) return false;
        
        // Get supervisor/HR emails
        $recipients = $this->getApprovalRecipients($request['employee_id'], $request['department_id']);
        
        $subject = "Leave Request Cancelled - {$request['employee_name']}";
        $body = "Dear Supervisor,

The following leave request has been cancelled by {$request['employee_name']}:

Details:
- Leave Type: {$request['leave_type']}
- Start Date: {$request['start_date']}
- End Date: {$request['end_date']}
- Days: {$request['days_requested']}

Best regards,
HRMS System";
        
        foreach ($recipients as $recipient) {
            $this->sendEmail($recipient['email'], $subject, $body, $recipient['name']);
        }
        
        return true;
    }
      /**
     * Send reminder for pending requests
     */
    public function sendPendingRequestReminder($days_pending = 3) {
        // Get requests pending for specified days
        $query = "
            SELECT 
                lr.id,
                lr.employee_id,
                lr.start_date,
                lr.end_date,
                lr.days_requested,
                lr.reason,
                lr.applied_date,
                e.first_name,
                e.last_name,
                e.email as employee_email,
                e.emp_id,
                e.department_id,
                lt.name as leave_type,
                DATEDIFF(NOW(), lr.applied_date) as days_pending
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.emp_id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.status = 'pending'
                AND DATEDIFF(NOW(), lr.applied_date) >= ?
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$days_pending]);
        
        while ($request = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recipients = $this->getApprovalRecipients($request['employee_id'], $request['department_id']);
            
            $subject = "Reminder: Pending Leave Request - {$request['first_name']} {$request['last_name']}";
            $body = "Dear Supervisor,

This is a reminder that the following leave request is still pending approval:

Employee: {$request['first_name']} {$request['last_name']} ({$request['emp_id']})
Leave Type: {$request['leave_type']}
Start Date: {$request['start_date']}
End Date: {$request['end_date']}
Days Requested: {$request['days_requested']}
Days Pending: {$request['days_pending']}

Please review and take appropriate action.

Best regards,
HRMS System";
            
            foreach ($recipients as $recipient) {
                $this->sendEmail($recipient['email'], $subject, $body, $recipient['name']);
            }
        }
    }
      /**
     * Get request details for email templates
     */
    private function getRequestDetails($request_id) {
        $query = "
            SELECT 
                lr.*,
                e.first_name,
                e.last_name,
                e.email as employee_email,
                e.emp_id,
                e.department_id,
                lt.name as leave_type,
                d.name as department_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.emp_id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE lr.id = ?
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($request) {
            $request['employee_name'] = $request['first_name'] . ' ' . $request['last_name'];
            $request['start_date'] = date('M d, Y', strtotime($request['start_date']));
            $request['end_date'] = date('M d, Y', strtotime($request['end_date']));
        }
        
        return $request;
    }
      /**
     * Get employee details
     */
    private function getEmployeeDetails($employee_id) {
        $query = "SELECT * FROM employees WHERE id = ?";
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$employee_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
      /**
     * Get recipients who should receive approval notifications
     */
    private function getApprovalRecipients($employee_id, $department_id) {
        $recipients = [];
        
        // Get supervisors and HR personnel
        $query = "
            SELECT DISTINCT e.email, e.first_name, e.last_name
            FROM employees e
            WHERE (e.role IN ('supervisor', 'hr', 'admin') 
                   OR (e.role = 'supervisor' AND e.department_id = ?))
                AND e.status = 'active'
                AND e.email IS NOT NULL
                AND e.email != ''
                AND e.id != ?
        ";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$department_id, $employee_id]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recipients[] = [
                'email' => $row['email'],
                'name' => $row['first_name'] . ' ' . $row['last_name']
            ];
        }
        
        return $recipients;
    }
    
    /**
     * Replace template variables with actual values
     */
    private function replaceTemplateVariables($template, $data) {
        $variables = [
            '{employee_name}' => $data['employee_name'] ?? '',
            '{employee_id}' => $data['emp_id'] ?? '',
            '{leave_type}' => $data['leave_type'] ?? '',
            '{start_date}' => $data['start_date'] ?? '',
            '{end_date}' => $data['end_date'] ?? '',
            '{days_requested}' => $data['days_requested'] ?? '',
            '{reason}' => $data['reason'] ?? '',
            '{approved_by}' => $data['approved_by'] ?? '',
            '{rejected_by}' => $data['rejected_by'] ?? '',
            '{rejection_reason}' => $data['rejection_reason'] ?? '',
            '{approval_comments}' => $data['approval_comments'] ?? '',
            '{department_name}' => $data['department_name'] ?? '',
            '{supervisor_name}' => 'Supervisor' // Can be improved to get actual supervisor name
        ];
        
        return str_replace(array_keys($variables), array_values($variables), $template);
    }
    
    /**
     * Send email using PHP mail function
     * In production, consider using PHPMailer or similar library
     */
    private function sendEmail($to_email, $subject, $body, $to_name = '') {
        $headers = "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "Reply-To: {$this->from_email}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Log email attempt
        $this->logEmailAttempt($to_email, $subject);
        
        // Send email
        $sent = mail($to_email, $subject, $body, $headers);
        
        // Log result
        $this->logEmailResult($to_email, $subject, $sent);
        
        return $sent;
    }
    
    /**
     * Log email attempts for debugging
     */
    private function logEmailAttempt($to_email, $subject) {
        $log_entry = date('Y-m-d H:i:s') . " - Attempting to send email to: $to_email, Subject: $subject\n";
        file_put_contents('../../logs/email.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log email results
     */
    private function logEmailResult($to_email, $subject, $success) {
        $status = $success ? 'SUCCESS' : 'FAILED';
        $log_entry = date('Y-m-d H:i:s') . " - Email $status - To: $to_email, Subject: $subject\n";
        file_put_contents('../../logs/email.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Helper function to send leave notifications
 */
function sendLeaveNotification($type, $request_id, $additional_data = []) {
    global $pdo;
    
    $notifier = new LeaveEmailNotifier($pdo);
    
    switch ($type) {
        case 'submitted':
            return $notifier->sendRequestSubmittedNotification($request_id);
            
        case 'approved':
            $approved_by = $additional_data['approved_by'] ?? 0;
            $comments = $additional_data['comments'] ?? '';
            return $notifier->sendRequestApprovedNotification($request_id, $approved_by, $comments);
            
        case 'rejected':
            $rejected_by = $additional_data['rejected_by'] ?? 0;
            $reason = $additional_data['reason'] ?? '';
            return $notifier->sendRequestRejectedNotification($request_id, $rejected_by, $reason);
            
        case 'cancelled':
            return $notifier->sendRequestCancelledNotification($request_id);
            
        default:
            return false;
    }
}

/**
 * Function to set up email reminder cron job
 * This should be called by a cron job to send pending request reminders
 */
function sendPendingRequestReminders() {
    global $connection;
    
    $notifier = new LeaveEmailNotifier($connection);
    $notifier->sendPendingRequestReminder(3); // Send reminder after 3 days
}
?>

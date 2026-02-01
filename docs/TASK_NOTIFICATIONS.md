# Task Notification System Documentation

## Overview
The task notification system provides automated email and SMS alerts for task-related events in the HRMS system. Users receive notifications when tasks are assigned, updated, completed, or become overdue.

## Features

### 1. **Task Assignment Notifications**
- Triggered when a new task is assigned to a user
- Triggered when a task is reassigned to a different user
- Includes: Task title, description, priority, due date, and category
- Sent to: The assignee (person receiving the task)

### 2. **Task Status Update Notifications**
- Triggered when task status changes (pending → in progress → completed)
- Includes: Task title, new status, current progress
- Sent to: The task creator (person who assigned the task)

### 3. **Task Completion Notifications**
- Triggered when a task is marked as completed
- Includes: Task title, completion date/time
- Sent to: The task creator

### 4. **Overdue Task Reminders**
- Automatically sent for tasks past their due date
- Includes: Task title, days overdue, priority
- Sent to: The task assignee
- **Requires scheduled job** (see Setup section)

## User Notification Preferences

Users can control which notifications they receive via the **Notification Preferences** page.

### Accessing Preferences
1. Navigate to: `/notification-preferences.php`
2. Or add a link in the user menu/profile section

### Preference Options
Each notification type can be configured for:
- **Email**: Detailed HTML notifications
- **SMS**: Brief text message alerts

Default settings:
- Email: ✅ Enabled for all notification types
- SMS: ❌ Disabled (recommended to keep disabled to avoid SMS charges)

### Database Storage
Preferences are stored in the `notification_preferences` table:
- `employee_id`: Employee's emp_id
- `notification_type`: One of: task_assigned, task_status_update, task_completed, task_overdue
- `email_enabled`: 1 (enabled) or 0 (disabled)
- `sms_enabled`: 1 (enabled) or 0 (disabled)

## Technical Implementation

### Core Files

#### 1. `includes/task_notification_helper.php`
Main notification engine with functions:
- `getUserNotificationPreferences($pdo, $employee_id, $notification_type)` - Retrieves user preferences
- `sendTaskAssignmentNotification($pdo, $task_id, $assigned_to, $assigned_by)` - Sends assignment notifications
- `sendTaskStatusUpdateNotification($pdo, $task_id, $new_status, $updated_by)` - Sends status change notifications
- `sendTaskCompletionNotification($pdo, $task_id)` - Sends completion notifications
- `sendOverdueTaskReminder($pdo, $task_id)` - Sends overdue reminders
- `sendTaskSMS($phone, $message)` - Helper for SMS sending

#### 2. `notification-preferences.php`
User interface for managing notification settings

#### 3. `scripts/send_overdue_reminders.php`
Scheduled script to send daily overdue task reminders

#### 4. `migrations/2025_01_15_000000_create_notification_preferences.php`
Database migration for notification preferences table

### Integration Points

#### Task Creation
**File:** `modules/tasks/create_task_handler.php`
**Location:** After line 187 (after transaction commit)
```php
require_once __DIR__ . '/../../includes/task_notification_helper.php';
try {
    $notification_result = sendTaskAssignmentNotification($pdo, $task_id, $assigned_to, $current_user_id);
} catch (Exception $notif_error) {
    error_log("Warning: Failed to send task notification: " . $notif_error->getMessage());
}
```

#### Task Reassignment
**File:** `modules/tasks/reassign_task.php`
**Location:** After task update, before commit
```php
require_once __DIR__ . '/../../includes/task_notification_helper.php';
try {
    sendTaskAssignmentNotification($pdo, $taskId, $assignedTo, $who);
} catch (Exception $notif_error) {
    error_log("Warning: Failed to send task reassignment notification: " . $notif_error->getMessage());
}
```

#### Task Status Updates
**File:** `modules/tasks/task_helpers.php`
**Function:** `updateTaskProgress()`
```php
require_once __DIR__ . '/../../includes/task_notification_helper.php';
try {
    sendTaskStatusUpdateNotification($pdo, $taskId, $status, $employeeId);
    
    if ($status === 'completed') {
        sendTaskCompletionNotification($pdo, $taskId);
    }
} catch (Exception $notif_error) {
    error_log("Warning: Failed to send task status notification: " . $notif_error->getMessage());
}
```

## Setup Instructions

### 1. Database Migration
Run the migration to create the notification preferences table:
```bash
php migrations/2025_01_15_000000_create_notification_preferences.php
```

This creates:
- `notification_preferences` table
- Default preferences for all existing employees

### 2. Configure Email Settings
Ensure email settings are configured in `includes/mail_helper.php`:
- SMTP host
- SMTP port
- SMTP username/password
- From email address

### 3. Configure SMS Settings (Optional)
If using SMS notifications:
- Configure SparrowSMS API credentials in `modules/sms/SparrowSMS.php`
- Test SMS sending functionality
- Note: SMS notifications incur charges

### 4. Set Up Overdue Task Reminders

#### Windows Task Scheduler
1. Open Task Scheduler
2. Create a new task:
   - **Name:** HRMS Overdue Task Reminders
   - **Trigger:** Daily at 9:00 AM
   - **Action:** Start a program
   - **Program:** `C:\path\to\php.exe`
   - **Arguments:** `D:\wwwroot\php-hrms\scripts\send_overdue_reminders.php`

#### Linux/Unix Cron Job
Add to crontab:
```bash
# Run daily at 9:00 AM
0 9 * * * /usr/bin/php /path/to/php-hrms/scripts/send_overdue_reminders.php
```

### 5. Add Navigation Link
Add a link to notification preferences in the user menu or settings:
```php
<a href="/notification-preferences.php">
    <i class="fas fa-bell"></i> Notification Preferences
</a>
```

## Email Templates

Notifications use styled HTML email templates with:
- Responsive design
- Color-coded headers (blue for assignments, green for completions, red for overdue)
- Priority badges
- Direct links to view task details
- Professional formatting

## SMS Templates

SMS notifications are brief and concise:
- **Assignment:** "New task assigned: '[Title]' by [Assignor]. Priority: [Priority]. Due: [Date]. Check HRMS."
- **Completion:** "Task completed: '[Title]' by [Assignee]. Check HRMS for details."
- **Overdue:** "REMINDER: Task '[Title]' is overdue by X day(s). Please complete urgently."

## Logs

### Email Logs
PHPMailer logs are handled by the existing `send_email()` function in `includes/mail_helper.php`

### SMS Logs
SparrowSMS API responses are logged via the SparrowSMS class

### Overdue Reminder Logs
Location: `logs/overdue_reminders.log`
Format:
```
[2025-01-15 09:00:00] === Starting Overdue Task Reminder Script ===
[2025-01-15 09:00:01] Found 3 overdue task(s)
[2025-01-15 09:00:02] Processing Task ID 45: 'Update Database Schema' - 2 days overdue
[2025-01-15 09:00:03]   ✓ Reminder sent successfully
```

## Error Handling

All notification functions:
- Return structured results: `['email' => bool, 'sms' => bool, 'messages' => array]`
- Log errors without failing the main task operation
- Use try-catch blocks to prevent notification failures from blocking task creation/updates
- Provide detailed error messages in logs

Example:
```php
try {
    sendTaskAssignmentNotification($pdo, $task_id, $assigned_to, $current_user);
} catch (Exception $e) {
    // Task is still created successfully even if notification fails
    error_log("Notification error: " . $e->getMessage());
}
```

## Testing Checklist

- [ ] Create a new task → Assignee receives email
- [ ] Reassign a task → New assignee receives email
- [ ] Update task status → Creator receives status update email
- [ ] Mark task as completed → Creator receives completion email
- [ ] Manually run overdue reminder script → Overdue task owners receive reminders
- [ ] Test notification preferences → Enable/disable email/SMS for each type
- [ ] Test with users who have no email/phone → System handles gracefully
- [ ] Test with invalid email/phone → Errors are logged, system continues

## Future Enhancements

1. **In-app notifications** - Bell icon with notification dropdown
2. **Digest emails** - Daily/weekly summary instead of individual emails
3. **Slack/Teams integration** - Send notifications to team channels
4. **Notification history** - Track all sent notifications in database
5. **Smart scheduling** - Don't send notifications outside working hours
6. **Notification templates** - Allow customization of email/SMS content
7. **Priority-based notifications** - Only notify for high/urgent tasks
8. **Escalation notifications** - Notify manager if task overdue > X days

## Troubleshooting

### Notifications not being sent
1. Check email configuration in `includes/mail_helper.php`
2. Verify database table `notification_preferences` exists
3. Check error logs: `logs/overdue_reminders.log` or PHP error log
4. Verify user has email address in employees table
5. Check user's notification preferences are enabled

### Overdue reminders not working
1. Verify scheduled task/cron job is configured
2. Check script permissions: `chmod +x scripts/send_overdue_reminders.php`
3. Run script manually to test: `php scripts/send_overdue_reminders.php`
4. Check log file: `logs/overdue_reminders.log`

### SMS not sending
1. Verify SparrowSMS API credentials
2. Check SMS balance/credits
3. Verify phone number format
4. Check user has SMS enabled in preferences

## Support
For issues or questions, contact the system administrator or refer to the main HRMS documentation.

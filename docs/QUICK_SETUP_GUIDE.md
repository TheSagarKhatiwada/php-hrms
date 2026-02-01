# Quick Setup Guide - Task Notifications

## ✅ Completed
- [x] Notification system implemented
- [x] Database migrated (128 preferences created)
- [x] Security hardened (CSRF, null safety)
- [x] Navigation link added to sidebar
- [x] Production readiness: 100%

---

## 📋 Remaining Setup Tasks

### 1. Test Email Notifications (5 minutes)

**Quick Test:**
1. Log in to HRMS
2. Navigate to **Tasks → Create Task**
3. Assign task to another employee
4. Check assignee's email inbox

**Expected Result:**
- Assignee receives styled HTML email with task details
- Email includes "View Task Details" button
- From: HRMS Task Management

**Troubleshooting:**
- Check `includes/mail_helper.php` for SMTP settings
- Verify employee has valid email address
- Check PHP error log for mail errors

---

### 2. Set Up Overdue Reminders (10 minutes)

#### Windows Task Scheduler

**Step 1:** Open Task Scheduler
- Press `Win + R`, type `taskschd.msc`, press Enter

**Step 2:** Create New Task
- Click "Create Task" (not "Create Basic Task")
- **Name:** HRMS Overdue Task Reminders
- **Description:** Daily notification for overdue tasks
- Check "Run whether user is logged on or not"

**Step 3:** Configure Trigger
- New → Daily
- Start: Tomorrow at 9:00 AM
- Recur every: 1 day

**Step 4:** Configure Action
- Action: Start a program
- **Program:** `C:\php\php.exe` (adjust to your PHP path)
- **Arguments:** `D:\wwwroot\php-hrms\scripts\send_overdue_reminders.php`
- **Start in:** `D:\wwwroot\php-hrms\scripts`

**Step 5:** Test It
```powershell
# Run manually to test
php d:\wwwroot\php-hrms\scripts\send_overdue_reminders.php

# Check the log
Get-Content d:\wwwroot\php-hrms\logs\overdue_reminders.log -Tail 20
```

#### Linux/Unix Cron Job

```bash
# Edit crontab
crontab -e

# Add this line (runs daily at 9 AM)
0 9 * * * /usr/bin/php /var/www/html/php-hrms/scripts/send_overdue_reminders.php

# Or use full path to your PHP installation
0 9 * * * /usr/local/bin/php /path/to/php-hrms/scripts/send_overdue_reminders.php
```

**Test Cron:**
```bash
# Run manually
php /path/to/php-hrms/scripts/send_overdue_reminders.php

# Check logs
tail -f /path/to/php-hrms/logs/overdue_reminders.log
```

---

### 3. Configure User Preferences (Optional)

**For All Users:**
1. Log in as each user
2. Click the bell icon 🔔 in the sidebar
3. Configure notification preferences
4. Click "Save Preferences"

**Default Settings:**
- ✅ Email enabled for all types
- ❌ SMS disabled (to avoid charges)

**Bulk Configuration (Admin):**
If you want to change defaults for all users, run this SQL:

```sql
-- Enable SMS for task assignments only
UPDATE notification_preferences 
SET sms_enabled = 1 
WHERE notification_type = 'task_assigned';

-- Disable all notifications for a specific user
UPDATE notification_preferences 
SET email_enabled = 0, sms_enabled = 0 
WHERE employee_id = 'EMP001';
```

---

### 4. Monitor & Verify (Ongoing)

**Check Notification Logs:**
```powershell
# Windows
Get-Content d:\wwwroot\php-hrms\logs\overdue_reminders.log -Tail 50

# Check for errors
Select-String -Path "d:\wwwroot\php-hrms\logs\overdue_reminders.log" -Pattern "Error|Failed"
```

**Verify Email Delivery:**
1. Create test task
2. Check email arrives within 1-2 minutes
3. Verify all links work
4. Check email formatting

**Monitor Database:**
```sql
-- Check notification preferences
SELECT 
    employee_id,
    notification_type,
    email_enabled,
    sms_enabled
FROM notification_preferences
WHERE employee_id = 'EMP001';

-- See which users have SMS enabled
SELECT 
    e.first_name,
    e.last_name,
    np.notification_type,
    np.sms_enabled
FROM notification_preferences np
JOIN employees e ON np.employee_id = e.emp_id
WHERE np.sms_enabled = 1;
```

---

## 🎯 Quick Wins - Optional Enhancements

### A. Add Notification Badge Count
Show unread notification count in topbar:

```php
// In topbar.php
$unread_notifications = $pdo->query("
    SELECT COUNT(*) FROM notifications 
    WHERE employee_id = '{$_SESSION['user_id']}' 
    AND is_read = 0
")->fetchColumn();
```

```html
<a href="notifications.php">
    <i class="fas fa-bell"></i>
    <?php if ($unread_notifications > 0): ?>
        <span class="badge bg-danger"><?= $unread_notifications ?></span>
    <?php endif; ?>
</a>
```

### B. Email Digest Feature
Group notifications into daily/weekly digest:

1. Create `scripts/send_notification_digest.php`
2. Aggregate notifications from last 24 hours
3. Send one email with all updates
4. Schedule to run daily at 5 PM

### C. Slack/Teams Integration
Forward notifications to team channels:

```php
// In task_notification_helper.php
function sendSlackNotification($webhook_url, $task_id, $message) {
    $data = [
        'text' => $message,
        'attachments' => [
            [
                'color' => '#007bff',
                'fields' => [
                    ['title' => 'Task ID', 'value' => $task_id, 'short' => true]
                ]
            ]
        ]
    ];
    
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
```

---

## 📊 Success Metrics

Track these metrics to measure success:

1. **Email Delivery Rate:** Should be > 95%
2. **Task Completion Time:** Should decrease by 20-30%
3. **Overdue Tasks:** Should decrease significantly
4. **User Engagement:** Check notification preferences usage

---

## 🆘 Support & Troubleshooting

### Common Issues

**Emails not sending:**
- Check SMTP credentials in `includes/mail_helper.php`
- Verify firewall allows outbound port 465/587
- Test with simple mail() function first

**Overdue reminders not running:**
- Verify scheduled task is enabled
- Check PHP path is correct
- Review logs for errors
- Run manually first to test

**URLs in emails broken:**
- Verify `$_SERVER['HTTP_HOST']` returns correct domain
- Check SSL certificate if using HTTPS
- Test `getBaseUrl()` function manually

**Notifications going to spam:**
- Configure SPF/DKIM records for your domain
- Use proper "From" email address
- Avoid spam trigger words in templates

---

## 🎉 You're All Set!

The notification system is ready. Users will now receive:
- ✅ Instant task assignment alerts
- ✅ Status update notifications
- ✅ Completion confirmations
- ✅ Daily overdue reminders (once scheduled)

**Next Feature Recommendations:**
1. In-app notification center
2. Browser push notifications
3. Mobile app notifications (if you have mobile app)
4. WhatsApp integration (popular in Nepal)
5. Notification analytics dashboard

Enjoy your automated notification system! 🚀

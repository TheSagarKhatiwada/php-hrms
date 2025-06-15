<?php
/**
 * Leave Module Configuration
 * Centralized configuration for the Leave Management System
 */

// Module Information
define('LEAVE_MODULE_VERSION', '1.0.0');
define('LEAVE_MODULE_NAME', 'Leave Management System');
define('LEAVE_MODULE_AUTHOR', 'HRMS Team');

// Default Settings
define('DEFAULT_LEAVE_BALANCE_ANNUAL', 25); // Default annual leave days
define('DEFAULT_LEAVE_BALANCE_SICK', 10);   // Default sick leave days
define('DEFAULT_LEAVE_BALANCE_CASUAL', 5);  // Default casual leave days

// Leave Request Settings
define('MAX_ADVANCE_DAYS', 90);             // Maximum days in advance to request leave
define('MIN_ADVANCE_HOURS', 24);            // Minimum hours in advance for leave request
define('ALLOW_HALF_DAY_LEAVE', true);       // Allow half-day leave requests
define('ALLOW_OVERLAPPING_REQUESTS', false); // Allow overlapping leave requests
define('REQUIRE_ATTACHMENT', false);        // Require attachment for leave requests

// Approval Settings
define('AUTO_APPROVE_CASUAL_LEAVE', false); // Auto-approve casual leave requests
define('MAX_CONSECUTIVE_DAYS', 30);         // Maximum consecutive days for single request
define('REQUIRE_REASON_FOR_REJECTION', true); // Require reason when rejecting requests

// Calendar Settings
define('WEEKEND_DAYS', [0, 6]);             // Weekend days (0 = Sunday, 6 = Saturday)
define('EXCLUDE_WEEKENDS_FROM_COUNT', true); // Exclude weekends from leave day count
define('EXCLUDE_HOLIDAYS_FROM_COUNT', true); // Exclude holidays from leave day count

// Notification Settings
define('SEND_EMAIL_NOTIFICATIONS', true);   // Send email notifications
define('EMAIL_ON_REQUEST_SUBMIT', true);    // Email when request is submitted
define('EMAIL_ON_REQUEST_APPROVED', true);  // Email when request is approved
define('EMAIL_ON_REQUEST_REJECTED', true);  // Email when request is rejected
define('EMAIL_ON_REQUEST_CANCELLED', true); // Email when request is cancelled

// File Upload Settings
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('UPLOAD_PATH', '../../uploads/leave/');

// Database Table Names
define('TABLE_LEAVE_REQUESTS', 'leave_requests');
define('TABLE_LEAVE_TYPES', 'leave_types');
define('TABLE_LEAVE_BALANCES', 'leave_balances');
define('TABLE_EMPLOYEES', 'employees');
define('TABLE_DEPARTMENTS', 'departments');

// Status Constants
define('LEAVE_STATUS_PENDING', 'pending');
define('LEAVE_STATUS_APPROVED', 'approved');
define('LEAVE_STATUS_REJECTED', 'rejected');
define('LEAVE_STATUS_CANCELLED', 'cancelled');

// Leave Type Constants
define('LEAVE_TYPE_ANNUAL', 'annual');
define('LEAVE_TYPE_SICK', 'sick');
define('LEAVE_TYPE_CASUAL', 'casual');
define('LEAVE_TYPE_MATERNITY', 'maternity');
define('LEAVE_TYPE_PATERNITY', 'paternity');

// Permission Constants
define('PERMISSION_VIEW_OWN_REQUESTS', 'view_own_requests');
define('PERMISSION_CREATE_REQUESTS', 'create_requests');
define('PERMISSION_CANCEL_OWN_REQUESTS', 'cancel_own_requests');
define('PERMISSION_VIEW_ALL_REQUESTS', 'view_all_requests');
define('PERMISSION_APPROVE_REQUESTS', 'approve_requests');
define('PERMISSION_MANAGE_LEAVE_TYPES', 'manage_leave_types');
define('PERMISSION_VIEW_REPORTS', 'view_reports');
define('PERMISSION_MANAGE_BALANCES', 'manage_balances');

// Role-based Permissions
$leave_permissions = [
    'employee' => [
        PERMISSION_VIEW_OWN_REQUESTS,
        PERMISSION_CREATE_REQUESTS,
        PERMISSION_CANCEL_OWN_REQUESTS
    ],
    'supervisor' => [
        PERMISSION_VIEW_OWN_REQUESTS,
        PERMISSION_CREATE_REQUESTS,
        PERMISSION_CANCEL_OWN_REQUESTS,
        PERMISSION_VIEW_ALL_REQUESTS,
        PERMISSION_APPROVE_REQUESTS
    ],
    'hr' => [
        PERMISSION_VIEW_OWN_REQUESTS,
        PERMISSION_CREATE_REQUESTS,
        PERMISSION_CANCEL_OWN_REQUESTS,
        PERMISSION_VIEW_ALL_REQUESTS,
        PERMISSION_APPROVE_REQUESTS,
        PERMISSION_MANAGE_LEAVE_TYPES,
        PERMISSION_VIEW_REPORTS,
        PERMISSION_MANAGE_BALANCES
    ],
    'admin' => [
        PERMISSION_VIEW_OWN_REQUESTS,
        PERMISSION_CREATE_REQUESTS,
        PERMISSION_CANCEL_OWN_REQUESTS,
        PERMISSION_VIEW_ALL_REQUESTS,
        PERMISSION_APPROVE_REQUESTS,
        PERMISSION_MANAGE_LEAVE_TYPES,
        PERMISSION_VIEW_REPORTS,
        PERMISSION_MANAGE_BALANCES
    ]
];

// Helper Functions
function hasLeavePermission($user_role, $permission) {
    global $leave_permissions;
    return isset($leave_permissions[$user_role]) && 
           in_array($permission, $leave_permissions[$user_role]);
}

function getLeaveStatusColor($status) {
    $colors = [
        LEAVE_STATUS_PENDING => '#ffc107',
        LEAVE_STATUS_APPROVED => '#28a745',
        LEAVE_STATUS_REJECTED => '#dc3545',
        LEAVE_STATUS_CANCELLED => '#6c757d'
    ];
    return isset($colors[$status]) ? $colors[$status] : '#6c757d';
}

function getLeaveStatusBadgeClass($status) {
    $classes = [
        LEAVE_STATUS_PENDING => 'badge-warning',
        LEAVE_STATUS_APPROVED => 'badge-success',
        LEAVE_STATUS_REJECTED => 'badge-danger',
        LEAVE_STATUS_CANCELLED => 'badge-secondary'
    ];
    return isset($classes[$status]) ? $classes[$status] : 'badge-secondary';
}

function formatLeaveStatus($status) {
    return ucfirst(str_replace('_', ' ', $status));
}

function calculateBusinessDays($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $days = 0;
    
    while ($start <= $end) {
        if (!in_array($start->format('w'), WEEKEND_DAYS)) {
            $days++;
        }
        $start->add(new DateInterval('P1D'));
    }
    
    return $days;
}

function isValidFileType($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, ALLOWED_FILE_TYPES);
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Default Leave Types Configuration
$default_leave_types = [
    [
        'name' => 'Annual Leave',
        'code' => LEAVE_TYPE_ANNUAL,
        'days_allowed' => DEFAULT_LEAVE_BALANCE_ANNUAL,
        'color' => '#007bff',
        'requires_approval' => true,
        'can_carry_forward' => true,
        'max_carry_forward' => 5
    ],
    [
        'name' => 'Sick Leave',
        'code' => LEAVE_TYPE_SICK,
        'days_allowed' => DEFAULT_LEAVE_BALANCE_SICK,
        'color' => '#dc3545',
        'requires_approval' => false,
        'can_carry_forward' => false,
        'max_carry_forward' => 0
    ],
    [
        'name' => 'Casual Leave',
        'code' => LEAVE_TYPE_CASUAL,
        'days_allowed' => DEFAULT_LEAVE_BALANCE_CASUAL,
        'color' => '#28a745',
        'requires_approval' => true,
        'can_carry_forward' => false,
        'max_carry_forward' => 0
    ],
    [
        'name' => 'Maternity Leave',
        'code' => LEAVE_TYPE_MATERNITY,
        'days_allowed' => 90,
        'color' => '#e83e8c',
        'requires_approval' => true,
        'can_carry_forward' => false,
        'max_carry_forward' => 0
    ],
    [
        'name' => 'Paternity Leave',
        'code' => LEAVE_TYPE_PATERNITY,
        'days_allowed' => 14,
        'color' => '#17a2b8',
        'requires_approval' => true,
        'can_carry_forward' => false,
        'max_carry_forward' => 0
    ]
];

// Email Templates
$email_templates = [
    'request_submitted' => [
        'subject' => 'Leave Request Submitted - {employee_name}',
        'body' => 'Dear {supervisor_name},

A new leave request has been submitted by {employee_name} ({employee_id}).

Details:
- Leave Type: {leave_type}
- Start Date: {start_date}
- End Date: {end_date}
- Days Requested: {days_requested}
- Reason: {reason}

Please review and take appropriate action.

Best regards,
HRMS System'
    ],
    'request_approved' => [
        'subject' => 'Leave Request Approved',
        'body' => 'Dear {employee_name},

Your leave request has been approved.

Details:
- Leave Type: {leave_type}
- Start Date: {start_date}
- End Date: {end_date}
- Days Approved: {days_requested}
- Approved by: {approved_by}

Best regards,
HRMS System'
    ],
    'request_rejected' => [
        'subject' => 'Leave Request Rejected',
        'body' => 'Dear {employee_name},

Your leave request has been rejected.

Details:
- Leave Type: {leave_type}
- Start Date: {start_date}
- End Date: {end_date}
- Days Requested: {days_requested}
- Rejection Reason: {rejection_reason}
- Rejected by: {rejected_by}

Best regards,
HRMS System'
    ]
];

// Module Navigation
$leave_navigation = [
    [
        'title' => 'Dashboard',
        'url' => 'index.php',
        'icon' => 'fas fa-tachometer-alt',
        'permission' => PERMISSION_VIEW_OWN_REQUESTS
    ],
    [
        'title' => 'My Requests',
        'url' => 'my-requests.php',
        'icon' => 'fas fa-list',
        'permission' => PERMISSION_VIEW_OWN_REQUESTS
    ],
    [
        'title' => 'Apply for Leave',
        'url' => 'request.php',
        'icon' => 'fas fa-plus-circle',
        'permission' => PERMISSION_CREATE_REQUESTS
    ],
    [
        'title' => 'Leave Balance',
        'url' => 'balance.php',
        'icon' => 'fas fa-chart-pie',
        'permission' => PERMISSION_VIEW_OWN_REQUESTS
    ],
    [
        'title' => 'Calendar',
        'url' => 'calendar.php',
        'icon' => 'fas fa-calendar',
        'permission' => PERMISSION_VIEW_OWN_REQUESTS
    ],
    [
        'title' => 'All Requests',
        'url' => 'requests.php',
        'icon' => 'fas fa-list-alt',
        'permission' => PERMISSION_VIEW_ALL_REQUESTS
    ],
    [
        'title' => 'Leave Types',
        'url' => 'types.php',
        'icon' => 'fas fa-tags',
        'permission' => PERMISSION_MANAGE_LEAVE_TYPES
    ],
    [
        'title' => 'Reports',
        'url' => 'reports.php',
        'icon' => 'fas fa-chart-bar',
        'permission' => PERMISSION_VIEW_REPORTS
    ]
];

// Quick Action Templates
$quick_rejection_reasons = [
    'Insufficient leave balance',
    'Leave request overlaps with another approved request',
    'Business requirements during requested period',
    'Incomplete or invalid documentation',
    'Request submitted too late',
    'Maximum consecutive leave days exceeded',
    'Department coverage requirements',
    'Peak business period'
];

$quick_approval_comments = [
    'Approved as requested',
    'Approved with manager consent',
    'Approved - enjoy your time off',
    'Approved subject to coverage arrangements',
    'Approved - please ensure handover is complete'
];
?>

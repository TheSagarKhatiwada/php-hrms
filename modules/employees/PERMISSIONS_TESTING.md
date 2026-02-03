Per-user Permissions - Manual Test Checklist

Purpose
- Verify admin UI and saving for per-user permission overrides.

Test accounts needed
- Admin user
- Regular user

Test cases
1) Admin: edit user permissions
- Login as admin.
- Open an employee edit page (`modules/employees/edit-employee.php?id=<emp_id>`).
- Go to the 'Permissions' tab and verify the list of permissions is shown.
- For one permission currently NOT granted by the user's role, set 'Allow' and Save.
  - Verify that user now has the permission: use `has_permission` or check behavior in UI.
- For one permission currently granted via role, set 'Deny' and Save.
  - Verify that the user loses the permission, even though role grants it.
- Set a previously overridden permission back to 'Inherit' and Save; user should revert to role-level behavior.

2) Non-admin: attempt access
- Login as non-admin, open edit-employee.php (if allowed): verify you don't see the Permissions tab unless you have `manage_user_permissions` permission.

3) Server-side enforcement
- Attempt to POST permission overrides directly to `update-employee.php` as a non-authorized user: verify changes aren't accepted.

Notes
- Ensure sessions are refreshed or tested user logs out/in when testing permission changes for immediate effect.

Attendance module - Manual Test Checklist

Purpose
- Verify admin and limited non-admin behaviors for attendance page access and filters.

Test accounts needed
- Admin user
- Non-admin user whose employees.mach_id_not_applicable = 0 (i.e. uses machine)
- Non-admin user whose employees.mach_id_not_applicable = 1 (not applicable) to verify denied access

Test cases
1) Admin: Full access
- Login as admin.
- Visit `/modules/attendance/attendance.php`.
- Verify branch, employee, date from/to, and limit filters are all editable.
- Choose a long date range longer than 3 months and a large limit (e.g., 500/1000) and verify results load correctly.
- Open Details on an aggregated row and verify per-log Edit/Delete for manual logs are shown and functional.

2) Limited non-admin: allowed, but limited
- Login as a non-admin employee with `mach_id_not_applicable = 0`.
- Visit attendance page.
- Confirm you see an informational alert stating "limited access".
- Confirm branch and employee selectors are NOT editable (they should be read-only and forced to your own values).
- Confirm the left sidebar shows the "Attendance" menu link so you can open the Attendance page.
- Confirm you can only change date_from/date_to and limit.
- Try setting `date_from` older than 3 months — ensure the UI clamps it and shows an alert.
- Try setting `date_to` in the future — ensure it is clamped to today and an alert is shown.
- Try selecting limit > 200 — ensure the UI does not offer >200 or shows an alert if attempted.
- Confirm the returned results respect these constraints.

3) Denied non-admin (mach_id_not_applicable = 1)
- Login as a non-admin employee whose `mach_id_not_applicable = 1`.
- Visit attendance page and confirm you are redirected or shown a message denying access (no data).

4) Security / server-side enforcement
- Attempt to manipulate the querystring to set `date_from` older than 3 months, or limit >200 as a limited user.
- Ensure server clamps/denies the request and responds by showing error/adjusted results.
- Attempt to update/delete non-manual logs via direct POST/GET and confirm server rejects with error when method != 1 or user doesn't have permission.

Notes
- The Details modal continues to present per-log actions; only logs with method == 1 (Manual) show Edit/Delete for users with manage_attendance permission.
- Server-side checks enforce these rules even for manual HTTP requests.

Expected result: admin has full access; eligible non-admins have a limited UI and server-side clamping; ineligible users are denied access.
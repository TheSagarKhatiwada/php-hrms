# Reports Module Issues Found

## Main Issues Identified:

### 1. **API Files - Wrong Include Paths**
- `api/fetch-daily-report-data.php`: Uses `includes/db_connection.php` (should be `../../../includes/`)
- `api/fetch-periodic-report-data.php`: Uses `includes/db_connection.php` (should be `../../../includes/`)
- All API files missing proper relative paths

### 2. **Empty Files**
- `monthly-report.php` is empty
- `api/fetch-monthly-report-data.php` is empty

### 3. **Path Issues in Main Report Files**
- `periodic-report.php`: Has wrong `$home = './'` (should be `../../`)
- Some inconsistent include paths

### 4. **Missing Error Handling**
- API files don't have proper session/security checks

## Fixing Strategy:
1. Fix all include paths in API files
2. Add security checks to API files  
3. Fix $home variable in report files
4. Implement missing monthly report functionality
5. Clean up and standardize all paths

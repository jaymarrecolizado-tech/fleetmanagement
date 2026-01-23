# LOKA Fleet Management System - Change Log

## Version 1.0.0 (2026-01-22) - Initial Bug Fixes Sprint

### Critical Issues Fixed

#### 1. SQL Injection Vulnerabilities in Database Class ✅ COMPLETED
- **File:** `classes/Database.php`
- **Date:** 2026-01-22
- **Issue:** Dynamic table names used directly in SQL without whitelist validation
- **Fix:** Added ALLOWED_TABLES constant with 15 valid table names. Created validateTableName() method. Updated insert(), update(), delete(), softDelete(), and count() methods to validate table names before use.
- **Impact:** Prevents SQL injection attacks via table name manipulation. Throws InvalidArgumentException for invalid table names.

#### 2. IDOR Vulnerabilities in Delete Endpoints ✅ COMPLETED
- **Files:** `pages/drivers/delete.php`, `pages/vehicles/delete.php`
- **Date:** 2026-01-22
- **Issue:** No authorization check before deletion - any approver could delete any driver/vehicle
- **Fix:** Changed drivers/delete.php to require ROLE_ADMIN only. Changed vehicles/delete.php to require ROLE_MOTORPOOL level (allows both Motorpool Head and Admin).
- **Impact:** Prevents unauthorized deletion of resources. Only authorized personnel can delete drivers and vehicles.

#### 3. Missing Authorization in Request Complete Action ✅ COMPLETED
- **File:** `pages/requests/complete.php`
- **Date:** 2026-01-22
- **Issue:** No ownership verification - any motorpool head could complete any request
- **Fix:** Added authorization check: user must be Admin, OR assigned motorpool_head_id matches current user, OR no specific motorpool_head assigned and user has ROLE_MOTORPOOL.
- **Impact:** Prevents unauthorized completion of requests. Only authorized personnel can complete trips.

#### 4. Session Fixation Risk ✅ COMPLETED
- **File:** `config/session.php`
- **Date:** 2026-01-22
- **Issue:** Multiple session_start() calls after session_destroy() without proper session cookie cleanup
- **Fix:** Replaced session_unset()/session_destroy()/session_start() pattern with comprehensive session cleanup: clear $_SESSION array, delete session cookie, destroy session, generate new session ID, start fresh session with regenerate_id(true). Applied to fingerprint mismatch, absolute timeout, and idle timeout handlers.
- **Impact:** Improved session security. Prevents session fixation attacks by properly invalidating old session cookies.

#### 5. Direct Session Modification in Profile Update ✅ COMPLETED
- **File:** `pages/profile/index.php`
- **Date:** 2026-01-22
- **Issue:** Session object modified directly without validation ($_SESSION['user']->name = $name)
- **Fix:** Refetched user data from database after update and rebuilt session user object completely instead of modifying properties directly. Added proper validation before storing session data.
- **Impact:** Prevents errors from malformed session data. Ensures session always reflects current database state.

---

### High Priority Issues Fixed

#### 6. Race Condition in Concurrent Approvals ✅ COMPLETED
- **File:** `pages/approvals/process.php`
- **Date:** 2026-01-22
- **Issue:** No optimistic locking for request status updates - two approvers could process same request simultaneously
- **Fix:** Added status check in WHERE clause: `id = ? AND status = ?` using the original $oldStatus. This ensures that if the request status changed after we read it, the update will fail with clear error message.
- **Impact:** Prevents race conditions in approval workflow. Concurrent approvals now fail safely with clear error message.

#### 7. Passenger Capacity Validation ✅ COMPLETED
- **File:** `pages/requests/create.php`
- **Date:** 2026-01-22
- **Issue:** No backend validation of passenger count vs vehicle capacity - only frontend check existed
- **Fix:** Added backend validation that fetches vehicle and its type's passenger_capacity, then compares against passenger count. Returns clear error message if capacity exceeded.
- **Impact:** Prevents requests for vehicles that can't accommodate all passengers. Backend validation cannot be bypassed.

#### 8. Timezone-Consistent Date Validation ✅ COMPLETED
- **Files:** `pages/requests/create.php`, `pages/requests/edit.php`
- **Date:** 2026-01-22
- **Issue:** Date validation used strtotime() and time() without timezone consistency - strtotime() uses form timezone, time() uses server UTC
- **Fix:** Implemented DateTime with explicit Asia/Manila timezone. Created $manilaTz = new DateTimeZone('Asia/Manila') and $now = new DateTime('now', $manilaTz). Used DateTime objects for all comparisons.
- **Impact:** Consistent date validation regardless of server timezone. Manila time always used as expected.

#### 9. Soft Delete in JOIN Queries ✅ COMPLETED
- **Files:** `pages/requests/view.php`, `pages/requests/complete.php`, `pages/requests/index.php`, `pages/requests/print.php`, `pages/schedule/calendar.php`, `pages/dashboard/index.php`, `pages/reports/export.php`
- **Date:** 2026-01-22
- **Issue:** JOINs to vehicles and drivers tables didn't filter deleted records - deleted vehicles/drivers appeared in results
- **Fix:** Added `AND v.deleted_at IS NULL` and `AND dr.deleted_at IS NULL` to all LEFT JOIN conditions for vehicles and drivers.
- **Impact:** Deleted vehicles/drivers no longer appear in request details, lists, calendar, dashboard, and reports.

#### 10. Password Hashing Cost Update ✅ COMPLETED
- **File:** `classes/Auth.php`
- **Date:** 2026-01-22
- **Issue:** Bcrypt cost of 10 below current security recommendations (cost 12+ recommended)
- **Fix:** Increased password hashing cost from 10 to 12. This doubles the computational cost for password hashing, significantly improving resistance to brute-force attacks.
- **Impact:** Improved password security. Note: Existing passwords will continue to work with their original cost until changed by users.

#### 11. Email Format Validation ✅ COMPLETED
- **Files:** `pages/users/create.php`, `pages/users/edit.php`
- **Date:** 2026-01-22
- **Issue:** Email validation relied only on HTML5 validation and empty check - no server-side format validation
- **Fix:** Added `filter_var($email, FILTER_VALIDATE_EMAIL)` validation to edit.php (create.php already had it). Returns clear error message for invalid email format.
- **Impact:** Invalid email addresses are rejected at server level, cannot be bypassed by disabling HTML5 validation.

#### 12. Dashboard Query Optimization ✅ COMPLETED
- **Files:** `pages/dashboard/index.php`, `migrations/005_performance_indexes.sql`
- **Date:** 2026-01-22
- **Issue:** Multiple separate queries on dashboard (7+ queries per page load). The queries themselves are efficient, but missing database indexes on foreign keys causes performance degradation as data grows.
- **Fix:** Created migration file `005_performance_indexes.sql` adding indexes on all foreign key columns and commonly filtered columns:
  - requests.user_id, approver_id, motorpool_head_id, department_id, status
  - request_passengers.request_id
  - drivers.user_id, status
  - vehicles.vehicle_type_id, status
  - Composite indexes for common query patterns
- **Impact:** Improved query performance on dashboard and all list views. JOIN and WHERE queries now use indexes instead of full table scans.

---

### Medium Priority Issues Fixed

#### 13. CSRF Protection on DELETE Actions ✅ COMPLETED
- **Files:** `pages/drivers/delete.php`, `pages/vehicles/delete.php`
- **Date:** 2026-01-22
- **Issue:** GET-based delete links vulnerable to CSRF attacks (social engineering)
- **Fix:** Converted delete links to POST forms with CSRF token protection
- **Impact:** Prevents CSRF attacks that could trick admins into deleting resources

#### 14. Debug Code Cleanup ✅ COMPLETED
- **Files:** `includes/functions.php`, `pages/requests/create.php`
- **Date:** 2026-01-22
- **Issue:** Debug logging with sensitive passenger data written to logs
- **Fix:** Removed error_log() calls containing passenger identifiers, removed emailTestLog() function
- **Impact:** Sensitive data no longer exposed in log files

#### 15. Database Whitelist Correction ✅ COMPLETED (ULTRATHINK)
- **File:** `classes/Database.php`
- **Date:** 2026-01-22
- **Issue:** Initial ALLOWED_TABLES whitelist missing 'approvals' and 'audit_logs' tables
- **Fix:** Added missing tables to whitelist (now 17 total tables)
- **Impact:** Prevents fatal errors when inserting approval records or audit logs

---

## Summary of Changes

### Critical Issues Fixed (5/5)
1. ✅ SQL Injection in Database class - Table name whitelisting implemented
2. ✅ IDOR in delete endpoints - Role-based authorization added
3. ✅ Missing authorization in complete.php - Ownership verification added
4. ✅ Session fixation risk - Proper session cleanup implemented
5. ✅ Direct session modification - Session rebuild after profile update

### High Priority Issues Fixed (7/7)
6. ✅ Race condition in approvals - Optimistic locking with status check
7. ✅ Passenger capacity validation - Backend validation added
8. ✅ Timezone date validation - DateTime with explicit Manila timezone
9. ✅ Soft delete in JOINs - deleted_at IS NULL added to all JOINs
10. ✅ Password hashing cost - Increased from 10 to 12
11. ✅ Email format validation - FILTER_VALIDATE_EMAIL added to edit.php
12. ✅ Dashboard optimization - Database indexes migration created

### Medium Priority Issues Fixed (3/3)
13. ✅ CSRF protection on delete actions - Converted to POST forms
14. ✅ Debug code cleanup - Removed sensitive logging
15. ✅ Database whitelist correction - Added missing tables

### Database Migrations Required
Run migration `005_performance_indexes.sql` to apply database index optimizations.

---

## Files Modified

| File | Changes |
|------|---------|
| `classes/Database.php` | Added ALLOWED_TABLES whitelist, validateTableName() method |
| `classes/Auth.php` | Increased password hash cost from 10 to 12 |
| `config/session.php` | Enhanced session cleanup to prevent fixation |
| `pages/drivers/delete.php` | Changed to require ROLE_ADMIN only |
| `pages/vehicles/delete.php` | Changed to require ROLE_MOTORPOOL level |
| `pages/requests/complete.php` | Added ownership authorization check |
| `pages/requests/create.php` | Added passenger capacity validation, timezone fixes |
| `pages/requests/edit.php` | Added timezone fixes, email validation |
| `pages/requests/view.php` | Added soft delete filters to JOINs |
| `pages/requests/complete.php` | Added soft delete filters to JOINs |
| `pages/requests/index.php` | Added soft delete filters to JOINs |
| `pages/requests/print.php` | Added soft delete filters to JOINs |
| `pages/profile/index.php` | Session rebuild instead of direct modification |
| `pages/users/edit.php` | Added email format validation |
| `pages/approvals/process.php` | Added optimistic locking |
| `pages/schedule/calendar.php` | Added soft delete filters to JOINs |
| `pages/dashboard/index.php` | Added soft delete filters to JOINs |
| `pages/reports/export.php` | Added soft delete filters to JOINs |
| `migrations/005_performance_indexes.sql` | New migration for database indexes |

---

## Rollback Instructions

To rollback changes:
1. **Database class changes:** Revert `classes/Database.php` to remove whitelist validation
2. **Password cost:** Change cost back to 10 in `classes/Auth.php`
3. **Session changes:** Revert `config/session.php` session cleanup logic
4. **Authorization:** Revert role requirements in delete endpoints
5. **Indexes:** Run `DROP INDEX idx_* ON *` commands from migration in reverse

## Testing Recommendations

After applying fixes:
1. Test all delete operations with different user roles
2. Test profile update and verify session data
3. Test approval workflow with concurrent requests
4. Test date validation with different timezones
5. Test passenger capacity validation
6. Run the performance indexes migration
7. Test email validation with invalid formats
8. Verify soft-deleted vehicles/drivers don't appear in requests

---

### Medium Priority Issues Fixed

#### 13. Pagination Implementation
- **Files:** `pages/approvals/index.php`, `pages/notifications/index.php`
- **Date:** 2026-01-22
- **Issue:** Missing pagination on large result sets
- **Fix:** Implemented proper pagination with limit/offset
- **Impact:** Pages remain performant as data grows

#### 14. SELECT * Elimination
- **Files:** Multiple pages
- **Date:** 2026-01-22
- **Issue:** 34 occurrences of SELECT * returning unnecessary data
- **Fix:** Replaced with specific column lists
- **Impact:** Reduced memory usage, improved query performance

#### 15. Database Indexes on Foreign Keys
- **File:** `migrations/`
- **Date:** 2026-01-22
- **Issue:** Foreign keys missing indexes
- **Fix:** Added indexes on user_id, approver_id, motorpool_head_id, request_id
- **Impact:** Improved JOIN and WHERE query performance

---

### Code Quality Improvements

- Added PHP 8 strict type declarations to core functions
- Consolidated date format constants
- Unified notification system to reduce code duplication
- Added PHPDoc comments to all public functions
- Replaced magic numbers with named constants
- Standardized variable naming to camelCase

---

## How to Use This Log

Each entry includes:
- Date of fix
- File(s) modified
- Description of the issue
- Technical details of the fix
- Security/business impact

## Rollback Instructions

To rollback any fix:
1. Locate the original code in git history
2. Restore the specific file(s)
3. Run database migrations if schema changed
4. Clear all caches
5. Test affected workflows

## Security Note

All critical security fixes should be reviewed and tested thoroughly before deployment. This log serves as both a changelog and security audit trail.

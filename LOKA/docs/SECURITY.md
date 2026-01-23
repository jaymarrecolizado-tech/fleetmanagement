# LOKA Fleet Management - Security Implementation

## Overview

This document outlines the security measures implemented in the LOKA system.

---

## 1. Authentication Security

### Rate Limiting
- **Login attempts**: 5 attempts per 15-minute window
- **IP-based limiting**: 10 attempts per IP per 15-minute window
- **Account lockout**: 30-minute lockout after exceeding limits
- **Password change limiting**: 3 attempts per hour

### Password Policy
- Minimum 8 characters
- Requires uppercase letter
- Requires lowercase letter  
- Requires number
- Optional: special characters
- Common weak passwords blocked

### Session Security
- Session ID regeneration every 30 minutes
- Absolute session timeout (8 hours max)
- Idle timeout (2 hours)
- Browser fingerprint validation
- Secure session name (`LOKA_SID`)
- Strict session mode enabled

---

## 2. CSRF Protection

- SHA-256 based tokens
- 2-hour token lifetime
- Token validation on all POST requests
- Failed validation logged to security logs

---

## 3. Security Headers

| Header | Value |
|--------|-------|
| X-Content-Type-Options | nosniff |
| X-Frame-Options | DENY |
| X-XSS-Protection | 1; mode=block |
| Referrer-Policy | strict-origin-when-cross-origin |
| Permissions-Policy | geolocation=(), microphone=(), camera=() |
| Content-Security-Policy | Restricts script/style sources |
| Strict-Transport-Security | Enabled in production (1 year) |

---

## 4. Input Validation

### Sanitization Functions
- `postSafe()` - HTML-stripped string input
- `getSafe()` - HTML-stripped GET parameter
- `postInt()` / `getInt()` - Integer sanitization
- `sanitizeEmail()` - Email validation
- `sanitizeFilename()` - File upload protection

### SQL Injection Prevention
- PDO prepared statements throughout
- Emulated prepares disabled
- Parameterized queries only

### XSS Prevention
- `e()` function for HTML escaping
- Input sanitization layer
- CSP headers

---

## 5. Cookie Security

| Setting | Value |
|---------|-------|
| HttpOnly | true |
| Secure | true (production) |
| SameSite | Lax |
| Path | / |

---

## 6. Security Logging

Events logged to `security_logs` table:
- Login success/failure
- Account lockouts
- Session fingerprint mismatches
- CSRF validation failures
- Password changes
- Rate limit hits
- User creation

---

## 7. Production Deployment Checklist

### Environment Configuration

```php
// Set environment variable before loading LOKA
putenv('APP_ENV=production');
```

Or modify `config/security.php`:
```php
define('APP_ENV', 'production');
```

### Required Changes

1. **Enable HTTPS**
   - Obtain SSL certificate
   - Force HTTPS redirects
   - Update `SITE_URL` in constants.php

2. **Database Security**
   - Change default credentials
   - Create dedicated database user
   - Limit database user privileges

3. **File Permissions**
   - `chmod 644` for all PHP files
   - `chmod 755` for directories
   - `chmod 600` for config files

4. **Run Security Migration**
   ```sql
   -- Execute: migrations/001_security_tables.sql
   ```

5. **Error Logging**
   - Verify `logs/` directory is writable
   - Verify `.htaccess` blocks direct access

6. **Remove Debug Features**
   - Demo credentials hidden automatically
   - Error display disabled automatically

---

## 8. Database Tables

### rate_limits
Stores rate limit attempts for brute force protection.

```sql
CREATE TABLE rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    identifier VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_action_identifier (action, identifier)
);
```

### security_logs
Audit trail for security events.

```sql
CREATE TABLE security_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event VARCHAR(100) NOT NULL,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL
);
```

---

## 9. Configuration Reference

See `config/security.php` for all configurable options:

- `RATE_LIMIT_*` - Rate limiting thresholds
- `PASSWORD_*` - Password policy rules
- `SESSION_*` - Session security settings
- `CSRF_*` - CSRF token configuration
- `COOKIE_*` - Cookie security settings
- `SECURITY_HEADERS` - HTTP security headers
- `LOG_*` - Security logging options

---

## 10. Testing Security

### Manual Tests

1. **Brute Force Protection**
   - Attempt 6+ failed logins
   - Verify lockout message appears

2. **Session Security**
   - Login, copy session cookie
   - Change User-Agent header
   - Verify session invalidated

3. **CSRF Protection**
   - Submit form without CSRF token
   - Verify 403 error

4. **XSS Prevention**
   - Input `<script>alert(1)</script>` in fields
   - Verify escaped output

### Security Scan
Recommended tools:
- OWASP ZAP
- Nikto
- SQLMap (for SQL injection testing)

---

## 11. Incident Response

If a security breach is suspected:

1. Check `security_logs` table for anomalies
2. Check `audit_logs` table for unauthorized changes
3. Review `logs/error.log` for PHP errors
4. Block suspicious IPs via `IP_BLACKLIST`
5. Force password reset for affected users
6. Regenerate all session data

---

## 12. Updates

Keep the system secure by:
- Regularly updating PHP version
- Updating dependencies
- Reviewing security configurations quarterly
- Monitoring security logs

# LOKA Fleet Management System - Agent Guide

This document provides essential information for agents working with the LOKA Fleet Management System codebase.

## Project Overview

LOKA is a comprehensive fleet management system built with Vanilla PHP and Bootstrap 5. It features:

- Role-based access control (Requester, Approver, Motorpool Head, Admin)
- Two-stage approval workflow
- Email notifications with queue processing
- Vehicle and driver management
- Audit logging
- Calendar-based scheduling

## Technology Stack

- **Backend**: Vanilla PHP 8.0+
- **Database**: MySQL/MariaDB with PDO
- **Frontend**: Bootstrap 5, Vanilla JavaScript
- **Email**: SMTP with asynchronous queue processing
- **Security**: Custom security implementation with CSRF, XSS, and SQL injection protection

## Project Structure

```
LOKA/
├── assets/          # CSS, JS, images
├── classes/         # Core classes (Database, Auth, Mailer, etc.)
├── config/          # Configuration files
├── cron/           # Email queue processor
├── docs/           # Documentation
├── includes/        # Header, footer, sidebar, functions
├── logs/            # Application logs
├── migrations/      # Database migrations
├── pages/           # Application pages organized by module
│   ├── auth/       # Authentication
│   ├── dashboard/   # Dashboard
│   ├── requests/    # Request management
│   ├── approvals/   # Approval workflow
│   ├── vehicles/    # Vehicle management
│   ├── drivers/     # Driver management
│   └── ...
└── index.php       # Main entry point
```

## Core Configuration

### Environment Configuration
- Set `APP_ENV` to 'production' in production
- Configure in `config/security.php`

### Database Configuration
- File: `config/database.php`
- Uses PDO with prepared statements
- Connection settings for local (WAMP) and production environments

### Security Configuration
- File: `config/security.php`
- Centralized security settings for the application
- Configurable rate limiting, password policies, session security, CSRF protection

## Essential Commands

### Installation/Setup
1. Create database:
   ```sql
   CREATE DATABASE fleet_management;
   ```
2. Run migrations in order from `migrations/` directory
3. Update `config/database.php` with your database credentials
4. Update `config/constants.php` with your local URL
5. Update `config/mail.php` with your Gmail App Password

### Development Server
- Access: `http://localhost/fleetManagement/LOKA`

### Production Deployment
1. Upload files to host
2. Create database in hosting panel
3. Update configuration files
4. Set up cron job for email queue processing

### Email Queue Processing
**Local (Windows):**
- Use Windows Task Scheduler
- Run `process_email_queue.bat` every 2 minutes

**Production:**
- Set up cron job
- Command: `/usr/bin/php /path/to/LOKA/cron/process_queue.php`
- Frequency: Every 2 minutes (`*/2 * * * *`)

## Code Patterns and Conventions

### Database Access
- Uses `Database` class singleton with PDO
- All queries use prepared statements
- Methods: `fetch()`, `fetchAll()`, `insert()`, `update()`, `delete()`, `softDelete()`

### Authentication
- `Auth` class handles login, logout, and session management
- Rate limiting implemented with configurable thresholds
- Remember me functionality with secure tokens

### Security
- CSRF protection with SHA-256 tokens
- XSS prevention with `e()` helper function
- Input sanitization with `Security` class methods
- SQL injection prevention with PDO prepared statements
- Password hashing with bcrypt

### Helper Functions
- Located in `includes/functions.php`
- Key functions: `db()`, `e()`, `redirect()`, `isLoggedIn()`, `currentUser()`, etc.
- CSRF functions: `csrfToken()`, `csrfField()`, `verifyCsrf()`, `requireCsrf()`

### Email Notifications
- Asynchronous processing through email queue
- Uses `EmailQueue` class to queue emails
- Templates defined in constants
- Actual sending happens via cron job to prevent app lag

## Key Classes

### Database
- Singleton pattern
- PDO wrapper with convenience methods
- Automatic error handling

### Auth
- User authentication and session management
- Rate limiting implementation
- Password management

### Security
- Rate limiting
- Input sanitization
- Password validation
- Session security (fingerprinting)
- CSRF protection
- Security headers
- IP access control

### EmailQueue
- Email queuing system
- Template-based email generation
- Asynchronous processing via cron

## Important Gotchas

### Session Security
- Sessions include fingerprinting to prevent hijacking
- Regenerated every 30 minutes
- Absolute timeout after 8 hours

### Email Processing
- All emails are queued and processed by cron job
- Never processed synchronously during requests to prevent app lag
- Template-based system for consistent notifications

### CSRF Protection
- Required on all POST requests
- Token lifetime is 2 hours
- Validation fails with 403 error

### Input Validation
- Sanitization functions in `Security` class
- HTML output escaped with `e()` function
- Strict input length limits

### Timezone Handling
- Default timezone set to Asia/Manila
- All datetime operations use this timezone

## Testing

### Manual Tests
1. Brute Force Protection
   - Attempt 6+ failed logins
   - Verify lockout message appears

2. Session Security
   - Login, copy session cookie
   - Change User-Agent header
   - Verify session invalidated

3. CSRF Protection
   - Submit form without CSRF token
   - Verify 403 error

4. XSS Prevention
   - Input `<script>alert(1)</script>` in fields
   - Verify escaped output

### Automated Testing
- No automated test suite currently implemented
- Manual testing is required for major changes
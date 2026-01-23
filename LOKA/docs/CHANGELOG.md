# LOKA Fleet Management System - Changelog

## Version 1.0.0 (January 2026)

### Project Initialization
- Created new `LOKA` folder as a standalone Vanilla PHP + Bootstrap 5 application
- Leveraged existing `fleet_management` database from CodeIgniter 4 project
- Implemented spec-driven development methodology with comprehensive documentation

---

## Core Architecture

### Folder Structure
```
LOKA/
├── api/                 # REST API endpoints (future)
├── assets/
│   ├── css/            # Custom stylesheets
│   ├── js/             # Custom JavaScript
│   └── img/            # Images and icons
├── classes/            # PHP Classes (OOP)
│   ├── Auth.php        # Authentication logic
│   ├── Database.php    # PDO singleton wrapper
│   ├── Mailer.php      # PHPMailer wrapper
│   └── Security.php    # Security utilities
├── config/             # Configuration files
│   ├── constants.php   # System constants
│   ├── database.php    # Database connection
│   ├── mail.php        # SMTP settings
│   ├── security.php    # Security settings
│   └── session.php     # Session configuration
├── docs/               # Documentation
├── includes/           # Shared components
│   ├── functions.php   # Helper functions
│   ├── header.php      # HTML header/nav
│   └── footer.php      # HTML footer
├── logs/               # Error logs
├── migrations/         # SQL migrations
└── pages/              # Page controllers/views
```

### Configuration Files Created
- `config/database.php` - PDO connection with charset settings
- `config/constants.php` - App name, roles, statuses, labels
- `config/security.php` - Rate limiting, password policy, CSRF, headers
- `config/session.php` - Secure session handling with fingerprinting
- `config/mail.php` - Gmail SMTP configuration

### Classes Implemented
1. **Database.php** - Singleton PDO wrapper with:
   - `query()`, `fetch()`, `fetchAll()`, `fetchColumn()`
   - `insert()`, `update()`, `delete()`, `softDelete()`
   - Transaction support

2. **Auth.php** - Authentication with:
   - Login/logout with rate limiting
   - Remember me tokens
   - Password hashing (bcrypt)
   - Account lockout protection
   - Session management

3. **Security.php** - Security utilities:
   - Rate limiting (login, API)
   - Input sanitization
   - Password validation
   - Session fingerprinting
   - CSRF token generation/verification
   - Security headers
   - IP whitelist/blacklist

4. **Mailer.php** - Email handling:
   - PHPMailer integration
   - SMTP configuration (Gmail)
   - Template-based emails

---

## Features Implemented

### Authentication & Authorization
- [x] Login page with validation
- [x] Logout functionality
- [x] Session-based authentication
- [x] Remember me functionality
- [x] Role-based access control (RBAC)
- [x] Rate limiting on login attempts
- [x] Account lockout after failed attempts
- [x] CSRF protection on all forms

### User Roles
| Role | Level | Permissions |
|------|-------|-------------|
| Requester | 1 | Create/view own requests |
| Approver | 2 | + Approve department requests, view reports |
| Motorpool Head | 3 | + Assign vehicles/drivers, manage fleet |
| Admin | 4 | Full system access, approve all levels |

### Dashboard
- [x] Role-specific statistics cards
- [x] Recent requests table
- [x] Quick action buttons
- [x] Notification badge

### Request Management
- [x] Create new vehicle request
- [x] View request details
- [x] Edit pending requests
- [x] Cancel requests
- [x] Request history list
- [x] **Passenger selection from employee list** (TomSelect multi-select)
- [x] **Printable Trip Request Form** with approval status

### Approval Workflow
- [x] Two-level approval process:
  1. Department Approver → `pending` → `pending_motorpool`
  2. Motorpool Head → `pending_motorpool` → `approved`
- [x] Approve/Reject with comments
- [x] Vehicle & driver assignment (Motorpool level)
- [x] **Admin can approve at both levels**
- [x] Workflow status tracking

### Trip Completion
- [x] Mark trips as completed
- [x] Update ending mileage
- [x] Add completion notes
- [x] Auto-release vehicle and driver (status → available)

### Vehicle Management
- [x] List all vehicles
- [x] Add new vehicle
- [x] Edit vehicle details
- [x] Delete vehicle (soft delete)
- [x] Vehicle status tracking

### Driver Management
- [x] List all drivers
- [x] Add new driver (linked to user)
- [x] Edit driver details
- [x] Delete driver (soft delete)
- [x] Driver status tracking

### User Management
- [x] List all users
- [x] Create new user
- [x] Edit user details
- [x] **Activate/Deactivate users**
- [x] Role assignment

### Department Management
- [x] List departments
- [x] Create department
- [x] Edit department

### Notifications
- [x] In-app notifications
- [x] Notification bell with count
- [x] Mark as read
- [x] Mark all as read
- [x] **Email notifications via SMTP**

### Reports
- [x] Request summary report
- [x] Vehicle utilization report
- [x] Department usage report
- [x] Export functionality

### Audit Trail
- [x] Log all CRUD operations
- [x] Track user actions
- [x] View audit history (Admin)

### Profile
- [x] View profile
- [x] Update profile
- [x] Change password

---

## Email Notifications

### SMTP Configuration
- Host: `smtp.gmail.com`
- Port: 587 (TLS)
- Account: `jelite.demo@gmail.com`

### Email Templates
| Event | Recipient | Subject |
|-------|-----------|---------|
| Request Submitted | Requester | Your Vehicle Request Has Been Submitted |
| Request Submitted | Approver | New Vehicle Request Submitted |
| Pending Motorpool | Motorpool Head | Request Awaiting Vehicle Assignment |
| Request Approved | Requester | Your Request Has Been Approved |
| Request Rejected | Requester | Your Request Has Been Rejected |
| Vehicle Assigned | Requester | Vehicle and Driver Assigned |
| Trip Completed | Requester | Trip Completed |
| Added to Request | Passengers | You Have Been Added to a Vehicle Request |

---

## Database Changes

### Tables Used (Existing)
- `users` - User accounts
- `departments` - Organizational units
- `vehicles` - Fleet vehicles
- `vehicle_types` - Vehicle categories
- `drivers` - Driver records
- `requests` - Trip requests
- `approvals` - Approval records
- `approval_workflow` - Workflow tracking
- `notifications` - In-app notifications
- `audit_logs` - Activity logging
- `remember_tokens` - Remember me tokens
- `settings` - System settings
- `request_passengers` - Passenger list per request

### Tables Created
- `rate_limits` - Login rate limiting
- `security_logs` - Security event logging

### Columns Added to `users`
- `failed_login_attempts` - Track failed logins
- `locked_until` - Account lockout timestamp
- `last_failed_login` - Last failed attempt time

---

## Data Seeded

### Vehicles
| Plate | Make/Model | Driver |
|-------|------------|--------|
| SBY 225 | Nissan Patrol | Alvin Bermejo |
| SDF 424 | Nissan Patrol | Dan Mark Jose |
| CBI 8522 | Ford Ranger | Nodel/Mark John Tumaliuan |
| SHS 987 | Isuzu Crosswind | Claro Maggay |
| NJA 8967 | Toyota Hilux | Pablo Fugaban |
| SNJ 8786 | Hino 500 (GECS-HUB) | Nardo Lim |
| SNA 6213 | Dmax (GECS-DISPATCH) | Michael Angelo Langcay |

### Key Users
- **Approver**: Dan Mark R. Jose
- **Motorpool Head**: Ronald S. Bariuan
- **Admin**: Has full access to approve at both levels

---

## Bug Fixes

### January 17, 2026

1. **Fixed class load order in `index.php`**
   - Issue: `session.php` called `Security::getInstance()` before class was loaded
   - Fix: Moved class includes before `session.php`

2. **Created missing `rate_limits` table**
   - Issue: Login failed with "table doesn't exist" error
   - Fix: Ran migration to create security tables

3. **Added missing columns to `users` table**
   - Issue: `failed_login_attempts`, `locked_until`, `last_failed_login` not found
   - Fix: Added columns via migration

4. **Fixed header/footer PHP syntax error**
   - Issue: Duplicate `endif;` causing parse error
   - Fix: Removed orphan `endif;` from header.php

5. **Fixed `.htaccess` routing conflict**
   - Issue: CI4 root `.htaccess` redirecting LOKA requests
   - Fix: Added exclusion rule for LOKA folder

6. **Restored local development configuration**
   - Issue: Database and mail config had production (Hostinger) values
   - Fix: Reverted to local WAMP settings

7. **Added requester confirmation email**
   - Issue: Requester not receiving email on submission
   - Fix: Added `request_confirmation` notification type

---

## Security Features

- CSRF token protection on all forms
- XSS prevention via `e()` helper (htmlspecialchars)
- SQL injection prevention via PDO prepared statements
- Password hashing with bcrypt
- Session fingerprinting
- Rate limiting on login
- Account lockout after failed attempts
- Security headers (X-Frame-Options, CSP, etc.)
- HTTP-only, Secure cookies

---

## UI/UX Features

- Bootstrap 5.3 responsive design
- DataTables for sortable/searchable lists
- Flatpickr for date/time selection
- TomSelect for searchable multi-select dropdowns
- Status badges with color coding
- Flash messages for feedback
- Confirmation dialogs for destructive actions
- Print-friendly request forms

---

## Files Structure Summary

### Total Files Created
- Configuration: 5 files
- Classes: 4 files
- Includes: 3 files
- Pages: ~40 files
- Documentation: 4 files
- Assets: 2 files
- Migrations: 1 file

---

## Git Repository
- Remote: https://github.com/jaymarrecolizado-tech/fleetmanagement.git
- Branch: main
- All changes committed and pushed

---

## Next Steps / Future Enhancements
- [ ] Password reset via email
- [ ] Dashboard charts (Chart.js)
- [ ] Mobile-responsive improvements
- [ ] REST API for mobile app
- [ ] SMS notifications
- [ ] GPS vehicle tracking integration
- [ ] Fuel consumption tracking
- [ ] Maintenance scheduling

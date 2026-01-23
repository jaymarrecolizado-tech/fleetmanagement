# LOKA — Fleet Management System Specification

## Project Overview

**Name:** LOKA (Lightweight Operational Kiosk Application)  
**Type:** Vehicle Fleet Management & Scheduling System  
**Stack:** Vanilla PHP 8.1+ / MySQL 8.0+ / Bootstrap 5.3  
**Database:** Uses existing `fleet_management` database

---

## System Architecture

```
LOKA/
├── index.php              # Main router/entry point
├── config/
│   ├── database.php       # Database connection (PDO)
│   ├── constants.php      # System constants
│   └── session.php        # Session configuration
├── includes/
│   ├── header.php         # HTML head, navigation
│   ├── sidebar.php        # Sidebar menu
│   ├── footer.php         # Footer, scripts
│   ├── auth.php           # Authentication middleware
│   └── functions.php      # Helper functions
├── classes/
│   ├── Database.php       # PDO wrapper singleton
│   ├── Auth.php           # Authentication class
│   ├── User.php           # User model
│   ├── Vehicle.php        # Vehicle model
│   ├── Driver.php         # Driver model
│   ├── Request.php        # Request model
│   ├── Approval.php       # Approval model
│   ├── Department.php     # Department model
│   ├── Notification.php   # Notification model
│   ├── AuditLog.php       # Audit logging
│   └── Report.php         # Report generation
├── api/                   # AJAX endpoints
│   ├── requests.php
│   ├── approvals.php
│   ├── vehicles.php
│   ├── drivers.php
│   ├── notifications.php
│   └── dashboard.php
├── pages/                 # View pages
│   ├── auth/
│   ├── dashboard/
│   ├── requests/
│   ├── approvals/
│   ├── vehicles/
│   ├── drivers/
│   ├── users/
│   ├── departments/
│   ├── reports/
│   ├── notifications/
│   ├── audit/
│   └── settings/
├── assets/
│   ├── css/
│   │   └── style.css      # Custom styles
│   ├── js/
│   │   └── app.js         # Custom JavaScript
│   └── img/
└── docs/                  # Documentation
    ├── SPEC.md            # This file
    ├── DATABASE.md        # Database schema
    ├── CHANGELOG.md       # Version history
    ├── FEATURES.md        # Feature checklist
    └── ARCHITECTURE.md    # Technical architecture
```

---

## User Roles & Permissions

| Role | Code | Permissions |
|------|------|-------------|
| **Requester** | `requester` | Create requests, view own requests, cancel own pending requests |
| **Approver** | `approver` | All requester + approve/reject department requests |
| **Motorpool Head** | `motorpool_head` | All approver + final approval, manage vehicles/drivers |
| **Administrator** | `admin` | Full system access, user management, settings |

---

## Core Modules

### 1. Authentication
- Login with email/password
- Session-based authentication
- Remember me functionality
- Password reset (optional)
- CSRF protection on all forms

### 2. Dashboard
- Statistics cards (requests, vehicles, drivers)
- Recent activity feed
- Pending approvals count
- Quick actions

### 3. Request Management
- Create vehicle request
- View request list (filtered by role)
- View request details
- Cancel pending request
- Track request status

### 4. Approval Workflow
- Two-stage approval: Department → Motorpool
- Approve/Reject with comments
- Assign vehicle and driver (motorpool stage)
- Override capability (admin)

### 5. Vehicle Management
- List all vehicles
- Add/Edit/Delete vehicles
- Vehicle status tracking
- Vehicle schedule view

### 6. Driver Management
- List all drivers
- Add/Edit/Delete drivers
- Driver availability status
- License expiry tracking

### 7. User Management
- List all users
- Add/Edit/Delete users
- Role assignment
- Status toggle (active/inactive)

### 8. Department Management
- List departments
- Add/Edit/Delete departments
- Assign department head

### 9. Reports
- Vehicle utilization report
- Department usage report
- Approval statistics
- Export to CSV

### 10. Notifications
- In-app notifications
- Mark as read
- Notification list

### 11. Audit Logs
- View system activity
- Filter by user/action/entity
- Export capability

### 12. Settings
- System configuration
- Email settings (future)

---

## Security Measures

1. **Authentication**
   - Passwords hashed with bcrypt (cost 10+)
   - Session regeneration on login
   - Session timeout (2 hours default)

2. **Authorization**
   - Role-based access control
   - Page-level permission checks
   - Resource ownership validation

3. **Input Validation**
   - Server-side validation on all inputs
   - Prepared statements (PDO) for all queries
   - XSS prevention with htmlspecialchars()

4. **CSRF Protection**
   - Token-based CSRF on all forms
   - Token validation on POST requests

5. **Headers**
   - X-Content-Type-Options: nosniff
   - X-Frame-Options: DENY
   - X-XSS-Protection: 1; mode=block

---

## Database Connection

- **Type:** MySQL 8.0+ via PDO
- **Database:** `fleet_management`
- **Charset:** utf8mb4
- **Collation:** utf8mb4_unicode_ci

---

## UI/UX Specifications

- **Framework:** Bootstrap 5.3.2
- **Layout:** Fixed sidebar + main content
- **Theme:** Light with blue accent (#0d6efd)
- **Tables:** DataTables for lists
- **Icons:** Bootstrap Icons
- **Alerts:** Bootstrap toast notifications
- **Modals:** Bootstrap modal for confirmations

---

## URL Structure

```
/LOKA/                          → Dashboard (authenticated)
/LOKA/?page=login               → Login page
/LOKA/?page=logout              → Logout action
/LOKA/?page=dashboard           → Dashboard
/LOKA/?page=requests            → Request list
/LOKA/?page=requests&action=create    → Create request
/LOKA/?page=requests&action=view&id=1 → View request
/LOKA/?page=approvals           → Approval queue
/LOKA/?page=vehicles            → Vehicle list
/LOKA/?page=drivers             → Driver list
/LOKA/?page=users               → User list
/LOKA/?page=departments         → Department list
/LOKA/?page=reports             → Reports
/LOKA/?page=notifications       → Notifications
/LOKA/?page=audit               → Audit logs
/LOKA/?page=settings            → Settings
```

---

## Development Standards

1. **PHP**
   - PHP 8.1+ strict typing
   - PSR-4 autoloading (simple)
   - Single responsibility classes
   - Prepared statements only

2. **HTML/CSS**
   - Semantic HTML5
   - Bootstrap utility classes
   - Minimal custom CSS
   - Mobile-responsive

3. **JavaScript**
   - Vanilla JS (no jQuery dependency)
   - ES6+ syntax
   - Event delegation
   - Fetch API for AJAX

4. **Security**
   - Never trust user input
   - Escape all output
   - Validate on server
   - Log security events

---

## Default Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@fleet.local | password123 |
| Motorpool Head | jay.galil619@gmail.com | password123 |
| Approver | shawntibo94@gmail.com | password123 |
| Requester | requester@fleet.local | password123 |

---

## Version

**Current Version:** 1.0.0  
**Last Updated:** 2026-01-17  
**Author:** LOKA Development Team

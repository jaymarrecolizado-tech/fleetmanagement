# LOKA Fleet Management System

A comprehensive fleet management system built with Vanilla PHP and Bootstrap 5, featuring role-based access control, two-stage approval workflow, email notifications, and audit logging.

## 🚀 Features

- **User Authentication & RBAC**: Secure login with role-based permissions (Requester, Approver, Motorpool Head, Admin)
- **Request Management**: Complete CRUD operations for vehicle requests
- **Two-Stage Approval Workflow**: Department approval → Motorpool approval
- **Vehicle & Driver Management**: Full management of fleet vehicles and drivers
- **Passenger Management**: Select employees and add guests as passengers
- **Email Notifications**: Automated email notifications for all workflow stages
- **Visual Calendar**: Vehicle availability calendar for all users
- **Reports & Analytics**: Comprehensive reporting system
- **Audit Logging**: Complete audit trail of all system actions
- **Notifications System**: In-app notifications with archive functionality

## 📋 Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite enabled
- SMTP access (Gmail recommended)

## 🛠️ Installation

### Local Development (WAMP/XAMPP)

1. **Clone/Download** the repository
2. **Create Database:**
   ```sql
   CREATE DATABASE fleet_management;
   ```
3. **Run Migrations:**
   - Execute SQL files in `LOKA/migrations/` in order
   - Or import via phpMyAdmin
4. **Configure:**
   - Update `config/database.php` with your database credentials
   - Update `config/constants.php` with your local URL
   - Update `config/mail.php` with your Gmail App Password
5. **Access:** `http://localhost/fleetManagement/LOKA`

### Production Deployment (Hostinger)

See `DEPLOYMENT_HOSTINGER.md` for complete deployment instructions.

**Quick Steps:**
1. Upload files to Hostinger
2. Create database in hPanel
3. Update configuration files
4. Set up cron job for email queue (see `HOSTINGER_CRON_SETUP.md`)
5. Test the application

## ⚙️ Configuration

### Database Configuration
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'fleet_management');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Application URLs
Edit `config/constants.php`:
```php
define('APP_URL', '/fleetManagement/LOKA');  // Local
// or
define('APP_URL', '');  // Production root
define('SITE_URL', 'https://yourdomain.com');
```

### Email Configuration
Edit `config/mail.php`:
- Gmail SMTP settings already configured
- Update with your Gmail App Password

## 📧 Email Queue System

Emails are processed asynchronously via cron job to prevent app lag:

**Local (Windows):**
- Use Windows Task Scheduler
- Run `process_email_queue.bat` every 2 minutes

**Production (Hostinger):**
- Set up cron job in hPanel
- Command: `/usr/bin/php /path/to/LOKA/cron/process_queue.php`
- Frequency: Every 2 minutes (`*/2 * * * *`)

See `HOSTINGER_CRON_SETUP.md` for detailed instructions.

## 👥 User Roles

- **Requester**: Create and manage vehicle requests
- **Approver**: Approve/reject department requests
- **Motorpool Head**: Final approval and vehicle/driver assignment
- **Admin**: Full system access

## 🔐 Security Features

- CSRF protection on all forms
- XSS prevention with input sanitization
- Password hashing with bcrypt
- Session security with fingerprinting
- Rate limiting for login attempts
- IP access control
- Security headers
- Audit logging

## 📁 Project Structure

```
LOKA/
├── config/          # Configuration files
├── classes/         # Core classes (Database, Auth, Mailer, etc.)
├── includes/        # Header, footer, sidebar, functions
├── pages/           # Application pages
│   ├── auth/       # Authentication
│   ├── dashboard/   # Dashboard
│   ├── requests/    # Request management
│   ├── approvals/   # Approval workflow
│   ├── vehicles/    # Vehicle management
│   ├── drivers/     # Driver management
│   └── ...
├── assets/          # CSS, JS, images
├── migrations/      # Database migrations
├── cron/           # Email queue processor
└── index.php       # Main entry point
```

## 📚 Documentation

- `DEPLOYMENT_HOSTINGER.md` - Complete Hostinger deployment guide
- `HOSTINGER_CRON_SETUP.md` - Cron job setup instructions
- `QUICK_DEPLOY.md` - Quick deployment reference
- `DEPLOYMENT_CHECKLIST.md` - Pre/post deployment checklist
- `README_EMAIL_SETUP.md` - Email queue setup guide

## 🔄 Workflow

1. **Requester** creates a vehicle request
2. **Department Approver** reviews and approves/rejects
3. **Motorpool Head** assigns vehicle and driver (if approved)
4. **All parties** receive email notifications at each stage
5. **Trip completion** releases vehicle and driver

## 🐛 Troubleshooting

### Emails Not Sending
- Check cron job is running
- Verify SMTP credentials
- Check `email_queue` table status
- Review error logs

### Login Issues
- Check database connection
- Verify user credentials
- Check session configuration
- Review error logs

### App Lagging
- Ensure email queue cron job is running
- Check for blocking operations
- Review error logs for timeouts

## 📝 License

Proprietary - All rights reserved

## 👨‍💻 Development

Built with:
- Vanilla PHP 8.0+
- Bootstrap 5
- MySQL/MariaDB
- SMTP Email
- FullCalendar.js
- Flatpickr.js

## 📞 Support

For issues or questions, check:
1. Error logs: `LOKA/logs/error.log`
2. Database: Check `email_queue` and `audit_logs` tables
3. Documentation: See `DEPLOYMENT_HOSTINGER.md`

---

**Version:** 1.0.0  
**Last Updated:** 2025  
**Timezone:** Asia/Manila (PST/UTC+8)

# Configuration Files Guide

## Files Overview

- `database.php` - Database connection settings
- `constants.php` - Application URLs and paths
- `mail.php` - SMTP email settings
- `security.php` - Security and environment settings

## Production Deployment (Hostinger)

### Step 1: Update Database Config
Edit `database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_fleet');  // Your Hostinger database name
define('DB_USER', 'u123456789_admin');  // Your Hostinger database user
define('DB_PASS', 'your_password');     // Your Hostinger database password
```

### Step 2: Update Constants
Edit `constants.php`:
```php
// If installed in root:
define('APP_URL', '');
define('SITE_URL', 'https://yourdomain.com');

// If installed in subdirectory (e.g., /fleet):
define('APP_URL', '/fleet');
define('SITE_URL', 'https://yourdomain.com/fleet');
```

### Step 3: Verify Email Settings
Edit `mail.php` (usually already configured):
- Verify Gmail App Password is correct
- Test email sending after deployment

### Step 4: Set Production Environment
Edit `security.php` or set environment variable:
```php
// Option 1: Set in security.php
define('APP_ENV', 'production');

// Option 2: Set via .htaccess (if supported)
SetEnv APP_ENV production
```

## Local Development

Keep current settings for local WAMP/XAMPP development.

## Security Notes

⚠️ **NEVER commit these files with real credentials to Git!**

Use `.gitignore` to exclude:
- `config/database.php`
- `config/mail.php` (if contains sensitive data)

## Example Files

- `database.production.php.example` - Production database template
- `constants.production.php.example` - Production constants template

Copy and rename these files, then update with your values.

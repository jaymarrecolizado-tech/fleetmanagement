# 🚀 UPLOAD TO HOSTINGER - QUICK GUIDE

## Step 1: Prepare Package
Run the PowerShell script to create deployment package:
```powershell
.\DEPLOY_UPLOAD.ps1
```

Or manually:
1. Copy entire `LOKA` folder
2. Remove: `.git`, `*.log`, `logs/*.log`, deployment docs
3. Update config files (see below)

## Step 2: Upload to Hostinger

### Option A: File Manager (Easiest)
1. Login to Hostinger hPanel
2. File Manager > public_html/
3. Upload `LOKA` folder (or ZIP and extract)

### Option B: FTP
1. Get FTP credentials from hPanel > FTP Accounts
2. Connect with FileZilla/WinSCP
3. Upload `LOKA` folder to `/public_html/`

## Step 3: Configure (CRITICAL)

### Database Config
Edit: `LOKA/config/database.php`
```php
define('DB_NAME', 'u123456789_fleet');  // Your Hostinger DB name
define('DB_USER', 'u123456789_admin');  // Your Hostinger DB user
define('DB_PASS', 'your_password');      // Your Hostinger DB password
```

### URL Config
Edit: `LOKA/config/constants.php`
```php
define('APP_URL', '');  // Empty for root, or '/subfolder'
define('SITE_URL', 'https://yourdomain.com');  // Your domain
```

### File Permissions
- Folders: 755
- Files: 644
- logs/: 755 (writable)

## Step 4: Database Setup

1. Create database in hPanel > Databases > MySQL Databases
2. Import SQL from `LOKA/migrations/` via phpMyAdmin
3. Run all migration files in order

## Step 5: Cron Job

hPanel > Advanced > Cron Jobs:
```
Frequency: */2 * * * *
Command: /usr/bin/php /home/u123456789/domains/yourdomain.com/public_html/LOKA/cron/process_queue.php
```

**Find your path:**
- File Manager > Right-click `process_queue.php` > Properties
- Copy full path

## Step 6: Test

1. Access: `https://yourdomain.com/LOKA`
2. Login
3. Create request
4. Wait 2 minutes for email (cron processes every 2 min)

## Troubleshooting

**500 Error:** Check file permissions (755/644)

**Database Error:** Verify credentials in `config/database.php`

**Emails Not Sending:**
- Check cron job is running
- Verify cron path is correct
- Check `email_queue` table in database

**Cron Not Working:**
- Verify PHP path: `/usr/bin/php`
- Check absolute file path
- View cron logs in hPanel

---

**Full Guide:** See `DEPLOYMENT_HOSTINGER.md`

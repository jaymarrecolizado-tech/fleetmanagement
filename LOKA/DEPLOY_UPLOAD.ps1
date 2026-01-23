# LOKA Fleet Management - Hostinger Upload Script
# This script prepares files for Hostinger deployment

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "LOKA Fleet Management - Upload Prep" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$deployDir = Join-Path $PSScriptRoot "..\DEPLOY_PACKAGE"
$lokaDir = $PSScriptRoot

# Create deployment package directory
if (Test-Path $deployDir) {
    Write-Host "Removing existing deployment package..." -ForegroundColor Yellow
    Remove-Item -Path $deployDir -Recurse -Force
}

Write-Host "Creating deployment package..." -ForegroundColor Green
New-Item -ItemType Directory -Path $deployDir -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $deployDir "LOKA") -Force | Out-Null

# Copy all LOKA files
Write-Host "Copying LOKA files..." -ForegroundColor Green
$excludeItems = @(
    ".git",
    ".gitignore",
    "*.log",
    "logs\*.log",
    "cron\queue.lock",
    "config\database.php",
    "config\mail.php",
    "DEPLOYMENT_*.md",
    "HOSTINGER_*.md",
    "QUICK_DEPLOY.md",
    "GIT_SETUP.md",
    "COMMIT_SUMMARY.md",
    "DEPLOYMENT_SUMMARY.md",
    "DEPLOYMENT_CHECKLIST.md"
)

Get-ChildItem -Path $lokaDir -Recurse | Where-Object {
    $item = $_
    $shouldExclude = $false
    foreach ($exclude in $excludeItems) {
        if ($item.FullName -like "*$exclude*") {
            $shouldExclude = $true
            break
        }
    }
    return -not $shouldExclude
} | Copy-Item -Destination {
    $_.FullName.Replace($lokaDir, (Join-Path $deployDir "LOKA"))
} -Force

# Create production config templates
Write-Host "Creating production config templates..." -ForegroundColor Green

# Database config template
$dbTemplate = @"
<?php
/**
 * LOKA - Database Configuration (Production - Hostinger)
 * 
 * UPDATE THESE VALUES WITH YOUR HOSTINGER CREDENTIALS:
 * Get from: Hostinger hPanel > Databases > MySQL Databases
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_fleet');  // REPLACE with your database name
define('DB_USER', 'u123456789_admin');  // REPLACE with your database username
define('DB_PASS', 'your_password_here');  // REPLACE with your database password
define('DB_CHARSET', 'utf8mb4');

define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
]);
"@

$dbPath = Join-Path $deployDir "LOKA\config\database.php"
Set-Content -Path $dbPath -Value $dbTemplate

# Constants config template
$constantsTemplate = @"
<?php
/**
 * LOKA - System Constants (Production)
 * 
 * UPDATE APP_URL and SITE_URL:
 * - APP_URL: Empty string '' if in root, or '/subfolder' if in subdirectory
 * - SITE_URL: Your full domain with https://
 */

define('APP_NAME', 'LOKA Fleet Management');
define('APP_VERSION', '1.0.0');

// URL Configuration - UPDATE THESE
define('APP_URL', '');  // Empty for root, or '/subfolder' for subdirectory
define('SITE_URL', 'https://yourdomain.com');  // REPLACE with your domain

// Paths (usually don't need to change)
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('CLASSES_PATH', BASE_PATH . '/classes');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('PAGES_PATH', BASE_PATH . '/pages');
define('ASSETS_PATH', APP_URL . '/assets');

// User Roles
define('ROLE_REQUESTER', 'requester');
define('ROLE_APPROVER', 'approver');
define('ROLE_MOTORPOOL', 'motorpool_head');
define('ROLE_ADMIN', 'admin');

define('ROLE_LEVELS', [
    ROLE_REQUESTER => 1,
    ROLE_APPROVER => 2,
    ROLE_MOTORPOOL => 3,
    ROLE_ADMIN => 4
]);

// Request Status
define('STATUS_DRAFT', 'draft');
define('STATUS_PENDING', 'pending');
define('STATUS_PENDING_MOTORPOOL', 'pending_motorpool');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_CANCELLED', 'cancelled');
define('STATUS_COMPLETED', 'completed');
define('STATUS_MODIFIED', 'modified');

// Vehicle Status
define('VEHICLE_AVAILABLE', 'available');
define('VEHICLE_IN_USE', 'in_use');
define('VEHICLE_MAINTENANCE', 'maintenance');
define('VEHICLE_OUT_OF_SERVICE', 'out_of_service');

// Driver Status
define('DRIVER_AVAILABLE', 'available');
define('DRIVER_ON_TRIP', 'on_trip');
define('DRIVER_ON_LEAVE', 'on_leave');
define('DRIVER_UNAVAILABLE', 'unavailable');

// User Status
define('USER_ACTIVE', 'active');
define('USER_INACTIVE', 'inactive');
define('USER_SUSPENDED', 'suspended');

// Session
define('SESSION_TIMEOUT', 7200);
define('REMEMBER_ME_DAYS', 30);

// Pagination
define('ITEMS_PER_PAGE', 15);

// Date Formats
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE', 'M d, Y');
define('DISPLAY_DATETIME', 'M d, Y h:i A');

// Status Labels & Colors
define('STATUS_LABELS', [
    STATUS_DRAFT => ['label' => 'Draft', 'color' => 'secondary'],
    STATUS_PENDING => ['label' => 'Pending Approval', 'color' => 'warning'],
    STATUS_PENDING_MOTORPOOL => ['label' => 'Pending Motorpool', 'color' => 'info'],
    STATUS_APPROVED => ['label' => 'Approved', 'color' => 'success'],
    STATUS_REJECTED => ['label' => 'Rejected', 'color' => 'danger'],
    STATUS_CANCELLED => ['label' => 'Cancelled', 'color' => 'dark'],
    STATUS_COMPLETED => ['label' => 'Completed', 'color' => 'primary'],
    STATUS_MODIFIED => ['label' => 'Modified', 'color' => 'warning']
]);

define('VEHICLE_STATUS_LABELS', [
    VEHICLE_AVAILABLE => ['label' => 'Available', 'color' => 'success'],
    VEHICLE_IN_USE => ['label' => 'In Use', 'color' => 'primary'],
    VEHICLE_MAINTENANCE => ['label' => 'Maintenance', 'color' => 'warning'],
    VEHICLE_OUT_OF_SERVICE => ['label' => 'Out of Service', 'color' => 'danger']
]);

define('DRIVER_STATUS_LABELS', [
    DRIVER_AVAILABLE => ['label' => 'Available', 'color' => 'success'],
    DRIVER_ON_TRIP => ['label' => 'On Trip', 'color' => 'primary'],
    DRIVER_ON_LEAVE => ['label' => 'On Leave', 'color' => 'warning'],
    DRIVER_UNAVAILABLE => ['label' => 'Unavailable', 'color' => 'danger']
]);

define('ROLE_LABELS', [
    ROLE_REQUESTER => ['label' => 'Requester', 'color' => 'secondary'],
    ROLE_APPROVER => ['label' => 'Approver', 'color' => 'info'],
    ROLE_MOTORPOOL => ['label' => 'Motorpool Head', 'color' => 'primary'],
    ROLE_ADMIN => ['label' => 'Administrator', 'color' => 'danger']
]);
"@

$constantsPath = Join-Path $deployDir "LOKA\config\constants.php"
Set-Content -Path $constantsPath -Value $constantsTemplate

# Create upload instructions
$uploadInstructions = @"
# LOKA Fleet Management - Hostinger Upload Instructions

## Package Location
The deployment package is ready in: DEPLOY_PACKAGE\LOKA\

## Upload Steps

### Method 1: Via Hostinger File Manager (Recommended)

1. **Login to Hostinger hPanel**
2. **Go to:** File Manager
3. **Navigate to:** public_html/
4. **Upload the entire LOKA folder:**
   - Option A: Upload as ZIP, then extract in File Manager
   - Option B: Upload folder directly via FTP client

### Method 2: Via FTP (FileZilla/WinSCP)

1. **Get FTP credentials** from Hostinger hPanel > FTP Accounts
2. **Connect** using FileZilla or WinSCP
3. **Navigate to:** /public_html/
4. **Upload entire LOKA folder** to public_html/

## After Upload - Configuration

### 1. Update Database Config
Edit: LOKA/config/database.php
- Update DB_NAME, DB_USER, DB_PASS with your Hostinger database credentials
- Get credentials from: hPanel > Databases > MySQL Databases

### 2. Update Constants
Edit: LOKA/config/constants.php
- Update APP_URL (empty '' for root, or '/subfolder' for subdirectory)
- Update SITE_URL (your full domain with https://)

### 3. Set File Permissions
Via File Manager or FTP:
- Folders: 755
- Files: 644
- logs/ folder: 755 (must be writable)

### 4. Set Up Cron Job
hPanel > Advanced > Cron Jobs:
- Frequency: */2 * * * *
- Command: /usr/bin/php /home/u123456789/domains/yourdomain.com/public_html/LOKA/cron/process_queue.php
- (Update path to match your actual file location)

### 5. Import Database
- Go to phpMyAdmin in hPanel
- Select your database
- Import SQL files from LOKA/migrations/ folder

## Test
1. Access: https://yourdomain.com/LOKA (or root if installed there)
2. Login and test
3. Create a request to test email notifications
4. Wait 2 minutes for cron to process emails

## Full Documentation
See: LOKA/DEPLOYMENT_HOSTINGER.md for detailed instructions
"@

Set-Content -Path (Join-Path $deployDir "UPLOAD_INSTRUCTIONS.md") -Value $uploadInstructions

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "Deployment Package Created!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Location: $deployDir\LOKA" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Review and update config files in DEPLOY_PACKAGE\LOKA\config\" -ForegroundColor White
Write-Host "2. Upload LOKA folder to Hostinger public_html/" -ForegroundColor White
Write-Host "3. Follow UPLOAD_INSTRUCTIONS.md" -ForegroundColor White
Write-Host ""
Write-Host "Press any key to open deployment folder..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

# Open deployment folder
Start-Process explorer.exe -ArgumentList $deployDir

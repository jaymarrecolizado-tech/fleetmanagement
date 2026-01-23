# Email Queue Setup Guide

## Problem Solved
The application was lagging because emails were being processed during requests. Now emails are **only queued** during requests and processed by a background cron job.

## How It Works
1. **During Requests**: Emails are only queued (added to database), never sent
2. **Background Processing**: A cron job processes queued emails every 1-2 minutes
3. **Result**: Zero lag during requests, emails sent within 1-2 minutes

## Setup Instructions

### For Windows (WAMP/XAMPP)

1. **Create a Batch File** (`process_email_queue.bat`):
```batch
@echo off
cd C:\wamp64\www\fleetManagement\LOKA
C:\wamp64\bin\php\php8.x.x\php.exe cron\process_queue.php
```

2. **Set Up Windows Task Scheduler**:
   - Open Task Scheduler
   - Create Basic Task
   - Name: "LOKA Email Queue Processor"
   - Trigger: Every 2 minutes
   - Action: Start a program
   - Program: `C:\wamp64\www\fleetManagement\LOKA\process_email_queue.bat`
   - Run whether user is logged on or not

### For Linux/Production

Add to crontab (`crontab -e`):
```bash
# Process email queue every 2 minutes
*/2 * * * * /usr/bin/php /var/www/html/LOKA/cron/process_queue.php >> /var/log/email_queue.log 2>&1
```

## Manual Processing

To manually process emails (for testing):
```bash
php LOKA/cron/process_queue.php
```

## Email Queue Status

Check email queue status in the admin panel:
- Go to Settings > Email Queue
- View pending, sent, and failed emails

## Benefits

✅ **Zero Lag**: Requests complete instantly  
✅ **Reliable**: Emails processed in background  
✅ **Scalable**: Can handle many emails without blocking  
✅ **Retry Logic**: Failed emails are retried automatically  

@echo off
REM LOKA Email Queue Processor - Windows Batch File
REM Run this every 2 minutes using Windows Task Scheduler
REM 
REM Setup in Task Scheduler:
REM   Trigger: Every 2 minutes, repeating every 2 minutes, indefinitely
REM   Action: Start a program
REM   Program: php.exe (full path, e.g., C:\wamp64\bin\php\php8.x\php.exe)
REM   Arguments: C:\wamp64\www\fleetManagement\LOKA\cron\process_queue.php
REM   Start in: C:\wamp64\www\fleetManagement\LOKA

echo [%date% %time%] Processing email queue...
"C:\wamp64\bin\php\php8.2\php.exe" "C:\wamp64\www\fleetManagement\LOKA\cron\process_queue.php"
echo [%date% %time%] Done.

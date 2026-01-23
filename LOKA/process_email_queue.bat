@echo off
REM LOKA Email Queue Processor - Windows Batch File
REM Run this via Windows Task Scheduler every 1-2 minutes

cd /d "%~dp0"
C:\wamp64\bin\php\php8.2.0\php.exe cron\process_queue.php

REM If PHP path is different, update the path above
REM Common paths:
REM C:\wamp64\bin\php\php8.1.0\php.exe
REM C:\wamp64\bin\php\php8.2.0\php.exe
REM C:\xampp\php\php.exe

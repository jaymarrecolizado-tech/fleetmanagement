@echo off
echo ========================================
echo LOKA Fleet Management - Upload Prep
echo ========================================
echo.

cd /d "%~dp0"
set DEPLOY_DIR=..\DEPLOY_PACKAGE
set LOKA_DIR=%~dp0

if exist "%DEPLOY_DIR%" (
    echo Removing existing deployment package...
    rmdir /s /q "%DEPLOY_DIR%"
)

echo Creating deployment package...
mkdir "%DEPLOY_DIR%"
mkdir "%DEPLOY_DIR%\LOKA"

echo Copying LOKA files...
xcopy /E /I /Y /EXCLUDE:exclude_list.txt "%LOKA_DIR%*" "%DEPLOY_DIR%\LOKA\"

echo.
echo ========================================
echo Deployment Package Created!
echo ========================================
echo.
echo Location: %DEPLOY_DIR%\LOKA
echo.
echo Next Steps:
echo 1. Review config files in DEPLOY_PACKAGE\LOKA\config\
echo 2. Upload LOKA folder to Hostinger public_html/
echo 3. Follow UPLOAD_NOW.md for instructions
echo.
pause

start "" "%DEPLOY_DIR%"

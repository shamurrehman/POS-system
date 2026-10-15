@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title Pharmacy POS - First-time setup

echo ==========================================================
echo   Pharmacy POS - first-time setup
echo ==========================================================
echo.

rem ---- Find PHP (XAMPP first, then PATH) ----
set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" (
    for /f "delims=" %%P in ('where php 2^>nul') do set "PHP=%%P"
)
if not exist "%PHP%" (
    echo [X] PHP was not found. Install XAMPP ^(PHP 8.2 or newer^) to C:\xampp and run this again.
    pause & exit /b 1
)
echo [OK] PHP: %PHP%

rem ---- PHP dependencies ----
if not exist "vendor\autoload.php" (
    echo Installing PHP packages...
    if exist "composer.phar" (
        "%PHP%" composer.phar install --no-dev --optimize-autoloader --no-interaction || goto :fail
    ) else (
        where composer >nul 2>nul || (
            echo [X] The vendor folder is missing and Composer is not installed.
            echo     Download composer.phar from https://getcomposer.org into this folder and run again.
            pause & exit /b 1
        )
        call composer install --no-dev --optimize-autoloader --no-interaction || goto :fail
    )
)
echo [OK] PHP packages

rem ---- Environment file and secret key ----
if not exist ".env" (
    copy /y ".env.example" ".env" >nul
    "%PHP%" artisan key:generate --force || goto :fail
    echo [OK] Created .env with a new secret key
) else (
    echo [OK] .env already exists - keeping it
)

rem ---- Database ----
if not exist "database\database.sqlite" type nul > "database\database.sqlite"
"%PHP%" artisan migrate --force || goto :fail
"%PHP%" artisan db:seed --force || goto :fail
echo [OK] Database ready

choice /c YN /m "Add the Pakistani cardiac medicine catalogue (brands only, no prices or stock)"
if not errorlevel 2 "%PHP%" artisan db:seed --class=CardiacMedicinesSeeder --force

rem ---- First administrator ----
echo.
choice /c YN /m "Create an administrator account now"
if errorlevel 2 goto :skipadmin
set /p ADMINUSER="Admin username: "
"%PHP%" artisan pos:create-admin "%ADMINUSER%"
:skipadmin

echo.
choice /c NY /m "Load DEMO data (sample medicines and sales, for practice only)"
if errorlevel 2 (
    "%PHP%" artisan db:seed --class=DemoSeeder --force
    echo [!] Demo logins: admin/Admin12345  manager/Manager12345  cashier/Cashier12345
    echo [!] Do NOT use demo data for the real shop. Delete database\database.sqlite and run install.bat again to start clean.
)

rem ---- Speed up the app ----
"%PHP%" artisan optimize >nul
"%PHP%" artisan pos:backup >nul 2>nul

rem ---- Desktop shortcut ----
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$s=(New-Object -ComObject WScript.Shell).CreateShortcut([Environment]::GetFolderPath('Desktop')+'\Pharmacy POS.lnk');" ^
  "$s.TargetPath='%~dp0start-pos.bat'; $s.WorkingDirectory='%~dp0'; $s.WindowStyle=7; $s.IconLocation='shell32.dll,43'; $s.Save()"
echo [OK] Desktop shortcut "Pharmacy POS" created

echo.
echo ==========================================================
echo   Setup complete. Double-click "Pharmacy POS" on the desktop.
echo ==========================================================
pause
exit /b 0

:fail
echo.
echo [X] Setup failed. Read the message above, fix it and run install.bat again.
pause
exit /b 1

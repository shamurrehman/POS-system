@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title Pharmacy POS launcher

rem ============================ OPTIONS ============================
rem Port the POS runs on (only reachable from this computer).
set "PORT=8000"
rem 1 = print receipts straight to the Windows DEFAULT printer with no dialog.
rem     Only use this if the receipt printer is the default printer, because
rem     A4 reports will also go to it. 0 = show the print dialog (it remembers your choice).
set "SILENT_PRINT=0"
rem =================================================================

set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" for /f "delims=" %%P in ('where php 2^>nul') do set "PHP=%%P"
if not exist "%PHP%" (
    echo PHP not found. Run install.bat first.
    pause & exit /b 1
)

rem Automatic backup when due (interval: BACKUP_EVERY_DAYS in .env)
"%PHP%" artisan pos:backup --if-needed >> "storage\logs\backup.log" 2>&1

rem Start the server if it is not already running
powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient('127.0.0.1', %PORT%)).Close(); exit 0 } catch { exit 1 }"
if errorlevel 1 (
    start "Pharmacy POS Server - keep this window open" /min "%PHP%" artisan serve --host=127.0.0.1 --port=%PORT% --no-reload
)

rem Wait until it answers (max ~20 s)
set /a TRIES=0
:wait
powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient('127.0.0.1', %PORT%)).Close(); exit 0 } catch { exit 1 }"
if not errorlevel 1 goto :open
set /a TRIES+=1
if %TRIES% GEQ 20 (
    echo The POS server did not start. Check storage\logs for errors.
    pause & exit /b 1
)
timeout /t 1 /nobreak >nul
goto :wait

:open
set "URL=http://127.0.0.1:%PORT%/login"
set "FLAGS=--app=%URL% --user-data-dir="%~dp0storage\browser-profile" --no-first-run --disable-features=Translate"
if "%SILENT_PRINT%"=="1" set "FLAGS=%FLAGS% --kiosk-printing"

rem Microsoft Edge is built into Windows and handles scanners reliably; Chrome is the fallback.
set "EDGE=%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if not exist "%EDGE%" set "EDGE=%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"
set "CHROME=%ProgramFiles%\Google\Chrome\Application\chrome.exe"

if exist "%EDGE%" (
    start "" "%EDGE%" %FLAGS%
) else if exist "%CHROME%" (
    start "" "%CHROME%" %FLAGS%
) else (
    start "" "%URL%"
)
exit /b 0

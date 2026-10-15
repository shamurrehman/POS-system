@echo off
rem One-time: run Pharmacy POS on XAMPP Apache as an auto-starting, self-restarting Windows service.
net session >nul 2>&1
if errorlevel 1 (
    echo Asking for administrator rights...
    powershell -NoProfile -Command "Start-Process -Verb RunAs -FilePath '%~f0'"
    exit /b
)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\setup-xampp-service.ps1"
pause

@echo off
rem Stops the Pharmacy POS server window started by start-pos.bat
taskkill /FI "WINDOWTITLE eq Pharmacy POS Server*" /T /F >nul 2>nul
echo Pharmacy POS server stopped.
timeout /t 2 >nul

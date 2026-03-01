@echo off
chcp 65001 >nul
title SlipScan Launcher

echo.
echo  ===================================
echo   SlipScan - Starting All Services
echo  ===================================
echo.

cd /d "%~dp0"

REM ── Start Flask OCR Service (port 5000) ──
echo [1/2] Starting Flask OCR + Frontend service on port 5000...
set PYTHONUTF8=1
start "SlipScan - Flask OCR (port 5000)" cmd /k "cd /d %~dp0 && venv\Scripts\python.exe ocr_service\app.py"

timeout /t 2 /nobreak >nul

REM ── Start Flask Backend API (port 8000) ──
echo [2/2] Starting Flask Backend API on port 8000...
start "SlipScan - Flask Backend (port 8000)" cmd /k "cd /d %~dp0 && venv\Scripts\python.exe backend_flask\app.py"

timeout /t 2 /nobreak >nul

echo.
echo  ===================================
echo   All services started!
echo  ===================================
echo.
echo   Frontend : http://localhost:5000
echo   API      : http://localhost:8000
echo   Health   : http://localhost:5000/health
echo   API Health: http://localhost:8000/health
echo.
echo  Close the opened windows to stop services.
echo.
pause

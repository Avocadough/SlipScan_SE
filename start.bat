@echo off
chcp 65001 >nul
title SlipScan Launcher

echo.
echo  ===================================
echo   SlipScan - Starting All Services
echo  ===================================
echo.

cd /d "%~dp0"

REM ── Load API Key from ocr_service/.env ──
set TYPHOON_OCR_API_KEY=
for /f "tokens=1,2 delims==" %%a in (ocr_service\.env) do (
    if "%%a"=="TYPHOON_OCR_API_KEY" set TYPHOON_OCR_API_KEY=%%b
)

if "%TYPHOON_OCR_API_KEY%"=="" (
    echo [WARN] TYPHOON_OCR_API_KEY not found in ocr_service\.env
    echo        OCR will not work without API key
    echo.
)

REM ── Start Flask OCR Service (port 5000) ──
echo [1/2] Starting Flask OCR + Frontend service on port 5000...
set PYTHONUTF8=1
start "SlipScan - Flask OCR (port 5000)" cmd /k "cd /d %~dp0 && venv\Scripts\python.exe ocr_service\app.py"

timeout /t 2 /nobreak >nul

REM ── Start PHP Backend (port 8000) ──
echo [2/2] Starting PHP API Backend on port 8000...
start "SlipScan - PHP Backend (port 8000)" cmd /k "cd /d %~dp0\backend && php -S 0.0.0.0:8000 index.php"

timeout /t 2 /nobreak >nul

echo.
echo  ===================================
echo   All services started!
echo  ===================================
echo.
echo   Frontend : http://localhost:5000
echo   PHP API  : http://localhost:8000
echo   Health   : http://localhost:5000/health
echo.
echo  Close the opened windows to stop services.
echo.
pause

@echo off
setlocal
cd /d "%~dp0"

where pyw.exe >nul 2>nul
if errorlevel 1 (
  echo Python for Windows was not found. Install Python 3, then double-click this file again.
  pause
  exit /b 1
)

start "Campaign Operator" /b pyw.exe "%~dp0campaign_control_panel.py"

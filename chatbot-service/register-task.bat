@echo off
:: ============================================================
:: Smart Attendance School — Register Task Scheduler Launcher
:: ============================================================
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0register-task.ps1"

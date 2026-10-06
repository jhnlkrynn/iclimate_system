@echo off
REM Starts everything iClimate needs: database, ML service, web, scheduler and queue worker. Safe to run twice.
REM   start-services.bat                    start (or repair) the services
REM   start-services.bat status             show what is running
REM   start-services.bat stop | restart
REM   start-services.bat install-autostart  start them automatically when you sign in to Windows (after a reboot)
REM Laravel 12 needs PHP 8.2+; XAMPP's own PHP 8.0 cannot run it. Set ICLIMATE_PHP to use another php.exe.
cd /d "%~dp0"
set "ACTION=%~1"
if "%ACTION%"=="" set "ACTION=start"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\iclimate-services.ps1" %ACTION%

@echo off
REM Starts the background services iClimate needs. Run from the project root.
cd /d "%~dp0"
start "iClimate Farming AI" /min cmd /c "cd python_scripts && ..\.venv\Scripts\python.exe -m uvicorn main:app --host 127.0.0.1 --port 8010 >> ..\farming-ai-8010.log 2>&1"
REM Always use XAMPP's PHP (has pdo_pgsql). WSL's php lacks it and causes "could not find driver" 500 errors.
start "iClimate Web" /min cmd /c "C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000 >> storage\logs\serve.log 2>&1"
start "iClimate Scheduler" /min cmd /c "php artisan schedule:work >> storage\logs\scheduler.log 2>&1"
start "iClimate Queue" /min cmd /c "php artisan queue:work --queue=analytics,default --tries=2 --timeout=120 >> storage\logs\queue.log 2>&1"

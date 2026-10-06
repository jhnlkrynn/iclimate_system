# Starts, stops and reports the background services iClimate needs on a Windows/XAMPP machine:
#   database (XAMPP MySQL), ML service (uvicorn), web (artisan serve), scheduler (schedule:work), queue (queue:work).
#
#   powershell -ExecutionPolicy Bypass -File scripts\iclimate-services.ps1 start      (also what start-services.bat runs)
#   ... status | stop | restart | install-autostart | remove-autostart
#
# Each service runs under its own small supervisor (this same script with "supervise"), which starts it again
# a few seconds after it exits: a crash, `php artisan queue:restart` after a code change, or the hourly worker
# refresh never leaves a service down. "start" is safe to run twice: a service that is already supervised is
# left alone, and stray copies started by hand (e.g. a second `artisan serve` on the same port) are closed.
# "install-autostart" registers a logon task for the current Windows user so everything comes back after a reboot.
param(
    [ValidateSet('start', 'stop', 'restart', 'status', 'supervise', 'install-autostart', 'remove-autostart')]
    [string] $Action = 'start',
    [string] $Name = ''
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$Script = $MyInvocation.MyCommand.Path
$TaskName = 'iClimate Services'
$Logs = Join-Path $Root 'storage\logs'

# Laravel 12 needs PHP 8.2+. XAMPP's own PHP is 8.0, so it is never used here. Override with ICLIMATE_PHP.
$Php = if ($env:ICLIMATE_PHP) { $env:ICLIMATE_PHP } else { 'C:\php85-cli\php.exe' }
$Python = Join-Path $Root '.venv\Scripts\python.exe'
$MysqlDir = if ($env:ICLIMATE_MYSQL_DIR) { $env:ICLIMATE_MYSQL_DIR } else { 'C:\xampp\mysql' }

# The web server. ICLIMATE_WEB_SERVER in .env (or the environment):
#   apache   Apache 2.4 + four PHP FastCGI workers (deploy/apache/httpd-iclimate.conf): several pages at once. Use this for real use.
#   artisan  `php artisan serve`: one request at a time. Fine for development only. (default when nothing is set)
function Get-EnvSetting([string] $key, [string] $default) {
    if (Test-Path "Env:$key") { return (Get-Item "Env:$key").Value }
    $line = Get-Content (Join-Path $Root '.env') -ErrorAction SilentlyContinue | Where-Object { $_ -match "^\s*$key\s*=" } | Select-Object -Last 1
    if ($line) { return ($line -replace "^\s*$key\s*=\s*", '').Trim().Trim('"').Trim("'") }
    return $default
}

$WebServer = (Get-EnvSetting 'ICLIMATE_WEB_SERVER' 'artisan').ToLower()
$WebPort = Get-EnvSetting 'ICLIMATE_WEB_PORT' '8000'
# 127.0.0.1 = this computer only. 0.0.0.0 = also other computers on the same network (open the port in Windows Firewall too).
$WebBind = Get-EnvSetting 'ICLIMATE_WEB_BIND' '127.0.0.1'
$ApacheDir = Get-EnvSetting 'ICLIMATE_APACHE_DIR' 'C:\xampp\apache'
$PhpCgi = Join-Path (Split-Path -Parent $Php) 'php-cgi.exe'
$ApacheConf = (Join-Path $Root 'deploy\apache\httpd-iclimate.conf') -replace '\\', '/'

# name -> command line (run from the project root), the text that identifies a stray copy, and its log file.
$Services = [ordered]@{
    ml        = @{ Command = "`"$Python`" -m uvicorn main:app --app-dir python_scripts --host 127.0.0.1 --port 8010"; Match = 'uvicorn main:app'; Log = 'farming-ai.log' }
}
if ($WebServer -eq 'apache') {
    # Each php-cgi process serves one request at a time; Apache shares the requests between the four of them.
    foreach ($port in 9085..9088) {
        $Services["php$port"] = @{ Command = "`"$PhpCgi`" -d expose_php=0 -d upload_max_filesize=50M -d post_max_size=55M -d memory_limit=256M -b 127.0.0.1:$port"; Match = "php-cgi\.exe.*-b 127\.0\.0\.1:$port"; Log = "php-cgi-$port.log" }
    }
    $Services['web'] = @{ Command = "`"$ApacheDir\bin\httpd.exe`" -C `"Define ICLIMATE_ROOT $($Root -replace '\\', '/')`" -C `"Define ICLIMATE_APACHE $($ApacheDir -replace '\\', '/')`" -C `"Define ICLIMATE_PORT $WebPort`" -C `"Define ICLIMATE_BIND $WebBind`" -f `"$ApacheConf`""; Match = 'httpd-iclimate\.conf|artisan serve|resources[\\/]+server\.php'; Log = 'apache-console.log' }
} else {
    $Services['web'] = @{ Command = "`"$Php`" artisan serve --host=$WebBind --port=$WebPort"; Match = 'artisan serve|resources[\\/]+server\.php|httpd-iclimate\.conf|php-cgi\.exe.*-b 127\.0\.0\.1:908'; Log = 'serve.log' }
}
# `schedule:work` starts a run every minute even while an earlier run is still busy, so a slow minute never makes
# the backup or an advisory fetch be skipped. Its own output ("Running [command] ... DONE") is not kept: each
# command writes storage\logs\automation-<command>.log, and failures go to the application log.
$Services['scheduler'] = @{ Command = "`"$Php`" artisan schedule:work"; Match = 'artisan schedule:(run|work)'; Log = 'NUL' }
# --max-time: the worker ends itself every hour and the supervisor starts a fresh one, so it never runs old code
# for long. --timeout is the fallback for jobs without their own limit; it stays below retry_after (config/queue.php).
$Services['queue'] = @{ Command = "`"$Php`" artisan queue:work --queue=analytics,default --tries=2 --timeout=300 --max-time=3600"; Match = 'artisan queue:work'; Log = 'queue.log' }

function Get-ProjectProcesses([string] $pattern) {
    Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -and $_.CommandLine -match $pattern -and $_.ProcessId -ne $PID }
}

function Get-Supervisor([string] $service) {
    Get-ProjectProcesses ('iclimate-services\.ps1.*supervise.*-Name ' + $service + '\b') | Where-Object { $_.Name -match 'powershell' }
}

function Stop-Tree([int] $processId) {
    # Through cmd: a process that has already ended makes taskkill write to stderr, which PowerShell 5.1 treats as an error.
    & cmd.exe /c "taskkill /PID $processId /T /F >nul 2>&1"
}

function Test-Port([int] $port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try { $client.Connect('127.0.0.1', $port); return $true } catch { return $false } finally { $client.Dispose() }
}

function Start-Database {
    if (Test-Port 3306) { return 'running' }
    $mysqld = Join-Path $MysqlDir 'bin\mysqld.exe'
    if (-not (Test-Path $mysqld)) { return 'not started (no MySQL found at ' + $MysqlDir + '; start your database yourself)' }

    # --bind-address: the database answers this computer only (XAMPP's default listens on the whole network).
    # --innodb-use-native-aio=0: XAMPP's MariaDB on this Windows build stops with an InnoDB assertion without it.
    Start-Process -FilePath $mysqld -WindowStyle Hidden -ArgumentList "--defaults-file=`"$MysqlDir\bin\my.ini`"", '--standalone', '--innodb-use-native-aio=0', '--bind-address=127.0.0.1'
    foreach ($i in 1..60) { if (Test-Port 3306) { return 'started' }; Start-Sleep -Seconds 1 }

    return 'did not answer on port 3306 within 60 seconds (see ' + $MysqlDir + '\data\*.err)'
}

function Start-Services {
    if (-not (Test-Path $Php)) { Write-Host "PHP 8.2+ was not found at $Php. Set ICLIMATE_PHP to your php.exe and run this again."; exit 1 }
    if ($WebServer -eq 'apache' -and -not ((Test-Path $PhpCgi) -and (Test-Path "$ApacheDir\bin\httpd.exe"))) {
        Write-Host "ICLIMATE_WEB_SERVER=apache needs $PhpCgi and $ApacheDir\bin\httpd.exe. Fix the paths or set ICLIMATE_WEB_SERVER=artisan."; exit 1
    }
    New-Item -ItemType Directory -Force $Logs | Out-Null
    Write-Host ('database   ' + (Start-Database))

    Push-Location $Root
    try {
        # Uploaded community photos are served through public\storage.
        if (-not (Test-Path 'public\storage')) { & $Php artisan storage:link | Out-Null }
    } finally { Pop-Location }

    foreach ($service in $Services.Keys) {
        if (Get-Supervisor $service) { Write-Host ("{0,-10} already running" -f $service); continue }
        # Copies started by hand would fight the supervised one for the same port or the same jobs.
        Get-ProjectProcesses $Services[$service].Match | Where-Object { $_.Name -match 'php|python|httpd' } | ForEach-Object { Stop-Tree $_.ProcessId }
        Start-Process -FilePath 'powershell.exe' -WindowStyle Hidden -WorkingDirectory $Root -ArgumentList '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', "`"$Script`"", 'supervise', '-Name', $service
        Write-Host ("{0,-10} started" -f $service)
    }

    Write-Host "iClimate web:       http://127.0.0.1:$WebPort  ($WebServer)"
    Write-Host 'iClimate ML health: http://127.0.0.1:8010/health'
    Write-Host "Logs: $Logs"
}

function Stop-Services {
    # Every supervisor first, whatever web server mode it was started in (the mode may have been changed since).
    Get-ProjectProcesses 'iclimate-services\.ps1.*supervise' | Where-Object { $_.Name -match 'powershell' } | ForEach-Object { Stop-Tree $_.ProcessId }
    Get-ProjectProcesses 'httpd-iclimate\.conf|php-cgi\.exe.*-b 127\.0\.0\.1:908|artisan serve|resources[\/]+server\.php' | Where-Object { $_.Name -match 'php|httpd' } | ForEach-Object { Stop-Tree $_.ProcessId }
    foreach ($service in $Services.Keys) {
        Get-Supervisor $service | ForEach-Object { Stop-Tree $_.ProcessId }
        Get-ProjectProcesses $Services[$service].Match | Where-Object { $_.Name -match 'php|python|httpd' } | ForEach-Object { Stop-Tree $_.ProcessId }
        Write-Host ("{0,-10} stopped" -f $service)
    }
    Write-Host 'The database is left running (other projects may use it).'
}

function Show-Status {
    Write-Host ("{0,-10} {1}" -f 'database', $(if (Test-Port 3306) { 'running (port 3306)' } else { 'NOT RUNNING' }))
    foreach ($service in $Services.Keys) {
        $supervised = [bool] (Get-Supervisor $service)
        $copies = @(Get-ProjectProcesses $Services[$service].Match | Where-Object { $_.Name -match 'php|python|httpd' -and $_.CommandLine -notmatch 'server\.php' }).Count
        $state = if ($supervised -and $copies -ge 1) { 'running' } elseif ($supervised) { 'restarting' } elseif ($copies -ge 1) { 'running, but not supervised (run "start" to fix)' } else { 'NOT RUNNING' }
        Write-Host ("{0,-10} {1}" -f $service, $state)
    }
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    Write-Host ("{0,-10} {1}" -f 'autostart', $(if ($task) { 'installed (starts when ' + $env:USERNAME + ' signs in to Windows)' } else { 'not installed (run "install-autostart")' }))
}

switch ($Action) {
    'supervise' {
        if (-not $Services.Contains($Name)) { Write-Host "Unknown service: $Name"; exit 1 }
        Set-Location $Root
        $log = if ($Services[$Name].Log -eq 'NUL') { 'NUL' } else { Join-Path $Logs $Services[$Name].Log }
        # php-cgi ends itself after this many requests (500 by default); the supervisor starts a fresh one.
        $env:PHP_FCGI_MAX_REQUESTS = '5000'
        while ($true) {
            # Keep the service log small: past 5 MB it becomes <name>.1 (replacing the previous .1) before the next start.
            if ($log -ne 'NUL' -and (Test-Path $log) -and (Get-Item $log).Length -gt 5MB) { Move-Item -Force $log "$log.1" -ErrorAction SilentlyContinue }
            # cmd does the redirection: PowerShell 5.1 wraps every stderr line of a native program in an error record.
            $redirect = if ($log -eq 'NUL') { '> NUL 2>&1' } else { ">> `"$log`" 2>&1" }
            & cmd.exe /c ($Services[$Name].Command + ' ' + $redirect)
            Start-Sleep -Seconds 5
        }
    }
    'start' { Start-Services }
    'stop' { Stop-Services }
    'restart' { Stop-Services; Start-Sleep -Seconds 2; Start-Services }
    'status' { Show-Status }
    'install-autostart' {
        $taskAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$Script`" start" -WorkingDirectory $Root
        $taskTrigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
        $taskSettings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -ExecutionTimeLimit ([TimeSpan]::Zero)
        Register-ScheduledTask -TaskName $TaskName -Action $taskAction -Trigger $taskTrigger -Settings $taskSettings -Description 'Starts the iClimate database, web, scheduler, queue worker and ML service.' -Force | Out-Null
        Write-Host "Installed: iClimate services now start when $($env:USERNAME) signs in to Windows."
    }
    'remove-autostart' {
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
        Write-Host 'Removed: iClimate services no longer start automatically.'
    }
}

STEP 19C-H2 — PERSISTENT WINDOWS BACKGROUND EXECUTION

Purpose
-------
Run Laravel's scheduler and ERP queue worker without keeping visible
PowerShell terminals open.

Installed tasks
---------------
1. SmartFactory DSS\Laravel Scheduler
   Runs php artisan schedule:run every minute.

2. SmartFactory DSS\ERP Queue Worker
   Starts at Windows logon and continuously runs:
   queue:work database --queue=erp-sync,default

Installation
------------
Open Windows PowerShell as the same Windows user that runs Laravel:

Set-Location C:\Users\OMEN\Herd\smartfactory-dss\laravel-app

powershell -ExecutionPolicy Bypass -File .\scripts\windows\Install-SmartFactoryBackgroundTasks.ps1

Status
------
powershell -ExecutionPolicy Bypass -File .\scripts\windows\Get-SmartFactoryBackgroundTaskStatus.ps1

Logs
----
storage\logs\windows-scheduler.log
storage\logs\windows-queue-worker.log

Restart after code changes
--------------------------
php artisan queue:restart

powershell -ExecutionPolicy Bypass -File .\scripts\windows\Restart-SmartFactoryBackgroundTasks.ps1

Removal
-------
powershell -ExecutionPolicy Bypass -File .\scripts\windows\Uninstall-SmartFactoryBackgroundTasks.ps1

Notes
-----
- These local-development tasks run only while the Windows user is
  logged in.
- The scripts discover the active php.exe path during installation.
- No password, API token or Redis secret is stored in the task action.
- The worker restarts itself every hour through --max-time=3600.
- The scheduler uses IgnoreNew so two schedule:run processes cannot
  overlap at the Windows Task Scheduler level.

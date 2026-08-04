# Phase 4 Operations Guide

## Project locations

DSS:

```text
C:\Users\OMEN\Herd\smartfactory-dss\laravel-app
```

Simulator:

```text
C:\Users\OMEN\Herd\smartfactory-dss\sage-erp-simulator
```

## Required services

Before synchronization:

- Laravel Herd is running.
- MySQL is running.
- The simulator is reachable over HTTPS.
- Redis is running in the Ubuntu VM.
- VirtualBox forwards Windows `127.0.0.1:6379` to Redis.

Test Redis connectivity:

```powershell
Test-NetConnection 127.0.0.1 -Port 6379
```

Expected:

```text
TcpTestSucceeded : True
```

## Clear caches

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\laravel-app
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
```

Do not run `migrate:fresh`.

## ERP handshake

```powershell
php artisan erp:handshake --resource=products --per-page=1
```

## Complete on-demand synchronization

```powershell
php artisan erp:sync:cycle --force --per-page=100 --max-pages=100
```

Required groups:

```text
catalog                 completed
factory-master          completed
production-execution    completed
maintenance             completed
quality                 completed
```

## Health monitoring

```powershell
php artisan erp:sync:health --details
```

Because persistent scheduling is disabled locally, health may become `DEGRADED` after 45 minutes. Running the synchronization cycle again should return it to `HEALTHY`.

## Administrator monitoring

```text
https://smartfactory-dss.test/admin/erp-monitoring
```

## Manual queued synchronization

After clicking **Queue synchronization**, run:

```powershell
php artisan queue:work database --queue=erp-sync,default --tries=20 --timeout=7200 --stop-when-empty
```

The worker processes waiting jobs and exits.

## Queue inspection

```powershell
php artisan queue:failed
```

```powershell
php artisan queue:monitor erp-sync,default --max=10
```

## Testing

```powershell
php artisan test --stop-on-failure
```

Tests terminate after completion and do not remain active.

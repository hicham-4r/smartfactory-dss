# Phase 6 Operations Guide

## Start local services

Laravel Herd and MySQL must be running.

Start FastAPI manually:

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\fastapi-ai
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Persistent FastAPI, Laravel Scheduler, and queue workers remain intentionally disabled
during local development.

## Stop FastAPI

In its PowerShell window, press:

```text
Ctrl+C
```

## Check the web workflow

```text
https://smartfactory-dss.test/ai-insights
```

The Administrator dashboard also contains AI service health information.

## Clear Laravel caches after environment changes

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\laravel-app
php artisan optimize:clear
```

## Run Laravel tests

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\laravel-app
php artisan test
```

## Run FastAPI tests

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\fastapi-ai
.\.venv\Scripts\python.exe -m pytest -q
```

## Inspect the current model pointer

```powershell
Get-Content D:\SmartFactoryDSS\models\MODELS_LATEST
```

Accepted model run:

```text
f0147a01-3d1a-45d9-9cb8-c2686b531be0
```

Do not edit artifacts, metrics JSON, manifests, hashes, or `MODELS_LATEST` manually.

## On-demand ERP queue worker

When an ERP synchronization job is intentionally queued:

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\laravel-app
php artisan queue:work database --queue=erp-sync,default --tries=20 --timeout=7200 --stop-when-empty
```

## Operational rules

- Do not run `migrate:fresh`.
- Do not expose FastAPI publicly.
- Do not commit `.env` files or model artifacts.
- Do not suppress `simulated_prototype` warnings.
- Do not retrain against an unverified feature run.
- Keep exact run IDs in exported technical evidence.

# Phase 6 Deployment Guide

## Local-development decision

Local Windows development uses on-demand execution:

- Laravel through Herd;
- FastAPI started manually;
- ERP queue worker started only when needed;
- Laravel Scheduler not kept permanently active.

This reduces unnecessary CPU, memory, Redis, and VM usage without removing production
capabilities.

## Production topology

Recommended logical deployment:

```text
Browser -> HTTPS reverse proxy -> Laravel
Laravel -> private authenticated network -> FastAPI
Laravel -> MySQL / Redis
FastAPI -> read-only verified dataset and model storage
```

FastAPI must not be exposed directly to public clients.

## Laravel production requirements

- HTTPS;
- production `APP_KEY`;
- secure session cookies;
- private environment files;
- database backups;
- Redis or production-approved cache and queue backend;
- restricted storage permissions;
- administrator 2FA;
- process manager for queue workers;
- scheduler invocation every minute.

Scheduler:

```cron
* * * * * cd /var/www/smartfactory-dss && php artisan schedule:run >> /dev/null 2>&1
```

Queue worker example:

```bash
php artisan queue:work database --queue=erp-sync,default --tries=20 --timeout=7200 --sleep=3
```

Use Supervisor, systemd, or an equivalent process manager. The queue `retry_after` must
be greater than the worker timeout.

## FastAPI production requirements

- restricted service account;
- private bind address;
- reverse proxy or private service network;
- strong shared bearer token;
- TLS verification unless traffic is isolated by a trusted local mechanism;
- process manager with restart policy;
- private model and dataset roots;
- filesystem permissions preventing web-user uploads;
- logs without request payloads or feature values.

## Required environment alignment

Laravel and FastAPI must use the same internal token.

Laravel key settings include:

```text
AI_SERVICE_BASE_URL
AI_SERVICE_TOKEN
AI_SERVICE_VERIFY_TLS
AI_ALLOW_INTERNAL_HTTP
AI_INFERENCE_DRIVER
AI_INFERENCE_METRICS_ENDPOINT
```

FastAPI key settings include:

```text
AI_INTERNAL_TOKEN
AI_ALLOWED_HOSTS
AI_MODEL_ROOT
AI_DATASET_ROOT
AI_PREPROCESSING_ROOT
AI_FEATURE_ROOT
```

After changing Laravel environment values:

```bash
php artisan optimize:clear
```

## Model publication

Model runs must be produced by the approved training pipeline, validated, and published
atomically. Do not copy arbitrary Joblib files into production.

## Deployment acceptance

Before release:

1. validate backups;
2. run database migrations without destructive reset;
3. run Laravel and FastAPI tests;
4. verify all required routes;
5. validate the model registry;
6. test role authorization;
7. execute one forecast, anomaly check, and maintenance-risk request;
8. export PDF, XLSX, and CSV;
9. verify prototype warnings remain visible.

# Phase 4 Deployment Notes

## Local-development decision

Persistent background tasks are disabled locally to avoid continuous CPU, memory, Redis, and VirtualBox usage.

This does not remove the feature. The application still contains:

- the Laravel schedule definition;
- the ERP cycle command;
- the queued manual synchronization job;
- Redis locking;
- monitoring and health checks.

## Production scheduler

A production server must run:

```bash
php artisan schedule:run
```

every minute.

Example cron entry:

```cron
* * * * * cd /var/www/smartfactory-dss && php artisan schedule:run >> /dev/null 2>&1
```

## Production queue worker

A process manager should continuously run:

```bash
php artisan queue:work database --queue=erp-sync,default --tries=20 --timeout=7200 --sleep=3
```

The queue `retry_after` value must be greater than the worker timeout.

Recommended:

```text
timeout     = 7200
retry_after = 7500
```

## Supervisor example

```ini
[program:smartfactory-erp-worker]
command=php /var/www/smartfactory-dss/artisan queue:work database --queue=erp-sync,default --tries=20 --timeout=7200 --sleep=3
directory=/var/www/smartfactory-dss
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/smartfactory-dss/storage/logs/erp-worker.log
stopwaitsecs=7300
```

After deployment:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start smartfactory-erp-worker:*
```

After code changes:

```bash
php artisan queue:restart
```

## Windows Server alternative

Windows Task Scheduler may be used to:

- invoke `schedule:run` every minute;
- start a queue worker at system startup;
- restart the worker after failure;
- run under a restricted service account;
- use the real `php.exe`;
- write logs to `storage\logs`.

# Phase 6 Troubleshooting

## AI service unavailable

Start FastAPI:

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\fastapi-ai
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Then clear Laravel configuration cache:

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\laravel-app
php artisan optimize:clear
```

## Port 8001 already used

```powershell
Get-NetTCPConnection -LocalPort 8001 -ErrorAction SilentlyContinue
```

Stop only a process you recognize, or configure the same alternate port in Laravel and
FastAPI.

## FastAPI returns 401

The Laravel `AI_SERVICE_TOKEN` and FastAPI `AI_INTERNAL_TOKEN` do not match, or one is
empty. Correct private `.env` files and clear Laravel caches.

## Browser shows Method Not Allowed

Do not browse directly to a POST form endpoint. Open:

```text
https://smartfactory-dss.test/ai-insights
```

Automatic POST endpoints include safe GET redirects, but the main page remains the correct
entry point.

## Forecast has no eligible history

Choose a prediction date whose previous day contains validated production for the selected
line and quantity unit. The model also requires sufficient rolling history.

## Maintenance says no history exists

Choose a machine with eligible history and a prediction date after machine-status,
downtime, or maintenance observations. The default minimum observed period is 30 days.

## Report link expired

Run the analysis again. AI report tokens are intentionally short-lived and user-bound.

## Metrics unavailable

Confirm:

- FastAPI is running;
- the metrics endpoint is configured;
- the model run still exists;
- registry checksums are valid;
- the inference driver is `fastapi`.

## Registry validation fails

Do not edit the run. Restore an intact checksummed model run or retrain from a verified
feature run.

## Weak model metrics

Weak metrics are not a software exception. They reflect current simulated data and model
generalization. Do not hide them or tune against the test set.

## Dashboard AI card is missing

Confirm the user has the correct role and permission. Operators intentionally do not see
AI Insights.

## Laravel view or route changes appear stale

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\laravel-app
php artisan optimize:clear
```

## Test database warning

Tests must use the isolated in-memory SQLite configuration. Never run tests against the
working production-style MySQL database.

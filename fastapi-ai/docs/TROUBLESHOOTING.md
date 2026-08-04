# Troubleshooting

## `py -3.12` is not found

Verify the Python launcher:

```powershell
py -0p
py -3.12 --version
```

Use the installed Python 3.12 path if the launcher is unavailable.

## Configuration validation fails

Confirm that:

- `.env` exists inside `fastapi-ai`;
- `AI_INTERNAL_TOKEN` has at least 32 characters;
- the same token exists as `AI_SERVICE_TOKEN` in `laravel-app\.env`;
- `AI_ALLOWED_HOSTS` includes the host Laravel uses;
- no token contains quotes copied from a formatted document.

## Laravel shows unavailable

Keep FastAPI running in a separate PowerShell window:

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\fastapi-ai
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Then clear Laravel configuration cache:

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\laravel-app
php artisan optimize:clear
```

## Port 8001 is already used

```powershell
Get-NetTCPConnection -LocalPort 8001 -ErrorAction SilentlyContinue
```

Stop only the process you recognize, or choose another local port in both environment files.


## Feature task produces no eligible rows

Confirm that the latest Step 21D run contains:

- production records spanning at least ten distinct dates;
- consecutive production dates for the next-day forecasting target;
- machine status, downtime, and maintenance history covering more than thirty days.

The Step 21E pipeline fails safely instead of publishing an incomplete feature run.

## A chronological split is empty

The default 70/15/15 split needs enough distinct timestamps after supervised boundary
purging. Do not use random splitting to bypass this control. Create a wider Step 21C
snapshot and rerun Steps 21D and 21E.

## Feature validation reports target leakage

Do not edit split CSV files manually. Delete only the failed unpublished staging folder,
then rerun feature engineering from the verified Step 21D run. The validator requires
supervised target windows to end before the next split begins.

## `FEATURE_LATEST` is missing

A feature run was not published successfully. Run:

```powershell
python -m app.cli.datasets features `
  --run <absolute preprocessed run path> `
  --output-root D:/SmartFactoryDSS/datasets/features
```

Then verify the returned run path with `verify-features`.

# SmartFactory DSS FastAPI AI Service

This service is the private Python boundary for Phase 6 of the SmartFactory DSS prototype.

Implemented through Step 21E:

- secure FastAPI application foundation;
- authenticated health, version and internal contract endpoints;
- versioned Laravel-to-FastAPI analytics snapshot validation;
- secure, checksummed raw dataset snapshot verification;
- deterministic data-quality validation and preprocessing;
- leakage-safe production forecasting, production-anomaly, and maintenance-risk feature datasets;
- deterministic chronological train/validation/test splits with supervised boundary purging;
- atomic feature-run publication with strict manifests, SHA-256 checksums, and validation;
- atomic preprocessed-run publication with manifests and SHA-256 checksums;
- safe row-issue reports without raw cell values;
- strict configuration, correlation IDs, sanitized logging and safe errors;
- automated tests and documentation.

It does **not** contain trained machine-learning models, direct database access,
Ollama integration, new KPI formulas, live forecasts, anomaly scores, or maintenance
recommendations. Step 21E creates only versioned feature inputs and chronological splits.

## Local Windows startup

The HTTP service is needed only for live Laravel-to-FastAPI checks:

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\fastapi-ai
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Dataset verification and preprocessing are local CLI operations and do not
require Uvicorn to be running.

## Quality checks

```powershell
.\.venv\Scripts\python.exe -m ruff format --check app tests
.\.venv\Scripts\python.exe -m ruff check app tests
.\.venv\Scripts\python.exe -m pytest --cov=app --cov-report=term-missing
```

## Preprocess the latest raw snapshot

```powershell
.\.venv\Scripts\python.exe -m app.cli.datasets preprocess `
  --snapshot D:/SmartFactoryDSS/datasets/snapshots/<snapshot UUID> `
  --output-root D:/SmartFactoryDSS/datasets/preprocessed
```

Verify the published run:

```powershell
.\.venv\Scripts\python.exe -m app.cli.datasets verify-preprocessed `
  --run D:/SmartFactoryDSS/datasets/preprocessed/runs/<run UUID>
```

See `docs/DATASET_SNAPSHOT_V1.md` and `docs/PREPROCESSING_PIPELINE_V1.md`.


## Engineer and verify the latest Step 21E feature run

Uvicorn is not required for local feature engineering.

```powershell
$PreprocessingRoot = 'D:\SmartFactoryDSS\datasets\preprocessed'
$RunId = (Get-Content "$PreprocessingRoot\PREPROCESSED_LATEST" -Raw).Trim()
$RunPath = Join-Path "$PreprocessingRoot\runs" $RunId

.\.venv\Scripts\python.exe -m app.cli.datasets features `
  --run $RunPath `
  --output-root D:\SmartFactoryDSS\datasets\features
```

Verify the published feature run:

```powershell
$FeatureRoot = 'D:\SmartFactoryDSS\datasets\features'
$FeatureRunId = (Get-Content "$FeatureRoot\FEATURE_LATEST" -Raw).Trim()

.\.venv\Scripts\python.exe -m app.cli.datasets verify-features `
  --run (Join-Path "$FeatureRoot\runs" $FeatureRunId)
```

The feature run contains separate chronological `train`, `validation`, and `test`
files for production forecasting, production anomaly detection, and maintenance risk.
Supervised target windows are purged at split boundaries to prevent future-label
leakage.


## Step 21F model training

Step 21F trains and versions private simulated-prototype models from a verified Step 21E feature
run. It adds production forecasting, unlabeled anomaly detection, and maintenance-risk baselines.
It does not expose inference endpoints or call Ollama. See docs/MODEL_TRAINING_V1.md.
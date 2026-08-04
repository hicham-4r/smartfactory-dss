# Phase 7 User Guide - Guarded AI Explanations

## Start the local services

Ollama must be installed with the exact model tag `llama3:8b`.

Start FastAPI in Windows PowerShell:

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\fastapi-ai
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Keep that window open. Laravel Herd serves the web application; do not run `php artisan serve`.

## Open AI Insights

```text
https://smartfactory-dss.test/ai-insights
```

Access depends on the authenticated role:

- Production Supervisor: forecast and anomaly explanations;
- Production Manager: forecast and anomaly explanations;
- Maintenance Manager: maintenance-risk explanations;
- Administrator: all supported explanations;
- Operator: no AI Insights or explanation access.

## Generate an explanation

1. Run an automatic or advanced forecast, anomaly check, or maintenance-risk analysis.
2. Confirm that the numeric inference result and model limitations are visible.
3. Select English or French.
4. Click **Generate explanation**.
5. Review the separate **Guarded AI explanation** section.

The explanation contains a summary, observations, suggested human checks, limitations, and grounding metadata. The numeric inference result above it remains authoritative.

## Interpret the three explanation types

### Production forecast

The narrative explains the supplied forecast and supplied history only. It is not a production commitment and must not invent trends, confidence intervals, or new values.

### Production anomaly

The anomaly score is a model score, not a percentage or probability. The narrative cannot claim a root cause. Review the validated production record and related operational evidence.

### Maintenance risk

The result is an AI-assisted prioritization prototype. It is not reliable predictive maintenance and cannot state that a machine will fail or issue stop/restart instructions.

## Export a report

After a successful explanation, PDF, Excel, and CSV exports can include it when it is linked to the exact same verified inference result. Exports keep verified facts and guarded narrative in separate sections. Export does not run the model or Ollama again.

## Safe error messages

- **Ollama unavailable/model missing/timeout:** the numeric result remains visible; start or repair the local model service and retry later.
- **Rate limited:** wait for the displayed retry period.
- **Output rejected:** the model response failed grounding or safety validation; no raw unsafe text is shown.
- **Snapshot invalid or expired:** rerun the inference to create a new short-lived snapshot.

## Operating boundary

Every result is based on `simulated_prototype` data. A human remains responsible for every production and maintenance decision. No explanation automatically changes data or controls equipment.

# AI Insights and Reports User Guide

## Start the AI service

Open Windows PowerShell:

```powershell
Set-Location C:\Users\OMEN\Herd\smartfactory-dss\fastapi-ai
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Keep that window open. Laravel Herd serves the web application, so do not run
`php artisan serve`.

## Open the application

```text
Dashboard    : https://smartfactory-dss.test/dashboard
Administrator: https://smartfactory-dss.test/admin
AI Insights  : https://smartfactory-dss.test/ai-insights
Reports      : https://smartfactory-dss.test/reports
```

Role dashboards show an AI card only when the authenticated user has access to at least
one AI operation.

## Automatic production forecast

1. Open AI Insights.
2. Choose a production line.
3. Choose the quantity unit.
4. Keep or choose a valid prediction date.
5. Submit the automatic forecast.

Laravel computes the model features from validated history. A successful result shows the
next-day predicted good quantity, model run, feature run, model name, request ID, and
limitations.

## Automatic anomaly check

1. Choose a recent validated production record.
2. Submit the anomaly check.
3. Review score, threshold, and classification.

Do not interpret the score as a percentage. A higher score is more unusual, and the
decision is based on comparison with the displayed threshold.

## Automatic maintenance risk

1. Choose a machine shown as eligible.
2. Choose the prediction date.
3. Submit the risk analysis.

Laravel requires sufficient status, downtime, or maintenance history before the date.
The result contains failure probability, predicted unplanned downtime, and advisory
priority.

## Export an AI report

After a successful result, download PDF, XLSX, or CSV from the result panel. The export
uses the exact stored result and does not execute a second prediction.

Report tokens are:

- bound to the authenticated user;
- stored temporarily in the encrypted session;
- limited in number;
- expired automatically.

## Advanced forms

The detailed manual feature forms remain available for technical troubleshooting. Normal
users should use the automatic forms.

## Important limits

- AI results are advisory.
- All current models use simulated-prototype data.
- No result should automatically control a line or create maintenance work.
- A human remains responsible for every operational decision.

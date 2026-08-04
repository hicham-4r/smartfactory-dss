# Phase 6 Route Catalog

## Laravel routes

### AI workspace

```text
GET  /ai-insights
POST /ai-insights/production/forecast
POST /ai-insights/production/anomaly
POST /ai-insights/maintenance/risk
```

Automatic feature-preparation routes:

```text
POST /ai-insights/automatic/production/forecast
POST /ai-insights/automatic/production/anomaly
POST /ai-insights/automatic/maintenance/risk
```

A browser GET to an automatic POST endpoint redirects to `/ai-insights` rather than
showing a Method Not Allowed exception.

### Reporting

```text
GET /reports
GET /reports/ai/{token}/export/{format}
GET /reports/production/export/{format}
```

Supported AI report formats are `pdf`, `xlsx`, and `csv`.

### Navigation

Role-aware AI navigation is mounted in:

```text
/dashboard
/admin
/reports
```

## FastAPI routes

Public liveness only:

```text
GET /health/live
```

Authenticated internal routes:

```text
GET  /health/ready
GET  /version
POST /internal/v1/ping
POST /internal/v1/contracts/analytics/validate
GET  /internal/v1/inference/models
POST /internal/v1/inference/production/forecast
POST /internal/v1/inference/production/anomaly
POST /internal/v1/inference/maintenance/risk
GET  /internal/v1/inference/models/{model_run_id}/metrics/{task}
```

## Access rules

- Administrator: all AI operations, reports, and service-health visibility.
- Production Manager: forecast and anomaly.
- Production Supervisor: forecast and anomaly.
- Maintenance Manager: maintenance risk.
- Operator: no AI workspace access.

Every Laravel request remains subject to authentication, password-change enforcement,
role permissions, throttling, and administrator 2FA where configured.

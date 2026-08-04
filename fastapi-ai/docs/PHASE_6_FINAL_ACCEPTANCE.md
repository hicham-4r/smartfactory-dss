# FastAPI Phase 6 Final Acceptance

The FastAPI service is accepted as the internal AI boundary for the SmartFactory DSS
simulated prototype.

## Accepted responsibilities

- contract validation;
- dataset and model integrity validation;
- offline preprocessing, feature engineering, and training;
- authenticated inference;
- authenticated read-only model metrics;
- no direct database or simulator access.

## Required routes

```text
GET  /health/live
GET  /health/ready
GET  /version
POST /internal/v1/contracts/analytics/validate
GET  /internal/v1/inference/models
POST /internal/v1/inference/production/forecast
POST /internal/v1/inference/production/anomaly
POST /internal/v1/inference/maintenance/risk
GET  /internal/v1/inference/models/{model_run_id}/metrics/{task}
```

## Accepted model run

```text
f0147a01-3d1a-45d9-9cb8-c2686b531be0
```

## Classification

Every response and metric remains `simulated_prototype`. The service does not provide
industrial guarantees and must remain inaccessible to public clients.

The complete cross-application documentation is installed in:

```text
laravel-app/docs/phase-6
```

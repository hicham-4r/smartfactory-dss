# SmartFactory DSS Phase 6 Documentation

## Status

Phase 6 is accepted as a **working simulated-prototype AI decision-support layer**.

The accepted software scope includes:

- sanitized Laravel dataset snapshots;
- deterministic preprocessing;
- leakage-controlled feature engineering;
- reproducible model training and private registry validation;
- authenticated FastAPI inference;
- role-aware Laravel AI Insights;
- automatic feature preparation;
- AI reports in PDF, XLSX, and CSV;
- verified model metrics and dashboard navigation.

This acceptance is **not industrial model validation**. Every result remains classified as
`simulated_prototype`, and no result may automatically control production, stop machinery,
schedule maintenance, or commit a production quantity.

## Documentation map

- `PHASE_6_COMPLETION_REPORT.md` — complete implementation summary and final status.
- `ARCHITECTURE.md` — components, data flow, trust boundaries, and responsibilities.
- `ROUTE_CATALOG.md` — Laravel and FastAPI routes and access rules.
- `MODEL_CARD_PRODUCTION_FORECASTING.md` — forecasting model behavior and performance.
- `MODEL_CARD_PRODUCTION_ANOMALY.md` — Isolation Forest behavior and score interpretation.
- `MODEL_CARD_MAINTENANCE_RISK.md` — classifier and downtime-regressor assessment.
- `METRICS_INTERPRETATION.md` — meaning of all reported metrics.
- `USER_GUIDE.md` — dashboard, AI Insights, and report-export workflow.
- `OPERATIONS.md` — local operational commands and routine checks.
- `DEPLOYMENT.md` — production deployment requirements.
- `SECURITY.md` — authentication, authorization, integrity, privacy, and audit controls.
- `BACKUP_AND_RECOVERY.md` — backup scope and restoration order.
- `TROUBLESHOOTING.md` — common failure modes and safe corrections.
- `INDUSTRIAL_VALIDATION_PLAN.md` — work required before real factory use.
- `TEST_EVIDENCE.md` — source-bundle test and registry evidence.
- `ACCEPTANCE.md` — final checklist and acceptance boundary.

## Core locations

```text
Project root : C:\Users\OMEN\Herd\smartfactory-dss
Laravel      : C:\Users\OMEN\Herd\smartfactory-dss\laravel-app
FastAPI      : C:\Users\OMEN\Herd\smartfactory-dss\fastapi-ai
Model root   : D:\SmartFactoryDSS\models
```

## Current accepted model run

```text
Model run          : f0147a01-3d1a-45d9-9cb8-c2686b531be0
Feature run        : 79f65f1f-b672-493f-91f3-60a648ac10a0
Preprocessed run   : d3905235-15ff-474c-aece-5b4620a1b599
Source system      : simulated_sage
Classification     : simulated_prototype
```

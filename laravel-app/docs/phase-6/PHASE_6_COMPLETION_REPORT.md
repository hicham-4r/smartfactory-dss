# Phase 6 Completion Report

## Objective

Phase 6 adds a secure AI decision-support layer to SmartFactory DSS without granting the
AI service direct access to Laravel's database or the simulated Sage ERP.

The implementation transforms authorized operational data into versioned snapshots,
preprocesses and validates it, engineers chronological features, trains baseline models,
serves authenticated inference, and exposes role-aware results and reports through Laravel.

## Completed implementation

### Data boundary

Laravel exports sanitized, versioned dataset snapshots. The export excludes credentials,
tokens, raw ERP payloads, free-text notes, user identities, contact details, and other
fields not required for model development.

### Preprocessing and features

FastAPI-side offline commands verify checksums and schemas before processing. Feature
engineering uses chronological train, validation, and test partitions. Supervised target
windows are purged at split boundaries to reduce future-label leakage.

### Model registry

The current accepted model run is:

```text
f0147a01-3d1a-45d9-9cb8-c2686b531be0
```

It is linked to feature run `79f65f1f-b672-493f-91f3-60a648ac10a0` and preprocessed run
`d3905235-15ff-474c-aece-5b4620a1b599`. Artifacts and metrics are private, checksummed, and published
atomically.

### Inference

Authenticated internal endpoints serve:

- next-day good-quantity forecasting;
- production anomaly scoring;
- seven-day maintenance failure probability and unplanned-downtime prediction;
- checksum-verified model evaluation metrics.

### Laravel integration

The existing role dashboards and existing reports workspace link to `/ai-insights`.
Laravel prepares features automatically from authorized DSS data, submits strict payloads
to FastAPI, and renders the result. A successful result can be exported as PDF, XLSX, or
CSV without executing another prediction.

### Reporting

AI reports include:

- input context;
- exact inference result;
- request ID;
- selected model;
- model and feature run IDs;
- evaluation metrics;
- MSE, including transparent RMSE-squared derivation for registry v1 files;
- limitations and `simulated_prototype` classification.

## Acceptance evidence

The uploaded final source bundle recorded:

```text
Laravel            : Laravel Framework 12.64.0
Laravel tests       : 490 passed
Laravel assertions  : 2483
Laravel duration    : 78.57 seconds
Python              : 3.12.10
FastAPI tests       : 179 passed, inferred from pytest progress dots
```

The source-bundle command that attempted to print FastAPI routes had a quoting error.
That error affected only evidence collection, not FastAPI. Route declarations are present
in source, and the Step 21P verifier performs an independent route-contract check.

## Model acceptance summary

| Task | Prototype software status | Industrial model status |
|---|---|---|
| Production forecasting | Accepted | Not validated |
| Production anomaly detection | Accepted | Not validated; no ground-truth anomaly labels |
| Maintenance risk | Accepted as a demonstration | Not reliable predictive maintenance |

The maintenance metrics are intentionally documented without hiding weak performance.
The forecasting and anomaly models are also presented with their real limitations.

## Final outcome

Phase 6 is complete for the PFE simulated environment. The architecture, security controls,
interfaces, tests, dashboards, reports, and technical documentation form a coherent
prototype. A separate real-data validation program is mandatory before industrial use.

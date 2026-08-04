# Phase 6 Architecture

## Logical flow

```text
Simulated Sage ERP
        |
        v
Laravel ERP integration and DSS database
        |
        | authorized sanitized snapshot
        v
Shared dataset boundary on disk D:
        |
        +--> raw snapshot validation
        +--> deterministic preprocessing
        +--> chronological feature engineering
        +--> reproducible training
        v
Private checksummed model registry
        |
        v
FastAPI internal inference and metrics API
        |
        | authenticated strict JSON contract
        v
Laravel AI Insights and existing Reports workspace
        |
        +--> role-aware result display
        +--> PDF / XLSX / CSV export
        +--> audit and security controls
```

## Responsibility boundaries

### Simulated Sage ERP

Produces prototype ERP data only. It is not a real Sage installation and does not prove
compatibility with a production company's Sage configuration.

### Laravel

Laravel remains the authoritative application boundary for:

- authentication and authorization;
- role-aware user experience;
- access to the DSS database;
- automatic feature preparation from authorized records;
- audit logging;
- encrypted session storage for temporary report snapshots;
- native PDF, XLSX, and CSV generation.

### FastAPI

FastAPI remains an internal AI service responsible for:

- offline snapshot validation;
- preprocessing;
- feature engineering;
- model training;
- model-registry integrity validation;
- strict authenticated inference;
- read-only model-metrics delivery.

FastAPI does not query MySQL, Laravel, or the simulator directly.

### Model registry

The model registry is private and immutable per run. A run contains:

```text
manifest.json
manifest.sha256
artifacts/*.joblib
metrics/*.json
```

Before loading Joblib artifacts, the registry validates paths, byte sizes, SHA-256 hashes,
manifest shape, and content fingerprint.

## Trust boundaries

1. Browser to Laravel: authenticated session, CSRF protection, RBAC, optional administrator 2FA.
2. Laravel to FastAPI: internal bearer token, strict endpoint paths, size limits, timeouts.
3. Laravel to shared datasets: sanitized export only.
4. FastAPI to datasets and models: safe-path validation, checksums, immutable run directories.
5. Report download: user-bound temporary token and authorization recheck.

## Explicit exclusions

Phase 6 does not include:

- direct FastAPI access to the DSS database;
- direct FastAPI access to the simulator;
- model-driven database writes;
- automatic production control;
- automatic maintenance work-order creation;
- Ollama-generated KPI calculations;
- industrial certification or real-data validation.

Ollama remains outside this accepted Phase 6 scope.

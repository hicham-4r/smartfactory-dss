# Step 20A Analytics Architecture

## Request flow

```text
GET /analytics/production
        │
        ▼
BrowseProductionKpiRequest
  - authorization
  - date validation
  - timezone validation
  - foreign-key filter validation
        │
        ▼
AnalyticsFilter
  - immutable normalized filters
  - local date boundaries
  - UTC half-open timestamp boundaries
        │
        ▼
ProductionKpiService
  - application orchestration
  - unit-safe merge
  - centralized formula calls
        │
        ├──────────────► KpiFormulaService
        │                 - percentage
        │                 - quantity/hour
        │                 - observed utilization
        │                 - zero-denominator protection
        │
        ▼
ProductionAnalyticsRepositoryInterface
        │
        ▼
EloquentProductionAnalyticsRepository
  - SQL aggregation
  - validated-record eligibility
  - scheduled-target eligibility
  - common filter application
        │
        ▼
ProductionKpiSummary + ProductionKpiUnitSummary
        │
        ▼
Blade view (presentation only)
```

## Security and operational decisions

- Route requires authentication, completed mandatory password change, administrator 2FA when applicable, and `production.kpis.view`.
- Response uses `no-store, private, max-age=0`.
- No ERP payload, synchronization cursor, token, safe context, or raw exception is displayed.
- No persistent scheduler or queue worker is required.
- No migration is needed for this first slice.
- Queries aggregate in the database rather than loading production records into PHP.
- The repository excludes pending and failed imports.
- The service does not combine incompatible quantity units.

## Deferred items

This package intentionally does not add charts, exports, notifications, ML, FastAPI, or Ollama. Those depend on validated deterministic KPI services.

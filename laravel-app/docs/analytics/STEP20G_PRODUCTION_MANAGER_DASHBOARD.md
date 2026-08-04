# Phase 5 — Step 20G: Production Manager Executive Dashboard

## Purpose

This step adds a role-specific executive dashboard for the Production Manager while preserving the shared Step 20E dashboard and the operational Production Supervisor dashboard.

## Data sources

The dashboard reuses the existing deterministic services:

- `ProductionKpiService`
- `ProductionBreakdownService`
- `QualityKpiService`

It reads unresolved critical production events directly from the controlled DSS workflow tables. It does not query the Sage simulator database directly.

## Indicators

- In-progress and completed order counts
- Production target versus actual by quantity unit
- Achievement, good output, rejection, runtime and downtime
- Monthly production trends
- Production-line, product-family and product comparisons
- Best and lowest-performing lines by quantity unit
- Failed inspections, blocked lots, rejected lots and critical nonconformities
- Recent unresolved critical production events

## Boundaries

- Quantities with different units are never combined.
- Quality counts do not use shift or execution-status filters because those dimensions are not preserved in the synchronized quality schema.
- Forecasting, anomaly prediction and AI executive summaries remain deferred until deterministic KPI acceptance and data-quality validation are complete.
- No migration or database rewrite is required.

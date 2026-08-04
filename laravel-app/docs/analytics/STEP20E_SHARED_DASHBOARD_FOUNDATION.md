# Phase 5 — Step 20E: Shared dashboard foundation

## Purpose

Step 20E replaces the Phase 4 placeholder dashboard with a secure,
role-aware overview that reuses the production, maintenance and quality KPI
services implemented in Steps 20A–20D.

The dashboard is a decision-support interface for simulated ERP and DSS
prototype records. It does not control machines, PLCs, SCADA or production
lines.

## Shared filter

The dashboard accepts one common period:

- `start_date`
- `end_date`
- `timezone`

The maximum range uses `analytics.maximum_range_days`.

The same period is mapped into the existing production, maintenance and
quality analytics filters. This avoids contradictory date windows between
dashboard cards and detailed analytics pages.

## Role behavior

### Operator

- Operator workspace link
- No cross-domain KPI snapshot

### Production Supervisor

- Supervisor production workspace
- Production KPI snapshot
- Quality KPI snapshot

### Production Manager

- Production KPI snapshot
- Quality KPI snapshot

### Maintenance Manager

- Maintenance KPI snapshot

### Administrator

- Production, maintenance and quality snapshots
- Administration link
- ERP synchronization monitoring link when both monitoring permissions exist

The service also checks the effective permission before calculating a snapshot
or exposing a module card.

## KPI reuse

Step 20E does not duplicate business formulas.

- Production snapshot comes from `ProductionKpiService`
- Maintenance snapshot comes from `MaintenanceKpiService`
- Quality snapshot comes from `QualityKpiService`

Dashboard-specific DTOs expose only compact values required by the overview.

## Data rules

- Different production quantity units are never summed on the shared page.
- Production cards show record and time counts rather than a mixed-unit total.
- Availability remains observed machine-state availability.
- Sample failure rate remains absent from the quality frontend.
- Empty denominators remain `N/A`.
- Responses use private no-store headers.

## Security

The route retains:

- authenticated access
- mandatory-password-change enforcement
- administrator two-factor enforcement
- role and permission checks inside the dashboard query service

## Tests

The package adds:

- dashboard filter unit tests
- role-aware service tests
- dashboard HTTP and authorization presentation tests

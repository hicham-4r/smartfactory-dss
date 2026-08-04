# Phase 5 — Step 20F: Production Supervisor dashboard

## Purpose

This step turns the shared `/dashboard` route into a detailed operational dashboard when the authenticated account's primary role is `production-supervisor`.

Other roles keep the Step 20E dashboard behavior.

## Reused verified services

No KPI formula is duplicated in the dashboard layer.

The supervisor dashboard reuses:

- `ProductionKpiService` for target, actual, good, rejected, runtime and downtime values;
- `ProductionBreakdownService` for production-line and shift comparisons;
- `QualityKpiService` for inspection, finished-lot and nonconformity attention counts;
- `ProductionAnalyticsRepositoryInterface` for canonical, period-data-backed line, product and shift choices.

## Operational workflow queues

The dedicated query service reads these controlled workflow facts:

- in-progress production orders;
- submitted production records whose validation status is pending;
- unresolved production events;
- unresolved critical production events.

Each queue is constrained by the selected period and applicable line, product, shift and execution-status filters.

## Filter rules

Supported dashboard filters:

- start date;
- end date;
- timezone;
- production line;
- product;
- shift;
- execution status: `in_progress` or `completed`.

Draft, planned, released and cancelled orders remain valid workflow states, but they are intentionally excluded from execution KPI status choices.

Line, product and shift options are canonical and data-backed. Incompatible options are hidden instead of shown as disabled grey entries.

## Quality limitation

The synchronized quality schema supports line and product dimensions. It does not preserve a reliable shift or production-order execution-status dimension for quality records. Therefore, shift and execution-status filters do not change the quality attention cards.

## Security

- The existing authenticated `/dashboard` route is preserved.
- The detailed supervisor section is created only for a user whose primary role is `production-supervisor` and who has `dashboard.production-supervisor.view`.
- Sensitive responses retain `no-store, private` headers.
- No database write is performed by dashboard queries.

## Database impact

None.

This package contains no migration, no synchronization command and no data modification.

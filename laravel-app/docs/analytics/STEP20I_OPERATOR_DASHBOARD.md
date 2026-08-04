# Phase 5 — Step 20I: Operator limited dashboard

## Objective

Provide the Operator with a personal, role-aware `/dashboard` view connected to
the existing production execution workflow.

## Access boundary

The dashboard is restricted to the authenticated user's linked `operators`
record. It never uses company-wide production analytics for the Operator role.

The dashboard displays only:

- current effective operator assignments;
- released and in-progress orders matching those current assignments;
- production records belonging to the linked operator;
- downtime and machine incidents reported by or attributed to the linked
  operator;
- personal runtime, downtime and quantity summaries for the selected period.

It does not display:

- records belonging to other operators;
- company-wide production totals;
- production-line rankings;
- quality summaries;
- maintenance KPIs;
- executive data;
- administrator functions;
- ERP synchronization information.

## Period semantics

The selected dashboard period controls:

- personal production-record metrics and history;
- personal runtime and downtime;
- personal quantity summaries;
- personal downtime and machine-incident history.

Current assignments are evaluated using the current date in the selected
timezone. Active work is restricted through those current assignments and to
orders whose status is `released` or `in_progress`.

## Quantity safety

Quantities are grouped by `quantity_unit`. Values from different units are
never combined.

## Safe exceptional states

When the authenticated account is not linked to an Operator employee record,
the dashboard returns an empty restricted snapshot and displays a linkage
warning.

When the linked Operator record is inactive, the dashboard displays an inactive
profile warning.

When no current assignment exists, no production order is exposed and the
dashboard displays an assignment warning.

## Security controls

- existing authentication and mandatory-password middleware remain unchanged;
- authorization still relies on `dashboard.operator.view`;
- no cross-role snapshot is generated;
- responses keep `no-store`, private cache headers;
- no sensitive credentials are displayed or logged;
- links use existing authorized Operator workflow routes.

## Deferred work

AI recommendations, anomaly prediction, and forecasting are not added in this
deterministic step.

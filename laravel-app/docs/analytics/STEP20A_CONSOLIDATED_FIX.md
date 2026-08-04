# Step 20A Consolidated Analytics Correction

This correction resolves three presentation and analytics problems without deleting synchronized data or changing the database schema.

## 1. Status-aware KPI basis

| Order status | KPI basis |
|---|---|
| Draft | Planning target and order count only |
| Planned | Planning target and order count only |
| Released | Released target and order count; execution values appear after production starts |
| In progress | Validated records plus pending records marked **provisional**; rejected records are excluded |
| Completed | Final validated records only |
| Cancelled | Cancelled-order planning context; execution KPIs are normally not applicable |
| All active statuses | Planned, released, in-progress, and completed targets; actuals remain validated-only |

The application does not fabricate actual production, rejection, runtime, downtime, or utilization values for orders that have not produced an execution record.

## 2. Canonical filter choices

Filter choices are derived from production orders and records that exist in the selected period. Exact-name duplicates are represented once.

When a canonical option is selected, the repository expands it to all database IDs with the same normalized name. This preserves KPI coverage across duplicated source records while keeping the business-facing dropdown clean.

Unused catalogue rows are not displayed in the analytics filters. The original rows remain in the database for ERP synchronization traceability.

## 3. No visible grey options

Incompatible choices are hidden by the page JavaScript rather than left visible as disabled grey options. The default filter state shows all canonical, data-backed choices for the period.

## Security and data integrity

- No migration is included.
- No data is deleted or rewritten.
- ERP external identifiers and synchronization metadata remain untouched.
- Rejected production records never contribute to KPIs.
- Pending records contribute only to an explicit `in_progress` view and are clearly marked provisional.

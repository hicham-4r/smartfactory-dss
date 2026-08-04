# Phase 5 — Step 20C: Maintenance KPI Foundation

## Scope

This implementation calculates deterministic maintenance indicators from the
DSS database after ERP synchronization. The analytics layer does not query the
simulated Sage ERP database directly.

## Data sources

| KPI source | DSS table | Important fields |
|---|---|---|
| Downtime | `production_events` | `event_type`, `category`, `downtime_type`, `started_at`, `duration_minutes`, `is_resolved`, `machine_id`, `production_line_id` |
| Machine status | `machine_status_events` | `machine_external_id`, `status`, `occurred_at`, `ended_at`, `duration_minutes` |
| Maintenance interventions | `maintenance_history` | `machine_external_id`, `maintenance_type`, `status`, `started_at`, `completed_at`, `downtime_minutes` |
| Machine and line identity | `machines`, `production_lines` | local IDs, external IDs, code and name |

## KPI dictionary

| Metric | Formula | Unit | Edge case |
|---|---|---:|---|
| Total downtime | Sum of downtime-event duration | minutes | Missing duration contributes zero and remains visible through event count |
| Planned downtime | Sum where `category = planned` | minutes | Requires Step 20C resynchronization |
| Unplanned downtime | Sum where `category = unplanned` | minutes | Requires Step 20C resynchronization |
| Unclassified downtime | Sum where category is null/unknown | minutes | Signals incomplete backfill |
| Observed availability | Running status minutes / all closed status minutes × 100 | % | Null when observed status minutes are zero |
| MTTR | Completed corrective-maintenance downtime / completed corrective count | minutes | Null when no completed corrective intervention exists |
| MTBF | Observed running minutes / recorded fault-event count | minutes | Null when no fault event exists |
| Failure frequency | Fault count / running hours × 100 | faults per 100 hours | Null when running time is zero |
| Repeated failures | Machine has at least two fault status events | boolean/count | Uses recorded fault episodes, not predictive failure probability |
| Preventive/corrective indicators | Intervention counts grouped by maintenance type | count | Type filter applies only to intervention metrics |
| Highest downtime machine | Machine with greatest summed downtime | machine | Null when all downtime totals are zero |

## Date attribution

Downtime and machine-status values are attributed to the date on which the
source event starts. Maintenance interventions use, in order:

1. `started_at`
2. `scheduled_at`
3. `completed_at`

The current vertical slice does not clip a cross-boundary event to the selected
period. This limitation is explicit and testable.

## Availability limitation

The DSS does not yet have an authoritative planned-production calendar per
machine. Therefore, this phase reports **observed status availability**:

`running minutes / total duration of closed machine-status events`

This value must not be presented as contractual OEE availability.

## Schema enrichment

The simulator already supplies planned/unplanned classification and detailed
downtime type, but the DSS previously discarded those source fields. The
additive migration preserves:

- `production_events.category`
- `production_events.downtime_type`
- `production_events.reason_code`
- `production_events.reason`
- `machine_status_events.duration_minutes`

The migration clears source checksums only for affected synchronized records.
The next full ERP synchronization repersists those records using the expanded
local schema.

## Security

The maintenance page requires:

- authentication;
- mandatory-password workflow completion;
- administrator 2FA when applicable;
- `maintenance.kpis.view`;
- validated filters;
- no-store/private response headers.

No ERP tokens, raw payloads, cursors, failure keys, or exception traces are
displayed.

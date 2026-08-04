# Phase 5 — Step 20B: Production Trends and Breakdowns

## Scope

Step 20B extends the accepted Step 20A production KPI summary. It does not
change authentication, ERP synchronization, background-worker decisions, or
the simulated Sage ERP.

All values are calculated from the synchronized DSS tables:

- `production_orders`
- `production_batches`
- `production_records`
- `products`
- `product_families`
- `production_lines`
- `shifts`

The DSS never queries the simulator database directly.

## Implemented analytics

- Daily production trend
- ISO-week production trend
- Monthly production trend
- Production by line
- Production by shift
- Production by product
- Production by product family
- Actual versus scheduled quantity
- Good and rejected quantity
- Target achievement
- Rejection rate
- Quality yield
- Good-output efficiency
- Runtime and downtime
- Average production rate
- Observed utilization
- Best-performing line per quantity unit
- Lowest-performing line per quantity unit

## Formula dictionary

| Metric | Formula | Data source | Zero-denominator behavior |
|---|---|---|---|
| Target achievement | actual quantity / scheduled target × 100 | order target, or batch planned quantity for shift-scoped analytics | `null` / N/A |
| Rejection rate | rejected quantity / actual quantity × 100 | production records | `null` / N/A |
| Quality yield | good quantity / actual quantity × 100 | production records | `null` / N/A |
| Good-output efficiency | good quantity / scheduled target × 100 | production records + scheduled target | `null` / N/A |
| Average production rate | actual quantity / runtime hours | production records | `null` / N/A |
| Observed utilization | runtime / (runtime + downtime) × 100 | production records | `null` / N/A |

Good-output efficiency is a transparent prototype KPI. It is not OEE because
the current schema does not provide all denominators needed for a standards-
based OEE calculation.

## Target allocation

### No shift filter

Targets are sourced from `production_orders.target_quantity` and assigned to
the order's `planned_start_at` date.

### Specific shift filter

The simulator exposes execution shifts reliably on production records, while a
synchronized order shift may be null. The denominator therefore uses each
matching batch's `production_batches.planned_quantity` once and assigns it to
the first eligible production-record date for that batch.

### Shift breakdown

A batch planned quantity appears for every shift containing an eligible record
from that batch. Consequently, shift target rows describe batch exposure and
must not be summed across shifts when a batch spans several shifts.

## Status rules

The execution analytics endpoint accepts only:

- all execution statuses (`null`): completed validated records plus in-progress
  validated or pending records;
- `in_progress`: validated and pending records, where pending values are marked
  provisional;
- `completed`: validated records only.

Draft, planned, released, and cancelled orders remain valid workflow states but
are intentionally excluded from the execution-performance dashboard.

## Quantity units

All database queries and result DTOs retain `quantity_unit`. Bottles, liters,
or any future units are never silently combined. Line rankings are calculated
separately for each unit.

## Line ranking

Within each quantity unit:

1. higher target achievement ranks first;
2. a tie prefers lower rejection rate;
3. a further tie prefers higher actual output;
4. the final tie-breaker is the line name.

The lowest ranking reverses these comparisons.

## Performance and security

- Aggregation occurs in SQL.
- Weekly and monthly rows are rolled up from already aggregated daily rows.
- The maximum date range remains controlled by the existing request.
- The existing production-KPI permission and no-store response headers remain.
- No migration is required.
- No ERP or Redis credentials are included.
- No machine-learning or LLM values are used.

# Phase 5 Step 20D — Quality KPI dictionary

## Data sources preserved in the DSS

Step 20D reads only synchronized DSS tables:

- `inspections`;
- `finished_lots`;
- `nonconformities`;
- `production_batches`, `production_orders`, `products`, `product_families`, and `production_lines` for analytical context.

The simulator exposes a separate quality-test-result resource containing BRIX, pH, fill volume, package integrity, microbiology, and sensory tests. That resource is not currently persisted in the DSS quality schema. Step 20D therefore does not fabricate per-test-code KPIs.

## Date attribution

| Record | Date used |
|---|---|
| Inspection | `inspected_at` |
| Finished lot | `produced_at` |
| Nonconformity | `detected_at` |

All boundaries are converted from the selected timezone to a half-open UTC interval: `[start of first day, start of day after end date)`.

## KPI formulas

| KPI | Formula | Zero-denominator behavior |
|---|---|---|
| Inspection pass rate | passed inspections / all eligible inspections × 100 | N/A |
| Sample failure rate | failed sampled quantity / sample size × 100 | N/A |
| Released-lot rate | released lots / all eligible finished lots × 100 | N/A |
| Held/rejected-lot rate | (blocked lots + rejected lots) / all eligible finished lots × 100 | N/A |
| Nonconformities per 100 inspections | nonconformities / inspections × 100 | N/A |
| Released-quantity rate | released quantity / produced quantity × 100, within one unit | N/A |
| Rejected-quantity rate | rejected quantity / produced quantity × 100, within one unit | N/A |

Quantities from different units are never summed together.

## Filters

- date range;
- timezone;
- production line;
- product family;
- product;
- inspection result;
- finished-lot status;
- nonconformity severity;
- nonconformity status;
- lot-number text search.

Line, family, and product choices are data-backed for the selected period and quality-status filters. Unrelated seeded catalogue entries are not displayed.

## Access and security

The page uses the existing `production.kpis.view` permission, which already belongs to Production Supervisors, Production Managers, and Administrators. Operators and Maintenance Managers do not receive access through this step. Responses use private no-store headers.

## Limitations

- The prototype uses simulated ERP and DSS data, not real company operational data.
- Quality-test-result rankings require a future additive synchronization resource and local table.
- The current finished-lot schema stores released and rejected quantities but does not store a distinct blocked quantity. Blocked status is counted at lot level; blocked quantity is not inferred.

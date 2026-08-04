# SmartFactory DSS — Phase 5 KPI Dictionary

## Scope and evidence basis

This dictionary is based on the current DSS schema contained in the Phase 5 source bundle. It does not assume fields that are absent from the database. All values are prototype indicators calculated from simulated ERP data or controlled DSS entries; they are not real company production figures.

### Eligibility rule used by the first implementation slice

Production actuals use only `production_records` where:

- `validation_status = validated`;
- `import_status` is `not_applicable`, `imported`, or `skipped`;
- the related production order is not cancelled unless the user explicitly filters for a status.

Production targets use `production_orders` where:

- `planned_start_at` falls in the selected timezone-aware period;
- `import_status` is `not_applicable`, `imported`, or `skipped`;
- status is planned, released, in progress, or completed unless the user explicitly selects another status.

Quantities are always calculated separately by `quantity_unit`. Incompatible units are never silently summed.

## Production KPIs

| KPI | Business meaning | Formula | Numerator | Denominator | Unit | Aggregation | Current data source | Filters | Edge cases | Interpretation | Current status / limitation |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Daily production | Validated quantity recorded for one day | `SUM(produced_quantity)` | Validated produced quantity | N/A | Per `quantity_unit` | Day | `production_records.production_date`, `produced_quantity` | Date, line, shift, product, family, order status/order | Empty set returns no data | Output recorded during the day | Ready for later trend query |
| Weekly production | Validated quantity recorded during a week | `SUM(produced_quantity)` | Validated produced quantity | N/A | Per `quantity_unit` | ISO/business week | Same as daily | Same | Week boundaries must use selected timezone | Weekly output | Ready for later trend query |
| Monthly production | Validated quantity recorded during a month | `SUM(produced_quantity)` | Validated produced quantity | N/A | Per `quantity_unit` | Month | Same as daily | Same | Month boundaries must use selected timezone | Monthly output | Ready for later trend query |
| Actual quantity | Total validated production in the selected period | `SUM(production_records.produced_quantity)` | Produced quantity | N/A | Per `quantity_unit` | Selected period | `production_records` joined through batch and order | Common production filters | Mixed units are split; pending/rejected records excluded | Actual validated output | Implemented in Step 20A |
| Target quantity | Production target scheduled in the selected period | `SUM(production_orders.target_quantity)` | Target quantity | N/A | Per `quantity_unit` | Selected period | `production_orders.planned_start_at`, `target_quantity` | Common production filters | Cancelled orders excluded by default; target period is based on planned start | Scheduled target | Implemented in Step 20A |
| Target achievement | Degree to which actual output meets scheduled target | `actual / target × 100` | Actual quantity | Target quantity | % | Period and unit | Actual + target queries | Same | Target zero returns `null / N/A` | 100% means target met | Implemented in Step 20A; actual date and target planned-start date may differ for delayed orders |
| Good quantity | Accepted quantity in validated records | `SUM(good_quantity)` | Good quantity | N/A | Per `quantity_unit` | Period | `production_records.good_quantity` | Same | Mixed units split | Conforming output | Implemented in Step 20A |
| Rejected quantity | Rejected quantity in validated records | `SUM(rejected_quantity)` | Rejected quantity | N/A | Per `quantity_unit` | Period | `production_records.rejected_quantity` | Same | Mixed units split | Nonconforming output | Implemented in Step 20A |
| Rejection rate | Share of recorded production rejected | `rejected / produced × 100` | Rejected quantity | Produced quantity | % | Period and unit | `production_records` | Same | Produced quantity zero returns `null / N/A` | Lower is normally better | Implemented in Step 20A |
| Production per line | Validated output grouped by line | `SUM(produced_quantity) GROUP BY production_line_id` | Produced quantity | N/A | Per unit | Line + period | `production_records.production_line_id` | Common filters | Mixed units split | Compares line output | Ready for Step 20B |
| Production per shift | Validated output grouped by shift | `SUM(produced_quantity) GROUP BY shift_id` | Produced quantity | N/A | Per unit | Shift + period | `production_records.shift_id` | Common filters | Shift assignments may cross midnight | Compares shift output | Ready for Step 20B |
| Production per product | Validated output grouped by order product | `SUM(produced_quantity) GROUP BY production_orders.product_id` | Produced quantity | N/A | Per unit | Product + period | Records → batch → order → product | Common filters | Requires join; mixed units split | Product output | Ready for Step 20B |
| Production per product family | Validated output grouped by product family | `SUM(produced_quantity) GROUP BY products.product_family_id` | Produced quantity | N/A | Per unit | Family + period | Records → order → product → family | Common filters | Requires join | Family output | Ready for Step 20B |
| Average production rate | Average output per recorded runtime hour | `actual quantity × 60 / runtime_minutes` | Actual quantity | Runtime minutes | Quantity unit/hour | Period and unit | `production_records.produced_quantity`, `runtime_minutes` | Common filters | Runtime zero returns `null / N/A` | Throughput during recorded runtime | Implemented in Step 20A |
| Production duration | Total validated runtime | `SUM(runtime_minutes)` | Runtime minutes | N/A | Minutes | Period | `production_records.runtime_minutes` | Common filters | Zero may mean missing or genuine zero | Recorded operating duration | Implemented in Step 20A |
| Total downtime | Downtime attached to validated production records | `SUM(downtime_minutes)` | Downtime minutes | N/A | Minutes | Period | `production_records.downtime_minutes` | Common filters | Do not add production-event duration again or downtime may be double counted | Recorded production downtime | Implemented in Step 20A |
| Observed utilization | Share of observed operating window spent running | `runtime / (runtime + downtime) × 100` | Runtime minutes | Runtime + downtime | % | Period and unit | `production_records.runtime_minutes`, `downtime_minutes` | Common filters | Zero observed time returns `null / N/A` | Operational utilization of recorded time | Implemented as provisional indicator in Step 20A |
| Scheduled line utilization | Share of scheduled available time spent running | `runtime / planned available time × 100` | Runtime | Planned available time | % | Line + period | Runtime exists; planned available time does not | Common filters | Denominator unavailable | True schedule utilization | Gap: add a justified production calendar/scheduled-availability source later |
| Production efficiency | Output compared with theoretical capacity during runtime | `actual / (nominal_capacity_per_hour × runtime_hours) × 100` | Actual quantity | Theoretical capacity output | % | Line + period | `production_lines.nominal_capacity_per_hour`, `capacity_unit`, record actual/runtime | Common filters | Calculate only when capacity unit matches production quantity unit and capacity is non-null | Capacity efficiency | Conditional; requires unit compatibility validation |
| Best-performing line | Line with the highest approved comparison score | `MAX(line achievement or efficiency)` | Selected line score | N/A | Ranking | Line + period | Aggregated line KPIs | Common filters | Tie handling and minimum data threshold required | Strongest line in selected period | Definition must be approved before implementation |
| Lowest-performing line | Line with the lowest approved comparison score | `MIN(line achievement or efficiency)` | Selected line score | N/A | Ranking | Line + period | Aggregated line KPIs | Common filters | Tie handling and minimum data threshold required | Weakest line in selected period | Definition must be approved before implementation |

## Maintenance KPIs

| KPI | Formula / source mapping | Status and limitation |
|---|---|---|
| Total operational downtime | Sum `production_events.duration_minutes` for `event_type = downtime`, or use production-record downtime for production summaries | Available, but the two sources must not be added together without deduplication rules |
| Planned downtime | Sum downtime classified as planned | Gap: ERP mapper receives `downtime_type`, but the current `production_events` table has no dedicated `downtime_type` column |
| Unplanned downtime | Sum downtime classified as unplanned | Same schema gap as planned downtime |
| Machine availability | `operating time / planned production time × 100` | Machine status intervals exist in `machine_status_events`, but planned production time is not explicitly stored; conditional |
| MTTR | Completed corrective repair duration / completed corrective interventions | Supported by `maintenance_history.maintenance_type`, `status`, `started_at`, `completed_at`, and `downtime_minutes`; ready for maintenance slice after repair-duration precedence is fixed |
| MTBF | Operating time / failure count | Failure events and machine status intervals exist; exact operating-time denominator and failure definition require approval |
| Failure frequency | Count fault/machine-incident events per machine and period | Supported using `machine_status_events.status = fault` or production machine incidents; one authoritative source must be selected |
| Repeated failures | Count repeated failures by machine and normalized reason/category | Partially supported; free-text reasons are available, but a stable failure code is absent locally |
| Highest-downtime machines | Rank machines by authoritative downtime minutes | Supported after choosing the non-duplicated downtime source |
| Maintenance intervention count | `COUNT(maintenance_history.id)` | Supported |
| Preventive versus corrective | Counts and percentages by `maintenance_type` | Supported (`preventive`, `corrective`, `inspection`, `calibration`) |
| Preventive completion rate | Completed preventive interventions / scheduled preventive interventions × 100 | Supported if cancelled rows are excluded and period semantics are documented |

## Quality KPIs

| KPI | Formula / source mapping | Status and limitation |
|---|---|---|
| Inspected lots | Distinct `batch_external_id` or `finished_lot_external_id` in `inspections` | Supported; definition should specify batch versus finished lot |
| Released lots | Count `finished_lots.status = released` | Supported |
| Held lots | Count `finished_lots.status IN (pending, blocked)` | Supported after business approval of “held” definition |
| Rejected lots | Count `finished_lots.status = rejected` | Supported |
| Nonconformity count | `COUNT(nonconformities.id)` | Supported |
| Nonconformity rate | Nonconformity count / inspected lot count × 100 | Supported, but multiple nonconformities may belong to one inspection; interpretation must state that it is event density, not probability |
| Defect quantity | `SUM(inspections.failed_quantity)` | Supported when failed quantity is populated |
| Sample rejection rate | `SUM(failed_quantity) / SUM(sample_size) × 100` | Supported; zero or null sample size returns `null / N/A` |
| Finished-lot rejection rate | `SUM(finished_lots.rejected_quantity) / SUM(produced_quantity) × 100` | Supported by quantity unit; mixed units must be separated |
| Quality status by line | Join inspection/lot batch external ID to local batch and order line | Possible because source external IDs are retained; relationship is string-based and should be encapsulated in a repository |
| Quality status by product | Join finished lot product external ID to local product external ID | Possible; source-system scope must be included to avoid accidental collisions |

## Formula and display rules

1. Division by zero returns `null`, displayed as `N/A`; it never becomes a misleading `0%`.
2. Empty datasets produce an explicit empty state.
3. Quantities with different units are displayed separately.
4. Timestamp periods use half-open UTC boundaries derived from the selected local timezone: `[local start 00:00, day after local end 00:00)`.
5. Date-based production records use inclusive local dates.
6. Cancelled production orders are excluded by default.
7. Failed and pending imports are excluded.
8. No KPI is calculated by Blade templates, controllers, the future LLM, or the future ML service.
9. Production-event downtime is not added to production-record downtime until a deduplication rule exists.
10. Every later dashboard, report, ML feature, and LLM explanation must consume these verified analytics outputs rather than reimplementing formulas.

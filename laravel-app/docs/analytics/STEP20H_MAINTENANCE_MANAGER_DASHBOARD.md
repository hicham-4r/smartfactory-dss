# Phase 5 — Step 20H: Maintenance Manager dashboard

## Purpose

This step adds the dedicated Maintenance Manager operational dashboard while
reusing the verified maintenance analytics service.

## Displayed indicators

- total downtime;
- planned, unplanned, and unclassified downtime;
- open downtime-event count;
- observed machine-state availability;
- MTTR;
- MTBF;
- failure frequency per 100 running hours;
- preventive and corrective interventions;
- repeated-failure machines;
- highest-downtime machine;
- machine-level status and maintenance indicators;
- maintenance intervention status by type.

## Filters

The dashboard supports:

- start and end date;
- timezone;
- production line;
- machine;
- maintenance type;
- downtime category.

Production-line and machine choices are period-data-backed. The machine list is
filtered in the browser when a production line is selected.

## Data semantics

Availability is observed machine-state availability and is not presented as OEE.
MTTR uses completed corrective-maintenance downtime. MTBF uses observed running
minutes divided by recognized failures. Missing denominators remain `N/A`.

## Deferred capabilities

AI maintenance recommendations and predictive-maintenance models remain deferred
until deterministic dashboards and FastAPI integration are implemented and
validated.

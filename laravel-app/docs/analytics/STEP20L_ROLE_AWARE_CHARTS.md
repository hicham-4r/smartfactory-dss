# Phase 5 — Step 20L: Role-aware chart visualizations

## Purpose

This step adds a local, dependency-free SVG chart layer to the validated
role-aware dashboards. It does not change KPI formulas, database queries,
authorization rules, filters, or role visibility.

## Architecture

Charts are rendered by:

```text
resources/js/smartfactory-charts.js
```

The module is imported by the existing Vite entry point:

```text
resources/js/app.js
```

No CDN or third-party charting library is used.

The server renders small, role-specific JSON chart configurations into
`application/json` script elements. JavaScript reads those configurations and
creates SVG charts in the browser. No additional API call or database query is
performed by JavaScript.

## Role isolation

The shared dashboard chart partial checks the existing role-specific snapshot
before rendering:

- Operator charts use only `operatorDashboard`;
- Production Supervisor charts use only `productionSupervisor`;
- Production Manager charts use only `productionManager`;
- Maintenance Manager charts use only `maintenanceManager`;
- Administrator charts use only the sanitized `AdministratorOperationsSnapshot`.

The Operator never receives company-wide production, maintenance, quality,
executive, ERP-monitoring, queue-payload, or administration data.

## Charts

### Operator

- personal produced, good, and rejected quantities, separated by unit;
- personal runtime versus downtime.

### Production Supervisor

- pending validations and event workload;
- target versus actual output by production line, separated by unit;
- target versus actual output by shift, separated by unit.

### Production Manager

- monthly target versus actual trend, separated by unit;
- actual and rejected output by production line;
- output by product family;
- quality risk indicators.

### Maintenance Manager

- planned, unplanned, and unclassified downtime;
- machine downtime;
- observed machine availability;
- maintenance intervention status by type.

### Administrator

- active versus inactive users;
- Operator account and assignment readiness exceptions;
- queue state;
- ERP synchronization indicators.

## Accessibility

Every chart includes:

- an SVG `role="img"` and an accessible label;
- value tooltips;
- an always-available HTML data-table fallback;
- a clear no-data state;
- responsive horizontal scrolling on narrow screens;
- reduced-motion support.

## Data rules

- Different quantity units are never combined.
- Missing values do not create additional server queries.
- Empty or all-zero charts display a safe no-data state.
- Chart data comes only from existing validated DTOs.
- Administrator charts contain sanitized counts only.
- Queue payloads, exception traces, ERP secrets, cursors, audit metadata,
  IP addresses, and user agents are not embedded.

## Build

After installation:

```powershell
npm run build
```

The project already contains the required Vite, Bootstrap, and Alpine
dependencies. No new npm package is introduced.

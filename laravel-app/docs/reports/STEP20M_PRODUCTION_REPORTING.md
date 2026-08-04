# Phase 5 — Step 20M: Production reporting foundation

## Purpose

Step 20M adds the first production-grade reporting workflow to SmartFactory
DSS. It reuses the deterministic production KPI and breakdown services already
validated in Phase 5.

## Workspace

The reporting interface is available at:

```text
/reports
```

The page provides a filterable preview and secure download actions.

## Filters

The report request supports:

- report type;
- start and end date;
- timezone;
- production line;
- product family;
- product;
- shift;
- production order;
- execution status.

The maximum date range continues to use
`analytics.maximum_range_days`.

## Report types

- daily production;
- weekly production;
- monthly production;
- production by line;
- production by product;
- production by shift;
- executive production.

Each type is checked against the existing role permission matrix.

## Export formats

### CSV

CSV uses UTF-8 with a byte-order mark for spreadsheet compatibility.
Formula-like string prefixes are neutralized before export.

### Excel

The `.xlsx` exporter writes a standards-based OpenXML workbook through a
dependency-free ZIP container. It includes:

- report metadata;
- applied filters;
- KPI summary;
- the selected primary breakdown;
- readable column widths;
- header styling;
- numeric cells for measurable values.

### PDF

The PDF exporter creates a valid PDF 1.4 document using the built-in Helvetica
font. Long reports are split across landscape A4 pages.

## Security

- report routes require authentication;
- mandatory password-change middleware remains active;
- Administrator 2FA middleware remains active;
- exports require `production.reports.export`;
- executive reports require the executive-report permission;
- filenames are sanitized;
- response caching is disabled;
- `X-Content-Type-Options: nosniff` is applied;
- spreadsheet formula injection is mitigated;
- report-generation events are recorded in `audit_logs`.

## Audit action

```text
reporting.production.generated
```

Audit metadata contains only:

- report type;
- export format;
- sanitized filename;
- validated filters;
- primary row count;
- quantity-unit count.

## Data semantics

- all KPI formulas come from the existing analytics services;
- mixed quantity units are never added together;
- completed views use validated records;
- in-progress pending records remain marked as provisional;
- rejected validation records are excluded.

## Deferred reporting

Maintenance reports and broader executive cross-domain reports remain separate
future steps. This step establishes the reusable production reporting
foundation first.

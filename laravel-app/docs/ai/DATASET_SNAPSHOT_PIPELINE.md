# Phase 6 Step 21C — dataset snapshot pipeline

## Boundary

```text
DSS MySQL
   ↓
Laravel dataset repository
   ↓
sanitization and deterministic ordering
   ↓
atomic CSV + manifest snapshot on shared storage
   ↓
FastAPI read-only verifier
   ↓
future preprocessing and model training
```

FastAPI never queries the DSS database or the simulated ERP database.

## Dataset files

The v1 schema supports:

- `production_records`;
- `downtime_events`;
- `machine_status_events`;
- `maintenance_history`;
- `quality_inspections`;
- `finished_lots`;
- `nonconformities`.

Only explicit structured fields are exported. Free-form operational text and
personal information are excluded.

## Storage configuration

Use `AI_DATASET_ROOT`. Local Windows configuration uses disk D, while later
container environments use mounted paths. The PHP source contains no hardcoded
Windows path.

## Operational behavior

- one exclusive filesystem lock prevents concurrent snapshot generation;
- files are streamed with Laravel cursors;
- row and byte limits protect local resources;
- every CSV has fixed ordered headers;
- every file and manifest has SHA-256 integrity metadata;
- a failed snapshot is deleted from staging;
- only a complete snapshot is published;
- `LATEST` is updated atomically;
- an audit record contains only snapshot metadata and checksums.

## Honesty

All exported data is labeled `simulated_prototype`. The snapshot must never be
described as company production data or as evidence of industrial predictive
accuracy.

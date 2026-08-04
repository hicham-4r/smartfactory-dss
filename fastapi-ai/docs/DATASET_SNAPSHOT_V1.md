# Dataset snapshot contract v1

## Purpose

Step 21C creates a reproducible, file-based boundary between the Laravel DSS and the
FastAPI AI service.

Laravel remains the only component that can query the DSS database. It authorizes,
filters, sanitizes, orders, and exports the records. FastAPI reads only published
snapshot files and never connects directly to MySQL or the simulated Sage ERP.

## Storage

The shared root is configured with `AI_DATASET_ROOT`.

Native Windows development uses:

```text
D:/SmartFactoryDSS/datasets
```

Later Docker and Kubernetes environments will use a mounted persistent path such as:

```text
/data/datasets
```

The application source code does not hardcode either path.

## Published structure

```text
AI_DATASET_ROOT/
├── LATEST
├── .snapshot.lock
├── .staging/
└── snapshots/
    └── <snapshot UUID>/
        ├── manifest.json
        ├── manifest.sha256
        └── data/
            ├── production_records.csv
            ├── downtime_events.csv
            ├── machine_status_events.csv
            ├── maintenance_history.csv
            ├── quality_inspections.csv
            ├── finished_lots.csv
            └── nonconformities.csv
```

A snapshot may contain any non-empty subset of the registered files.

## Safety and privacy

The exporter deliberately excludes:

- user IDs, user names, email addresses, phone numbers, and passwords;
- Operator identities and employee codes;
- free-form notes, comments, descriptions, reasons, and corrective-action text;
- bearer tokens, database credentials, raw ERP payloads, and audit payloads;
- real-company claims.

The manifest always contains:

```json
{
  "data_classification": "simulated_prototype",
  "source_system": "simulated_sage"
}
```

CSV formula prefixes are neutralized before writing.

## Reproducibility

Each file has:

- exact ordered v1 columns;
- deterministic row ordering;
- row count;
- byte count;
- SHA-256 checksum.

The manifest has its own checksum. A content fingerprint combines the date range,
source classification, and every dataset checksum.

Publishing is atomic: files are created under `.staging` and renamed into
`snapshots/<UUID>` only after all hashes and the manifest are complete.

## Laravel command

```powershell
php artisan ai:dataset:snapshot `
  --start=2026-01-01 `
  --end=2026-08-02 `
  --timezone=Africa/Casablanca `
  --datasets=all
```

## FastAPI verification

```powershell
python -m app.cli.datasets verify `
  --snapshot D:/SmartFactoryDSS/datasets/snapshots/<snapshot UUID>
```

The validator checks the manifest contract, safe paths, exact schemas, file sizes,
hashes, row counts, and content fingerprint.

## Current limitation

Step 21C prepares trustworthy training inputs. It does not train a model, calculate
new KPIs, produce forecasts, detect anomalies, or call Ollama.

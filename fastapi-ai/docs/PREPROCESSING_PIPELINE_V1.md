# Data-quality and preprocessing pipeline v1

## Purpose

Step 21D converts one verified Step 21C raw dataset snapshot into an immutable,
checksummed preprocessing run. The operation is deterministic for the same input
rows and ruleset. It does not train a model or create predictive features.

Laravel remains the only component that queries the DSS database. FastAPI code
reads only the published file snapshot on the shared dataset volume.

## Storage layout

Native Windows development uses the configured root:

```text
D:/SmartFactoryDSS/datasets/preprocessed
```

Published structure:

```text
preprocessed/
├── PREPROCESSED_LATEST
├── .preprocessing.lock
├── .staging/
└── runs/
    └── <run UUID>/
        ├── manifest.json
        ├── manifest.sha256
        ├── quality-report.json
        ├── data/
        │   └── <dataset>.csv
        └── issues/
            └── <dataset>.jsonl
```

The input snapshot is never modified. Publication is atomic: the complete run is
built under `.staging` and renamed only after internal verification succeeds.

## Ruleset v1

The pipeline performs only deterministic, explainable transformations:

- strips surrounding and repeated whitespace;
- converts timestamps to timezone-aware UTC ISO-8601 values;
- canonicalizes dates, integers, finite decimals and Boolean values;
- uppercases identifiers, quantity units and currency codes;
- lowercases categorical values;
- preserves missing optional values as blank;
- rejects rows with missing required values or invalid types;
- rejects impossible temporal or quantity relationships;
- removes later occurrences of exact normalized duplicates;
- protects CSV text fields from formula-prefix execution.

The pipeline deliberately does **not**:

- impute numeric or categorical values;
- delete statistical outliers;
- encode categories;
- scale numeric values;
- create target variables or predictive features;
- connect to MySQL or the simulated ERP;
- call Ollama;
- train or select a model.

These operations belong to later feature-engineering and model-training steps.

## Privacy and traceability

The quality report and issue files contain only dataset names, row numbers,
column names, rule codes, counts, ranges and safe messages. They do not contain
raw cell values.

Every run preserves:

```json
{
  "source_system": "simulated_sage",
  "data_classification": "simulated_prototype"
}
```

Each data file, issue file, quality report and manifest has a SHA-256 checksum.
The content fingerprint links the preprocessing result to the exact source
snapshot fingerprint and ruleset version.

## Commands

Create a preprocessing run:

```powershell
python -m app.cli.datasets preprocess `
  --snapshot D:/SmartFactoryDSS/datasets/snapshots/<snapshot UUID> `
  --output-root D:/SmartFactoryDSS/datasets/preprocessed
```

Verify a published run:

```powershell
python -m app.cli.datasets verify-preprocessed `
  --run D:/SmartFactoryDSS/datasets/preprocessed/runs/<run UUID>
```

A successful preprocessing receipt is structural and data-quality evidence for
the simulated prototype. It is not evidence of model accuracy or industrial
validation.

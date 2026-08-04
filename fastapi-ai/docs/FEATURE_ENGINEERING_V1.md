# Feature engineering contract v1

## Purpose

Step 21E converts the verified Step 21D preprocessing run into deterministic,
versioned feature inputs for the three structured machine-learning objectives:

- production forecasting;
- production anomaly detection;
- maintenance risk and prioritization.

This step does not train a model or produce an operational prediction. It prepares
reproducible inputs and leakage-safe chronological partitions.

## Trust boundary

FastAPI reads only the published Step 21D files. It does not connect to Laravel,
MySQL, the simulated Sage ERP database, or Ollama.

Every feature manifest preserves:

```json
{
  "source_system": "simulated_sage",
  "data_classification": "simulated_prototype"
}
```

Feature outputs must never be described as real company observations.

## Published structure

```text
AI_FEATURE_ROOT/
├── FEATURE_LATEST
├── .feature-engineering.lock
├── .staging/
└── runs/
    └── <feature run UUID>/
        ├── manifest.json
        ├── manifest.sha256
        └── data/
            ├── production_forecasting/
            │   ├── train.csv
            │   ├── validation.csv
            │   └── test.csv
            ├── production_anomaly/
            │   ├── train.csv
            │   ├── validation.csv
            │   └── test.csv
            └── maintenance_risk/
                ├── train.csv
                ├── validation.csv
                └── test.csv
```

Publication is atomic. Files are written below `.staging`, validated, then renamed
into `runs/<UUID>`. `FEATURE_LATEST` is replaced only after successful validation.

## Production forecasting task

The task is grouped by production line, quantity unit, and calendar day.

Inputs are built only from the forecast origin day and earlier observations:

- one-day and seven-day good-quantity lags;
- seven-day observed rolling statistics;
- previous-day produced, target, runtime, and downtime values;
- previous-day rejection and achievement ratios;
- calendar fields.

The target is next-calendar-day good quantity. Quantity units remain separated.
The target day is not used as an input feature.

## Production anomaly task

The anomaly task creates one numeric/categorical feature row per verified production
record. It includes deterministic ratios such as:

- achievement ratio;
- rejection ratio;
- good-yield ratio;
- throughput per hour;
- downtime ratio.

It has no supervised target in Step 21E. Later anomaly-model selection must fit only
on the training partition.

## Maintenance-risk task

The task creates daily prediction rows by machine. Features use only events strictly
before `prediction_date`:

- seven-day machine-state and fault history;
- seven-day total and unplanned downtime history;
- thirty-day preventive/corrective maintenance history;
- days since the last failure and maintenance event.

The targets describe the following seven-day window:

- whether a recognized failure occurs;
- unplanned downtime minutes.

Failure recognition uses deterministic prototype rules based on explicit unplanned
classification, recognized failure/fault/breakdown terms, critical downtime, and fault
machine states. These rules are documented assumptions for simulated data and are not
industrial validation.

## Chronological splitting and leakage prevention

The default split ratios are:

```text
train      70%
validation 15%
test       15%
```

Splits use global chronological timestamps, never random shuffling.

For supervised tasks, rows whose target window crosses the next split boundary are
purged:

- production forecasting uses a one-day target horizon;
- maintenance risk uses a seven-day target horizon.

The manifest records generated, retained, and purged row counts and the timestamp range
of every split.

## Reproducibility and validation

Each split file has:

- an exact registered v1 header;
- deterministic ordering;
- row count;
- byte size;
- SHA-256 checksum;
- minimum and maximum timestamp.

The validator checks:

- manifest and checksum integrity;
- exact task schemas;
- safe relative paths;
- file size, hash, and row count;
- deterministic row order;
- non-overlapping chronological split ranges;
- supervised target-window separation;
- content fingerprint consistency.

## Current limitation

Step 21E does not:

- train or select models;
- estimate business performance;
- serve predictions through HTTP;
- produce anomaly scores;
- prioritize maintenance work orders;
- call Ollama.

Those activities begin only after the feature contract and splits are accepted.

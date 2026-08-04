# Model training and registry contract v1

## Purpose

Step 21F trains reproducible baseline models from one verified Step 21E feature run. It never
queries Laravel, MySQL, or the simulated Sage ERP. The only training input is the published,
checksummed feature directory.

All outputs remain explicitly classified as `simulated_prototype`.

## Registered tasks

### Production forecasting

Candidate regressors:

- mean dummy baseline;
- linear regression;
- gradient boosting regressor;
- random forest regressor.

Candidates are fitted only on the chronological training split and selected by validation MAE.
The selected algorithm is refitted on train plus validation, then evaluated once on the untouched
test split. Metrics include MAE, RMSE, R², and MAPE only for non-zero targets.

### Production anomaly detection

An Isolation Forest is fitted without labels. The anomaly threshold is derived from the configured
training-score quantile. Reports contain score distributions and anomaly rates, but deliberately do
not claim accuracy because the feature contract has no ground-truth anomaly label.

### Maintenance risk

The task produces two related estimators:

- a failure-risk classifier for `target_failure_next_7d`;
- an unplanned-downtime regressor for
  `target_unplanned_downtime_minutes_next_7d`.

Classifier candidates are a prior baseline, logistic regression, and random forest. Regressor
candidates mirror the production forecasting baselines. When a training split contains only one
failure class, the pipeline uses the prior baseline and records the limitation rather than inventing
classification performance.

The result must be described as an **AI-assisted maintenance prioritization and anomaly detection
prototype**, not reliable industrial predictive maintenance.

## Registry layout

```text
AI_MODEL_ROOT/
├── MODELS_LATEST
├── .model-training.lock
├── .staging/
└── runs/
    └── <model run UUID>/
        ├── manifest.json
        ├── manifest.sha256
        ├── artifacts/
        │   ├── production_forecasting.joblib
        │   ├── production_anomaly.joblib
        │   └── maintenance_risk.joblib
        └── metrics/
            ├── production_forecasting.json
            ├── production_anomaly.json
            └── maintenance_risk.json
```

Publishing is atomic. Every artifact and metric file has an exact byte count and SHA-256 checksum.
The validator verifies metadata and hashes without deserializing Joblib artifacts.

## Reproducibility

- fixed random seed;
- chronological Step 21E splits;
- deterministic single-worker tree estimators;
- recorded Python, NumPy, Pandas, Scikit-learn, and Joblib versions;
- versioned training ruleset and model-registry contracts;
- immutable source feature fingerprint.

## Security and limitations

Joblib files are executable Python serialization artifacts. They must remain private, must be loaded
only after checksum and registry validation, and must never be accepted from users or external
systems.

Step 21F does not expose inference endpoints, generate operational forecasts, create alerts, call
Ollama, or modify Laravel data. Those integrations follow only after the registry is accepted.

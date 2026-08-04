# Phase 6 security design through Step 21D

- FastAPI remains an internal service and binds to `127.0.0.1` during native Windows development.
- Laravel authenticates to HTTP endpoints with a bearer token stored only in private environment files.
- FastAPI does not connect directly to MySQL or the simulated Sage ERP.
- Raw and preprocessed snapshots remain explicitly classified as `simulated_prototype` from `simulated_sage`.
- Dataset manifests, files, quality reports and issue files are protected with SHA-256 checksums.
- Raw snapshots are immutable; preprocessing publishes atomically under a separate root.
- Preprocessing locks prevent overlapping local publication runs.
- Safe-path validation prevents manifest paths from escaping their run directory.
- Data-quality reports and issue files do not contain raw cell values, identities, tokens or credentials.
- User, operator, email, phone, free-text note and raw ERP payload fields remain excluded by the Laravel exporter.
- CSV formula prefixes are neutralized during export and protected again during preprocessing.
- Missing required values, invalid types and impossible logical relationships are rejected rather than silently imputed.
- Optional missing values remain blank and are measured in the quality report.
- Exact normalized duplicates are removed with row-number-only trace records.
- Numeric imputation, outlier removal, feature engineering, model training and Ollama are not performed in Step 21D.
- Normal Laravel production, maintenance, quality and ERP workflows do not depend on the preprocessing pipeline.


## Step 21E feature-engineering controls

- Feature engineering reads only a verified Step 21D run from the shared file boundary.
- No direct Laravel, MySQL, simulated ERP, or Ollama connection is introduced.
- Feature outputs inherit the mandatory `simulated_prototype` classification.
- Train, validation, and test partitions are chronological; random shuffling is not used.
- Supervised target windows are purged at split boundaries to prevent future-label leakage.
- Quantity units remain separated in production forecasting.
- Published feature files use exact schemas, deterministic ordering, SHA-256 checksums,
  atomic publication, and a content fingerprint.
- Feature manifests contain operational codes and aggregates only; they do not contain
  users, operator identities, email addresses, phone numbers, tokens, credentials, or
  free-form comments.
- Step 21E does not produce live predictions, anomaly scores, or maintenance decisions.

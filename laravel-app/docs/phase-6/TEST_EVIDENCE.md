# Phase 6 Test and Registry Evidence

## Source

Evidence was collected from:

```text
SMARTFACTORY_PHASE6_STEP21P_SOURCE_BUNDLE.txt
SHA-256: 1B64C24CC41FA6807F4EDA3C40E65AC2278BF304CB53AF49EC3C74067B139522
Generated: 2026-08-03T21:52:49.0153180+01:00
```

## Laravel

```text
Version    : Laravel Framework 12.64.0
Tests      : 490 passed
Assertions : 2483
Duration   : 78.57 seconds
```

The suite covers security, RBAC, ERP integration, dashboards, analytics, automatic feature
preparation, inference clients, AI model metrics, AI reports, and native reporting.

## FastAPI

```text
Python : 3.12.10
Tests  : 179 passed
```

The source-bundle output used pytest quiet progress; the count is derived from the visible
progress dots.

The source-bundle FastAPI route-inventory command had an inline quoting error. It did not
run application code incorrectly and did not cause a test failure. Step 21P includes
`scripts/check_fastapi_acceptance.py`, which checks the route contract without inline
PowerShell/Python quoting.

## Model lineage

```text
Model run        : f0147a01-3d1a-45d9-9cb8-c2686b531be0
Feature run      : 79f65f1f-b672-493f-91f3-60a648ac10a0
Preprocessed run : d3905235-15ff-474c-aece-5b4620a1b599
Manifest SHA-256 : 91E716F89DEE12EEE7161E8BDE81ED0EEC7F54A43CBE4E4560DA89C850879FC4
Fingerprint      : 520524eeb7d7f88dc9e80b2e4e4e6ba62da20714f45b077dc2b58e9f16e8fd50
```

## Environment captured in the model manifest

```text
Python        : 3.12.10
NumPy         : 2.3.5
Pandas        : 2.2.3
Scikit-learn  : 1.8.0
Joblib        : 1.5.3
```

## Metrics-file checksums

```text
Forecast    : 8DD8E6A6FAA77D87C0DF5D4BA3D332A164A35FFD9DA3E384A8582C9604FCDBA5
Anomaly     : 7E97D443136119F8768F636654D72ADF61BF4A570915F3E82EE1F19A17E9CA14
Maintenance : 967F4DB41EBA1AEC84873F8D534D13E3AD492FFF856D0BBBBD784A510D5659C3
```

## Runtime verification

Running the Step 21P verifier writes:

```text
laravel-app/docs/phase-6/evidence/FINAL_ACCEPTANCE_RUNTIME_EVIDENCE.txt
```

That runtime evidence records the verification timestamp, versions, routes, model run,
and successful completion of both full test suites.

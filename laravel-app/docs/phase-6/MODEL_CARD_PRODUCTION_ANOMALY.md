# Model Card — Production Anomaly Detection

## Intended use

Identify production records that are unusual relative to patterns learned from the
simulated-prototype feature set.

## Model

```text
Algorithm           : isolation_forest
Configured rate     : 2.00%
Selection rule      : train_score_quantile_threshold
Model run           : f0147a01-3d1a-45d9-9cb8-c2686b531be0
Training rows       : 2259
Validation rows     : 468
Test rows           : 511
```

## Score meaning

The API reverses the Isolation Forest decision function so that:

```text
higher score = more unusual
score >= threshold = anomaly
score < threshold = not an anomaly
```

The score is not a probability or percentage. It is meaningful only relative to the
threshold and score distribution from the same model run.

## Distribution diagnostics

| Split | Rows | Anomalies | Rate | Median score | P99 score |
|---|---:|---:|---:|---:|---:|
| Train | 2259 | 46 | 2.04% | -0.076412 | 0.009932 |
| Validation | 468 | 9 | 1.92% | -0.077175 | 0.011276 |
| Test | 511 | 20 | 3.91% | -0.071817 | 0.013917 |

The train and validation rates are close to the configured 2%. The test rate is
3.91%, which may indicate a changed test-period
distribution or more unusual simulated records.

## Accuracy limitation

There are no verified ground-truth anomaly labels. Accuracy, precision, recall, and F1
cannot be claimed. The reported values are score-distribution diagnostics only.

## Human workflow

An anomaly should trigger investigation, not an automatic shutdown. A reviewer should
check production context, data quality, equipment events, quality results, and whether
the record represents a legitimate product or shift change.

## Required future validation

Create expert-reviewed labels, measure false-alert and missed-anomaly rates, define
severity categories, calibrate thresholds by line or product family, and monitor drift.

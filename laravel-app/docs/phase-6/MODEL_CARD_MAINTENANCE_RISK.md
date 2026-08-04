# Model Card — Maintenance Risk

## Intended use

Provide advisory prioritization using:

1. probability of a failure in the next seven days;
2. predicted unplanned-downtime minutes in the next seven days;
3. a rule-based priority derived from probability, downtime, and machine criticality.

## Models

```text
Selected classifier : random_forest_classifier
Selected regressor  : gradient_boosting_regressor
Combined model      : random_forest_classifier+gradient_boosting_regressor
Model run           : f0147a01-3d1a-45d9-9cb8-c2686b531be0
Training rows       : 1218
Validation rows     : 147
Test rows           : 315
```

## Failure-classifier test performance

| Metric | Value |
|---|---:|
| Accuracy | 0.4095 |
| Balanced accuracy | 0.4910 |
| Precision | 0.3824 |
| Recall | 0.8525 |
| F1 | 0.5279 |
| ROC-AUC | 0.5284 |
| Average precision | 0.4993 |
| Brier score | 0.3587 |

Confusion matrix:

```text
True negatives  : 25
False positives : 168
False negatives : 18
True positives  : 104
```

The classifier has high recall but produces many false positives. Balanced accuracy and
ROC-AUC are close to 0.5, so discrimination on the test period is weak.

## Downtime-regressor test performance

| Metric | Value |
|---|---:|
| MAE | 75.07 minutes |
| MSE | 7519.93 |
| RMSE | 86.72 minutes |
| R² | -0.3532 |
| MAPE | 50.91% |
| Test rows | 315 |

Negative R² means the regressor performs worse than predicting the test-set mean under
the R² definition. It must not be described as reliable predictive maintenance.

## Priority interpretation

The application maps model outputs into `low`, `medium`, `high`, or `critical`. This
priority is an advisory triage signal. It does not replace inspections, safety procedures,
manufacturer recommendations, or maintenance planning.

## Acceptance decision

The software workflow is accepted as a PFE prototype. The current maintenance models are
not accepted for real maintenance scheduling or automatic work-order generation.

## Required future validation

Use verified failure labels, richer condition-monitoring signals, realistic class balance,
maintenance expert review, cost-sensitive thresholds, calibration, and prospective shadow
testing.

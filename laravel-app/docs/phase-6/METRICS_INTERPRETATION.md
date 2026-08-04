# AI Metrics Interpretation Guide

## Regression metrics

### MAE

Mean Absolute Error is the average absolute difference between actual and predicted values.
It stays in the target unit and is usually the easiest operational metric to explain.

Lower is better.

### MSE

Mean Squared Error averages squared errors. It strongly penalizes large mistakes and uses
squared target units.

Lower is better.

The current accepted registry predates explicit MSE storage. The API transparently derives
MSE as `RMSE²` and labels that derivation in reports. New training runs store MSE directly.

### RMSE

Root Mean Squared Error is the square root of MSE. It returns to the target unit while
remaining more sensitive than MAE to large errors.

Lower is better.

### R²

R² measures explained variation relative to a mean baseline.

```text
1.0  = perfect predictions
0.0  = comparable to predicting the mean
<0.0 = worse than the mean baseline
```

R² alone does not indicate whether errors are operationally acceptable.

### MAPE

Mean Absolute Percentage Error expresses average absolute relative error for non-zero
targets.

Lower is better. MAPE can be unstable when actual values are near zero, which is why the
report also states how many rows were eligible.

## Classification metrics

### Accuracy

Fraction of all predictions that are correct. It can be misleading with imbalanced classes.

### Balanced accuracy

Average recall across the positive and negative classes. It is more informative than raw
accuracy when one class dominates.

### Precision

Among predicted failures, the fraction that are real failures. Low precision means many
false alarms.

### Recall

Among real failures, the fraction detected. Low recall means missed failures.

### F1

Harmonic mean of precision and recall.

### ROC-AUC

Ranking discrimination across thresholds. A value close to 0.5 suggests near-random
ranking.

### Average precision

Summarizes the precision-recall curve and is useful for imbalanced positive events.

### Brier score

Mean squared error of predicted probabilities. Lower is better and reflects both
calibration and discrimination.

### Confusion matrix

```text
True positive  : correctly predicted failure
True negative  : correctly predicted non-failure
False positive : maintenance alert without a failure
False negative : missed failure
```

## Anomaly metrics

Anomaly score is a relative unusualness score, not a probability.

For this model:

```text
higher score = more unusual
score >= threshold = anomaly
```

Anomaly rate shows how many observations cross the threshold. Without verified labels,
it does not prove accuracy.

## Current model summary

```text
Forecast test R²       : 0.5748
Forecast test MAPE     : 26.05%
Anomaly test rate      : 3.91%
Maintenance ROC-AUC    : 0.5284
Maintenance downtime R²: -0.3532
```

These values justify the permanent prototype warnings.

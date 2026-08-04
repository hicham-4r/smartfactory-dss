# Model Card — Production Forecasting

## Intended use

Predict the next day's good production quantity for one production line and quantity unit.
The output is advisory decision support for the simulated prototype.

## Model

```text
Selected model     : random_forest_regressor
Selection rule     : validation_mae
Model run          : f0147a01-3d1a-45d9-9cb8-c2686b531be0
Feature run        : 79f65f1f-b672-493f-91f3-60a648ac10a0
Training rows      : 183
Validation rows    : 36
Test rows          : 42
```

The candidate set contains a mean baseline, linear regression, gradient boosting, and
random forest. Selection uses validation MAE. The selected candidate is then fitted on
train plus validation and evaluated once on the untouched chronological test split.

## Final test performance

| Metric | Value | Interpretation |
|---|---:|---|
| MAE | 13350.27 | Average absolute error in the quantity unit |
| MSE | 1114122320.98 | Squared-error metric; dominated by large errors |
| RMSE | 33378.47 | Large errors receive extra weight |
| R² | 0.5748 | Moderate explained variance |
| MAPE | 26.05% | Average relative error on non-zero targets |
| Test rows | 42 | Small chronological holdout |

## Assessment

Validation performance was much stronger than test performance. The selected random
forest reached validation MAE of approximately
5424.36
and validation MAPE of
3.38%,
but final test MAPE rose to 26.05%.

This gap may indicate temporal distribution change, limited data, or overfitting to the
training and validation periods. The model is suitable for demonstrating the full
forecasting pipeline, but not for an industrial production commitment.

## Prohibited uses

- committing customer orders;
- automatically setting production targets;
- changing line schedules without human review;
- treating the forecast as validated factory capacity;
- comparing scores across unrelated model runs without context.

## Required future validation

Use real historical data across multiple seasons and products, compare against planning
baselines, test by line and quantity unit, establish error tolerances with production
management, and run the model in shadow mode before operational decisions.

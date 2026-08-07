from __future__ import annotations

import math
from typing import Any

import numpy as np
from sklearn.metrics import (
    accuracy_score,
    average_precision_score,
    balanced_accuracy_score,
    brier_score_loss,
    confusion_matrix,
    f1_score,
    mean_absolute_error,
    mean_squared_error,
    precision_score,
    r2_score,
    recall_score,
    roc_auc_score,
)


def finite_float(value: float | int | np.floating[Any]) -> float | None:
    number = float(value)
    return number if math.isfinite(number) else None


def regression_metrics(
    expected: np.ndarray[Any, Any],
    predicted: np.ndarray[Any, Any],
) -> dict[str, float | int | None]:
    y_true = np.asarray(expected, dtype=float)
    y_pred = np.asarray(predicted, dtype=float)
    absolute_percentage_mask = np.abs(y_true) > 1e-12

    mape: float | None = None
    mape_coverage = int(absolute_percentage_mask.sum())
    if mape_coverage > 0:
        mape = float(
            np.mean(
                np.abs(
                    (y_true[absolute_percentage_mask] - y_pred[absolute_percentage_mask])
                    / y_true[absolute_percentage_mask]
                )
            )
            * 100.0
        )

    r2: float | None = None
    if len(y_true) >= 2 and np.unique(y_true).size >= 2:
        r2 = finite_float(r2_score(y_true, y_pred))

    mse = mean_squared_error(y_true, y_pred)
    return {
        "row_count": len(y_true),
        "mae": finite_float(mean_absolute_error(y_true, y_pred)),
        "mse": finite_float(mse),
        "rmse": finite_float(math.sqrt(float(mse))),
        "r2": r2,
        "mape_percentage": finite_float(mape) if mape is not None else None,
        "mape_eligible_row_count": mape_coverage,
    }


def classification_metrics(
    expected: np.ndarray[Any, Any],
    predicted: np.ndarray[Any, Any],
    probability: np.ndarray[Any, Any] | None,
) -> dict[str, Any]:
    y_true = np.asarray(expected, dtype=int)
    y_pred = np.asarray(predicted, dtype=int)
    matrix = confusion_matrix(y_true, y_pred, labels=[0, 1])
    has_two_classes = np.unique(y_true).size == 2

    roc_auc: float | None = None
    average_precision: float | None = None
    brier: float | None = None
    if probability is not None:
        probability = np.asarray(probability, dtype=float)
        brier = finite_float(brier_score_loss(y_true, probability))
        if has_two_classes:
            roc_auc = finite_float(roc_auc_score(y_true, probability))
            average_precision = finite_float(average_precision_score(y_true, probability))

    balanced_accuracy: float | None = None
    if has_two_classes:
        balanced_accuracy = finite_float(balanced_accuracy_score(y_true, y_pred))

    return {
        "row_count": len(y_true),
        "positive_count": int((y_true == 1).sum()),
        "negative_count": int((y_true == 0).sum()),
        "accuracy": finite_float(accuracy_score(y_true, y_pred)),
        "balanced_accuracy": balanced_accuracy,
        "precision": finite_float(precision_score(y_true, y_pred, zero_division=0)),
        "recall": finite_float(recall_score(y_true, y_pred, zero_division=0)),
        "f1": finite_float(f1_score(y_true, y_pred, zero_division=0)),
        "roc_auc": roc_auc,
        "average_precision": average_precision,
        "brier_score": brier,
        "confusion_matrix": {
            "true_negative": int(matrix[0, 0]),
            "false_positive": int(matrix[0, 1]),
            "false_negative": int(matrix[1, 0]),
            "true_positive": int(matrix[1, 1]),
        },
    }


def anomaly_score_metrics(
    scores: np.ndarray[Any, Any],
    threshold: float,
) -> dict[str, float | int | None]:
    values = np.asarray(scores, dtype=float)
    flags = values >= threshold
    return {
        "row_count": int(values.size),
        "anomaly_count": int(flags.sum()),
        "anomaly_rate_percentage": finite_float(flags.mean() * 100.0),
        "score_minimum": finite_float(np.min(values)),
        "score_median": finite_float(np.quantile(values, 0.50)),
        "score_p95": finite_float(np.quantile(values, 0.95)),
        "score_p99": finite_float(np.quantile(values, 0.99)),
        "score_maximum": finite_float(np.max(values)),
        "threshold": finite_float(threshold),
    }

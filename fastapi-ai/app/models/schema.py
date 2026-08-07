from __future__ import annotations

from dataclasses import dataclass
from typing import Literal

MODEL_REGISTRY_CONTRACT = "smartfactory.ml.model.registry"
MODEL_MANIFEST_VERSION = "v1"
MODEL_TRAINING_RULESET_VERSION = "v1"
MODEL_DATA_CLASSIFICATION = "simulated_prototype"

ModelTaskName = Literal[
    "production_forecasting",
    "production_anomaly",
    "maintenance_risk",
]
ModelTaskType = Literal[
    "regression",
    "unsupervised_anomaly_detection",
    "classification_and_regression",
]


@dataclass(frozen=True, slots=True)
class ModelTaskDefinition:
    name: ModelTaskName
    task_type: ModelTaskType
    target_columns: tuple[str, ...]
    excluded_columns: tuple[str, ...]
    categorical_columns: tuple[str, ...]
    selection_metric: str

    @property
    def all_excluded_columns(self) -> tuple[str, ...]:
        return tuple(dict.fromkeys((*self.excluded_columns, *self.target_columns)))


MODEL_TASKS: dict[ModelTaskName, ModelTaskDefinition] = {
    "production_forecasting": ModelTaskDefinition(
        name="production_forecasting",
        task_type="regression",
        target_columns=("target_good_quantity_next_day",),
        excluded_columns=(
            "feature_date",
            "target_date",
            "target_window_end_date",
        ),
        categorical_columns=("production_line_code", "quantity_unit"),
        selection_metric="validation_mae",
    ),
    "production_anomaly": ModelTaskDefinition(
        name="production_anomaly",
        task_type="unsupervised_anomaly_detection",
        target_columns=(),
        excluded_columns=("event_time_utc", "production_date"),
        categorical_columns=(
            "production_line_code",
            "product_family_code",
            "product_code",
            "shift_code",
            "quantity_unit",
        ),
        selection_metric="train_score_quantile_threshold",
    ),
    "maintenance_risk": ModelTaskDefinition(
        name="maintenance_risk",
        task_type="classification_and_regression",
        target_columns=(
            "target_failure_next_7d",
            "target_unplanned_downtime_minutes_next_7d",
        ),
        excluded_columns=("prediction_date", "target_window_end_date"),
        categorical_columns=(
            "production_line_code",
            "machine_code",
            "machine_type",
        ),
        selection_metric="validation_average_precision_and_mae",
    ),
}


def model_task_definition(name: str) -> ModelTaskDefinition:
    try:
        return MODEL_TASKS[name]  # type: ignore[index]
    except KeyError as exception:
        raise KeyError(f"Unknown model task: {name}") from exception

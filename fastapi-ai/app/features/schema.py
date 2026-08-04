from __future__ import annotations

from dataclasses import dataclass
from typing import Literal

FEATURE_CONTRACT = "smartfactory.ml.feature.snapshot"
FEATURE_MANIFEST_VERSION = "v1"
FEATURE_RULESET_VERSION = "v1"
FEATURE_SCHEMA_VERSION = "v1"
FEATURE_DATA_CLASSIFICATION = "simulated_prototype"

FeatureTaskName = Literal[
    "production_forecasting",
    "production_anomaly",
    "maintenance_risk",
]
FeatureSplitName = Literal["train", "validation", "test"]


@dataclass(frozen=True, slots=True)
class FeatureTaskDefinition:
    name: FeatureTaskName
    timestamp_column: str
    target_end_exclusive_column: str | None
    label_horizon_days: int
    source_datasets: tuple[str, ...]
    target_columns: tuple[str, ...]
    columns: tuple[str, ...]


PRODUCTION_FORECAST_COLUMNS = (
    "feature_date",
    "target_date",
    "target_window_end_date",
    "production_line_code",
    "quantity_unit",
    "days_of_history",
    "rolling_observation_count_7d",
    "day_of_week",
    "month",
    "good_quantity_lag_1d",
    "good_quantity_lag_7d",
    "good_quantity_mean_7d",
    "good_quantity_min_7d",
    "good_quantity_max_7d",
    "produced_quantity_lag_1d",
    "target_quantity_lag_1d",
    "runtime_minutes_lag_1d",
    "downtime_minutes_lag_1d",
    "rejection_rate_lag_1d",
    "achievement_rate_lag_1d",
    "target_good_quantity_next_day",
)

PRODUCTION_ANOMALY_COLUMNS = (
    "event_time_utc",
    "production_date",
    "production_line_code",
    "product_family_code",
    "product_code",
    "shift_code",
    "quantity_unit",
    "production_order_priority",
    "target_quantity",
    "produced_quantity",
    "good_quantity",
    "rejected_quantity",
    "runtime_minutes",
    "downtime_minutes",
    "achievement_ratio",
    "rejection_ratio",
    "good_yield_ratio",
    "throughput_per_hour",
    "downtime_ratio",
    "is_validated",
)

MAINTENANCE_RISK_COLUMNS = (
    "prediction_date",
    "target_window_end_date",
    "production_line_code",
    "machine_code",
    "machine_type",
    "is_critical",
    "days_observed",
    "status_event_count_7d",
    "fault_status_event_count_7d",
    "running_minutes_7d",
    "fault_minutes_7d",
    "downtime_event_count_7d",
    "unplanned_downtime_event_count_7d",
    "total_downtime_minutes_7d",
    "unplanned_downtime_minutes_7d",
    "maintenance_event_count_30d",
    "preventive_maintenance_count_30d",
    "corrective_maintenance_count_30d",
    "maintenance_downtime_minutes_30d",
    "days_since_last_failure",
    "days_since_last_maintenance",
    "target_failure_next_7d",
    "target_unplanned_downtime_minutes_next_7d",
)

FEATURE_TASKS: dict[FeatureTaskName, FeatureTaskDefinition] = {
    "production_forecasting": FeatureTaskDefinition(
        name="production_forecasting",
        timestamp_column="feature_date",
        target_end_exclusive_column="target_window_end_date",
        label_horizon_days=1,
        source_datasets=("production_records",),
        target_columns=("target_good_quantity_next_day",),
        columns=PRODUCTION_FORECAST_COLUMNS,
    ),
    "production_anomaly": FeatureTaskDefinition(
        name="production_anomaly",
        timestamp_column="event_time_utc",
        target_end_exclusive_column=None,
        label_horizon_days=0,
        source_datasets=("production_records",),
        target_columns=(),
        columns=PRODUCTION_ANOMALY_COLUMNS,
    ),
    "maintenance_risk": FeatureTaskDefinition(
        name="maintenance_risk",
        timestamp_column="prediction_date",
        target_end_exclusive_column="target_window_end_date",
        label_horizon_days=7,
        source_datasets=(
            "downtime_events",
            "machine_status_events",
            "maintenance_history",
        ),
        target_columns=(
            "target_failure_next_7d",
            "target_unplanned_downtime_minutes_next_7d",
        ),
        columns=MAINTENANCE_RISK_COLUMNS,
    ),
}


def task_definition(name: str) -> FeatureTaskDefinition:
    try:
        return FEATURE_TASKS[name]  # type: ignore[index]
    except KeyError as exception:
        raise KeyError(f"Unknown feature task: {name}") from exception

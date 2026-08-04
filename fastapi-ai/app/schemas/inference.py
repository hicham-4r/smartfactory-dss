from __future__ import annotations

from datetime import date, datetime
from typing import Annotated, Literal
from uuid import UUID

from pydantic import Field, StringConstraints

from app.schemas.common import StrictRequestModel, StrictResponseModel

CodeText = Annotated[str, StringConstraints(strip_whitespace=True, min_length=1, max_length=100)]
NonNegativeFloat = Annotated[float, Field(ge=0)]
Probability = Annotated[float, Field(ge=0, le=1)]


class ProductionForecastFeatures(StrictRequestModel):
    production_line_code: CodeText
    quantity_unit: CodeText
    days_of_history: int = Field(ge=1)
    rolling_observation_count_7d: int = Field(ge=1, le=7)
    day_of_week: int = Field(ge=0, le=6)
    month: int = Field(ge=1, le=12)
    good_quantity_lag_1d: NonNegativeFloat
    good_quantity_lag_7d: NonNegativeFloat | None = None
    good_quantity_mean_7d: NonNegativeFloat
    good_quantity_min_7d: NonNegativeFloat
    good_quantity_max_7d: NonNegativeFloat
    produced_quantity_lag_1d: NonNegativeFloat
    target_quantity_lag_1d: NonNegativeFloat
    runtime_minutes_lag_1d: int = Field(ge=0)
    downtime_minutes_lag_1d: int = Field(ge=0)
    rejection_rate_lag_1d: NonNegativeFloat | None = None
    achievement_rate_lag_1d: NonNegativeFloat | None = None


class ProductionAnomalyFeatures(StrictRequestModel):
    production_line_code: CodeText
    product_family_code: CodeText
    product_code: CodeText
    shift_code: CodeText
    quantity_unit: CodeText
    production_order_priority: int = Field(ge=0)
    target_quantity: NonNegativeFloat
    produced_quantity: NonNegativeFloat
    good_quantity: NonNegativeFloat
    rejected_quantity: NonNegativeFloat
    runtime_minutes: int = Field(ge=0)
    downtime_minutes: int = Field(ge=0)
    achievement_ratio: NonNegativeFloat | None = None
    rejection_ratio: NonNegativeFloat | None = None
    good_yield_ratio: NonNegativeFloat | None = None
    throughput_per_hour: NonNegativeFloat | None = None
    downtime_ratio: NonNegativeFloat | None = None
    is_validated: bool


class MaintenanceRiskFeatures(StrictRequestModel):
    production_line_code: CodeText
    machine_code: CodeText
    machine_type: CodeText
    is_critical: bool
    days_observed: int = Field(ge=0)
    status_event_count_7d: int = Field(ge=0)
    fault_status_event_count_7d: int = Field(ge=0)
    running_minutes_7d: int = Field(ge=0)
    fault_minutes_7d: int = Field(ge=0)
    downtime_event_count_7d: int = Field(ge=0)
    unplanned_downtime_event_count_7d: int = Field(ge=0)
    total_downtime_minutes_7d: int = Field(ge=0)
    unplanned_downtime_minutes_7d: int = Field(ge=0)
    maintenance_event_count_30d: int = Field(ge=0)
    preventive_maintenance_count_30d: int = Field(ge=0)
    corrective_maintenance_count_30d: int = Field(ge=0)
    maintenance_downtime_minutes_30d: int = Field(ge=0)
    days_since_last_failure: int | None = Field(default=None, ge=0)
    days_since_last_maintenance: int | None = Field(default=None, ge=0)


class ProductionForecastRequest(StrictRequestModel):
    prediction_date: date
    model_run_id: UUID | None = None
    features: ProductionForecastFeatures


class ProductionAnomalyRequest(StrictRequestModel):
    event_time_utc: datetime
    model_run_id: UUID | None = None
    features: ProductionAnomalyFeatures


class MaintenanceRiskRequest(StrictRequestModel):
    prediction_date: date
    model_run_id: UUID | None = None
    features: MaintenanceRiskFeatures


class InferenceMetadata(StrictResponseModel):
    model_run_id: UUID
    source_feature_run_id: UUID
    model_name: str
    data_classification: Literal["simulated_prototype"]
    limitations: list[str]


class ProductionForecastResponse(StrictResponseModel):
    status: Literal["ok"]
    predicted_good_quantity_next_day: NonNegativeFloat
    prediction_date: date
    metadata: InferenceMetadata
    request_id: str


class ProductionAnomalyResponse(StrictResponseModel):
    status: Literal["ok"]
    anomaly_score: float
    threshold: float
    is_anomaly: bool
    event_time_utc: datetime
    metadata: InferenceMetadata
    request_id: str


class MaintenanceRiskResponse(StrictResponseModel):
    status: Literal["ok"]
    failure_probability_next_7d: Probability
    predicted_unplanned_downtime_minutes_next_7d: NonNegativeFloat
    priority: Literal["low", "medium", "high", "critical"]
    prediction_date: date
    metadata: InferenceMetadata
    request_id: str


class ModelRegistryResponse(StrictResponseModel):
    status: Literal["ready"]
    model_run_id: UUID
    source_feature_run_id: UUID
    tasks: list[str]
    data_classification: Literal["simulated_prototype"]
    request_id: str

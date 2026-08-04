from __future__ import annotations

from typing import Any, Literal
from uuid import UUID

from app.schemas.common import StrictResponseModel

ModelTask = Literal[
    "production_forecasting",
    "production_anomaly",
    "maintenance_risk",
]


class ModelMetricsResponse(StrictResponseModel):
    status: Literal["ok"]
    model_run_id: UUID
    source_feature_run_id: UUID
    task: ModelTask
    selected_model: str
    data_classification: Literal["simulated_prototype"]
    metrics: dict[str, Any]
    metric_derivations: dict[str, str]
    limitations: list[str]
    request_id: str

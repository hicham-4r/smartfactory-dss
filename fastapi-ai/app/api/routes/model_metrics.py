from __future__ import annotations

from typing import Literal
from uuid import UUID

from fastapi import APIRouter, Depends, Request, status

from app.api.routes.inference import raise_safe_inference_error, service_from_request
from app.core.request_context import current_request_id
from app.core.security import require_internal_token
from app.schemas.model_metrics import ModelMetricsResponse

router = APIRouter(
    prefix="/internal/v1/inference/models",
    tags=["Internal model evaluation metrics"],
    dependencies=[Depends(require_internal_token)],
)

TaskName = Literal[
    "production_forecasting",
    "production_anomaly",
    "maintenance_risk",
]


@router.get(
    "/{model_run_id}/metrics/{task}",
    response_model=ModelMetricsResponse,
    status_code=status.HTTP_200_OK,
    summary="Return checksum-verified evaluation metrics for one model task",
)
async def metrics(
    model_run_id: UUID,
    task: TaskName,
    request: Request,
) -> ModelMetricsResponse:
    try:
        result = service_from_request(request).model_metrics(
            task,
            model_run_id=model_run_id,
        )
    except Exception as exception:
        raise_safe_inference_error(exception)

    return ModelMetricsResponse(
        status="ok",
        model_run_id=result.run_id,
        source_feature_run_id=result.source_feature_run_id,
        task=result.task,
        selected_model=result.selected_model,
        data_classification="simulated_prototype",
        metrics=result.metrics,
        metric_derivations=result.metric_derivations,
        limitations=result.limitations,
        request_id=current_request_id(),
    )

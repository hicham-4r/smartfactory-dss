from __future__ import annotations

from fastapi import APIRouter, Depends, Request, status

from app.core.errors import ApiError
from app.core.request_context import current_request_id
from app.core.security import require_internal_token
from app.inference.registry import InferenceRegistryError
from app.inference.service import InferenceExecutionError, InferenceResult, InferenceService
from app.schemas.inference import (
    InferenceMetadata,
    MaintenanceRiskRequest,
    MaintenanceRiskResponse,
    ModelRegistryResponse,
    ProductionAnomalyRequest,
    ProductionAnomalyResponse,
    ProductionForecastRequest,
    ProductionForecastResponse,
)

router = APIRouter(
    prefix="/internal/v1/inference",
    tags=["Internal model inference"],
    dependencies=[Depends(require_internal_token)],
)


def service_from_request(request: Request) -> InferenceService:
    return request.app.state.inference_service


def metadata(result: InferenceResult) -> InferenceMetadata:
    return InferenceMetadata(
        model_run_id=result.run.run_id,
        source_feature_run_id=result.run.source_feature_run_id,
        model_name=result.selected_model,
        data_classification="simulated_prototype",
        limitations=result.limitations,
    )


def raise_safe_inference_error(exception: Exception) -> None:
    if isinstance(exception, InferenceRegistryError):
        status_code = status.HTTP_503_SERVICE_UNAVAILABLE
        code = exception.code
        message = exception.message
    elif isinstance(exception, InferenceExecutionError):
        status_code = status.HTTP_422_UNPROCESSABLE_ENTITY
        code = exception.code
        message = exception.message
    else:
        status_code = status.HTTP_500_INTERNAL_SERVER_ERROR
        code = "inference_internal_error"
        message = "The inference request could not be completed safely."
    raise ApiError(
        status_code=status_code,
        code=code,
        message=message,
        request_id=current_request_id(),
    ) from exception


@router.get("/models", response_model=ModelRegistryResponse)
async def models(request: Request) -> ModelRegistryResponse:
    try:
        run = service_from_request(request).registry_metadata()
    except Exception as exception:
        raise_safe_inference_error(exception)
    return ModelRegistryResponse(
        status="ready",
        model_run_id=run.run_id,
        source_feature_run_id=run.source_feature_run_id,
        tasks=list(run.artifacts),
        data_classification="simulated_prototype",
        request_id=current_request_id(),
    )


@router.post("/production/forecast", response_model=ProductionForecastResponse)
async def forecast(
    payload: ProductionForecastRequest,
    request: Request,
) -> ProductionForecastResponse:
    try:
        result = service_from_request(request).forecast(
            payload.features.model_dump(),
            model_run_id=payload.model_run_id,
        )
    except Exception as exception:
        raise_safe_inference_error(exception)
    return ProductionForecastResponse(
        status="ok",
        predicted_good_quantity_next_day=result.value["prediction"],
        prediction_date=payload.prediction_date,
        metadata=metadata(result),
        request_id=current_request_id(),
    )


@router.post("/production/anomaly", response_model=ProductionAnomalyResponse)
async def anomaly(
    payload: ProductionAnomalyRequest,
    request: Request,
) -> ProductionAnomalyResponse:
    try:
        result = service_from_request(request).anomaly(
            payload.features.model_dump(),
            model_run_id=payload.model_run_id,
        )
    except Exception as exception:
        raise_safe_inference_error(exception)
    return ProductionAnomalyResponse(
        status="ok",
        anomaly_score=result.value["score"],
        threshold=result.value["threshold"],
        is_anomaly=result.value["is_anomaly"],
        event_time_utc=payload.event_time_utc,
        metadata=metadata(result),
        request_id=current_request_id(),
    )


@router.post("/maintenance/risk", response_model=MaintenanceRiskResponse)
async def maintenance(
    payload: MaintenanceRiskRequest,
    request: Request,
) -> MaintenanceRiskResponse:
    try:
        result = service_from_request(request).maintenance(
            payload.features.model_dump(),
            model_run_id=payload.model_run_id,
        )
    except Exception as exception:
        raise_safe_inference_error(exception)
    return MaintenanceRiskResponse(
        status="ok",
        failure_probability_next_7d=result.value["probability"],
        predicted_unplanned_downtime_minutes_next_7d=result.value["downtime"],
        priority=result.value["priority"],
        prediction_date=payload.prediction_date,
        metadata=metadata(result),
        request_id=current_request_id(),
    )

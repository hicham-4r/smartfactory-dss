from __future__ import annotations

from datetime import UTC, datetime

from fastapi import APIRouter, Depends, Request, status

from app.core.request_context import current_request_id
from app.core.security import require_internal_token, settings_from_request
from app.llm.models import OllamaHealthSnapshot
from app.schemas.health import (
    DependencyHealthResponse,
    LiveHealthResponse,
    ReadyHealthResponse,
)

router = APIRouter(tags=["Health"])


@router.get(
    "/health/live",
    response_model=LiveHealthResponse,
    status_code=status.HTTP_200_OK,
    summary="Minimal liveness probe",
)
async def live(request: Request) -> LiveHealthResponse:
    settings = settings_from_request(request)
    return LiveHealthResponse(
        status="alive",
        service=settings.app_name,
        checked_at=datetime.now(UTC),
        request_id=current_request_id(),
    )


@router.get(
    "/health/ready",
    response_model=ReadyHealthResponse,
    dependencies=[Depends(require_internal_token)],
    status_code=status.HTTP_200_OK,
    summary="Authenticated readiness probe",
)
async def ready(request: Request) -> ReadyHealthResponse:
    settings = settings_from_request(request)

    try:
        ollama = await request.app.state.ollama_client.health()
    except Exception:
        ollama = OllamaHealthSnapshot.unavailable(
            model=settings.ollama_model if settings.ollama_enabled else None,
            message="The local Ollama health check failed safely.",
        )

    return ReadyHealthResponse(
        status="ready",
        service=settings.app_name,
        version=settings.app_version,
        api_version=settings.api_version,
        checked_at=datetime.now(UTC),
        dependencies=[
            DependencyHealthResponse(
                name="ollama",
                status=ollama.status.value,
                required=False,
                model=ollama.model,
                latency_ms=ollama.latency_ms,
                message=ollama.message,
            )
        ],
        request_id=current_request_id(),
    )

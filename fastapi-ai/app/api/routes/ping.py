from __future__ import annotations

from fastapi import APIRouter, Depends, Request, status

from app.core.request_context import current_request_id
from app.core.security import require_internal_token, settings_from_request
from app.schemas.ping import FoundationPingRequest, FoundationPingResponse

router = APIRouter(prefix="/internal/v1", tags=["Internal foundation"])


@router.post(
    "/ping",
    response_model=FoundationPingResponse,
    dependencies=[Depends(require_internal_token)],
    status_code=status.HTTP_200_OK,
    summary="Validate the Laravel-to-FastAPI foundation contract",
)
async def ping(
    payload: FoundationPingRequest,
    request: Request,
) -> FoundationPingResponse:
    settings = settings_from_request(request)
    return FoundationPingResponse(
        status="ok",
        accepted_client=payload.client,
        api_version=settings.api_version,
        request_id=current_request_id(),
    )

from __future__ import annotations

from fastapi import APIRouter, Depends, Request, status

from app.core.request_context import current_request_id
from app.core.security import require_internal_token, settings_from_request
from app.schemas.version import VersionResponse

router = APIRouter(tags=["Metadata"])


@router.get(
    "/version",
    response_model=VersionResponse,
    dependencies=[Depends(require_internal_token)],
    status_code=status.HTTP_200_OK,
    summary="Authenticated service version",
)
async def version(request: Request) -> VersionResponse:
    settings = settings_from_request(request)
    return VersionResponse(
        service=settings.app_name,
        version=settings.app_version,
        api_version=settings.api_version,
        environment=settings.app_env,
        request_id=current_request_id(),
    )

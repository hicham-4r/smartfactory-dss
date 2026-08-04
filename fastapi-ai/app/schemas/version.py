from __future__ import annotations

from app.schemas.common import StrictResponseModel


class VersionResponse(StrictResponseModel):
    service: str
    version: str
    api_version: str
    environment: str
    request_id: str

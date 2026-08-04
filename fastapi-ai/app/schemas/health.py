from __future__ import annotations

from datetime import datetime
from typing import Literal

from pydantic import Field

from app.schemas.common import StrictResponseModel


class LiveHealthResponse(StrictResponseModel):
    status: Literal["alive"]
    service: str
    checked_at: datetime
    request_id: str


class DependencyHealthResponse(StrictResponseModel):
    name: Literal["ollama"]
    status: Literal["available", "degraded", "disabled"]
    required: bool
    model: str | None = None
    latency_ms: int | None = Field(default=None, ge=0)
    message: str


class ReadyHealthResponse(StrictResponseModel):
    status: Literal["ready"]
    service: str
    version: str
    api_version: str
    checked_at: datetime
    dependencies: list[DependencyHealthResponse]
    request_id: str

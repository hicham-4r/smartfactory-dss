from __future__ import annotations

from typing import Literal

from pydantic import Field

from app.schemas.common import StrictRequestModel, StrictResponseModel


class FoundationPingRequest(StrictRequestModel):
    client: Literal["laravel"]
    nonce: str = Field(min_length=8, max_length=64, pattern=r"^[A-Za-z0-9._:-]+$")


class FoundationPingResponse(StrictResponseModel):
    status: Literal["ok"]
    accepted_client: Literal["laravel"]
    api_version: str
    request_id: str

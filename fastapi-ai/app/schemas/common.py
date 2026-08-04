from __future__ import annotations

from pydantic import BaseModel, ConfigDict


class StrictResponseModel(BaseModel):
    model_config = ConfigDict(extra="forbid")


class StrictRequestModel(BaseModel):
    model_config = ConfigDict(extra="forbid", str_strip_whitespace=True)

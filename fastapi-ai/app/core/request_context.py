from __future__ import annotations

import re
from contextvars import ContextVar
from uuid import uuid4

_REQUEST_ID_PATTERN = re.compile(r"^[A-Za-z0-9._:-]{1,100}$")
_request_id_context: ContextVar[str | None] = ContextVar("request_id", default=None)


def normalize_request_id(value: str | None) -> str:
    if value is not None:
        candidate = value.strip()
        if _REQUEST_ID_PATTERN.fullmatch(candidate):
            return candidate
    return str(uuid4())


def set_request_id(value: str):
    return _request_id_context.set(value)


def reset_request_id(token) -> None:
    _request_id_context.reset(token)


def current_request_id() -> str:
    return _request_id_context.get() or str(uuid4())

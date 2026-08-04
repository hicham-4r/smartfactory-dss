from __future__ import annotations

import hashlib
import hmac

from fastapi import Request, Security
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.core.config import Settings
from app.core.errors import ApiError
from app.core.request_context import current_request_id

_bearer = HTTPBearer(auto_error=False)


def tokens_match(expected: str, provided: str) -> bool:
    expected_digest = hashlib.sha256(expected.encode("utf-8")).digest()
    provided_digest = hashlib.sha256(provided.encode("utf-8")).digest()
    return hmac.compare_digest(expected_digest, provided_digest)


def settings_from_request(request: Request) -> Settings:
    return request.app.state.settings


async def require_internal_token(
    request: Request,
    credentials: HTTPAuthorizationCredentials | None = Security(_bearer),
) -> None:
    settings = settings_from_request(request)
    provided = credentials.credentials if credentials is not None else ""

    if (
        credentials is None
        or credentials.scheme.lower() != "bearer"
        or not tokens_match(settings.internal_token.get_secret_value(), provided)
    ):
        raise ApiError(
            status_code=401,
            code="unauthenticated",
            message="A valid internal bearer token is required.",
            request_id=current_request_id(),
            headers={"WWW-Authenticate": 'Bearer realm="SmartFactory AI Service"'},
        )

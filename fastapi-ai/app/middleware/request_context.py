from __future__ import annotations

import ipaddress
import logging
import time
from collections.abc import Awaitable, Callable

from fastapi import Request
from fastapi.responses import JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.responses import Response

from app.core.config import Settings
from app.core.errors import error_payload
from app.core.request_context import (
    normalize_request_id,
    reset_request_id,
    set_request_id,
)

logger = logging.getLogger("smartfactory.requests")

_METRICS_PATH = "/metrics"


class RequestContextMiddleware(BaseHTTPMiddleware):
    def __init__(self, app, *, settings: Settings) -> None:
        super().__init__(app)
        self.settings = settings

    async def dispatch(
        self,
        request: Request,
        call_next: Callable[[Request], Awaitable[Response]],
    ) -> Response:
        request_id = normalize_request_id(
            request.headers.get("X-Request-ID")
        )
        context_token = set_request_id(request_id)
        started_at = time.perf_counter()
        status_code = 500

        try:
            host_error = self._host_error(
                request,
                request_id,
            )
            if host_error is not None:
                status_code = host_error.status_code
                return self._secure(
                    host_error,
                    request_id,
                )

            size_error = self._size_error(
                request,
                request_id,
            )
            if size_error is not None:
                status_code = size_error.status_code
                return self._secure(
                    size_error,
                    request_id,
                )

            response = await call_next(request)
            status_code = response.status_code
            return self._secure(
                response,
                request_id,
            )
        finally:
            duration_ms = max(
                0,
                round(
                    (
                        time.perf_counter()
                        - started_at
                    )
                    * 1_000
                ),
            )
            logger.info(
                "request_completed",
                extra={
                    "request_id": request_id,
                    "method": request.method,
                    "path": request.url.path[:500],
                    "duration_ms": duration_ms,
                    "status_code": status_code,
                },
            )
            reset_request_id(context_token)

    def _host_error(
        self,
        request: Request,
        request_id: str,
    ) -> JSONResponse | None:
        host = request.url.hostname
        if host is not None:
            normalized = host.lower()
            if (
                self._host_allowed(normalized)
                or self._private_metrics_host_allowed(
                    request,
                    normalized,
                )
            ):
                return None

        return JSONResponse(
            status_code=400,
            content=error_payload(
                code="invalid_host",
                message="The request host is not allowed.",
                request_id=request_id,
            ),
        )

    def _host_allowed(self, host: str) -> bool:
        for allowed in self.settings.allowed_host_list:
            if allowed.startswith("*."):
                suffix = allowed[1:]
                if (
                    host.endswith(suffix)
                    and host != suffix.lstrip(".")
                ):
                    return True
            elif host == allowed:
                return True
        return False

    @staticmethod
    def _private_metrics_host_allowed(
        request: Request,
        host: str,
    ) -> bool:
        if request.url.path != _METRICS_PATH:
            return False

        try:
            address = ipaddress.ip_address(host)
        except ValueError:
            return False

        return (
            address.is_private
            and not address.is_unspecified
            and not address.is_multicast
            and not address.is_link_local
        )

    def _size_error(
        self,
        request: Request,
        request_id: str,
    ) -> JSONResponse | None:
        content_length = request.headers.get(
            "Content-Length"
        )
        if content_length is None:
            return None

        try:
            size = int(content_length)
        except ValueError:
            return JSONResponse(
                status_code=400,
                content=error_payload(
                    code="invalid_content_length",
                    message=(
                        "The Content-Length header "
                        "is invalid."
                    ),
                    request_id=request_id,
                ),
            )

        if size < 0:
            return JSONResponse(
                status_code=400,
                content=error_payload(
                    code="invalid_content_length",
                    message=(
                        "The Content-Length header "
                        "is invalid."
                    ),
                    request_id=request_id,
                ),
            )

        if size > self.settings.max_request_bytes:
            return JSONResponse(
                status_code=413,
                content=error_payload(
                    code="request_too_large",
                    message=(
                        "The request exceeds the "
                        "configured size limit."
                    ),
                    request_id=request_id,
                ),
            )

        return None

    @staticmethod
    def _secure(
        response: Response,
        request_id: str,
    ) -> Response:
        response.headers["X-Request-ID"] = request_id
        response.headers[
            "Cache-Control"
        ] = "no-store, private, max-age=0"
        response.headers["Pragma"] = "no-cache"
        response.headers[
            "X-Content-Type-Options"
        ] = "nosniff"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers[
            "Referrer-Policy"
        ] = "no-referrer"
        return response

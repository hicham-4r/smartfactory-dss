from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from starlette.exceptions import HTTPException as StarletteHTTPException

from app.core.request_context import current_request_id


@dataclass(slots=True)
class ApiError(Exception):
    status_code: int
    code: str
    message: str
    request_id: str
    details: list[dict[str, Any]] | None = None
    headers: dict[str, str] = field(default_factory=dict)


def error_payload(
    *,
    code: str,
    message: str,
    request_id: str,
    details: list[dict[str, Any]] | None = None,
) -> dict[str, Any]:
    error: dict[str, Any] = {
        "code": code,
        "message": message,
        "request_id": request_id,
    }
    if details:
        error["details"] = details
    return {"error": error}


def install_exception_handlers(app: FastAPI) -> None:
    @app.exception_handler(ApiError)
    async def api_error_handler(_: Request, exception: ApiError) -> JSONResponse:
        return JSONResponse(
            status_code=exception.status_code,
            content=error_payload(
                code=exception.code,
                message=exception.message,
                request_id=exception.request_id,
                details=exception.details,
            ),
            headers=exception.headers,
        )

    @app.exception_handler(RequestValidationError)
    async def validation_error_handler(
        _: Request,
        exception: RequestValidationError,
    ) -> JSONResponse:
        details: list[dict[str, Any]] = []
        for error in exception.errors()[:20]:
            location = [
                str(item)
                for item in error.get("loc", ())
                if str(item) not in {"body", "query", "path", "header"}
            ]
            details.append(
                {
                    "field": ".".join(location) or "request",
                    "type": str(error.get("type", "validation_error")),
                    "message": str(error.get("msg", "Invalid value"))[:200],
                }
            )

        return JSONResponse(
            status_code=422,
            content=error_payload(
                code="validation_error",
                message="The request did not satisfy the API contract.",
                request_id=current_request_id(),
                details=details,
            ),
        )

    @app.exception_handler(StarletteHTTPException)
    async def http_error_handler(_: Request, exception: StarletteHTTPException) -> JSONResponse:
        message_by_status = {
            404: "The requested resource was not found.",
            405: "The HTTP method is not allowed for this resource.",
        }
        return JSONResponse(
            status_code=exception.status_code,
            content=error_payload(
                code="http_error",
                message=message_by_status.get(
                    exception.status_code,
                    "The request could not be completed.",
                ),
                request_id=current_request_id(),
            ),
            headers=exception.headers,
        )

    @app.exception_handler(Exception)
    async def unhandled_error_handler(_: Request, __: Exception) -> JSONResponse:
        return JSONResponse(
            status_code=500,
            content=error_payload(
                code="internal_error",
                message="The service could not complete the request safely.",
                request_id=current_request_id(),
            ),
        )

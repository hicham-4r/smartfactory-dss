from fastapi import APIRouter, Request
from fastapi.responses import Response

from app.observability.metrics import NativeMetricsRegistry

router = APIRouter(tags=["observability"])


@router.get("/metrics", include_in_schema=False)
async def native_metrics(request: Request) -> Response:
    registry: NativeMetricsRegistry = request.app.state.native_metrics
    return Response(
        content=registry.render(),
        status_code=200,
        headers={
            "Content-Type": "text/plain; version=0.0.4; charset=utf-8",
            "Cache-Control": "no-store, private, max-age=0",
            "X-Content-Type-Options": "nosniff",
        },
    )

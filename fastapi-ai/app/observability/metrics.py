from __future__ import annotations

import math
import re
import threading
import time
from collections import defaultdict
from collections.abc import Awaitable, Callable
from dataclasses import dataclass

from fastapi import Request
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.responses import Response

_BUCKETS = (0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0)
_ROUTE_SANITIZER = re.compile(r"[^A-Za-z0-9_.:()/{\}-]+")


@dataclass(frozen=True, slots=True)
class SeriesKey:
    method: str
    route: str
    status_class: str


class NativeMetricsRegistry:
    def __init__(
        self,
        *,
        service: str,
        environment: str,
        version: str,
        ollama_enabled: bool,
        max_series: int = 256,
    ) -> None:
        self.service = service
        self.environment = environment
        self.version = version
        self.ollama_enabled = ollama_enabled
        self.max_series = max(16, min(max_series, 1024))
        self.started_at = time.time()
        self._lock = threading.Lock()
        self._in_flight = 0
        self._count: dict[SeriesKey, int] = defaultdict(int)
        self._duration_sum: dict[SeriesKey, float] = defaultdict(float)
        self._buckets: dict[tuple[SeriesKey, float], int] = defaultdict(int)
        self._infinite_bucket: dict[SeriesKey, int] = defaultdict(int)

    def begin_request(self) -> None:
        with self._lock:
            self._in_flight += 1

    def end_request(
        self,
        *,
        method: str,
        route: str,
        status_code: int,
        duration_seconds: float,
    ) -> None:
        key = self._series_key(method, route, status_code)
        duration = duration_seconds if math.isfinite(duration_seconds) else 0.0
        duration = max(0.0, duration)

        with self._lock:
            self._in_flight = max(0, self._in_flight - 1)
            if key not in self._count and len(self._count) >= self.max_series:
                key = SeriesKey(key.method, "__overflow__", key.status_class)
            self._count[key] += 1
            self._duration_sum[key] += duration
            for bucket in _BUCKETS:
                if duration <= bucket:
                    self._buckets[(key, bucket)] += 1
            self._infinite_bucket[key] += 1

    def render(self) -> str:
        with self._lock:
            count = dict(self._count)
            duration_sum = dict(self._duration_sum)
            buckets = dict(self._buckets)
            infinite_bucket = dict(self._infinite_bucket)
            in_flight = self._in_flight

        service = self._escape(self.service)
        lines = [
            "# HELP smartfactory_application_info Static application identity.",
            "# TYPE smartfactory_application_info gauge",
            (
                'smartfactory_application_info{service="%s",environment="%s",'
                'runtime="python",version="%s"} 1'
            )
            % (
                service,
                self._escape(self.environment),
                self._escape(self.version),
            ),
            "# HELP smartfactory_ollama_enabled Whether guarded local Ollama generation is enabled.",
            "# TYPE smartfactory_ollama_enabled gauge",
            'smartfactory_ollama_enabled{service="%s"} %d'
            % (service, 1 if self.ollama_enabled else 0),
            "# HELP smartfactory_http_requests_in_flight Current in-flight HTTP requests.",
            "# TYPE smartfactory_http_requests_in_flight gauge",
            'smartfactory_http_requests_in_flight{service="%s"} %d'
            % (service, in_flight),
            "# HELP smartfactory_http_requests_total Total HTTP requests by bounded route and status class.",
            "# TYPE smartfactory_http_requests_total counter",
            "# HELP smartfactory_http_request_duration_seconds HTTP request duration histogram.",
            "# TYPE smartfactory_http_request_duration_seconds histogram",
            "# HELP smartfactory_metrics_state_started_timestamp_seconds Metrics-state start timestamp.",
            "# TYPE smartfactory_metrics_state_started_timestamp_seconds gauge",
            'smartfactory_metrics_state_started_timestamp_seconds{service="%s"} %.3f'
            % (service, self.started_at),
        ]

        for key in sorted(count, key=lambda item: (item.method, item.route, item.status_class)):
            labels = self._labels(key)
            lines.append(f"smartfactory_http_requests_total{{{labels}}} {count[key]}")
            for bucket in _BUCKETS:
                lines.append(
                    "smartfactory_http_request_duration_seconds_bucket"
                    f'{{{labels},le="{self._bucket_label(bucket)}"}} '
                    f"{buckets.get((key, bucket), 0)}"
                )
            lines.append(
                "smartfactory_http_request_duration_seconds_bucket"
                f'{{{labels},le="+Inf"}} {infinite_bucket.get(key, count[key])}'
            )
            lines.append(
                f"smartfactory_http_request_duration_seconds_sum{{{labels}}} "
                f"{duration_sum.get(key, 0.0):.9f}"
            )
            lines.append(
                f"smartfactory_http_request_duration_seconds_count{{{labels}}} {count[key]}"
            )

        return "\n".join(lines) + "\n"

    def _series_key(self, method: str, route: str, status_code: int) -> SeriesKey:
        normalized_method = method.strip().upper()
        if not re.fullmatch(r"[A-Z]{1,12}", normalized_method):
            normalized_method = "OTHER"
        normalized_route = _ROUTE_SANITIZER.sub("_", route.strip()).strip("_")
        normalized_route = normalized_route[:160] or "unknown"
        status_class = f"{status_code // 100}xx" if 100 <= status_code <= 599 else "5xx"
        return SeriesKey(normalized_method, normalized_route, status_class)

    def _labels(self, key: SeriesKey) -> str:
        return (
            f'service="{self._escape(self.service)}",'
            f'method="{self._escape(key.method)}",'
            f'route="{self._escape(key.route)}",'
            f'status_class="{self._escape(key.status_class)}"'
        )

    @staticmethod
    def _bucket_label(bucket: float) -> str:
        return f"{bucket:.3f}".rstrip("0").rstrip(".")

    @staticmethod
    def _escape(value: str) -> str:
        return value.replace("\\", "\\\\").replace("\n", "\\n").replace('"', '\\"')


class NativeMetricsMiddleware(BaseHTTPMiddleware):
    def __init__(self, app, *, registry: NativeMetricsRegistry) -> None:
        super().__init__(app)
        self.registry = registry

    async def dispatch(
        self,
        request: Request,
        call_next: Callable[[Request], Awaitable[Response]],
    ) -> Response:
        if request.url.path == "/metrics":
            return await call_next(request)

        self.registry.begin_request()
        started_at = time.perf_counter()
        status_code = 500

        try:
            response = await call_next(request)
            status_code = response.status_code
            return response
        finally:
            route = request.scope.get("route")
            route_path = getattr(route, "path", None) or "unmatched"
            self.registry.end_request(
                method=request.method,
                route=str(route_path),
                status_code=status_code,
                duration_seconds=max(0.0, time.perf_counter() - started_at),
            )

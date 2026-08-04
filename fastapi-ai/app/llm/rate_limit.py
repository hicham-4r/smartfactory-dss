from __future__ import annotations

import asyncio
import math
from collections import deque
from collections.abc import AsyncIterator, Callable
from contextlib import asynccontextmanager
from dataclasses import dataclass
from time import monotonic


@dataclass(slots=True)
class ExplanationRateLimitError(Exception):
    retry_after_seconds: int
    reason: str


class ExplanationRateLimiter:
    """Small process-local admission control for the private local model."""

    def __init__(
        self,
        requests_per_minute: int,
        max_concurrent_requests: int,
        *,
        clock: Callable[[], float] = monotonic,
    ) -> None:
        if not 1 <= requests_per_minute <= 120:
            raise ValueError("requests_per_minute is outside the safe range")
        if not 1 <= max_concurrent_requests <= 4:
            raise ValueError("max_concurrent_requests is outside the safe range")

        self._requests_per_minute = requests_per_minute
        self._max_concurrent_requests = max_concurrent_requests
        self._clock = clock
        self._events: deque[float] = deque()
        self._active = 0
        self._lock = asyncio.Lock()

    @asynccontextmanager
    async def admit(self) -> AsyncIterator[None]:
        await self._enter()
        try:
            yield
        finally:
            await self._leave()

    async def _enter(self) -> None:
        async with self._lock:
            now = self._clock()
            while self._events and now - self._events[0] >= 60.0:
                self._events.popleft()

            if len(self._events) >= self._requests_per_minute:
                retry_after = max(1, math.ceil(60.0 - (now - self._events[0])))
                raise ExplanationRateLimitError(
                    retry_after_seconds=retry_after,
                    reason="minute_limit",
                )

            if self._active >= self._max_concurrent_requests:
                raise ExplanationRateLimitError(
                    retry_after_seconds=1,
                    reason="concurrency_limit",
                )

            self._events.append(now)
            self._active += 1

    async def _leave(self) -> None:
        async with self._lock:
            self._active = max(0, self._active - 1)

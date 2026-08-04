from __future__ import annotations

from dataclasses import dataclass
from enum import StrEnum


class OllamaHealthStatus(StrEnum):
    AVAILABLE = "available"
    DEGRADED = "degraded"
    DISABLED = "disabled"


@dataclass(frozen=True, slots=True)
class OllamaHealthSnapshot:
    status: OllamaHealthStatus
    model: str | None
    latency_ms: int | None
    message: str

    @classmethod
    def available(cls, model: str, latency_ms: int) -> OllamaHealthSnapshot:
        return cls(
            status=OllamaHealthStatus.AVAILABLE,
            model=model,
            latency_ms=max(0, latency_ms),
            message="The configured local Ollama model is available.",
        )

    @classmethod
    def model_missing(cls, model: str, latency_ms: int) -> OllamaHealthSnapshot:
        return cls(
            status=OllamaHealthStatus.DEGRADED,
            model=model,
            latency_ms=max(0, latency_ms),
            message="The configured local Ollama model is not installed.",
        )

    @classmethod
    def unavailable(cls, model: str | None, message: str) -> OllamaHealthSnapshot:
        return cls(
            status=OllamaHealthStatus.DEGRADED,
            model=model,
            latency_ms=None,
            message=message,
        )

    @classmethod
    def disabled(cls) -> OllamaHealthSnapshot:
        return cls(
            status=OllamaHealthStatus.DISABLED,
            model=None,
            latency_ms=None,
            message="The Ollama integration is disabled by configuration.",
        )

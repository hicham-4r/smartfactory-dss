from __future__ import annotations

import json
import logging
from datetime import UTC, datetime
from typing import Any

_SENSITIVE_KEYS = {
    "authorization",
    "cookie",
    "password",
    "secret",
    "token",
    "api_key",
    "internal_token",
}
_STANDARD_LOG_RECORD_KEYS = set(logging.makeLogRecord({}).__dict__)


def _sanitize(value: Any, *, key: str | None = None) -> Any:
    if key is not None and any(part in key.lower() for part in _SENSITIVE_KEYS):
        return "[REDACTED]"
    if isinstance(value, dict):
        return {
            str(item_key): _sanitize(item, key=str(item_key)) for item_key, item in value.items()
        }
    if isinstance(value, (list, tuple)):
        return [_sanitize(item) for item in value]
    if isinstance(value, str):
        return value[:1_000]
    if isinstance(value, (int, float, bool)) or value is None:
        return value
    return str(value)[:1_000]


class JsonFormatter(logging.Formatter):
    def format(self, record: logging.LogRecord) -> str:
        payload: dict[str, Any] = {
            "timestamp": datetime.now(UTC).isoformat(),
            "level": record.levelname,
            "logger": record.name,
            "message": record.getMessage()[:1_000],
        }
        for key, value in record.__dict__.items():
            if key in _STANDARD_LOG_RECORD_KEYS or key.startswith("_"):
                continue
            payload[key] = _sanitize(value, key=key)
        if record.exc_info:
            payload["exception_type"] = record.exc_info[0].__name__
        return json.dumps(payload, ensure_ascii=False, separators=(",", ":"))


def configure_logging(level: str) -> None:
    root = logging.getLogger()
    root.setLevel(level)

    for handler in root.handlers:
        if getattr(handler, "_smartfactory_json_handler", False):
            handler.setLevel(level)
            return

    handler = logging.StreamHandler()
    handler.setLevel(level)
    handler.setFormatter(JsonFormatter())
    handler._smartfactory_json_handler = True  # type: ignore[attr-defined]
    root.handlers.clear()
    root.addHandler(handler)

from __future__ import annotations

import asyncio
import json

import httpx
import pytest
from pydantic import SecretStr

from app.core.config import Settings
from app.llm.clients.ollama import OllamaHttpClient
from app.llm.errors import (
    OllamaProtocolError,
    OllamaRequestTooLargeError,
    OllamaResponseTooLargeError,
    OllamaTimeoutError,
)
from app.llm.models import OllamaHealthStatus


def settings(**overrides: object) -> Settings:
    return Settings(
        _env_file=None,
        internal_token=SecretStr("x" * 40),
        ollama_enabled=True,
        ollama_model="llama3:8b",
        **overrides,
    )


def run(coroutine):
    return asyncio.run(coroutine)


def test_exact_installed_model_is_discovered() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        assert request.url.path == "/api/tags"
        assert request.headers["User-Agent"] == "SmartFactory-DSS-AI/0.1"
        assert "authorization" not in request.headers
        return httpx.Response(
            200,
            json={
                "models": [
                    {"name": "llama3:8b"},
                    {"name": "another-model:latest"},
                ]
            },
        )

    client = OllamaHttpClient(
        settings(),
        transport=httpx.MockTransport(handler),
    )

    snapshot = run(client.health())

    assert snapshot.status is OllamaHealthStatus.AVAILABLE
    assert snapshot.model == "llama3:8b"
    assert snapshot.latency_ms is not None


def test_missing_exact_model_is_reported_without_downloading() -> None:
    transport = httpx.MockTransport(
        lambda _: httpx.Response(
            200,
            json={"models": [{"name": "llama3.1:8b"}]},
        )
    )
    client = OllamaHttpClient(settings(), transport=transport)

    snapshot = run(client.health())

    assert snapshot.status is OllamaHealthStatus.DEGRADED
    assert snapshot.model == "llama3:8b"
    assert "not installed" in snapshot.message.lower()


def test_timeout_is_translated_to_typed_safe_error() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        raise httpx.ReadTimeout("internal detail", request=request)

    client = OllamaHttpClient(
        settings(),
        transport=httpx.MockTransport(handler),
    )

    with pytest.raises(OllamaTimeoutError) as captured:
        run(client.list_models())

    assert "internal detail" not in captured.value.message


def test_oversized_response_is_stopped_before_json_parsing() -> None:
    body = b"x" * 2_000
    client = OllamaHttpClient(
        settings(ollama_max_response_bytes=1_024),
        transport=httpx.MockTransport(
            lambda _: httpx.Response(
                200,
                content=body,
                headers={"Content-Length": str(len(body))},
            )
        ),
    )

    with pytest.raises(OllamaResponseTooLargeError):
        run(client.list_models())


def test_malformed_model_inventory_is_rejected() -> None:
    client = OllamaHttpClient(
        settings(),
        transport=httpx.MockTransport(
            lambda _: httpx.Response(200, content=b'{"models":"invalid"}')
        ),
    )

    with pytest.raises(OllamaProtocolError):
        run(client.list_models())


def test_request_payload_is_json_encoded_and_bounded() -> None:
    client = OllamaHttpClient(settings(ollama_max_request_bytes=1_024))

    encoded = client.encode_json_payload({"model": "llama3:8b", "stream": False})

    assert json.loads(encoded) == {"model": "llama3:8b", "stream": False}

    with pytest.raises(OllamaRequestTooLargeError):
        client.encode_json_payload({"facts": "x" * 2_000})

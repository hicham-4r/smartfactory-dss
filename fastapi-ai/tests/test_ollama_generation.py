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
    OllamaResponseTooLargeError,
    OllamaTimeoutError,
)


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


def schema() -> dict:
    return {
        "type": "object",
        "properties": {"status": {"type": "string"}},
        "required": ["status"],
        "additionalProperties": False,
    }


def test_chat_generation_uses_exact_model_schema_and_private_safe_headers() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        assert request.method == "POST"
        assert request.url.path == "/api/chat"
        assert request.headers["Content-Type"] == "application/json"
        assert request.headers["User-Agent"] == "SmartFactory-DSS-AI/0.1"
        assert "authorization" not in request.headers

        payload = json.loads(request.content)
        assert payload["model"] == "llama3:8b"
        assert payload["stream"] is False
        assert payload["format"] == schema()
        assert payload["options"]["temperature"] == 0
        assert payload["options"]["seed"] == 42
        assert payload["options"]["num_predict"] == 768

        return httpx.Response(
            200,
            json={
                "model": "llama3:8b",
                "message": {
                    "role": "assistant",
                    "content": '{"status":"ok"}',
                },
                "done": True,
            },
        )

    client = OllamaHttpClient(
        settings(),
        transport=httpx.MockTransport(handler),
    )

    result = run(
        client.generate(
            [
                {"role": "system", "content": "Return strict JSON."},
                {"role": "user", "content": "Return the allowed status."},
            ],
            schema(),
        )
    )

    assert result == '{"status":"ok"}'


def test_generation_timeout_is_translated_without_internal_details() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        raise httpx.ReadTimeout("SECRET TIMEOUT DETAIL", request=request)

    client = OllamaHttpClient(
        settings(),
        transport=httpx.MockTransport(handler),
    )

    with pytest.raises(OllamaTimeoutError) as captured:
        run(
            client.generate(
                [{"role": "user", "content": "Return JSON."}],
                schema(),
            )
        )

    assert "SECRET TIMEOUT DETAIL" not in captured.value.message


def test_generation_rejects_malformed_or_incomplete_protocol_response() -> None:
    client = OllamaHttpClient(
        settings(),
        transport=httpx.MockTransport(
            lambda _: httpx.Response(
                200,
                json={
                    "message": {"role": "assistant", "content": "{}"},
                    "done": False,
                },
            )
        ),
    )

    with pytest.raises(OllamaProtocolError):
        run(
            client.generate(
                [{"role": "user", "content": "Return JSON."}],
                schema(),
            )
        )


def test_generated_content_and_full_response_are_bounded() -> None:
    content = "x" * 5_000
    client = OllamaHttpClient(
        settings(
            explanation_max_output_bytes=4_096,
            ollama_max_response_bytes=10_000,
        ),
        transport=httpx.MockTransport(
            lambda _: httpx.Response(
                200,
                json={
                    "message": {"role": "assistant", "content": content},
                    "done": True,
                },
            )
        ),
    )

    with pytest.raises(OllamaResponseTooLargeError):
        run(
            client.generate(
                [{"role": "user", "content": "Return JSON."}],
                schema(),
            )
        )

    oversized_body = b"x" * 2_000
    bounded_client = OllamaHttpClient(
        settings(ollama_max_response_bytes=1_024),
        transport=httpx.MockTransport(
            lambda _: httpx.Response(
                200,
                content=oversized_body,
                headers={"Content-Length": str(len(oversized_body))},
            )
        ),
    )

    with pytest.raises(OllamaResponseTooLargeError):
        run(
            bounded_client.generate(
                [{"role": "user", "content": "Return JSON."}],
                schema(),
            )
        )

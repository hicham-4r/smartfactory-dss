from __future__ import annotations

import json
from collections.abc import Mapping, Sequence
from time import perf_counter
from typing import Any, Protocol

import httpx

from app.core.config import Settings
from app.llm.errors import (
    OllamaClientError,
    OllamaModelMissingError,
    OllamaProtocolError,
    OllamaRequestTooLargeError,
    OllamaResponseTooLargeError,
    OllamaTimeoutError,
    OllamaUnavailableError,
)
from app.llm.models import OllamaHealthSnapshot


class OllamaClient(Protocol):
    async def health(self) -> OllamaHealthSnapshot: ...

    async def list_models(self) -> tuple[str, ...]: ...

    async def generate(
        self,
        messages: Sequence[Mapping[str, str]],
        response_schema: Mapping[str, Any],
    ) -> str: ...

    def encode_json_payload(self, payload: Mapping[str, Any]) -> bytes: ...


class DisabledOllamaClient:
    async def health(self) -> OllamaHealthSnapshot:
        return OllamaHealthSnapshot.disabled()

    async def list_models(self) -> tuple[str, ...]:
        return ()

    async def generate(
        self,
        messages: Sequence[Mapping[str, str]],
        response_schema: Mapping[str, Any],
    ) -> str:
        del messages, response_schema
        raise OllamaUnavailableError()

    def encode_json_payload(self, payload: Mapping[str, Any]) -> bytes:
        del payload
        raise OllamaUnavailableError()


class OllamaHttpClient:
    """Bounded, private-network client for the local Ollama API."""

    def __init__(
        self,
        settings: Settings,
        *,
        transport: httpx.AsyncBaseTransport | None = None,
    ) -> None:
        self._base_url = settings.ollama_base_url
        self._model = settings.ollama_model
        self._tags_endpoint = settings.ollama_tags_endpoint
        self._generate_endpoint = settings.ollama_generate_endpoint
        self._health_timeout = httpx.Timeout(
            timeout=settings.ollama_timeout_seconds,
            connect=settings.ollama_connect_timeout_seconds,
        )
        self._generation_timeout = httpx.Timeout(
            timeout=settings.ollama_generation_timeout_seconds,
            connect=settings.ollama_connect_timeout_seconds,
        )
        self._max_request_bytes = settings.ollama_max_request_bytes
        self._max_response_bytes = settings.ollama_max_response_bytes
        self._max_generated_text_bytes = settings.explanation_max_output_bytes
        self._num_predict = settings.ollama_num_predict
        self._user_agent = settings.ollama_user_agent
        self._transport = transport

    async def health(self) -> OllamaHealthSnapshot:
        started_at = perf_counter()

        try:
            models = await self.list_models()
        except OllamaClientError as exception:
            return OllamaHealthSnapshot.unavailable(
                model=self._model,
                message=exception.message,
            )
        except Exception:
            return OllamaHealthSnapshot.unavailable(
                model=self._model,
                message="The local Ollama health check failed safely.",
            )

        latency_ms = max(0, round((perf_counter() - started_at) * 1000))

        if self._model not in models:
            return OllamaHealthSnapshot.model_missing(
                model=self._model,
                latency_ms=latency_ms,
            )

        return OllamaHealthSnapshot.available(
            model=self._model,
            latency_ms=latency_ms,
        )

    async def list_models(self) -> tuple[str, ...]:
        body = await self._request(
            method="GET",
            endpoint=self._tags_endpoint,
            timeout=self._health_timeout,
        )
        return self._parse_models(body)

    async def generate(
        self,
        messages: Sequence[Mapping[str, str]],
        response_schema: Mapping[str, Any],
    ) -> str:
        normalized_messages = self._validated_messages(messages)
        if not isinstance(response_schema, Mapping) or not response_schema:
            raise OllamaProtocolError()

        payload = {
            "model": self._model,
            "messages": normalized_messages,
            "stream": False,
            "format": dict(response_schema),
            "options": {
                "temperature": 0,
                "seed": 42,
                "num_predict": self._num_predict,
            },
            "keep_alive": "5m",
        }
        encoded = self.encode_json_payload(payload)
        body = await self._request(
            method="POST",
            endpoint=self._generate_endpoint,
            timeout=self._generation_timeout,
            content=encoded,
        )
        return self._parse_generation(body)

    def encode_json_payload(self, payload: Mapping[str, Any]) -> bytes:
        try:
            encoded = json.dumps(
                payload,
                ensure_ascii=False,
                separators=(",", ":"),
                allow_nan=False,
            ).encode("utf-8")
        except (TypeError, ValueError) as exception:
            raise OllamaProtocolError() from exception

        if len(encoded) > self._max_request_bytes:
            raise OllamaRequestTooLargeError()

        return encoded

    async def _request(
        self,
        *,
        method: str,
        endpoint: str,
        timeout: httpx.Timeout,
        content: bytes | None = None,
    ) -> bytes:
        headers = {
            "Accept": "application/json",
            "User-Agent": self._user_agent,
        }
        if content is not None:
            headers["Content-Type"] = "application/json"

        try:
            async with httpx.AsyncClient(
                base_url=self._base_url,
                timeout=timeout,
                follow_redirects=False,
                headers=headers,
                limits=httpx.Limits(
                    max_connections=2,
                    max_keepalive_connections=1,
                ),
                transport=self._transport,
            ) as client:
                async with client.stream(
                    method,
                    endpoint,
                    content=content,
                ) as response:
                    if response.status_code == 404:
                        raise OllamaModelMissingError()
                    if response.status_code != 200:
                        raise OllamaUnavailableError()

                    declared_length = response.headers.get("Content-Length")
                    if declared_length is not None:
                        try:
                            if int(declared_length) > self._max_response_bytes:
                                raise OllamaResponseTooLargeError()
                        except ValueError as exception:
                            raise OllamaProtocolError() from exception

                    body = bytearray()
                    async for chunk in response.aiter_bytes():
                        body.extend(chunk)
                        if len(body) > self._max_response_bytes:
                            raise OllamaResponseTooLargeError()
        except httpx.TimeoutException as exception:
            raise OllamaTimeoutError() from exception
        except httpx.RequestError as exception:
            raise OllamaUnavailableError() from exception

        return bytes(body)

    @staticmethod
    def _validated_messages(
        messages: Sequence[Mapping[str, str]],
    ) -> list[dict[str, str]]:
        if not 1 <= len(messages) <= 6:
            raise OllamaProtocolError()

        normalized: list[dict[str, str]] = []
        for message in messages:
            role = message.get("role")
            content = message.get("content")
            if role not in {"system", "user", "assistant"}:
                raise OllamaProtocolError()
            if not isinstance(content, str):
                raise OllamaProtocolError()
            clean_content = content.strip()
            if not clean_content or len(clean_content) > 65_536:
                raise OllamaProtocolError()
            normalized.append({"role": role, "content": clean_content})

        return normalized

    @staticmethod
    def _parse_models(body: bytes) -> tuple[str, ...]:
        payload = OllamaHttpClient._decode_object(body)

        raw_models = payload.get("models")
        if not isinstance(raw_models, list) or len(raw_models) > 10_000:
            raise OllamaProtocolError()

        models: set[str] = set()

        for item in raw_models:
            if not isinstance(item, dict):
                raise OllamaProtocolError()

            name = item.get("name", item.get("model"))
            if not isinstance(name, str):
                raise OllamaProtocolError()

            normalized = name.strip()
            if not normalized or len(normalized) > 164:
                raise OllamaProtocolError()

            models.add(normalized)

        return tuple(sorted(models))

    def _parse_generation(self, body: bytes) -> str:
        payload = self._decode_object(body)

        message = payload.get("message")
        if not isinstance(message, dict) or message.get("role") != "assistant":
            raise OllamaProtocolError()

        content = message.get("content")
        if not isinstance(content, str):
            raise OllamaProtocolError()

        normalized = content.strip()
        if not normalized:
            raise OllamaProtocolError()
        if len(normalized.encode("utf-8")) > self._max_generated_text_bytes:
            raise OllamaResponseTooLargeError()
        if payload.get("done") is not True:
            raise OllamaProtocolError()

        return normalized

    @staticmethod
    def _decode_object(body: bytes) -> dict[str, Any]:
        try:
            payload = json.loads(body.decode("utf-8"))
        except (UnicodeDecodeError, json.JSONDecodeError) as exception:
            raise OllamaProtocolError() from exception

        if not isinstance(payload, dict):
            raise OllamaProtocolError()
        return payload

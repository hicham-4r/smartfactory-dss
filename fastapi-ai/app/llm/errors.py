from __future__ import annotations

from dataclasses import dataclass


@dataclass(slots=True)
class OllamaClientError(Exception):
    code: str
    message: str


class OllamaTimeoutError(OllamaClientError):
    def __init__(self) -> None:
        super().__init__(
            code="ollama_timeout",
            message="The local Ollama service did not respond within the configured timeout.",
        )


class OllamaUnavailableError(OllamaClientError):
    def __init__(self) -> None:
        super().__init__(
            code="ollama_unavailable",
            message="The local Ollama service is unavailable.",
        )


class OllamaModelMissingError(OllamaClientError):
    def __init__(self) -> None:
        super().__init__(
            code="ollama_model_missing",
            message="The configured local Ollama model is not installed.",
        )


class OllamaProtocolError(OllamaClientError):
    def __init__(self) -> None:
        super().__init__(
            code="ollama_protocol_error",
            message="The local Ollama service returned an invalid response.",
        )


class OllamaResponseTooLargeError(OllamaClientError):
    def __init__(self) -> None:
        super().__init__(
            code="ollama_response_too_large",
            message="The local Ollama response exceeded the configured size limit.",
        )


class OllamaRequestTooLargeError(OllamaClientError):
    def __init__(self) -> None:
        super().__init__(
            code="ollama_request_too_large",
            message="The Ollama request exceeded the configured size limit.",
        )

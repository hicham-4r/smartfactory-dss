from __future__ import annotations

import ipaddress
import re
from functools import lru_cache
from typing import Literal
from urllib.parse import urlsplit

from pydantic import Field, SecretStr, field_validator, model_validator
from pydantic_settings import BaseSettings, SettingsConfigDict

_VERSION_PATTERN = re.compile(r"^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$")
_API_VERSION_PATTERN = re.compile(r"^v[1-9][0-9]*$")
_HOST_PATTERN = re.compile(
    r"^(?:\*\.)?(?:[A-Za-z0-9](?:[A-Za-z0-9.-]{0,251}[A-Za-z0-9])?|localhost|"
    r"(?:[0-9]{1,3}\.){3}[0-9]{1,3})$"
)
_OLLAMA_MODEL_PATTERN = re.compile(
    r"^[A-Za-z0-9][A-Za-z0-9._/-]{0,99}(?::[A-Za-z0-9][A-Za-z0-9._-]{0,63})?$"
)
_CONTROL_CHARACTERS = re.compile(r"[\x00-\x1f\x7f]")
_PLACEHOLDER_TOKENS = {
    "change-me",
    "replace-me",
    "your-token-here",
    "secret",
    "token",
}
_ALLOWED_PRIVATE_OLLAMA_HOSTS = {
    "localhost",
    "host.docker.internal",
}


def _is_private_ollama_host(host: str) -> bool:
    normalized = host.strip().lower().rstrip(".")

    if normalized in _ALLOWED_PRIVATE_OLLAMA_HOSTS:
        return True

    try:
        address = ipaddress.ip_address(normalized)
    except ValueError:
        return False

    return address.is_loopback or address.is_private


class Settings(BaseSettings):
    """Validated, environment-backed service configuration."""

    model_config = SettingsConfigDict(
        env_file=".env",
        env_prefix="AI_",
        case_sensitive=False,
        extra="ignore",
        validate_default=True,
    )

    app_name: str = Field(default="SmartFactory DSS AI Service", min_length=3, max_length=100)
    app_env: Literal["local", "testing", "container", "production"] = "local"
    app_version: str = "0.1.0"
    api_version: str = "v1"
    internal_token: SecretStr
    log_level: Literal["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"] = "INFO"
    docs_enabled: bool = True
    allowed_hosts: str = "127.0.0.1,localhost"
    max_request_bytes: int = Field(default=1_048_576, ge=1_024, le=10_485_760)
    host: str = "127.0.0.1"
    port: int = Field(default=8001, ge=1, le=65_535)
    model_root: str = ""

    # Private local Ollama boundary.
    ollama_enabled: bool = True
    ollama_base_url: str = "http://127.0.0.1:11434"
    ollama_model: str = "llama3:8b"
    ollama_tags_endpoint: str = "/api/tags"
    ollama_generate_endpoint: str = "/api/chat"
    ollama_connect_timeout_seconds: float = Field(default=2.0, ge=0.1, le=30.0)
    ollama_timeout_seconds: float = Field(default=5.0, ge=0.1, le=120.0)
    ollama_generation_timeout_seconds: float = Field(default=90.0, ge=1.0, le=300.0)
    ollama_max_request_bytes: int = Field(default=262_144, ge=1_024, le=10_485_760)
    ollama_max_response_bytes: int = Field(default=1_048_576, ge=1_024, le=10_485_760)
    ollama_num_predict: int = Field(default=768, ge=64, le=2_048)
    ollama_user_agent: str = Field(
        default="SmartFactory-DSS-AI/0.1",
        min_length=3,
        max_length=200,
    )

    # Guarded explanation endpoint limits. Rate limiting is process-local in native
    # development; a distributed limiter can replace it in the later container phase.
    explanation_rate_limit_per_minute: int = Field(default=6, ge=1, le=120)
    explanation_max_concurrent_requests: int = Field(default=1, ge=1, le=4)
    explanation_max_payload_bytes: int = Field(default=131_072, ge=4_096, le=1_048_576)
    explanation_max_output_bytes: int = Field(default=32_768, ge=4_096, le=262_144)
    explanation_generation_attempts: int = Field(default=2, ge=1, le=2)

    @field_validator("app_name", "host", "ollama_user_agent")
    @classmethod
    def strip_non_empty_strings(cls, value: str) -> str:
        normalized = value.strip()
        if not normalized:
            raise ValueError("must not be empty")
        if _CONTROL_CHARACTERS.search(normalized):
            raise ValueError("must not contain control characters")
        return normalized

    @field_validator("model_root")
    @classmethod
    def validate_model_root(cls, value: str) -> str:
        normalized = value.strip()
        if _CONTROL_CHARACTERS.search(normalized):
            raise ValueError("must not contain control characters")
        return normalized

    @field_validator("app_version")
    @classmethod
    def validate_app_version(cls, value: str) -> str:
        normalized = value.strip()
        if not _VERSION_PATTERN.fullmatch(normalized):
            raise ValueError("must be a semantic version such as 0.1.0")
        return normalized

    @field_validator("api_version")
    @classmethod
    def validate_api_version(cls, value: str) -> str:
        normalized = value.strip().lower()
        if not _API_VERSION_PATTERN.fullmatch(normalized):
            raise ValueError("must use a version identifier such as v1")
        return normalized

    @field_validator("internal_token")
    @classmethod
    def validate_internal_token(cls, value: SecretStr) -> SecretStr:
        raw_token = value.get_secret_value()
        if _CONTROL_CHARACTERS.search(raw_token):
            raise ValueError("must not contain control characters")
        token = raw_token.strip()
        if len(token) < 32:
            raise ValueError("must contain at least 32 characters")
        if len(token) > 4_096:
            raise ValueError("must not exceed 4096 characters")
        if token.lower() in _PLACEHOLDER_TOKENS:
            raise ValueError("must not use a placeholder value")
        return SecretStr(token)

    @field_validator("allowed_hosts")
    @classmethod
    def validate_allowed_hosts(cls, value: str) -> str:
        hosts = [item.strip().lower() for item in value.split(",") if item.strip()]
        if not hosts:
            raise ValueError("must contain at least one host")
        if len(hosts) > 50:
            raise ValueError("must contain at most 50 hosts")
        for host in hosts:
            if host == "*":
                raise ValueError("wildcard host access is not allowed")
            if not _HOST_PATTERN.fullmatch(host):
                raise ValueError(f"contains an invalid host entry: {host}")
        return ",".join(dict.fromkeys(hosts))

    @field_validator("ollama_base_url")
    @classmethod
    def validate_ollama_base_url(cls, value: str) -> str:
        normalized = value.strip().rstrip("/")
        if _CONTROL_CHARACTERS.search(normalized):
            raise ValueError("must not contain control characters")

        parsed = urlsplit(normalized)

        if parsed.scheme not in {"http", "https"}:
            raise ValueError("must use HTTP or HTTPS")
        if parsed.username is not None or parsed.password is not None:
            raise ValueError("must not contain embedded credentials")
        if parsed.query or parsed.fragment:
            raise ValueError("must not contain a query string or fragment")
        if parsed.path not in {"", "/"}:
            raise ValueError("must not contain an application path")
        if parsed.hostname is None or not _is_private_ollama_host(parsed.hostname):
            raise ValueError("must target localhost, host.docker.internal, or a private IP")
        if parsed.port is not None and not 1 <= parsed.port <= 65_535:
            raise ValueError("contains an invalid port")

        return normalized

    @field_validator("ollama_model")
    @classmethod
    def validate_ollama_model(cls, value: str) -> str:
        normalized = value.strip()
        if _CONTROL_CHARACTERS.search(normalized):
            raise ValueError("must not contain control characters")
        if not _OLLAMA_MODEL_PATTERN.fullmatch(normalized):
            raise ValueError("must be an exact, safe Ollama model tag")
        return normalized

    @field_validator("ollama_tags_endpoint", "ollama_generate_endpoint")
    @classmethod
    def validate_ollama_endpoint(cls, value: str) -> str:
        normalized = value.strip()
        if (
            not normalized.startswith("/")
            or normalized.startswith("//")
            or "?" in normalized
            or "#" in normalized
            or _CONTROL_CHARACTERS.search(normalized)
        ):
            raise ValueError("must be a safe relative endpoint beginning with one slash")
        return normalized

    @model_validator(mode="after")
    def validate_ollama_and_explanation_limits(self) -> Settings:
        if self.ollama_connect_timeout_seconds > self.ollama_timeout_seconds:
            raise ValueError("Ollama connection timeout cannot exceed health timeout")
        if self.ollama_connect_timeout_seconds > self.ollama_generation_timeout_seconds:
            raise ValueError("Ollama connection timeout cannot exceed generation timeout")
        return self

    @property
    def allowed_host_list(self) -> list[str]:
        return [item for item in self.allowed_hosts.split(",") if item]

    @property
    def docs_url(self) -> str | None:
        return "/docs" if self.docs_enabled else None

    @property
    def redoc_url(self) -> str | None:
        return "/redoc" if self.docs_enabled else None

    @property
    def openapi_url(self) -> str | None:
        return "/openapi.json" if self.docs_enabled else None


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    return Settings()

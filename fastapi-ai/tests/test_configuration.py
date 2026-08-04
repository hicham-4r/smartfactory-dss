from __future__ import annotations

import pytest
from pydantic import SecretStr, ValidationError

from app.core.config import Settings


def test_token_is_redacted_in_representation() -> None:
    token = "a" * 40
    settings = Settings(_env_file=None, internal_token=SecretStr(token))

    assert token not in repr(settings)
    assert "**********" in repr(settings)


@pytest.mark.parametrize(
    "token",
    ["short", "change-me", "x" * 31, "x" * 33 + "\n"],
)
def test_invalid_tokens_are_rejected(token: str) -> None:
    with pytest.raises(ValidationError):
        Settings(_env_file=None, internal_token=SecretStr(token))


@pytest.mark.parametrize("hosts", ["", "*", "bad host", "http://localhost"])
def test_invalid_allowed_hosts_are_rejected(hosts: str) -> None:
    with pytest.raises(ValidationError):
        Settings(
            _env_file=None,
            internal_token=SecretStr("x" * 40),
            allowed_hosts=hosts,
        )


@pytest.mark.parametrize(
    "url",
    [
        "http://example.com:11434",
        "http://user:password@127.0.0.1:11434",
        "ftp://127.0.0.1:11434",
        "http://127.0.0.1:11434/api",
    ],
)
def test_public_or_unsafe_ollama_urls_are_rejected(url: str) -> None:
    with pytest.raises(ValidationError):
        Settings(
            _env_file=None,
            internal_token=SecretStr("x" * 40),
            ollama_base_url=url,
        )


@pytest.mark.parametrize(
    "url",
    [
        "http://127.0.0.1:11434",
        "http://localhost:11434",
        "http://host.docker.internal:11434",
        "http://192.168.1.10:11434",
    ],
)
def test_private_ollama_urls_are_accepted(url: str) -> None:
    settings = Settings(
        _env_file=None,
        internal_token=SecretStr("x" * 40),
        ollama_base_url=url,
    )

    assert settings.ollama_base_url == url


def test_exact_installed_ollama_model_is_preserved() -> None:
    settings = Settings(
        _env_file=None,
        internal_token=SecretStr("x" * 40),
        ollama_model="llama3:8b",
    )

    assert settings.ollama_model == "llama3:8b"


def test_ollama_connection_timeout_cannot_exceed_total_timeout() -> None:
    with pytest.raises(ValidationError):
        Settings(
            _env_file=None,
            internal_token=SecretStr("x" * 40),
            ollama_connect_timeout_seconds=6,
            ollama_timeout_seconds=5,
        )

from __future__ import annotations

import pytest
from fastapi.testclient import TestClient
from pydantic import SecretStr

from app.core.config import Settings
from app.factory import create_app

TEST_TOKEN = "test-token-" + ("x" * 48)


@pytest.fixture
def settings() -> Settings:
    return Settings(
        _env_file=None,
        app_env="testing",
        internal_token=SecretStr(TEST_TOKEN),
        allowed_hosts="testserver,127.0.0.1,localhost",
        docs_enabled=True,
        max_request_bytes=65_536,
        ollama_enabled=False,
    )


@pytest.fixture
def client(settings: Settings) -> TestClient:
    return TestClient(create_app(settings), raise_server_exceptions=False)


@pytest.fixture
def auth_headers() -> dict[str, str]:
    return {"Authorization": f"Bearer {TEST_TOKEN}"}

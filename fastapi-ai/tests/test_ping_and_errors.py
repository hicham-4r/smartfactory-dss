from __future__ import annotations

from fastapi import FastAPI
from fastapi.testclient import TestClient

from app.core.config import Settings
from app.factory import create_app


def test_ping_rejects_unknown_fields(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    response = client.post(
        "/internal/v1/ping",
        headers=auth_headers,
        json={"client": "laravel", "nonce": "nonce-1234", "extra": "forbidden"},
    )

    assert response.status_code == 422
    payload = response.json()
    assert payload["error"]["code"] == "validation_error"
    assert "extra" in str(payload["error"]["details"])


def test_malformed_json_uses_standard_error(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    response = client.post(
        "/internal/v1/ping",
        headers={**auth_headers, "Content-Type": "application/json"},
        content='{"client":"laravel","nonce":',
    )

    assert response.status_code == 422
    assert response.json()["error"]["code"] == "validation_error"
    assert response.json()["error"]["request_id"] == response.headers["X-Request-ID"]


def test_valid_ping_returns_only_foundation_metadata(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    response = client.post(
        "/internal/v1/ping",
        headers=auth_headers,
        json={"client": "laravel", "nonce": "nonce-1234"},
    )

    assert response.status_code == 200
    assert response.json() == {
        "status": "ok",
        "accepted_client": "laravel",
        "api_version": "v1",
        "request_id": response.headers["X-Request-ID"],
    }


def test_unknown_route_uses_standard_error(client: TestClient) -> None:
    response = client.get("/does-not-exist")

    assert response.status_code == 404
    assert response.json()["error"]["code"] == "http_error"
    assert response.json()["error"]["request_id"] == response.headers["X-Request-ID"]


def test_unhandled_exception_is_sanitized(settings: Settings) -> None:
    app: FastAPI = create_app(settings)

    @app.get("/testing/failure")
    async def failure() -> None:
        raise RuntimeError("SECRET INTERNAL DETAIL")

    client = TestClient(app, raise_server_exceptions=False)
    response = client.get("/testing/failure")

    assert response.status_code == 500
    assert response.json()["error"]["code"] == "internal_error"
    assert "SECRET INTERNAL DETAIL" not in response.text

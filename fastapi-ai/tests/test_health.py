from __future__ import annotations

from fastapi.testclient import TestClient

from app.llm.models import OllamaHealthSnapshot


class FakeOllamaClient:
    def __init__(self, snapshot: OllamaHealthSnapshot) -> None:
        self._snapshot = snapshot

    async def health(self) -> OllamaHealthSnapshot:
        return self._snapshot


def test_live_health_is_minimal_and_public(client: TestClient) -> None:
    response = client.get("/health/live")

    assert response.status_code == 200
    assert response.json()["status"] == "alive"
    assert response.json()["service"] == "SmartFactory DSS AI Service"
    assert response.json()["request_id"] == response.headers["X-Request-ID"]
    assert response.headers["Cache-Control"].startswith("no-store")


def test_ready_health_reports_available_ollama_without_exposing_configuration(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    client.app.state.ollama_client = FakeOllamaClient(
        OllamaHealthSnapshot.available("llama3:8b", 12)
    )

    response = client.get("/health/ready", headers=auth_headers)

    assert response.status_code == 200
    payload = response.json()
    assert payload["status"] == "ready"
    assert payload["version"] == "0.1.0"
    assert payload["api_version"] == "v1"
    assert payload["dependencies"] == [
        {
            "name": "ollama",
            "status": "available",
            "required": False,
            "model": "llama3:8b",
            "latency_ms": 12,
            "message": "The configured local Ollama model is available.",
        }
    ]
    assert "127.0.0.1:11434" not in response.text
    assert payload["request_id"] == response.headers["X-Request-ID"]


def test_ollama_failure_does_not_make_verified_ml_inference_unready(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    client.app.state.ollama_client = FakeOllamaClient(
        OllamaHealthSnapshot.unavailable(
            model="llama3:8b",
            message="The local Ollama service is unavailable.",
        )
    )

    response = client.get("/health/ready", headers=auth_headers)

    assert response.status_code == 200
    payload = response.json()
    assert payload["status"] == "ready"
    assert payload["dependencies"][0]["status"] == "degraded"
    assert payload["dependencies"][0]["model"] == "llama3:8b"

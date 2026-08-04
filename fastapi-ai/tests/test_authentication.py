from fastapi.testclient import TestClient


def test_missing_token_returns_safe_error(client: TestClient) -> None:
    response = client.get("/health/ready")

    assert response.status_code == 401
    assert response.headers["WWW-Authenticate"] == 'Bearer realm="SmartFactory AI Service"'
    payload = response.json()
    assert payload["error"]["code"] == "unauthenticated"
    message = payload["error"]["message"].lower()
    assert "token" not in message or "valid" in message
    assert payload["error"]["request_id"] == response.headers["X-Request-ID"]


def test_invalid_token_does_not_reveal_configuration(client: TestClient) -> None:
    response = client.get(
        "/version",
        headers={"Authorization": "Bearer definitely-wrong-token"},
    )

    assert response.status_code == 401
    body = response.text
    assert "test-token" not in body
    assert "definitely-wrong-token" not in body

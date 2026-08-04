from fastapi.testclient import TestClient


def test_disallowed_host_returns_standard_error(client: TestClient) -> None:
    response = client.get(
        "/health/live",
        headers={"Host": "attacker.example"},
    )

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "invalid_host"
    assert response.json()["error"]["request_id"] == response.headers["X-Request-ID"]


def test_oversized_request_is_rejected_before_parsing(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    response = client.post(
        "/internal/v1/ping",
        headers={**auth_headers, "Content-Length": "70000"},
        content="{}",
    )

    assert response.status_code == 413
    assert response.json()["error"]["code"] == "request_too_large"

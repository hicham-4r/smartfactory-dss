from fastapi.testclient import TestClient


def test_valid_laravel_request_id_is_preserved(client: TestClient) -> None:
    request_id = "laravel-request-123"
    response = client.get("/health/live", headers={"X-Request-ID": request_id})

    assert response.headers["X-Request-ID"] == request_id
    assert response.json()["request_id"] == request_id


def test_invalid_request_id_is_replaced(client: TestClient) -> None:
    response = client.get(
        "/health/live",
        headers={"X-Request-ID": "invalid request id with spaces"},
    )

    generated = response.headers["X-Request-ID"]
    assert generated != "invalid request id with spaces"
    assert response.json()["request_id"] == generated

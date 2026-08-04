from fastapi.testclient import TestClient


def test_version_requires_authentication(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    response = client.get("/version", headers=auth_headers)

    assert response.status_code == 200
    assert response.json()["version"] == "0.1.0"
    assert response.json()["api_version"] == "v1"
    assert response.json()["environment"] == "testing"

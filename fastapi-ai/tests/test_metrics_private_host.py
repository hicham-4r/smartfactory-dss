from __future__ import annotations

from fastapi.testclient import TestClient

from app.factory import create_app


def test_metrics_accepts_private_kubernetes_pod_ip_host(
    settings,
) -> None:
    with TestClient(create_app(settings)) as client:
        response = client.get(
            "/metrics",
            headers={
                "Host": "10.244.0.53:8001",
            },
        )

    assert response.status_code == 200
    assert "smartfactory_application_info" in response.text


def test_private_ip_host_remains_denied_outside_metrics(
    settings,
) -> None:
    with TestClient(create_app(settings)) as client:
        response = client.get(
            "/health/live",
            headers={
                "Host": "10.244.0.53:8001",
            },
        )

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "invalid_host"


def test_metrics_rejects_public_ip_host(
    settings,
) -> None:
    with TestClient(create_app(settings)) as client:
        response = client.get(
            "/metrics",
            headers={
                "Host": "8.8.8.8:8001",
            },
        )

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "invalid_host"


def test_metrics_rejects_unconfigured_hostname(
    settings,
) -> None:
    with TestClient(create_app(settings)) as client:
        response = client.get(
            "/metrics",
            headers={
                "Host": "attacker.example",
            },
        )

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "invalid_host"

from fastapi.testclient import TestClient

from app.core.config import Settings
from app.factory import create_app


def test_native_metrics_exposes_bounded_prometheus_contract(tmp_path) -> None:
    settings = Settings(
        internal_token="x" * 64,
        app_env="testing",
        allowed_hosts="testserver,localhost",
        model_root=str(tmp_path),
        ollama_enabled=False,
    )
    app = create_app(settings)

    with TestClient(app) as client:
        assert client.get("/health/live").status_code == 200
        response = client.get("/metrics")

    assert response.status_code == 200
    assert response.headers["content-type"].startswith("text/plain")
    assert "smartfactory_application_info" in response.text
    assert "smartfactory_http_requests_total" in response.text
    assert 'route="/health/live"' in response.text
    assert "password" not in response.text.lower()
    assert "token" not in response.text.lower()

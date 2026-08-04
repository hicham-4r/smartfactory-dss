from __future__ import annotations

from dataclasses import dataclass

from fastapi.testclient import TestClient


@dataclass
class FakeMetrics:
    run_id: str = "11111111-1111-4111-8111-111111111111"
    source_feature_run_id: str = "22222222-2222-4222-8222-222222222222"
    task: str = "production_forecasting"
    selected_model: str = "random_forest_regressor"
    metrics: dict = None
    metric_derivations: dict = None
    limitations: list[str] = None

    def __post_init__(self) -> None:
        self.metrics = self.metrics or {
            "test_metrics": {
                "mae": 10.0,
                "mse": 400.0,
                "rmse": 20.0,
                "r2": 0.8,
            }
        }
        self.metric_derivations = self.metric_derivations or {}
        self.limitations = self.limitations or ["Simulated-prototype data only."]


class FakeService:
    def model_metrics(self, task, *, model_run_id):
        result = FakeMetrics()
        result.task = task
        result.run_id = str(model_run_id)
        return result


def test_metrics_requires_authentication(client: TestClient) -> None:
    response = client.get(
        "/internal/v1/inference/models/"
        "11111111-1111-4111-8111-111111111111/metrics/production_forecasting"
    )
    assert response.status_code == 401


def test_verified_metrics_contract(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    client.app.state.inference_service = FakeService()
    response = client.get(
        "/internal/v1/inference/models/"
        "11111111-1111-4111-8111-111111111111/metrics/production_forecasting",
        headers=auth_headers,
    )
    assert response.status_code == 200
    body = response.json()
    assert body["metrics"]["test_metrics"]["mse"] == 400.0
    assert body["data_classification"] == "simulated_prototype"
    assert body["request_id"] == response.headers["X-Request-ID"]


def test_unknown_metrics_task_is_rejected(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    client.app.state.inference_service = FakeService()
    response = client.get(
        "/internal/v1/inference/models/11111111-1111-4111-8111-111111111111/metrics/unknown",
        headers=auth_headers,
    )
    assert response.status_code == 422


def test_registry_v1_rmse_is_transparently_extended_with_mse() -> None:
    from app.inference.registry import ModelRegistryLoader

    payload = {
        "test_metrics": {"rmse": 20.0},
        "candidate_validation_metrics": {
            "baseline": {"rmse": 5.0},
        },
    }
    derivations: dict[str, str] = {}

    ModelRegistryLoader._add_derived_mse(payload, derivations)

    assert payload["test_metrics"]["mse"] == 400.0
    assert payload["candidate_validation_metrics"]["baseline"]["mse"] == 25.0
    assert "metrics.test_metrics.mse" in derivations

from __future__ import annotations

from dataclasses import dataclass
from types import SimpleNamespace

from fastapi.testclient import TestClient


@dataclass
class FakeResult:
    value: dict
    run: object
    selected_model: str
    limitations: list[str]


class FakeInferenceService:
    def __init__(self) -> None:
        self.run = SimpleNamespace(
            run_id="11111111-1111-4111-8111-111111111111",
            source_feature_run_id="22222222-2222-4222-8222-222222222222",
            artifacts={
                "production_forecasting": {},
                "production_anomaly": {},
                "maintenance_risk": {},
            },
        )

    def registry_metadata(self):
        return self.run

    def forecast(self, features, *, model_run_id=None):
        assert "production_line_code" in features
        return FakeResult(
            {"prediction": 123.5},
            self.run,
            "gradient_boosting_regressor",
            ["Simulated-prototype data only."],
        )

    def anomaly(self, features, *, model_run_id=None):
        return FakeResult(
            {"score": 0.8, "threshold": 0.5, "is_anomaly": True},
            self.run,
            "isolation_forest",
            ["No ground-truth anomaly labels are available."],
        )

    def maintenance(self, features, *, model_run_id=None):
        return FakeResult(
            {"probability": 0.75, "downtime": 130.0, "priority": "critical"},
            self.run,
            "classifier+regressor",
            ["AI-assisted prioritization prototype only."],
        )


def _install_fake(client: TestClient) -> None:
    client.app.state.inference_service = FakeInferenceService()


def test_inference_requires_authentication(client: TestClient) -> None:
    response = client.get("/internal/v1/inference/models")
    assert response.status_code == 401


def test_model_registry_metadata_is_compact(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    _install_fake(client)
    response = client.get("/internal/v1/inference/models", headers=auth_headers)
    assert response.status_code == 200
    assert response.json()["status"] == "ready"
    assert response.json()["data_classification"] == "simulated_prototype"


def test_production_forecast_contract(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    _install_fake(client)
    response = client.post(
        "/internal/v1/inference/production/forecast",
        headers=auth_headers,
        json={
            "prediction_date": "2026-08-04",
            "features": {
                "production_line_code": "LINE-01",
                "quantity_unit": "L",
                "days_of_history": 30,
                "rolling_observation_count_7d": 7,
                "day_of_week": 1,
                "month": 8,
                "good_quantity_lag_1d": 100,
                "good_quantity_lag_7d": 90,
                "good_quantity_mean_7d": 95,
                "good_quantity_min_7d": 80,
                "good_quantity_max_7d": 110,
                "produced_quantity_lag_1d": 105,
                "target_quantity_lag_1d": 120,
                "runtime_minutes_lag_1d": 420,
                "downtime_minutes_lag_1d": 20,
                "rejection_rate_lag_1d": 0.01,
                "achievement_rate_lag_1d": 0.875,
            },
        },
    )
    assert response.status_code == 200
    body = response.json()
    assert body["predicted_good_quantity_next_day"] == 123.5
    assert body["metadata"]["data_classification"] == "simulated_prototype"
    assert body["request_id"] == response.headers["X-Request-ID"]


def test_unknown_inference_field_is_rejected(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    _install_fake(client)
    response = client.post(
        "/internal/v1/inference/production/forecast",
        headers=auth_headers,
        json={
            "prediction_date": "2026-08-04",
            "features": {"production_line_code": "LINE-01"},
            "secret": "forbidden",
        },
    )
    assert response.status_code == 422
    assert response.json()["error"]["code"] == "validation_error"

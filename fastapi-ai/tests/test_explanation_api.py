from __future__ import annotations

import json
from copy import deepcopy

from fastapi.testclient import TestClient

from app.llm.errors import OllamaTimeoutError, OllamaUnavailableError
from app.llm.rate_limit import ExplanationRateLimiter
from app.llm.service import ExplanationGenerationService

MODEL_LIMITATIONS = [
    "Simulated-prototype data only.",
    "The forecast is not an industrial production commitment.",
]

VALID_REQUEST = {
    "contract_name": "smartfactory.llm.explanation",
    "contract_version": "v1",
    "explanation_id": "33333333-3333-4333-8333-333333333333",
    "requested_at": "2026-08-04T01:15:00+01:00",
    "role": "production_supervisor",
    "language": "en",
    "facts": {
        "explanation_type": "production_forecast",
        "prediction_date": "2026-08-05",
        "production_line_code": "LINE-01",
        "quantity_unit": "L",
        "history": {
            "days_of_history": 30,
            "rolling_observation_count_7d": 7,
            "good_quantity_lag_1d": 1000.0,
            "good_quantity_mean_7d": 980.0,
            "target_quantity_lag_1d": 1050.0,
            "runtime_minutes_lag_1d": 420,
            "downtime_minutes_lag_1d": 20,
            "rejection_rate_lag_1d": 0.01,
            "achievement_rate_lag_1d": 0.95,
        },
        "result": {"predicted_good_quantity_next_day": 995.0},
        "model": {
            "model_run_id": "11111111-1111-4111-8111-111111111111",
            "source_feature_run_id": "22222222-2222-4222-8222-222222222222",
            "model_name": "random_forest_regressor",
            "data_classification": "simulated_prototype",
            "limitations": MODEL_LIMITATIONS,
        },
    },
}

VALID_OUTPUT = {
    "summary": "The verified forecast for LINE-01 is 995 L on 2026-08-05.",
    "observations": ["The supplied seven-day mean is 980 L."],
    "suggested_human_checks": ["Review validated downtime events for LINE-01."],
    "limitations": [
        (
            "This explanation uses only verified simulated-prototype facts and is not "
            "an industrial commitment."
        ),
        *MODEL_LIMITATIONS,
    ],
    "referenced_fact_keys": [
        "facts.result.predicted_good_quantity_next_day",
        "facts.history.good_quantity_mean_7d",
        "facts.production_line_code",
        "facts.prediction_date",
        "facts.model.data_classification",
        "facts.model.limitations",
    ],
}


class FakeOllamaClient:
    def __init__(self, outputs: list[str] | None = None, error: Exception | None = None) -> None:
        self.outputs = outputs or [json.dumps(VALID_OUTPUT)]
        self.error = error
        self.calls = 0
        self.messages = None
        self.schema = None

    async def generate(self, messages, response_schema):
        self.calls += 1
        self.messages = messages
        self.schema = response_schema
        if self.error is not None:
            raise self.error
        index = min(self.calls - 1, len(self.outputs) - 1)
        return self.outputs[index]


def install_fake(client: TestClient, fake: FakeOllamaClient) -> None:
    client.app.state.explanation_service = ExplanationGenerationService(
        fake,
        client.app.state.settings,
    )


def test_explanation_endpoint_requires_internal_authentication(client: TestClient) -> None:
    response = client.post(
        "/internal/v1/explanations/generate",
        json=VALID_REQUEST,
    )

    assert response.status_code == 401
    assert response.json()["error"]["code"] == "unauthenticated"


def test_valid_explanation_is_generated_with_request_id_and_no_store_headers(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    fake = FakeOllamaClient()
    install_fake(client, fake)

    response = client.post(
        "/internal/v1/explanations/generate",
        headers={**auth_headers, "X-Request-ID": "step22d-request-001"},
        json=VALID_REQUEST,
    )

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "generated"
    assert body["explanation_type"] == "production_forecast"
    assert body["role"] == "production_supervisor"
    assert body["data_classification"] == "simulated_prototype"
    assert body["narrative"]["summary"].endswith("2026-08-05.")
    assert body["request_id"] == "step22d-request-001"
    assert response.headers["X-Request-ID"] == "step22d-request-001"
    assert response.headers["Cache-Control"].startswith("no-store")
    assert fake.calls == 1
    assert fake.schema["type"] == "object"
    serialized_messages = json.dumps(fake.messages).lower()
    assert "database_url" not in serialized_messages
    assert "authorization" not in serialized_messages


def test_invalid_first_model_output_receives_one_bounded_retry(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    fake = FakeOllamaClient(outputs=["not-json", json.dumps(VALID_OUTPUT)])
    install_fake(client, fake)

    response = client.post(
        "/internal/v1/explanations/generate",
        headers=auth_headers,
        json=VALID_REQUEST,
    )

    assert response.status_code == 200
    assert fake.calls == 2
    assert fake.messages[-1]["role"] == "user"
    assert "invalid_json" in fake.messages[-1]["content"]
    assert "not-json" not in fake.messages[-1]["content"]


def test_repeated_unsafe_model_output_returns_safe_bad_gateway(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    raw_secret = "SECRET RAW MODEL OUTPUT"
    fake = FakeOllamaClient(outputs=[raw_secret, raw_secret])
    install_fake(client, fake)

    response = client.post(
        "/internal/v1/explanations/generate",
        headers=auth_headers,
        json=VALID_REQUEST,
    )

    assert response.status_code == 502
    assert response.json()["error"]["code"] == "explanation_output_rejected"
    assert raw_secret not in response.text
    assert fake.calls == 2


def test_ollama_timeout_and_unavailability_are_isolated_safely(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    install_fake(client, FakeOllamaClient(error=OllamaTimeoutError()))
    timeout_response = client.post(
        "/internal/v1/explanations/generate",
        headers=auth_headers,
        json=VALID_REQUEST,
    )
    assert timeout_response.status_code == 503
    assert timeout_response.json()["error"]["code"] == "ollama_timeout"
    assert timeout_response.headers["Retry-After"] == "5"

    client.app.state.explanation_rate_limiter = ExplanationRateLimiter(6, 1)
    install_fake(client, FakeOllamaClient(error=OllamaUnavailableError()))
    unavailable_response = client.post(
        "/internal/v1/explanations/generate",
        headers=auth_headers,
        json=VALID_REQUEST,
    )
    assert unavailable_response.status_code == 503
    assert unavailable_response.json()["error"]["code"] == "ollama_unavailable"


def test_process_local_rate_limit_returns_retry_after(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    fake = FakeOllamaClient()
    install_fake(client, fake)
    client.app.state.explanation_rate_limiter = ExplanationRateLimiter(1, 1)

    first = client.post(
        "/internal/v1/explanations/generate",
        headers=auth_headers,
        json=VALID_REQUEST,
    )
    second = client.post(
        "/internal/v1/explanations/generate",
        headers=auth_headers,
        json=VALID_REQUEST,
    )

    assert first.status_code == 200
    assert second.status_code == 429
    assert second.json()["error"]["code"] == "explanation_rate_limited"
    assert int(second.headers["Retry-After"]) >= 1
    assert fake.calls == 1


def test_endpoint_specific_payload_limit_is_enforced_after_contract_validation(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    payload = deepcopy(VALID_REQUEST)
    payload["facts"]["model"]["limitations"] = [
        f"Verified limitation {index}: " + ("x" * 340) for index in range(10)
    ]
    settings = client.app.state.settings.model_copy(update={"explanation_max_payload_bytes": 4_096})
    fake = FakeOllamaClient()
    client.app.state.explanation_service = ExplanationGenerationService(fake, settings)

    response = client.post(
        "/internal/v1/explanations/generate",
        headers=auth_headers,
        json=payload,
    )

    assert response.status_code == 413
    assert response.json()["error"]["code"] == "explanation_request_too_large"
    assert fake.calls == 0


def test_explanation_endpoint_is_post_only(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    response = client.get(
        "/internal/v1/explanations/generate",
        headers=auth_headers,
    )

    assert response.status_code == 405
    assert response.json()["error"]["code"] == "http_error"

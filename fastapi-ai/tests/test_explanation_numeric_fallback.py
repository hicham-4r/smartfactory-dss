from __future__ import annotations

import json
from copy import deepcopy

from fastapi.testclient import TestClient

from app.llm.fallbacks import build_numeric_safe_fallback
from app.llm.output import build_explanation_response
from app.llm.service import ExplanationGenerationService
from app.schemas.explanation import ExplanationContractRequest
from tests.test_explanation_api import VALID_OUTPUT, VALID_REQUEST
from tests.test_guarded_explanation import ANOMALY_PAYLOAD, MAINTENANCE_PAYLOAD


class RepeatingOllamaClient:
    def __init__(self, output: str) -> None:
        self.output = output
        self.calls = 0
        self.messages = None

    async def generate(self, messages, response_schema):
        del response_schema
        self.calls += 1
        self.messages = messages
        return self.output


def test_repeated_unsupported_number_uses_safe_server_fallback(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    unsafe = deepcopy(VALID_OUTPUT)
    unsafe["observations"] = ["The unsupported comparison value is 42 L."]
    fake = RepeatingOllamaClient(json.dumps(unsafe))
    client.app.state.explanation_service = ExplanationGenerationService(
        fake,
        client.app.state.settings,
    )

    response = client.post(
        "/internal/v1/explanations/generate",
        headers={**auth_headers, "X-Request-ID": "numeric-fallback-request"},
        json=VALID_REQUEST,
    )

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "generated"
    assert body["request_id"] == "numeric-fallback-request"
    assert fake.calls == 2
    assert "42" not in json.dumps(body["narrative"])
    assert "authoritative result" in body["narrative"]["summary"]
    assert body["narrative"]["suggested_human_checks"][0].startswith("Review ")
    assert "Do not include any numeric token" in fake.messages[-1]["content"]


def test_numeric_fallback_is_valid_for_all_explanation_types_and_languages() -> None:
    for payload in (VALID_REQUEST, ANOMALY_PAYLOAD, MAINTENANCE_PAYLOAD):
        request = ExplanationContractRequest.model_validate(payload)
        narrative = build_numeric_safe_fallback(request)
        response = build_explanation_response(
            request,
            narrative,
            request_id="fallback-contract-test",
        )

        assert response.status == "generated"
        assert response.data_classification == "simulated_prototype"
        assert narrative.limitations
        assert narrative.referenced_fact_keys

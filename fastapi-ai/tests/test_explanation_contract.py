from __future__ import annotations

from copy import deepcopy

import pytest
from pydantic import ValidationError

from app.llm.contracts import (
    ExplanationGroundingError,
    allowed_fact_keys,
    build_prompt_fact_bundle,
    validate_narrative_grounding,
)
from app.schemas.explanation import (
    ExplanationContractRequest,
    ExplanationNarrative,
    ExplanationRole,
    ExplanationType,
)

MODEL_FACTS = {
    "model_run_id": "11111111-1111-4111-8111-111111111111",
    "source_feature_run_id": "22222222-2222-4222-8222-222222222222",
    "model_name": "random_forest_regressor",
    "data_classification": "simulated_prototype",
    "limitations": [
        "Simulated-prototype data only.",
        "The forecast is not an industrial production commitment.",
    ],
}

FORECAST_PAYLOAD = {
    "contract_name": "smartfactory.llm.explanation",
    "contract_version": "v1",
    "explanation_id": "33333333-3333-4333-8333-333333333333",
    "requested_at": "2026-08-04T00:30:00+01:00",
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
        "model": MODEL_FACTS,
    },
}


def valid_narrative() -> ExplanationNarrative:
    return ExplanationNarrative(
        summary="The verified forecast remains close to the recent seven-day average.",
        observations=["The forecast is 995 L for the stated prediction date."],
        suggested_human_checks=["Review validated downtime events for the line."],
        limitations=["This is a simulated-prototype decision-support result."],
        referenced_fact_keys=[
            "facts.result.predicted_good_quantity_next_day",
            "facts.history.good_quantity_mean_7d",
        ],
    )


def test_valid_forecast_contract_builds_only_allowlisted_prompt_facts() -> None:
    request = ExplanationContractRequest.model_validate(FORECAST_PAYLOAD)
    bundle = build_prompt_fact_bundle(request)
    payload = bundle.to_prompt_payload()

    assert payload["explanation_type"] == "production_forecast"
    assert payload["role"] == "production_supervisor"
    assert payload["data_classification"] == "simulated_prototype"
    assert payload["facts"]["result"]["predicted_good_quantity_next_day"] == 995.0
    assert "token" not in str(payload).lower()
    assert set(payload["allowed_fact_keys"]) == allowed_fact_keys(
        ExplanationType.PRODUCTION_FORECAST
    )


def test_unknown_or_sensitive_request_fields_are_rejected() -> None:
    payload = deepcopy(FORECAST_PAYLOAD)
    payload["database_url"] = "mysql://forbidden"

    with pytest.raises(ValidationError):
        ExplanationContractRequest.model_validate(payload)


def test_naive_timestamps_are_rejected() -> None:
    payload = deepcopy(FORECAST_PAYLOAD)
    payload["requested_at"] = "2026-08-04T00:30:00"

    with pytest.raises(ValidationError):
        ExplanationContractRequest.model_validate(payload)


def test_maintenance_role_cannot_request_production_explanation() -> None:
    payload = deepcopy(FORECAST_PAYLOAD)
    payload["role"] = "maintenance_manager"

    with pytest.raises(ValidationError):
        ExplanationContractRequest.model_validate(payload)


def test_administrator_can_request_supported_explanations() -> None:
    payload = deepcopy(FORECAST_PAYLOAD)
    payload["role"] = "administrator"

    request = ExplanationContractRequest.model_validate(payload)

    assert request.role is ExplanationRole.ADMINISTRATOR


def test_narrative_references_must_stay_inside_type_allowlist() -> None:
    request = ExplanationContractRequest.model_validate(FORECAST_PAYLOAD)
    narrative = valid_narrative()

    validate_narrative_grounding(request, narrative)

    unsafe = narrative.model_copy(update={"referenced_fact_keys": ["facts.context.machine_code"]})
    with pytest.raises(ExplanationGroundingError):
        validate_narrative_grounding(request, unsafe)


def test_narrative_contract_rejects_extra_sections_and_duplicate_items() -> None:
    with pytest.raises(ValidationError):
        ExplanationNarrative.model_validate(
            {
                "summary": "Verified summary.",
                "observations": ["Same observation.", "same observation."],
                "suggested_human_checks": ["Review the validated records."],
                "limitations": ["Prototype limitation."],
                "referenced_fact_keys": ["facts.model.limitations"],
                "root_cause": "Invented",
            }
        )


def test_anomaly_score_is_a_finite_value_not_a_probability_contract() -> None:
    payload = deepcopy(FORECAST_PAYLOAD)
    payload["role"] = "production_manager"
    payload["facts"] = {
        "explanation_type": "production_anomaly",
        "context": {
            "event_time_utc": "2026-08-04T00:00:00Z",
            "production_line_code": "LINE-02",
            "product_family_code": "VALENCIA-PREMIUM",
            "product_code": "ORANGE-1L",
            "shift_code": "SHIFT-A",
            "quantity_unit": "L",
            "target_quantity": 1000,
            "produced_quantity": 900,
            "good_quantity": 880,
            "rejected_quantity": 20,
            "runtime_minutes": 400,
            "downtime_minutes": 40,
            "achievement_ratio": 0.9,
            "rejection_ratio": 0.0222,
            "good_yield_ratio": 0.9778,
            "throughput_per_hour": 135,
            "downtime_ratio": 0.1,
            "is_validated": True,
        },
        "result": {
            "anomaly_score": -0.031,
            "threshold": 0.0,
            "is_anomaly": False,
        },
        "model": {
            **MODEL_FACTS,
            "model_name": "isolation_forest",
            "limitations": [
                "The anomaly score is not a percentage or probability.",
                "No verified ground-truth anomaly labels are available.",
            ],
        },
    }

    request = ExplanationContractRequest.model_validate(payload)

    assert request.facts.result.anomaly_score == -0.031


def test_maintenance_contract_enforces_probability_bounds() -> None:
    payload = deepcopy(FORECAST_PAYLOAD)
    payload["role"] = "maintenance_manager"
    payload["facts"] = {
        "explanation_type": "maintenance_risk",
        "context": {
            "prediction_date": "2026-08-05",
            "production_line_code": "LINE-03",
            "machine_code": "FILLER-03",
            "machine_type": "filling_machine",
            "is_critical": True,
            "days_observed": 120,
            "fault_status_event_count_7d": 3,
            "fault_minutes_7d": 80,
            "unplanned_downtime_event_count_7d": 2,
            "unplanned_downtime_minutes_7d": 60,
            "maintenance_event_count_30d": 4,
            "preventive_maintenance_count_30d": 1,
            "corrective_maintenance_count_30d": 3,
            "maintenance_downtime_minutes_30d": 180,
            "days_since_last_failure": 2,
            "days_since_last_maintenance": 10,
        },
        "result": {
            "failure_probability_next_7d": 1.2,
            "predicted_unplanned_downtime_minutes_next_7d": 75,
            "priority": "high",
        },
        "model": {
            **MODEL_FACTS,
            "model_name": "random_forest_classifier+gradient_boosting_regressor",
            "limitations": [
                "AI-assisted maintenance prioritization prototype only.",
                "The classifier has many false positives.",
            ],
        },
    }

    with pytest.raises(ValidationError):
        ExplanationContractRequest.model_validate(payload)

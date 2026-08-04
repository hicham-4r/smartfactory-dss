from __future__ import annotations

from dataclasses import dataclass
from typing import Any

from app.schemas.explanation import (
    EXPLANATION_CONTRACT_NAME,
    EXPLANATION_CONTRACT_VERSION,
    ExplanationContractRequest,
    ExplanationNarrative,
    ExplanationRole,
    ExplanationType,
)

_FACT_ALLOWLIST: dict[ExplanationType, frozenset[str]] = {
    ExplanationType.PRODUCTION_FORECAST: frozenset(
        {
            "facts.explanation_type",
            "facts.prediction_date",
            "facts.production_line_code",
            "facts.quantity_unit",
            "facts.history.days_of_history",
            "facts.history.rolling_observation_count_7d",
            "facts.history.good_quantity_lag_1d",
            "facts.history.good_quantity_mean_7d",
            "facts.history.target_quantity_lag_1d",
            "facts.history.runtime_minutes_lag_1d",
            "facts.history.downtime_minutes_lag_1d",
            "facts.history.rejection_rate_lag_1d",
            "facts.history.achievement_rate_lag_1d",
            "facts.result.predicted_good_quantity_next_day",
            "facts.model.model_run_id",
            "facts.model.source_feature_run_id",
            "facts.model.model_name",
            "facts.model.data_classification",
            "facts.model.limitations",
        }
    ),
    ExplanationType.PRODUCTION_ANOMALY: frozenset(
        {
            "facts.explanation_type",
            "facts.context.event_time_utc",
            "facts.context.production_line_code",
            "facts.context.product_family_code",
            "facts.context.product_code",
            "facts.context.shift_code",
            "facts.context.quantity_unit",
            "facts.context.target_quantity",
            "facts.context.produced_quantity",
            "facts.context.good_quantity",
            "facts.context.rejected_quantity",
            "facts.context.runtime_minutes",
            "facts.context.downtime_minutes",
            "facts.context.achievement_ratio",
            "facts.context.rejection_ratio",
            "facts.context.good_yield_ratio",
            "facts.context.throughput_per_hour",
            "facts.context.downtime_ratio",
            "facts.context.is_validated",
            "facts.result.anomaly_score",
            "facts.result.threshold",
            "facts.result.is_anomaly",
            "facts.model.model_run_id",
            "facts.model.source_feature_run_id",
            "facts.model.model_name",
            "facts.model.data_classification",
            "facts.model.limitations",
        }
    ),
    ExplanationType.MAINTENANCE_RISK: frozenset(
        {
            "facts.explanation_type",
            "facts.context.prediction_date",
            "facts.context.production_line_code",
            "facts.context.machine_code",
            "facts.context.machine_type",
            "facts.context.is_critical",
            "facts.context.days_observed",
            "facts.context.fault_status_event_count_7d",
            "facts.context.fault_minutes_7d",
            "facts.context.unplanned_downtime_event_count_7d",
            "facts.context.unplanned_downtime_minutes_7d",
            "facts.context.maintenance_event_count_30d",
            "facts.context.preventive_maintenance_count_30d",
            "facts.context.corrective_maintenance_count_30d",
            "facts.context.maintenance_downtime_minutes_30d",
            "facts.context.days_since_last_failure",
            "facts.context.days_since_last_maintenance",
            "facts.result.failure_probability_next_7d",
            "facts.result.predicted_unplanned_downtime_minutes_next_7d",
            "facts.result.priority",
            "facts.model.model_run_id",
            "facts.model.source_feature_run_id",
            "facts.model.model_name",
            "facts.model.data_classification",
            "facts.model.limitations",
        }
    ),
}


@dataclass(frozen=True, slots=True)
class PromptFactBundle:
    contract_name: str
    contract_version: str
    explanation_id: str
    explanation_type: ExplanationType
    role: ExplanationRole
    language: str
    data_classification: str
    facts: dict[str, Any]
    allowed_fact_keys: tuple[str, ...]

    def to_prompt_payload(self) -> dict[str, Any]:
        return {
            "contract_name": self.contract_name,
            "contract_version": self.contract_version,
            "explanation_id": self.explanation_id,
            "explanation_type": self.explanation_type.value,
            "role": self.role.value,
            "language": self.language,
            "data_classification": self.data_classification,
            "facts": self.facts,
            "allowed_fact_keys": list(self.allowed_fact_keys),
        }


class ExplanationGroundingError(ValueError):
    """Raised when a narrative references a fact outside the strict allowlist."""


def allowed_fact_keys(explanation_type: ExplanationType) -> frozenset[str]:
    return _FACT_ALLOWLIST[explanation_type]


def build_prompt_fact_bundle(request: ExplanationContractRequest) -> PromptFactBundle:
    explanation_type = request.facts.explanation_type
    serialized_facts = request.facts.model_dump(mode="json")
    selected: dict[str, Any] = {}

    for full_path in sorted(_FACT_ALLOWLIST[explanation_type]):
        relative_path = full_path.removeprefix("facts.")
        value = _read_path(serialized_facts, relative_path)
        _write_path(selected, relative_path, value)

    return PromptFactBundle(
        contract_name=EXPLANATION_CONTRACT_NAME,
        contract_version=EXPLANATION_CONTRACT_VERSION,
        explanation_id=str(request.explanation_id),
        explanation_type=explanation_type,
        role=request.role,
        language=request.language.value,
        data_classification="simulated_prototype",
        facts=selected,
        allowed_fact_keys=tuple(sorted(_FACT_ALLOWLIST[explanation_type])),
    )


def validate_narrative_grounding(
    request: ExplanationContractRequest,
    narrative: ExplanationNarrative,
) -> None:
    allowed = _FACT_ALLOWLIST[request.facts.explanation_type]
    unsupported = [key for key in narrative.referenced_fact_keys if key not in allowed]
    if unsupported:
        raise ExplanationGroundingError(
            "The explanation referenced one or more facts outside the strict allowlist."
        )


def _read_path(payload: dict[str, Any], dotted_path: str) -> Any:
    current: Any = payload
    for segment in dotted_path.split("."):
        if not isinstance(current, dict) or segment not in current:
            raise ExplanationGroundingError(
                "The strict fact allowlist does not match the validated contract."
            )
        current = current[segment]
    return current


def _write_path(target: dict[str, Any], dotted_path: str, value: Any) -> None:
    segments = dotted_path.split(".")
    current = target
    for segment in segments[:-1]:
        next_value = current.setdefault(segment, {})
        if not isinstance(next_value, dict):
            raise ExplanationGroundingError(
                "The strict fact allowlist contains a conflicting path."
            )
        current = next_value
    current[segments[-1]] = value

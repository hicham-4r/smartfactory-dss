from __future__ import annotations

import re
from datetime import date, datetime
from enum import StrEnum
from typing import Annotated, Literal
from uuid import UUID

from pydantic import Field, StringConstraints, field_validator, model_validator

from app.schemas.common import StrictRequestModel, StrictResponseModel

EXPLANATION_CONTRACT_NAME = "smartfactory.llm.explanation"
EXPLANATION_CONTRACT_VERSION = "v1"

_CONTROL_CHARACTERS = re.compile(r"[\x00-\x1f\x7f]")
_FACT_KEY_PATTERN = re.compile(r"^facts\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$")

SafeCode = Annotated[
    str,
    StringConstraints(
        strip_whitespace=True,
        min_length=1,
        max_length=100,
        pattern=r"^[A-Za-z0-9][A-Za-z0-9._:/+\-]{0,99}$",
    ),
]
QuantityUnit = Annotated[
    str,
    StringConstraints(strip_whitespace=True, min_length=1, max_length=30),
]
NarrativeSummary = Annotated[
    str,
    StringConstraints(strip_whitespace=True, min_length=1, max_length=600),
]
NarrativeItem = Annotated[
    str,
    StringConstraints(strip_whitespace=True, min_length=1, max_length=300),
]
LimitationText = Annotated[
    str,
    StringConstraints(strip_whitespace=True, min_length=1, max_length=400),
]
FactKey = Annotated[
    str,
    StringConstraints(strip_whitespace=True, min_length=7, max_length=160),
]
FiniteFloat = Annotated[float, Field(allow_inf_nan=False)]
NonNegativeFloat = Annotated[float, Field(ge=0, allow_inf_nan=False)]
Probability = Annotated[float, Field(ge=0, le=1, allow_inf_nan=False)]
NonNegativeInt = Annotated[int, Field(ge=0)]


class ExplanationRole(StrEnum):
    PRODUCTION_SUPERVISOR = "production_supervisor"
    PRODUCTION_MANAGER = "production_manager"
    MAINTENANCE_MANAGER = "maintenance_manager"
    ADMINISTRATOR = "administrator"


class ExplanationType(StrEnum):
    PRODUCTION_FORECAST = "production_forecast"
    PRODUCTION_ANOMALY = "production_anomaly"
    MAINTENANCE_RISK = "maintenance_risk"


class ExplanationLanguage(StrEnum):
    ENGLISH = "en"
    FRENCH = "fr"


_ALLOWED_ROLES: dict[ExplanationType, frozenset[ExplanationRole]] = {
    ExplanationType.PRODUCTION_FORECAST: frozenset(
        {
            ExplanationRole.PRODUCTION_SUPERVISOR,
            ExplanationRole.PRODUCTION_MANAGER,
            ExplanationRole.ADMINISTRATOR,
        }
    ),
    ExplanationType.PRODUCTION_ANOMALY: frozenset(
        {
            ExplanationRole.PRODUCTION_SUPERVISOR,
            ExplanationRole.PRODUCTION_MANAGER,
            ExplanationRole.ADMINISTRATOR,
        }
    ),
    ExplanationType.MAINTENANCE_RISK: frozenset(
        {
            ExplanationRole.MAINTENANCE_MANAGER,
            ExplanationRole.ADMINISTRATOR,
        }
    ),
}


class ExplanationModelFacts(StrictRequestModel):
    model_run_id: UUID
    source_feature_run_id: UUID
    model_name: SafeCode
    data_classification: Literal["simulated_prototype"] = "simulated_prototype"
    limitations: list[LimitationText] = Field(min_length=1, max_length=10)

    @field_validator("limitations")
    @classmethod
    def limitations_must_be_unique(cls, value: list[str]) -> list[str]:
        return _unique_text_items(value, "limitations")


class ProductionForecastHistoryFacts(StrictRequestModel):
    days_of_history: int = Field(ge=1, le=3660)
    rolling_observation_count_7d: int = Field(ge=1, le=7)
    good_quantity_lag_1d: NonNegativeFloat
    good_quantity_mean_7d: NonNegativeFloat
    target_quantity_lag_1d: NonNegativeFloat
    runtime_minutes_lag_1d: NonNegativeInt
    downtime_minutes_lag_1d: NonNegativeInt
    rejection_rate_lag_1d: NonNegativeFloat | None = None
    achievement_rate_lag_1d: NonNegativeFloat | None = None


class ProductionForecastResultFacts(StrictRequestModel):
    predicted_good_quantity_next_day: NonNegativeFloat


class ProductionForecastExplanationFacts(StrictRequestModel):
    explanation_type: Literal[ExplanationType.PRODUCTION_FORECAST] = (
        ExplanationType.PRODUCTION_FORECAST
    )
    prediction_date: date
    production_line_code: SafeCode
    quantity_unit: QuantityUnit
    history: ProductionForecastHistoryFacts
    result: ProductionForecastResultFacts
    model: ExplanationModelFacts


class ProductionAnomalyContextFacts(StrictRequestModel):
    event_time_utc: datetime
    production_line_code: SafeCode
    product_family_code: SafeCode
    product_code: SafeCode
    shift_code: SafeCode
    quantity_unit: QuantityUnit
    target_quantity: NonNegativeFloat
    produced_quantity: NonNegativeFloat
    good_quantity: NonNegativeFloat
    rejected_quantity: NonNegativeFloat
    runtime_minutes: NonNegativeInt
    downtime_minutes: NonNegativeInt
    achievement_ratio: NonNegativeFloat | None = None
    rejection_ratio: NonNegativeFloat | None = None
    good_yield_ratio: NonNegativeFloat | None = None
    throughput_per_hour: NonNegativeFloat | None = None
    downtime_ratio: NonNegativeFloat | None = None
    is_validated: bool

    @field_validator("event_time_utc")
    @classmethod
    def event_time_requires_timezone(cls, value: datetime) -> datetime:
        return _timezone_aware(value, "event_time_utc")


class ProductionAnomalyResultFacts(StrictRequestModel):
    anomaly_score: FiniteFloat
    threshold: FiniteFloat
    is_anomaly: bool


class ProductionAnomalyExplanationFacts(StrictRequestModel):
    explanation_type: Literal[ExplanationType.PRODUCTION_ANOMALY] = (
        ExplanationType.PRODUCTION_ANOMALY
    )
    context: ProductionAnomalyContextFacts
    result: ProductionAnomalyResultFacts
    model: ExplanationModelFacts


class MaintenanceContextFacts(StrictRequestModel):
    prediction_date: date
    production_line_code: SafeCode
    machine_code: SafeCode
    machine_type: SafeCode
    is_critical: bool
    days_observed: NonNegativeInt
    fault_status_event_count_7d: NonNegativeInt
    fault_minutes_7d: NonNegativeInt
    unplanned_downtime_event_count_7d: NonNegativeInt
    unplanned_downtime_minutes_7d: NonNegativeInt
    maintenance_event_count_30d: NonNegativeInt
    preventive_maintenance_count_30d: NonNegativeInt
    corrective_maintenance_count_30d: NonNegativeInt
    maintenance_downtime_minutes_30d: NonNegativeInt
    days_since_last_failure: NonNegativeInt | None = None
    days_since_last_maintenance: NonNegativeInt | None = None


class MaintenanceRiskResultFacts(StrictRequestModel):
    failure_probability_next_7d: Probability
    predicted_unplanned_downtime_minutes_next_7d: NonNegativeFloat
    priority: Literal["low", "medium", "high", "critical"]


class MaintenanceRiskExplanationFacts(StrictRequestModel):
    explanation_type: Literal[ExplanationType.MAINTENANCE_RISK] = ExplanationType.MAINTENANCE_RISK
    context: MaintenanceContextFacts
    result: MaintenanceRiskResultFacts
    model: ExplanationModelFacts


ExplanationFacts = Annotated[
    ProductionForecastExplanationFacts
    | ProductionAnomalyExplanationFacts
    | MaintenanceRiskExplanationFacts,
    Field(discriminator="explanation_type"),
]


class ExplanationContractRequest(StrictRequestModel):
    contract_name: Literal["smartfactory.llm.explanation"] = EXPLANATION_CONTRACT_NAME
    contract_version: Literal["v1"] = EXPLANATION_CONTRACT_VERSION
    explanation_id: UUID
    requested_at: datetime
    role: ExplanationRole
    language: ExplanationLanguage = ExplanationLanguage.ENGLISH
    facts: ExplanationFacts

    @field_validator("requested_at")
    @classmethod
    def requested_at_requires_timezone(cls, value: datetime) -> datetime:
        return _timezone_aware(value, "requested_at")

    @model_validator(mode="after")
    def role_must_be_authorized_for_explanation_type(self) -> ExplanationContractRequest:
        explanation_type = self.facts.explanation_type
        if self.role not in _ALLOWED_ROLES[explanation_type]:
            raise ValueError(
                f"role '{self.role.value}' is not authorized for "
                f"'{explanation_type.value}' explanations"
            )
        return self


class ExplanationNarrative(StrictResponseModel):
    summary: NarrativeSummary
    observations: list[NarrativeItem] = Field(min_length=1, max_length=5)
    suggested_human_checks: list[NarrativeItem] = Field(min_length=1, max_length=5)
    limitations: list[LimitationText] = Field(min_length=1, max_length=12)
    referenced_fact_keys: list[FactKey] = Field(min_length=1, max_length=40)

    @field_validator(
        "observations",
        "suggested_human_checks",
        "limitations",
    )
    @classmethod
    def narrative_lists_must_be_unique(cls, value: list[str], info) -> list[str]:
        return _unique_text_items(value, info.field_name)

    @field_validator("summary")
    @classmethod
    def summary_must_not_contain_control_characters(cls, value: str) -> str:
        return _safe_text(value, "summary")

    @field_validator("observations", "suggested_human_checks", "limitations")
    @classmethod
    def list_items_must_not_contain_control_characters(
        cls,
        value: list[str],
        info,
    ) -> list[str]:
        return [_safe_text(item, info.field_name) for item in value]

    @field_validator("referenced_fact_keys")
    @classmethod
    def fact_keys_must_be_safe_and_unique(cls, value: list[str]) -> list[str]:
        normalized: list[str] = []
        seen: set[str] = set()
        for item in value:
            key = item.strip()
            if not _FACT_KEY_PATTERN.fullmatch(key):
                raise ValueError("referenced_fact_keys contains an invalid fact path")
            if key in seen:
                raise ValueError("referenced_fact_keys must not contain duplicates")
            seen.add(key)
            normalized.append(key)
        return normalized


class ExplanationContractResponse(StrictResponseModel):
    status: Literal["generated"] = "generated"
    contract_name: Literal["smartfactory.llm.explanation"] = EXPLANATION_CONTRACT_NAME
    contract_version: Literal["v1"] = EXPLANATION_CONTRACT_VERSION
    explanation_id: UUID
    explanation_type: ExplanationType
    role: ExplanationRole
    language: ExplanationLanguage
    data_classification: Literal["simulated_prototype"] = "simulated_prototype"
    narrative: ExplanationNarrative
    request_id: str

    @field_validator("request_id")
    @classmethod
    def request_id_must_be_safe(cls, value: str) -> str:
        normalized = value.strip()
        if not normalized or len(normalized) > 200:
            raise ValueError("request_id must contain between 1 and 200 characters")
        return _safe_text(normalized, "request_id")


def allowed_roles_for(explanation_type: ExplanationType) -> frozenset[ExplanationRole]:
    return _ALLOWED_ROLES[explanation_type]


def _timezone_aware(value: datetime, field_name: str) -> datetime:
    if value.tzinfo is None or value.utcoffset() is None:
        raise ValueError(f"{field_name} must include a timezone offset")
    return value


def _safe_text(value: str, field_name: str) -> str:
    if _CONTROL_CHARACTERS.search(value):
        raise ValueError(f"{field_name} must not contain control characters")
    return value


def _unique_text_items(value: list[str], field_name: str) -> list[str]:
    normalized: list[str] = []
    seen: set[str] = set()
    for item in value:
        safe = _safe_text(item.strip(), field_name)
        folded = safe.casefold()
        if folded in seen:
            raise ValueError(f"{field_name} must not contain duplicate items")
        seen.add(folded)
        normalized.append(safe)
    return normalized

from __future__ import annotations

from datetime import date, datetime
from decimal import Decimal, InvalidOperation
from typing import Annotated, Literal
from uuid import UUID
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from pydantic import (
    AfterValidator,
    Field,
    StrictInt,
    StringConstraints,
    field_validator,
    model_validator,
)

from app.schemas.common import StrictRequestModel, StrictResponseModel

ANALYTICS_CONTRACT_NAME = "smartfactory.analytics.snapshot"
ANALYTICS_CONTRACT_VERSION = "v1"
ANALYTICS_SOURCE_APPLICATION = "smartfactory-dss-laravel"
ANALYTICS_DATA_CLASSIFICATION = "simulated_prototype"

AnalyticsSection = Literal[
    "production_kpis",
    "production_breakdowns",
    "maintenance_kpis",
    "quality_kpis",
]

NonNegativeInt = Annotated[StrictInt, Field(ge=0)]
PositiveInt = Annotated[StrictInt, Field(gt=0)]
Percentage = Annotated[float, Field(ge=0, le=100)]
NonNegativeFloat = Annotated[float, Field(ge=0)]
ShortText = Annotated[str, StringConstraints(strip_whitespace=True, min_length=1, max_length=200)]
LongText = Annotated[str, StringConstraints(strip_whitespace=True, min_length=1, max_length=4_000)]
KeyText = Annotated[str, StringConstraints(strip_whitespace=True, min_length=1, max_length=200)]
QuantityUnit = Annotated[str, StringConstraints(strip_whitespace=True, min_length=1, max_length=40)]
SourceSystem = Annotated[
    str,
    StringConstraints(
        strip_whitespace=True,
        min_length=1,
        max_length=50,
        pattern=r"^[a-z0-9][a-z0-9._-]*$",
    ),
]


def _validate_non_negative_decimal(value: str) -> str:
    try:
        decimal_value = Decimal(value)
    except (InvalidOperation, ValueError) as exception:
        raise ValueError("must be a valid decimal string") from exception

    if not decimal_value.is_finite():
        raise ValueError("must be finite")

    if decimal_value < 0:
        raise ValueError("must not be negative")

    return value


NonNegativeDecimalString = Annotated[
    str,
    StringConstraints(
        strip_whitespace=True,
        min_length=1,
        max_length=80,
        pattern=r"^\d+(?:\.\d+)?$",
    ),
    AfterValidator(_validate_non_negative_decimal),
]


def _require_timezone_aware(value: datetime) -> datetime:
    if value.tzinfo is None or value.utcoffset() is None:
        raise ValueError("must include an explicit timezone offset")
    return value


AwareDateTime = Annotated[datetime, AfterValidator(_require_timezone_aware)]


class DateRangeFilter(StrictRequestModel):
    start_date: date
    end_date: date
    timezone: Annotated[str, StringConstraints(strip_whitespace=True, min_length=1, max_length=100)]

    @field_validator("timezone")
    @classmethod
    def validate_timezone(cls, value: str) -> str:
        try:
            ZoneInfo(value)
        except ZoneInfoNotFoundError as exception:
            raise ValueError("must be a valid IANA timezone") from exception
        return value

    @model_validator(mode="after")
    def validate_date_range(self) -> DateRangeFilter:
        if self.end_date < self.start_date:
            raise ValueError("end_date must not precede start_date")
        if (self.end_date - self.start_date).days + 1 > 366:
            raise ValueError("date range must not exceed 366 inclusive days")
        return self


class ProductionAnalyticsFilterContract(DateRangeFilter):
    production_line_id: PositiveInt | None = None
    product_id: PositiveInt | None = None
    product_family_id: PositiveInt | None = None
    shift_id: PositiveInt | None = None
    machine_id: PositiveInt | None = None
    production_order_id: PositiveInt | None = None
    status: ShortText | None = None


class MaintenanceAnalyticsFilterContract(DateRangeFilter):
    production_line_id: PositiveInt | None = None
    machine_id: PositiveInt | None = None
    maintenance_type: ShortText | None = None
    downtime_category: Literal["planned", "unplanned"] | None = None


class QualityAnalyticsFilterContract(DateRangeFilter):
    production_line_id: PositiveInt | None = None
    product_id: PositiveInt | None = None
    product_family_id: PositiveInt | None = None
    inspection_result: ShortText | None = None
    lot_status: ShortText | None = None
    nonconformity_severity: ShortText | None = None
    nonconformity_status: ShortText | None = None
    lot_number: ShortText | None = None


class ProductionKpiUnitContract(StrictRequestModel):
    quantity_unit: QuantityUnit
    target_order_count: NonNegativeInt
    record_count: NonNegativeInt
    validated_record_count: NonNegativeInt
    provisional_record_count: NonNegativeInt
    is_provisional: bool
    target_quantity: NonNegativeDecimalString
    actual_quantity: NonNegativeDecimalString
    good_quantity: NonNegativeDecimalString
    rejected_quantity: NonNegativeDecimalString
    runtime_minutes: NonNegativeInt
    downtime_minutes: NonNegativeInt
    achievement_percentage: NonNegativeFloat | None = None
    rejection_percentage: Percentage | None = None
    average_production_rate_per_hour: NonNegativeDecimalString | None = None
    observed_utilization_percentage: Percentage | None = None


class ProductionKpiSummaryContract(StrictRequestModel):
    filter: ProductionAnalyticsFilterContract
    generated_at: AwareDateTime
    data_basis: LongText
    unit_count: NonNegativeInt
    has_mixed_units: bool
    is_provisional: bool
    record_count: NonNegativeInt
    validated_record_count: NonNegativeInt
    provisional_record_count: NonNegativeInt
    target_order_count: NonNegativeInt
    runtime_minutes: NonNegativeInt
    downtime_minutes: NonNegativeInt
    units: Annotated[list[ProductionKpiUnitContract], Field(max_length=1_000)]

    @model_validator(mode="after")
    def validate_unit_count(self) -> ProductionKpiSummaryContract:
        if self.unit_count != len(self.units):
            raise ValueError("unit_count must match the number of unit rows")
        return self


class ProductionMetricRowContract(StrictRequestModel):
    key: KeyText
    label: ShortText
    quantity_unit: QuantityUnit
    target_count: NonNegativeInt
    record_count: NonNegativeInt
    validated_record_count: NonNegativeInt
    provisional_record_count: NonNegativeInt
    is_provisional: bool
    target_quantity: NonNegativeDecimalString
    actual_quantity: NonNegativeDecimalString
    good_quantity: NonNegativeDecimalString
    rejected_quantity: NonNegativeDecimalString
    runtime_minutes: NonNegativeInt
    downtime_minutes: NonNegativeInt
    achievement_percentage: NonNegativeFloat | None = None
    rejection_percentage: Percentage | None = None
    quality_yield_percentage: Percentage | None = None
    good_output_efficiency_percentage: NonNegativeFloat | None = None
    average_production_rate_per_hour: NonNegativeDecimalString | None = None
    observed_utilization_percentage: Percentage | None = None


ProductionRows = Annotated[list[ProductionMetricRowContract], Field(max_length=5_000)]


class ProductionBreakdownContract(StrictRequestModel):
    filter: ProductionAnalyticsFilterContract
    generated_at: AwareDateTime
    is_empty: bool
    has_mixed_units: bool
    line_ranking_basis: LongText
    shift_target_caution: LongText
    daily_trend: ProductionRows
    weekly_trend: ProductionRows
    monthly_trend: ProductionRows
    by_production_line: ProductionRows
    by_shift: ProductionRows
    by_product: ProductionRows
    by_product_family: ProductionRows
    best_lines_by_unit: ProductionRows
    lowest_lines_by_unit: ProductionRows


class MaintenanceMachineMetricContract(StrictRequestModel):
    machine_id: PositiveInt
    machine_code: ShortText
    machine_name: ShortText
    production_line_id: PositiveInt
    production_line_name: ShortText
    downtime_event_count: NonNegativeInt
    open_downtime_event_count: NonNegativeInt
    total_downtime_minutes: NonNegativeInt
    planned_downtime_minutes: NonNegativeInt
    unplanned_downtime_minutes: NonNegativeInt
    unclassified_downtime_minutes: NonNegativeInt
    observed_status_minutes: NonNegativeInt
    running_minutes: NonNegativeInt
    fault_event_count: NonNegativeInt
    maintenance_intervention_count: NonNegativeInt
    preventive_intervention_count: NonNegativeInt
    corrective_intervention_count: NonNegativeInt
    completed_corrective_count: NonNegativeInt
    corrective_repair_minutes: NonNegativeInt
    availability_percentage: Percentage | None = None
    mttr_minutes: Annotated[float, Field(ge=0)] | None = None
    mtbf_minutes: Annotated[float, Field(ge=0)] | None = None
    failures_per_100_running_hours: Annotated[float, Field(ge=0)] | None = None
    has_repeated_failures: bool


class MaintenanceTypeMetricContract(StrictRequestModel):
    maintenance_type: ShortText
    label: ShortText
    intervention_count: NonNegativeInt
    planned_count: NonNegativeInt
    in_progress_count: NonNegativeInt
    completed_count: NonNegativeInt
    cancelled_count: NonNegativeInt
    downtime_minutes: NonNegativeInt


class MaintenanceKpiSummaryContract(StrictRequestModel):
    filter: MaintenanceAnalyticsFilterContract
    generated_at: AwareDateTime
    data_basis: LongText
    downtime_event_count: NonNegativeInt
    open_downtime_event_count: NonNegativeInt
    total_downtime_minutes: NonNegativeInt
    planned_downtime_minutes: NonNegativeInt
    unplanned_downtime_minutes: NonNegativeInt
    unclassified_downtime_minutes: NonNegativeInt
    observed_status_minutes: NonNegativeInt
    running_minutes: NonNegativeInt
    fault_event_count: NonNegativeInt
    maintenance_intervention_count: NonNegativeInt
    preventive_intervention_count: NonNegativeInt
    corrective_intervention_count: NonNegativeInt
    completed_corrective_count: NonNegativeInt
    corrective_repair_minutes: NonNegativeInt
    repeated_failure_machine_count: NonNegativeInt
    availability_percentage: Percentage | None = None
    mttr_minutes: Annotated[float, Field(ge=0)] | None = None
    mtbf_minutes: Annotated[float, Field(ge=0)] | None = None
    failures_per_100_running_hours: Annotated[float, Field(ge=0)] | None = None
    machines: Annotated[list[MaintenanceMachineMetricContract], Field(max_length=5_000)]
    maintenance_types: Annotated[list[MaintenanceTypeMetricContract], Field(max_length=100)]


class QualityUnitMetricContract(StrictRequestModel):
    quantity_unit: QuantityUnit
    lot_count: NonNegativeInt
    produced_quantity: NonNegativeDecimalString
    released_quantity: NonNegativeDecimalString
    rejected_quantity: NonNegativeDecimalString
    released_quantity_percentage: Percentage | None = None
    rejected_quantity_percentage: Percentage | None = None


class QualityDimensionMetricContract(StrictRequestModel):
    key: KeyText
    label: ShortText
    inspection_count: NonNegativeInt
    passed_inspection_count: NonNegativeInt
    failed_inspection_count: NonNegativeInt
    conditional_inspection_count: NonNegativeInt
    pending_inspection_count: NonNegativeInt
    lot_count: NonNegativeInt
    released_lot_count: NonNegativeInt
    blocked_lot_count: NonNegativeInt
    rejected_lot_count: NonNegativeInt
    pending_lot_count: NonNegativeInt
    nonconformity_count: NonNegativeInt
    open_nonconformity_count: NonNegativeInt
    resolved_nonconformity_count: NonNegativeInt
    inspection_pass_percentage: Percentage | None = None
    released_lot_percentage: Percentage | None = None
    nonconformities_per_100_inspections: Annotated[float, Field(ge=0)] | None = None


class QualityCategoryMetricContract(StrictRequestModel):
    category: ShortText
    nonconformity_count: NonNegativeInt
    open_count: NonNegativeInt
    resolved_count: NonNegativeInt
    minor_count: NonNegativeInt
    major_count: NonNegativeInt
    critical_count: NonNegativeInt


class QualityKpiSummaryContract(StrictRequestModel):
    filter: QualityAnalyticsFilterContract
    generated_at: AwareDateTime
    data_basis: LongText
    is_empty: bool
    inspection_count: NonNegativeInt
    passed_inspection_count: NonNegativeInt
    failed_inspection_count: NonNegativeInt
    conditional_inspection_count: NonNegativeInt
    pending_inspection_count: NonNegativeInt
    sample_size: NonNegativeInt
    passed_sample_quantity: NonNegativeInt
    failed_sample_quantity: NonNegativeInt
    lot_count: NonNegativeInt
    released_lot_count: NonNegativeInt
    blocked_lot_count: NonNegativeInt
    rejected_lot_count: NonNegativeInt
    pending_lot_count: NonNegativeInt
    nonconformity_count: NonNegativeInt
    open_nonconformity_count: NonNegativeInt
    resolved_nonconformity_count: NonNegativeInt
    minor_nonconformity_count: NonNegativeInt
    major_nonconformity_count: NonNegativeInt
    critical_nonconformity_count: NonNegativeInt
    inspection_pass_percentage: Percentage | None = None
    sample_failure_percentage: Percentage | None = None
    released_lot_percentage: Percentage | None = None
    held_rejected_lot_percentage: Percentage | None = None
    nonconformities_per_100_inspections: Annotated[float, Field(ge=0)] | None = None
    quantity_units: Annotated[list[QualityUnitMetricContract], Field(max_length=1_000)]
    by_production_line: Annotated[list[QualityDimensionMetricContract], Field(max_length=5_000)]
    by_product: Annotated[list[QualityDimensionMetricContract], Field(max_length=5_000)]
    by_product_family: Annotated[list[QualityDimensionMetricContract], Field(max_length=5_000)]
    nonconformity_categories: Annotated[
        list[QualityCategoryMetricContract],
        Field(max_length=1_000),
    ]


class AnalyticsContractMetadata(StrictRequestModel):
    snapshot_id: UUID
    contract_name: Literal["smartfactory.analytics.snapshot"]
    contract_version: Literal["v1"]
    source_application: Literal["smartfactory-dss-laravel"]
    source_system: SourceSystem
    data_classification: Literal["simulated_prototype"]
    generated_at: AwareDateTime
    timezone: Annotated[str, StringConstraints(strip_whitespace=True, min_length=1, max_length=100)]

    @field_validator("timezone")
    @classmethod
    def validate_timezone(cls, value: str) -> str:
        try:
            ZoneInfo(value)
        except ZoneInfoNotFoundError as exception:
            raise ValueError("must be a valid IANA timezone") from exception
        return value


class AnalyticsContractPayload(StrictRequestModel):
    production_kpis: ProductionKpiSummaryContract | None = None
    production_breakdowns: ProductionBreakdownContract | None = None
    maintenance_kpis: MaintenanceKpiSummaryContract | None = None
    quality_kpis: QualityKpiSummaryContract | None = None

    @model_validator(mode="after")
    def require_at_least_one_section(self) -> AnalyticsContractPayload:
        if not self.section_names():
            raise ValueError("at least one analytics section is required")
        return self

    def section_names(self) -> list[AnalyticsSection]:
        ordered: list[AnalyticsSection] = []
        if self.production_kpis is not None:
            ordered.append("production_kpis")
        if self.production_breakdowns is not None:
            ordered.append("production_breakdowns")
        if self.maintenance_kpis is not None:
            ordered.append("maintenance_kpis")
        if self.quality_kpis is not None:
            ordered.append("quality_kpis")
        return ordered


class AnalyticsSnapshotContractRequest(StrictRequestModel):
    metadata: AnalyticsContractMetadata
    payload: AnalyticsContractPayload

    @model_validator(mode="after")
    def validate_section_timezones(self) -> AnalyticsSnapshotContractRequest:
        expected = self.metadata.timezone
        section_filters = [
            self.payload.production_kpis.filter if self.payload.production_kpis else None,
            (
                self.payload.production_breakdowns.filter
                if self.payload.production_breakdowns
                else None
            ),
            self.payload.maintenance_kpis.filter if self.payload.maintenance_kpis else None,
            self.payload.quality_kpis.filter if self.payload.quality_kpis else None,
        ]
        for section_filter in section_filters:
            if section_filter is not None and section_filter.timezone != expected:
                raise ValueError("all analytics section timezones must match metadata.timezone")
        return self


class AnalyticsContractAcceptedResponse(StrictResponseModel):
    status: Literal["accepted"]
    contract_name: Literal["smartfactory.analytics.snapshot"]
    contract_version: Literal["v1"]
    snapshot_id: UUID
    accepted_sections: list[AnalyticsSection]
    received_at: datetime
    request_id: str

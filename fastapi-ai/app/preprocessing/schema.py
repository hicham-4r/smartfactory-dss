from __future__ import annotations

from dataclasses import dataclass
from typing import Literal

from app.datasets.schema import DATASET_COLUMNS

PREPROCESSING_CONTRACT = "smartfactory.ml.preprocessed.snapshot"
PREPROCESSING_MANIFEST_VERSION = "v1"
PREPROCESSING_RULESET_VERSION = "v1"
PREPROCESSING_DATA_CLASSIFICATION = "simulated_prototype"

ColumnKind = Literal[
    "text",
    "code",
    "category",
    "date",
    "datetime",
    "integer",
    "decimal",
    "boolean",
    "unit",
    "currency",
]


@dataclass(frozen=True, slots=True)
class ColumnRule:
    kind: ColumnKind
    required: bool = True
    non_negative: bool = False


OPTIONAL_COLUMNS: dict[str, set[str]] = {
    "production_records": {"ended_at_utc", "source_version"},
    "downtime_events": {
        "ended_at_utc",
        "shift_code",
        "category",
        "downtime_type",
        "source_version",
    },
    "machine_status_events": {"ended_at_utc", "duration_minutes"},
    "maintenance_history": {
        "started_at_utc",
        "completed_at_utc",
        "downtime_minutes",
        "cost",
        "currency",
    },
    "quality_inspections": {"sample_size", "passed_quantity", "failed_quantity", "source_version"},
    "finished_lots": {"expiry_date", "released_at_utc"},
    "nonconformities": {"corrected_at_utc", "category"},
}

DATE_COLUMNS = {
    "production_date",
    "expiry_date",
}

DATETIME_COLUMNS = {
    "started_at_utc",
    "ended_at_utc",
    "occurred_at_utc",
    "scheduled_at_utc",
    "completed_at_utc",
    "inspected_at_utc",
    "produced_at_utc",
    "released_at_utc",
    "detected_at_utc",
    "corrected_at_utc",
    "source_updated_at_utc",
}

INTEGER_COLUMNS = {
    "production_order_priority",
    "runtime_minutes",
    "downtime_minutes",
    "duration_minutes",
    "sample_size",
    "passed_quantity",
    "failed_quantity",
    "source_version",
}

DECIMAL_COLUMNS = {
    "target_quantity",
    "produced_quantity",
    "good_quantity",
    "rejected_quantity",
    "released_quantity",
    "cost",
}

BOOLEAN_COLUMNS = {
    "is_validated",
    "is_resolved",
    "is_critical",
}

CODE_COLUMNS = {
    "production_line_code",
    "product_family_code",
    "product_code",
    "shift_code",
    "machine_code",
}

UNIT_COLUMNS = {"quantity_unit"}
CURRENCY_COLUMNS = {"currency"}

CATEGORY_COLUMNS = {
    "production_order_status",
    "record_status",
    "validation_status",
    "import_status",
    "machine_type",
    "severity",
    "category",
    "downtime_type",
    "status",
    "maintenance_type",
    "inspection_type",
    "result",
}

NON_NEGATIVE_COLUMNS = INTEGER_COLUMNS | DECIMAL_COLUMNS


def rule_for(dataset: str, column: str) -> ColumnRule:
    if dataset not in DATASET_COLUMNS:
        raise KeyError(f"Unknown dataset: {dataset}")
    if column not in DATASET_COLUMNS[dataset]:
        raise KeyError(f"Unknown column for {dataset}: {column}")

    if column in DATE_COLUMNS:
        kind: ColumnKind = "date"
    elif column in DATETIME_COLUMNS:
        kind = "datetime"
    elif column in INTEGER_COLUMNS:
        kind = "integer"
    elif column in DECIMAL_COLUMNS:
        kind = "decimal"
    elif column in BOOLEAN_COLUMNS:
        kind = "boolean"
    elif column in CODE_COLUMNS:
        kind = "code"
    elif column in UNIT_COLUMNS:
        kind = "unit"
    elif column in CURRENCY_COLUMNS:
        kind = "currency"
    elif column in CATEGORY_COLUMNS:
        kind = "category"
    else:
        kind = "text"

    return ColumnRule(
        kind=kind,
        required=column not in OPTIONAL_COLUMNS[dataset],
        non_negative=column in NON_NEGATIVE_COLUMNS,
    )


def dataset_rules(dataset: str) -> dict[str, ColumnRule]:
    return {column: rule_for(dataset, column) for column in DATASET_COLUMNS[dataset]}

from __future__ import annotations

from datetime import date, datetime
from typing import Annotated, Literal
from uuid import UUID
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from pydantic import (
    Field,
    StringConstraints,
    field_validator,
    model_validator,
)

from app.datasets.schema import (
    DATASET_COLUMNS,
    DATASET_CONTRACT,
    DATASET_SCHEMA_VERSION,
    DATA_CLASSIFICATION,
    MANIFEST_VERSION,
    SOURCE_APPLICATION,
)
from app.schemas.common import StrictRequestModel

Sha256Text = Annotated[
    str,
    StringConstraints(
        strip_whitespace=True,
        pattern=r"^[0-9a-f]{64}$",
    ),
]
SourceSystem = Annotated[
    str,
    StringConstraints(
        strip_whitespace=True,
        min_length=1,
        max_length=50,
        pattern=r"^[a-z0-9][a-z0-9._-]*$",
    ),
]
DatasetName = Literal[
    "production_records",
    "downtime_events",
    "machine_status_events",
    "maintenance_history",
    "quality_inspections",
    "finished_lots",
    "nonconformities",
]


class DatasetPeriod(StrictRequestModel):
    start_date: date
    end_date: date
    timezone: Annotated[
        str,
        StringConstraints(
            strip_whitespace=True,
            min_length=1,
            max_length=100,
        ),
    ]
    utc_start: datetime
    utc_end_exclusive: datetime

    @field_validator("timezone")
    @classmethod
    def validate_timezone(cls, value: str) -> str:
        try:
            ZoneInfo(value)
        except ZoneInfoNotFoundError as exception:
            raise ValueError("must be a valid IANA timezone") from exception
        return value

    @field_validator("utc_start", "utc_end_exclusive")
    @classmethod
    def require_aware_datetime(cls, value: datetime) -> datetime:
        if value.tzinfo is None or value.utcoffset() is None:
            raise ValueError("must include an explicit timezone offset")
        return value

    @model_validator(mode="after")
    def validate_period(self) -> DatasetPeriod:
        if self.end_date < self.start_date:
            raise ValueError("end_date must not precede start_date")
        if (self.end_date - self.start_date).days + 1 > 366:
            raise ValueError("date range must not exceed 366 inclusive days")
        if self.utc_end_exclusive <= self.utc_start:
            raise ValueError("utc_end_exclusive must follow utc_start")
        return self


class DatasetGenerator(StrictRequestModel):
    name: Literal["smartfactory-dss-laravel"]
    version: Annotated[
        str,
        StringConstraints(
            strip_whitespace=True,
            pattern=r"^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$",
        ),
    ]


class DatasetFileManifest(StrictRequestModel):
    name: DatasetName
    file: Annotated[
        str,
        StringConstraints(
            strip_whitespace=True,
            min_length=1,
            max_length=200,
            pattern=r"^data/[a-z0-9_]+\.csv$",
        ),
    ]
    schema_version: Literal["v1"]
    row_count: int = Field(ge=0, le=1_000_000_000)
    byte_size: int = Field(ge=1, le=10_737_418_240)
    sha256: Sha256Text
    columns: Annotated[
        list[
            Annotated[
                str,
                StringConstraints(
                    strip_whitespace=True,
                    min_length=1,
                    max_length=100,
                    pattern=r"^[a-z][a-z0-9_]*$",
                ),
            ]
        ],
        Field(min_length=1, max_length=200),
    ]

    @model_validator(mode="after")
    def validate_registered_schema(self) -> DatasetFileManifest:
        expected_file = f"data/{self.name}.csv"
        if self.file != expected_file:
            raise ValueError("dataset file does not match its registered name")

        expected_columns = DATASET_COLUMNS[self.name]
        if self.columns != expected_columns:
            raise ValueError("dataset columns do not match the registered v1 schema")

        return self


class DatasetSnapshotManifest(StrictRequestModel):
    manifest_version: Literal["v1"]
    dataset_contract: Literal["smartfactory.ml.dataset.snapshot"]
    dataset_schema_version: Literal["v1"]
    snapshot_id: UUID
    source_application: Literal["smartfactory-dss-laravel"]
    source_system: SourceSystem
    data_classification: Literal["simulated_prototype"]
    generated_at: datetime
    period: DatasetPeriod
    generator: DatasetGenerator
    total_rows: int = Field(ge=0, le=7_000_000_000)
    datasets: Annotated[
        list[DatasetFileManifest],
        Field(min_length=1, max_length=7),
    ]
    content_fingerprint: Sha256Text

    @field_validator("generated_at")
    @classmethod
    def require_aware_generated_at(cls, value: datetime) -> datetime:
        if value.tzinfo is None or value.utcoffset() is None:
            raise ValueError("generated_at must include an explicit timezone offset")
        return value

    @model_validator(mode="after")
    def validate_manifest(self) -> DatasetSnapshotManifest:
        if self.manifest_version != MANIFEST_VERSION:
            raise ValueError("unsupported manifest version")
        if self.dataset_contract != DATASET_CONTRACT:
            raise ValueError("unsupported dataset contract")
        if self.dataset_schema_version != DATASET_SCHEMA_VERSION:
            raise ValueError("unsupported dataset schema version")
        if self.source_application != SOURCE_APPLICATION:
            raise ValueError("unsupported source application")
        if self.data_classification != DATA_CLASSIFICATION:
            raise ValueError("unsupported data classification")

        names = [item.name for item in self.datasets]
        if len(names) != len(set(names)):
            raise ValueError("dataset names must be unique")
        if self.total_rows != sum(item.row_count for item in self.datasets):
            raise ValueError("total_rows must equal the dataset row total")

        return self

from __future__ import annotations

import csv
import hashlib
import json
import os
import shutil
from collections import Counter
from dataclasses import dataclass, field
from datetime import UTC, datetime
from decimal import Decimal, InvalidOperation
from pathlib import Path
from typing import Any
from uuid import uuid4

from app.datasets.schema import DATASET_COLUMNS
from app.datasets.validator import DatasetSnapshotValidator
from app.preprocessing.normalization import NormalizationIssue, normalize_row
from app.preprocessing.schema import (
    PREPROCESSING_CONTRACT,
    PREPROCESSING_DATA_CLASSIFICATION,
    PREPROCESSING_MANIFEST_VERSION,
    PREPROCESSING_RULESET_VERSION,
    dataset_rules,
)


class DatasetPreprocessingError(Exception):
    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True, slots=True)
class PreprocessingReceipt:
    run_id: str
    source_snapshot_id: str
    source_content_fingerprint: str
    quality_status: str
    input_rows: int
    output_rows: int
    rejected_rows: int
    duplicate_rows: int
    run_path: str
    content_fingerprint: str

    def to_dict(self) -> dict[str, int | str]:
        return {
            "status": "preprocessed",
            "run_id": self.run_id,
            "source_snapshot_id": self.source_snapshot_id,
            "source_content_fingerprint": self.source_content_fingerprint,
            "quality_status": self.quality_status,
            "input_rows": self.input_rows,
            "output_rows": self.output_rows,
            "rejected_rows": self.rejected_rows,
            "duplicate_rows": self.duplicate_rows,
            "run_path": self.run_path,
            "content_fingerprint": self.content_fingerprint,
        }


@dataclass(slots=True)
class ColumnStatistics:
    kind: str
    required: bool
    input_blank_count: int = 0
    output_blank_count: int = 0
    invalid_count: int = 0
    unique_hashes: set[str] = field(default_factory=set)
    unique_tracking_capped: bool = False
    minimum: str | None = None
    maximum: str | None = None

    def observe_input(self, value: str | None) -> None:
        if value is None or not str(value).strip():
            self.input_blank_count += 1

    def observe_output(
        self,
        value: str,
        *,
        maximum_unique_values: int,
    ) -> None:
        if not value:
            self.output_blank_count += 1
            return

        if not self.unique_tracking_capped:
            digest = hashlib.sha256(value.encode("utf-8")).hexdigest()
            self.unique_hashes.add(digest)
            if len(self.unique_hashes) > maximum_unique_values:
                self.unique_hashes.clear()
                self.unique_tracking_capped = True

        if self.kind in {"integer", "decimal"}:
            self._observe_decimal(value)
        elif self.kind in {"date", "datetime"}:
            self._observe_ordered_text(value)

    def _observe_decimal(self, value: str) -> None:
        try:
            parsed = Decimal(value)
        except InvalidOperation:
            return

        if self.minimum is None or parsed < Decimal(self.minimum):
            self.minimum = value
        if self.maximum is None or parsed > Decimal(self.maximum):
            self.maximum = value

    def _observe_ordered_text(self, value: str) -> None:
        if self.minimum is None or value < self.minimum:
            self.minimum = value
        if self.maximum is None or value > self.maximum:
            self.maximum = value

    def to_dict(self) -> dict[str, Any]:
        return {
            "kind": self.kind,
            "required": self.required,
            "input_blank_count": self.input_blank_count,
            "output_blank_count": self.output_blank_count,
            "invalid_count": self.invalid_count,
            "unique_non_blank": (None if self.unique_tracking_capped else len(self.unique_hashes)),
            "unique_tracking_capped": self.unique_tracking_capped,
            "minimum": self.minimum,
            "maximum": self.maximum,
        }


@dataclass(slots=True)
class DatasetProcessingResult:
    name: str
    file: str
    issues_file: str
    columns: list[str]
    input_rows: int
    output_rows: int
    rejected_rows: int
    duplicate_rows: int
    warning_count: int
    issue_count: int
    stored_issue_count: int
    omitted_issue_count: int
    byte_size: int
    sha256: str
    issues_byte_size: int
    issues_sha256: str
    column_statistics: dict[str, ColumnStatistics]
    issue_summary: Counter[str]

    def manifest_dict(self) -> dict[str, Any]:
        return {
            "name": self.name,
            "file": self.file,
            "schema_version": "v1",
            "input_row_count": self.input_rows,
            "row_count": self.output_rows,
            "rejected_row_count": self.rejected_rows,
            "duplicate_row_count": self.duplicate_rows,
            "warning_count": self.warning_count,
            "byte_size": self.byte_size,
            "sha256": self.sha256,
            "columns": self.columns,
        }

    def issue_manifest_dict(self) -> dict[str, Any]:
        return {
            "dataset": self.name,
            "file": self.issues_file,
            "issue_count": self.issue_count,
            "stored_issue_count": self.stored_issue_count,
            "omitted_issue_count": self.omitted_issue_count,
            "byte_size": self.issues_byte_size,
            "sha256": self.issues_sha256,
        }

    def quality_dict(self) -> dict[str, Any]:
        return {
            "name": self.name,
            "input_row_count": self.input_rows,
            "output_row_count": self.output_rows,
            "rejected_row_count": self.rejected_rows,
            "duplicate_row_count": self.duplicate_rows,
            "warning_count": self.warning_count,
            "issue_summary": dict(sorted(self.issue_summary.items())),
            "columns": {
                name: statistics.to_dict() for name, statistics in self.column_statistics.items()
            },
        }


class DatasetPreprocessingPipeline:
    def __init__(
        self,
        *,
        maximum_stored_issues: int = 10_000,
        maximum_unique_values: int = 50_000,
        fail_on_rejected: bool = False,
    ) -> None:
        if maximum_stored_issues < 0:
            raise ValueError("maximum_stored_issues must not be negative")
        if maximum_unique_values < 100:
            raise ValueError("maximum_unique_values must be at least 100")

        self.maximum_stored_issues = maximum_stored_issues
        self.maximum_unique_values = maximum_unique_values
        self.fail_on_rejected = fail_on_rejected

    def run(
        self,
        snapshot_directory: str | Path,
        output_root: str | Path,
    ) -> PreprocessingReceipt:
        source_root = self._resolve_directory(snapshot_directory, "snapshot_not_found")
        output = self._resolve_output_root(output_root, source_root)
        source_receipt = DatasetSnapshotValidator().validate(source_root)
        source_manifest = self._load_source_manifest(source_root)

        run_id = str(uuid4())
        staging_root = output / ".staging" / run_id
        final_root = output / "runs" / run_id
        lock_path = output / ".preprocessing.lock"

        self._acquire_lock(lock_path)
        try:
            staging_root.mkdir(parents=True, exist_ok=False)
            (staging_root / "data").mkdir()
            (staging_root / "issues").mkdir()

            results = [
                self._process_dataset(
                    source_root=source_root,
                    staging_root=staging_root,
                    dataset=dataset,
                )
                for dataset in source_manifest["datasets"]
            ]

            if any(result.input_rows > 0 and result.output_rows == 0 for result in results):
                raise DatasetPreprocessingError(
                    "dataset_fully_rejected",
                    "At least one non-empty dataset had no valid rows after preprocessing.",
                )

            rejected_rows = sum(result.rejected_rows for result in results)
            if self.fail_on_rejected and rejected_rows > 0:
                raise DatasetPreprocessingError(
                    "rejected_rows_present",
                    "Rejected rows were found while strict preprocessing was enabled.",
                )

            generated_at = datetime.now(UTC).isoformat().replace("+00:00", "Z")
            quality_report = self._quality_report(
                run_id=run_id,
                generated_at=generated_at,
                source_manifest=source_manifest,
                results=results,
            )
            quality_path = staging_root / "quality-report.json"
            self._write_json(quality_path, quality_report)

            quality_hash = self._sha256(quality_path)
            manifest = self._preprocessing_manifest(
                run_id=run_id,
                generated_at=generated_at,
                source_manifest=source_manifest,
                results=results,
                quality_path=quality_path,
                quality_hash=quality_hash,
            )
            manifest_path = staging_root / "manifest.json"
            self._write_json(manifest_path, manifest)
            manifest_hash = self._sha256(manifest_path)
            (staging_root / "manifest.sha256").write_text(
                f"{manifest_hash}  manifest.json\n",
                encoding="ascii",
                newline="\n",
            )

            from app.preprocessing.validator import PreprocessedRunValidator

            PreprocessedRunValidator().validate(staging_root)

            final_root.parent.mkdir(parents=True, exist_ok=True)
            if final_root.exists():
                raise DatasetPreprocessingError(
                    "run_already_exists",
                    "The preprocessing run identifier already exists.",
                )
            os.replace(staging_root, final_root)
            self._publish_latest_pointer(output, run_id)

            input_rows = sum(result.input_rows for result in results)
            output_rows = sum(result.output_rows for result in results)
            duplicate_rows = sum(result.duplicate_rows for result in results)
            quality_status = quality_report["quality_status"]

            return PreprocessingReceipt(
                run_id=run_id,
                source_snapshot_id=source_receipt.snapshot_id,
                source_content_fingerprint=source_receipt.content_fingerprint,
                quality_status=quality_status,
                input_rows=input_rows,
                output_rows=output_rows,
                rejected_rows=rejected_rows,
                duplicate_rows=duplicate_rows,
                run_path=str(final_root),
                content_fingerprint=manifest["content_fingerprint"],
            )
        except Exception:
            shutil.rmtree(staging_root, ignore_errors=True)
            raise
        finally:
            self._release_lock(lock_path)

    def _process_dataset(
        self,
        *,
        source_root: Path,
        staging_root: Path,
        dataset: dict[str, Any],
    ) -> DatasetProcessingResult:
        name = str(dataset["name"])
        columns = DATASET_COLUMNS[name]
        source_path = source_root / str(dataset["file"])
        output_relative = f"data/{name}.csv"
        issues_relative = f"issues/{name}.jsonl"
        output_path = staging_root / output_relative
        issues_path = staging_root / issues_relative
        rules = dataset_rules(name)
        statistics = {
            column: ColumnStatistics(
                kind=rules[column].kind,
                required=rules[column].required,
            )
            for column in columns
        }
        issue_summary: Counter[str] = Counter()
        seen_rows: set[str] = set()
        input_rows = 0
        output_rows = 0
        rejected_rows = 0
        duplicate_rows = 0
        warning_count = 0
        issue_count = 0
        stored_issue_count = 0

        with (
            source_path.open("r", encoding="utf-8", newline="") as source_handle,
            output_path.open("w", encoding="utf-8", newline="") as output_handle,
            issues_path.open("w", encoding="utf-8", newline="\n") as issues_handle,
        ):
            reader = csv.DictReader(source_handle, strict=True)
            if reader.fieldnames != columns:
                raise DatasetPreprocessingError(
                    "source_header_mismatch",
                    "A source dataset header changed after snapshot validation.",
                )

            writer = csv.DictWriter(
                output_handle,
                fieldnames=columns,
                lineterminator="\n",
                extrasaction="raise",
            )
            writer.writeheader()

            for row_number, row in enumerate(reader, start=2):
                input_rows += 1
                for column in columns:
                    statistics[column].observe_input(row.get(column))

                normalized, row_issues = normalize_row(name, row)
                self._record_invalid_columns(statistics, row_issues)

                errors = [issue for issue in row_issues if issue.severity == "error"]
                warnings = [issue for issue in row_issues if issue.severity == "warning"]

                issue_count, stored_issue_count = self._store_issues(
                    issues_handle=issues_handle,
                    row_number=row_number,
                    issues=row_issues,
                    issue_summary=issue_summary,
                    issue_count=issue_count,
                    stored_issue_count=stored_issue_count,
                )
                warning_count += len(warnings)

                if errors:
                    rejected_rows += 1
                    continue

                row_key = hashlib.sha256(
                    "\x1f".join(normalized[column] for column in columns).encode("utf-8")
                ).hexdigest()
                if row_key in seen_rows:
                    duplicate_rows += 1
                    duplicate_issue = NormalizationIssue(
                        severity="warning",
                        code="duplicate_row_removed",
                        field=None,
                        message="An exact normalized duplicate row was removed.",
                    )
                    issue_count, stored_issue_count = self._store_issues(
                        issues_handle=issues_handle,
                        row_number=row_number,
                        issues=[duplicate_issue],
                        issue_summary=issue_summary,
                        issue_count=issue_count,
                        stored_issue_count=stored_issue_count,
                    )
                    warning_count += 1
                    continue

                seen_rows.add(row_key)
                writer.writerow(normalized)
                output_rows += 1
                for column in columns:
                    statistics[column].observe_output(
                        normalized[column],
                        maximum_unique_values=self.maximum_unique_values,
                    )

        return DatasetProcessingResult(
            name=name,
            file=output_relative,
            issues_file=issues_relative,
            columns=columns,
            input_rows=input_rows,
            output_rows=output_rows,
            rejected_rows=rejected_rows,
            duplicate_rows=duplicate_rows,
            warning_count=warning_count,
            issue_count=issue_count,
            stored_issue_count=stored_issue_count,
            omitted_issue_count=max(0, issue_count - stored_issue_count),
            byte_size=output_path.stat().st_size,
            sha256=self._sha256(output_path),
            issues_byte_size=issues_path.stat().st_size,
            issues_sha256=self._sha256(issues_path),
            column_statistics=statistics,
            issue_summary=issue_summary,
        )

    def _store_issues(
        self,
        *,
        issues_handle,
        row_number: int,
        issues: list[NormalizationIssue],
        issue_summary: Counter[str],
        issue_count: int,
        stored_issue_count: int,
    ) -> tuple[int, int]:
        for issue in issues:
            issue_count += 1
            issue_summary[issue.code] += 1
            if stored_issue_count >= self.maximum_stored_issues:
                continue
            issues_handle.write(
                json.dumps(
                    issue.to_dict(row_number=row_number),
                    ensure_ascii=False,
                    separators=(",", ":"),
                )
                + "\n"
            )
            stored_issue_count += 1
        return issue_count, stored_issue_count

    @staticmethod
    def _record_invalid_columns(
        statistics: dict[str, ColumnStatistics],
        issues: list[NormalizationIssue],
    ) -> None:
        for issue in issues:
            if issue.severity == "error" and issue.field in statistics:
                statistics[issue.field].invalid_count += 1

    def _quality_report(
        self,
        *,
        run_id: str,
        generated_at: str,
        source_manifest: dict[str, Any],
        results: list[DatasetProcessingResult],
    ) -> dict[str, Any]:
        rejected = sum(result.rejected_rows for result in results)
        duplicates = sum(result.duplicate_rows for result in results)
        warnings = sum(result.warning_count for result in results)
        quality_status = "passed"
        if rejected or duplicates or warnings:
            quality_status = "passed_with_warnings"

        return {
            "report_contract": "smartfactory.ml.data-quality-report",
            "report_version": "v1",
            "run_id": run_id,
            "ruleset_version": PREPROCESSING_RULESET_VERSION,
            "generated_at": generated_at,
            "source_snapshot_id": source_manifest["snapshot_id"],
            "source_content_fingerprint": source_manifest["content_fingerprint"],
            "source_system": source_manifest["source_system"],
            "data_classification": source_manifest["data_classification"],
            "quality_status": quality_status,
            "summary": {
                "input_row_count": sum(result.input_rows for result in results),
                "output_row_count": sum(result.output_rows for result in results),
                "rejected_row_count": rejected,
                "duplicate_row_count": duplicates,
                "warning_count": warnings,
                "stored_issue_count": sum(result.stored_issue_count for result in results),
                "omitted_issue_count": sum(result.omitted_issue_count for result in results),
            },
            "policies": {
                "missing_required_values": "reject_row",
                "missing_optional_values": "preserve_blank",
                "invalid_types": "reject_row",
                "exact_normalized_duplicates": "remove_later_occurrence",
                "numeric_imputation": "not_performed",
                "categorical_imputation": "not_performed",
                "outlier_removal": "not_performed",
                "target_feature_engineering": "not_performed",
                "raw_values_in_report": False,
            },
            "datasets": [result.quality_dict() for result in results],
        }

    def _preprocessing_manifest(
        self,
        *,
        run_id: str,
        generated_at: str,
        source_manifest: dict[str, Any],
        results: list[DatasetProcessingResult],
        quality_path: Path,
        quality_hash: str,
    ) -> dict[str, Any]:
        datasets = [result.manifest_dict() for result in results]
        issue_files = [result.issue_manifest_dict() for result in results]
        content_fingerprint = self._content_fingerprint(
            source_manifest=source_manifest,
            results=results,
            quality_hash=quality_hash,
        )

        return {
            "manifest_version": PREPROCESSING_MANIFEST_VERSION,
            "preprocessing_contract": PREPROCESSING_CONTRACT,
            "ruleset_version": PREPROCESSING_RULESET_VERSION,
            "run_id": run_id,
            "generated_at": generated_at,
            "source_snapshot": {
                "snapshot_id": source_manifest["snapshot_id"],
                "content_fingerprint": source_manifest["content_fingerprint"],
                "dataset_contract": source_manifest["dataset_contract"],
                "dataset_schema_version": source_manifest["dataset_schema_version"],
                "period": source_manifest["period"],
            },
            "source_system": source_manifest["source_system"],
            "data_classification": source_manifest["data_classification"],
            "input_row_count": sum(result.input_rows for result in results),
            "total_rows": sum(result.output_rows for result in results),
            "rejected_row_count": sum(result.rejected_rows for result in results),
            "duplicate_row_count": sum(result.duplicate_rows for result in results),
            "datasets": datasets,
            "issue_files": issue_files,
            "quality_report": {
                "file": "quality-report.json",
                "byte_size": quality_path.stat().st_size,
                "sha256": quality_hash,
            },
            "content_fingerprint": content_fingerprint,
        }

    @staticmethod
    def _content_fingerprint(
        *,
        source_manifest: dict[str, Any],
        results: list[DatasetProcessingResult],
        quality_hash: str,
    ) -> str:
        lines = [
            PREPROCESSING_CONTRACT,
            PREPROCESSING_RULESET_VERSION,
            str(source_manifest["snapshot_id"]),
            str(source_manifest["content_fingerprint"]),
            str(source_manifest["source_system"]),
            PREPROCESSING_DATA_CLASSIFICATION,
            quality_hash,
        ]
        for result in sorted(results, key=lambda item: item.name):
            lines.append(
                "|".join(
                    [
                        result.name,
                        result.sha256,
                        str(result.output_rows),
                        str(result.byte_size),
                        result.issues_sha256,
                    ]
                )
            )
        return hashlib.sha256("\n".join(lines).encode("utf-8")).hexdigest()

    @staticmethod
    def _load_source_manifest(root: Path) -> dict[str, Any]:
        try:
            return json.loads((root / "manifest.json").read_text(encoding="utf-8"))
        except (OSError, UnicodeError, json.JSONDecodeError) as exception:
            raise DatasetPreprocessingError(
                "source_manifest_unreadable",
                "The source dataset manifest could not be read.",
            ) from exception

    @staticmethod
    def _resolve_directory(value: str | Path, code: str) -> Path:
        try:
            path = Path(value).expanduser().resolve(strict=True)
        except (OSError, FileNotFoundError) as exception:
            raise DatasetPreprocessingError(
                code,
                "The requested directory does not exist.",
            ) from exception
        if not path.is_dir():
            raise DatasetPreprocessingError(code, "The requested path is not a directory.")
        return path

    @staticmethod
    def _resolve_output_root(value: str | Path, source_root: Path) -> Path:
        path = Path(value).expanduser()
        path.mkdir(parents=True, exist_ok=True)
        resolved = path.resolve(strict=True)
        try:
            resolved.relative_to(source_root)
        except ValueError:
            return resolved
        raise DatasetPreprocessingError(
            "unsafe_output_root",
            "The preprocessing output root must not be inside the source snapshot.",
        )

    @staticmethod
    def _acquire_lock(path: Path) -> None:
        try:
            descriptor = os.open(path, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        except FileExistsError as exception:
            raise DatasetPreprocessingError(
                "preprocessing_locked",
                "Another preprocessing run appears to be active.",
            ) from exception
        with os.fdopen(descriptor, "w", encoding="ascii", newline="\n") as handle:
            handle.write(f"pid={os.getpid()}\n")

    @staticmethod
    def _release_lock(path: Path) -> None:
        try:
            path.unlink(missing_ok=True)
        except OSError:
            pass

    @staticmethod
    def _publish_latest_pointer(output_root: Path, run_id: str) -> None:
        temporary = output_root / ".PREPROCESSED_LATEST.tmp"
        latest = output_root / "PREPROCESSED_LATEST"
        temporary.write_text(run_id + "\n", encoding="ascii", newline="\n")
        os.replace(temporary, latest)

    @staticmethod
    def _write_json(path: Path, payload: dict[str, Any]) -> None:
        path.write_text(
            json.dumps(payload, ensure_ascii=False, indent=2, sort_keys=True) + "\n",
            encoding="utf-8",
            newline="\n",
        )

    @staticmethod
    def _sha256(path: Path) -> str:
        digest = hashlib.sha256()
        with path.open("rb") as handle:
            for chunk in iter(lambda: handle.read(1_048_576), b""):
                digest.update(chunk)
        return digest.hexdigest()

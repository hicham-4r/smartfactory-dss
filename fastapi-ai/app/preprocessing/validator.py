from __future__ import annotations

import csv
import hashlib
import hmac
import json
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any
from uuid import UUID

from app.datasets.schema import DATASET_COLUMNS
from app.preprocessing.schema import (
    PREPROCESSING_CONTRACT,
    PREPROCESSING_DATA_CLASSIFICATION,
    PREPROCESSING_MANIFEST_VERSION,
    PREPROCESSING_RULESET_VERSION,
)


class PreprocessedRunValidationError(Exception):
    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True, slots=True)
class PreprocessedRunReceipt:
    run_id: str
    source_snapshot_id: str
    quality_status: str
    total_rows: int
    datasets: tuple[str, ...]
    content_fingerprint: str

    def to_dict(self) -> dict[str, Any]:
        return {
            "status": "valid",
            "run_id": self.run_id,
            "source_snapshot_id": self.source_snapshot_id,
            "quality_status": self.quality_status,
            "total_rows": self.total_rows,
            "datasets": list(self.datasets),
            "content_fingerprint": self.content_fingerprint,
        }


class PreprocessedRunValidator:
    def __init__(
        self,
        *,
        manifest_max_bytes: int = 1_048_576,
        file_max_bytes: int = 536_870_912,
        max_rows_per_file: int = 1_000_000,
    ) -> None:
        if manifest_max_bytes < 1_024:
            raise ValueError("manifest_max_bytes must be at least 1024")
        if file_max_bytes < 1_024:
            raise ValueError("file_max_bytes must be at least 1024")
        if max_rows_per_file < 1:
            raise ValueError("max_rows_per_file must be positive")
        self.manifest_max_bytes = manifest_max_bytes
        self.file_max_bytes = file_max_bytes
        self.max_rows_per_file = max_rows_per_file

    def validate(self, run_directory: str | Path) -> PreprocessedRunReceipt:
        root = self._run_root(run_directory)
        manifest_bytes = self._read_file(
            root / "manifest.json",
            maximum_bytes=self.manifest_max_bytes,
            missing_code="manifest_not_found",
        )
        self._verify_checksum_file(
            root / "manifest.sha256",
            expected_filename="manifest.json",
            payload=manifest_bytes,
        )
        manifest = self._parse_json(manifest_bytes, "invalid_manifest")
        self._validate_manifest_shape(manifest)

        dataset_names: list[str] = []
        for dataset in manifest["datasets"]:
            self._validate_dataset(root, dataset)
            dataset_names.append(dataset["name"])

        issue_names: list[str] = []
        for issue_file in manifest["issue_files"]:
            self._validate_issue_file(root, issue_file)
            issue_names.append(issue_file["dataset"])

        if sorted(dataset_names) != sorted(issue_names):
            raise PreprocessedRunValidationError(
                "issue_dataset_mismatch",
                "Issue files do not match the preprocessed datasets.",
            )

        quality_report = self._validate_quality_report(root, manifest)
        self._validate_totals(manifest)
        self._validate_fingerprint(manifest)

        return PreprocessedRunReceipt(
            run_id=manifest["run_id"],
            source_snapshot_id=manifest["source_snapshot"]["snapshot_id"],
            quality_status=quality_report["quality_status"],
            total_rows=manifest["total_rows"],
            datasets=tuple(dataset_names),
            content_fingerprint=manifest["content_fingerprint"],
        )

    def _validate_manifest_shape(self, manifest: dict[str, Any]) -> None:
        expected_keys = {
            "manifest_version",
            "preprocessing_contract",
            "ruleset_version",
            "run_id",
            "generated_at",
            "source_snapshot",
            "source_system",
            "data_classification",
            "input_row_count",
            "total_rows",
            "rejected_row_count",
            "duplicate_row_count",
            "datasets",
            "issue_files",
            "quality_report",
            "content_fingerprint",
        }
        if set(manifest) != expected_keys:
            raise PreprocessedRunValidationError(
                "invalid_manifest",
                "The preprocessing manifest fields are invalid.",
            )
        if manifest["manifest_version"] != PREPROCESSING_MANIFEST_VERSION:
            self._invalid_manifest()
        if manifest["preprocessing_contract"] != PREPROCESSING_CONTRACT:
            self._invalid_manifest()
        if manifest["ruleset_version"] != PREPROCESSING_RULESET_VERSION:
            self._invalid_manifest()
        if manifest["data_classification"] != PREPROCESSING_DATA_CLASSIFICATION:
            self._invalid_manifest()
        self._require_uuid(manifest["run_id"])
        self._require_aware_datetime(manifest["generated_at"])
        self._require_sha256(manifest["content_fingerprint"])

        source = manifest["source_snapshot"]
        if not isinstance(source, dict):
            self._invalid_manifest()
        required_source = {
            "snapshot_id",
            "content_fingerprint",
            "dataset_contract",
            "dataset_schema_version",
            "period",
        }
        if set(source) != required_source:
            self._invalid_manifest()
        self._require_uuid(source["snapshot_id"])
        self._require_sha256(source["content_fingerprint"])
        if source["dataset_contract"] != "smartfactory.ml.dataset.snapshot":
            self._invalid_manifest()
        if source["dataset_schema_version"] != "v1":
            self._invalid_manifest()

        datasets = manifest["datasets"]
        issues = manifest["issue_files"]
        if not isinstance(datasets, list) or not 1 <= len(datasets) <= 7:
            self._invalid_manifest()
        if not isinstance(issues, list) or len(issues) != len(datasets):
            self._invalid_manifest()

        names = [item.get("name") for item in datasets if isinstance(item, dict)]
        if len(names) != len(datasets) or len(names) != len(set(names)):
            self._invalid_manifest()
        if any(name not in DATASET_COLUMNS for name in names):
            self._invalid_manifest()

        for key in (
            "input_row_count",
            "total_rows",
            "rejected_row_count",
            "duplicate_row_count",
        ):
            self._require_non_negative_integer(manifest[key])

    def _validate_dataset(self, root: Path, dataset: dict[str, Any]) -> None:
        expected_keys = {
            "name",
            "file",
            "schema_version",
            "input_row_count",
            "row_count",
            "rejected_row_count",
            "duplicate_row_count",
            "warning_count",
            "byte_size",
            "sha256",
            "columns",
        }
        if not isinstance(dataset, dict) or set(dataset) != expected_keys:
            self._invalid_manifest()
        name = dataset["name"]
        if dataset["file"] != f"data/{name}.csv":
            self._invalid_manifest()
        if dataset["schema_version"] != "v1":
            self._invalid_manifest()
        if dataset["columns"] != DATASET_COLUMNS[name]:
            self._invalid_manifest()
        for key in (
            "input_row_count",
            "row_count",
            "rejected_row_count",
            "duplicate_row_count",
            "warning_count",
            "byte_size",
        ):
            self._require_non_negative_integer(dataset[key])
        if dataset["byte_size"] < 1:
            self._invalid_manifest()
        if dataset["input_row_count"] != (
            dataset["row_count"] + dataset["rejected_row_count"] + dataset["duplicate_row_count"]
        ):
            self._invalid_manifest()
        self._require_sha256(dataset["sha256"])

        path = self._safe_file(root, dataset["file"])
        self._verify_file_metadata(path, dataset["byte_size"], dataset["sha256"])
        row_count = self._count_csv_rows(path, dataset["columns"])
        if row_count != dataset["row_count"]:
            raise PreprocessedRunValidationError(
                "dataset_row_count_mismatch",
                "A preprocessed dataset row count does not match its manifest.",
            )

    def _validate_issue_file(self, root: Path, issue_file: dict[str, Any]) -> None:
        expected_keys = {
            "dataset",
            "file",
            "issue_count",
            "stored_issue_count",
            "omitted_issue_count",
            "byte_size",
            "sha256",
        }
        if not isinstance(issue_file, dict) or set(issue_file) != expected_keys:
            self._invalid_manifest()
        dataset = issue_file["dataset"]
        if dataset not in DATASET_COLUMNS:
            self._invalid_manifest()
        if issue_file["file"] != f"issues/{dataset}.jsonl":
            self._invalid_manifest()
        for key in (
            "issue_count",
            "stored_issue_count",
            "omitted_issue_count",
            "byte_size",
        ):
            self._require_non_negative_integer(issue_file[key])
        if issue_file["issue_count"] != (
            issue_file["stored_issue_count"] + issue_file["omitted_issue_count"]
        ):
            self._invalid_manifest()
        self._require_sha256(issue_file["sha256"])

        path = self._safe_file(root, issue_file["file"])
        self._verify_file_metadata(path, issue_file["byte_size"], issue_file["sha256"])
        line_count = self._validate_issue_lines(path)
        if line_count != issue_file["stored_issue_count"]:
            raise PreprocessedRunValidationError(
                "issue_count_mismatch",
                "An issue file line count does not match its manifest.",
            )

    def _validate_quality_report(
        self,
        root: Path,
        manifest: dict[str, Any],
    ) -> dict[str, Any]:
        quality = manifest["quality_report"]
        if not isinstance(quality, dict) or set(quality) != {
            "file",
            "byte_size",
            "sha256",
        }:
            self._invalid_manifest()
        if quality["file"] != "quality-report.json":
            self._invalid_manifest()
        self._require_non_negative_integer(quality["byte_size"])
        self._require_sha256(quality["sha256"])
        path = self._safe_file(root, quality["file"])
        self._verify_file_metadata(path, quality["byte_size"], quality["sha256"])
        report = self._parse_json(path.read_bytes(), "invalid_quality_report")

        required = {
            "report_contract",
            "report_version",
            "run_id",
            "ruleset_version",
            "generated_at",
            "source_snapshot_id",
            "source_content_fingerprint",
            "source_system",
            "data_classification",
            "quality_status",
            "summary",
            "policies",
            "datasets",
        }
        if set(report) != required:
            raise PreprocessedRunValidationError(
                "invalid_quality_report",
                "The quality report fields are invalid.",
            )
        if report["report_contract"] != "smartfactory.ml.data-quality-report":
            raise PreprocessedRunValidationError(
                "invalid_quality_report",
                "The quality report contract is invalid.",
            )
        if report["report_version"] != "v1":
            raise PreprocessedRunValidationError(
                "invalid_quality_report",
                "The quality report version is invalid.",
            )
        if report["run_id"] != manifest["run_id"]:
            raise PreprocessedRunValidationError(
                "quality_report_mismatch",
                "The quality report run identifier does not match.",
            )
        if report["source_snapshot_id"] != manifest["source_snapshot"]["snapshot_id"]:
            raise PreprocessedRunValidationError(
                "quality_report_mismatch",
                "The quality report source snapshot does not match.",
            )
        if report["data_classification"] != PREPROCESSING_DATA_CLASSIFICATION:
            raise PreprocessedRunValidationError(
                "quality_report_mismatch",
                "The quality report classification does not match.",
            )
        if report["quality_status"] not in {"passed", "passed_with_warnings"}:
            raise PreprocessedRunValidationError(
                "invalid_quality_report",
                "The quality report status is invalid.",
            )
        if report["policies"].get("raw_values_in_report") is not False:
            raise PreprocessedRunValidationError(
                "invalid_quality_report",
                "The quality report privacy policy is invalid.",
            )
        return report

    @staticmethod
    def _validate_totals(manifest: dict[str, Any]) -> None:
        datasets = manifest["datasets"]
        if manifest["input_row_count"] != sum(item["input_row_count"] for item in datasets):
            PreprocessedRunValidator._invalid_manifest()
        if manifest["total_rows"] != sum(item["row_count"] for item in datasets):
            PreprocessedRunValidator._invalid_manifest()
        if manifest["rejected_row_count"] != sum(item["rejected_row_count"] for item in datasets):
            PreprocessedRunValidator._invalid_manifest()
        if manifest["duplicate_row_count"] != sum(item["duplicate_row_count"] for item in datasets):
            PreprocessedRunValidator._invalid_manifest()

    def _validate_fingerprint(self, manifest: dict[str, Any]) -> None:
        lines = [
            PREPROCESSING_CONTRACT,
            PREPROCESSING_RULESET_VERSION,
            str(manifest["source_snapshot"]["snapshot_id"]),
            str(manifest["source_snapshot"]["content_fingerprint"]),
            str(manifest["source_system"]),
            PREPROCESSING_DATA_CLASSIFICATION,
            str(manifest["quality_report"]["sha256"]),
        ]
        issue_by_dataset = {item["dataset"]: item for item in manifest["issue_files"]}
        for dataset in sorted(manifest["datasets"], key=lambda item: item["name"]):
            issue = issue_by_dataset[dataset["name"]]
            lines.append(
                "|".join(
                    [
                        dataset["name"],
                        dataset["sha256"],
                        str(dataset["row_count"]),
                        str(dataset["byte_size"]),
                        issue["sha256"],
                    ]
                )
            )
        expected = hashlib.sha256("\n".join(lines).encode("utf-8")).hexdigest()
        if not hmac.compare_digest(expected, manifest["content_fingerprint"]):
            raise PreprocessedRunValidationError(
                "content_fingerprint_mismatch",
                "The preprocessing content fingerprint is invalid.",
            )

    def _count_csv_rows(self, path: Path, columns: list[str]) -> int:
        try:
            with path.open("r", encoding="utf-8", newline="") as handle:
                reader = csv.reader(handle, strict=True)
                header = next(reader)
                if header != columns:
                    raise PreprocessedRunValidationError(
                        "dataset_header_mismatch",
                        "A preprocessed dataset header does not match its manifest.",
                    )
                count = 0
                for row in reader:
                    count += 1
                    if count > self.max_rows_per_file:
                        raise PreprocessedRunValidationError(
                            "dataset_row_limit_exceeded",
                            "A preprocessed dataset exceeds the configured row limit.",
                        )
                    if len(row) != len(columns):
                        raise PreprocessedRunValidationError(
                            "dataset_column_count_mismatch",
                            "A preprocessed dataset row has an invalid column count.",
                        )
                return count
        except (OSError, UnicodeError, csv.Error, StopIteration) as exception:
            if isinstance(exception, PreprocessedRunValidationError):
                raise
            raise PreprocessedRunValidationError(
                "dataset_csv_invalid",
                "A preprocessed dataset CSV is invalid.",
            ) from exception

    @staticmethod
    def _validate_issue_lines(path: Path) -> int:
        count = 0
        try:
            with path.open("r", encoding="utf-8") as handle:
                for line in handle:
                    if not line.strip():
                        continue
                    payload = json.loads(line)
                    if set(payload) != {
                        "row_number",
                        "severity",
                        "code",
                        "field",
                        "message",
                    }:
                        raise ValueError("invalid issue fields")
                    if payload["severity"] not in {"error", "warning"}:
                        raise ValueError("invalid issue severity")
                    count += 1
        except (OSError, UnicodeError, json.JSONDecodeError, ValueError) as exception:
            raise PreprocessedRunValidationError(
                "issue_file_invalid",
                "A preprocessing issue file is invalid.",
            ) from exception
        return count

    def _verify_file_metadata(self, path: Path, byte_size: int, sha256: str) -> None:
        actual_size = path.stat().st_size
        if actual_size != byte_size:
            raise PreprocessedRunValidationError(
                "file_size_mismatch",
                "A preprocessing file size does not match its manifest.",
            )
        if actual_size > self.file_max_bytes:
            raise PreprocessedRunValidationError(
                "file_too_large",
                "A preprocessing file exceeds the configured size limit.",
            )
        actual_hash = self._sha256(path)
        if not hmac.compare_digest(actual_hash, sha256):
            raise PreprocessedRunValidationError(
                "file_checksum_mismatch",
                "A preprocessing file checksum does not match its manifest.",
            )

    @staticmethod
    def _safe_file(root: Path, relative: str) -> Path:
        candidate = (root / relative).resolve(strict=False)
        try:
            candidate.relative_to(root)
        except ValueError as exception:
            raise PreprocessedRunValidationError(
                "unsafe_file_path",
                "A preprocessing file path escapes the run directory.",
            ) from exception
        if not candidate.is_file():
            raise PreprocessedRunValidationError(
                "file_missing",
                "A declared preprocessing file is missing.",
            )
        return candidate

    @staticmethod
    def _run_root(value: str | Path) -> Path:
        try:
            root = Path(value).expanduser().resolve(strict=True)
        except (OSError, FileNotFoundError) as exception:
            raise PreprocessedRunValidationError(
                "run_not_found",
                "The preprocessing run directory does not exist.",
            ) from exception
        if not root.is_dir():
            raise PreprocessedRunValidationError(
                "run_not_directory",
                "The preprocessing run path is not a directory.",
            )
        return root

    @staticmethod
    def _read_file(path: Path, *, maximum_bytes: int, missing_code: str) -> bytes:
        try:
            size = path.stat().st_size
        except OSError as exception:
            raise PreprocessedRunValidationError(
                missing_code,
                "A required preprocessing file is missing.",
            ) from exception
        if size < 2 or size > maximum_bytes:
            raise PreprocessedRunValidationError(
                "file_size_invalid",
                "A required preprocessing file has an invalid size.",
            )
        try:
            return path.read_bytes()
        except OSError as exception:
            raise PreprocessedRunValidationError(
                "file_unreadable",
                "A required preprocessing file could not be read.",
            ) from exception

    @staticmethod
    def _verify_checksum_file(path: Path, *, expected_filename: str, payload: bytes) -> None:
        try:
            content = path.read_text(encoding="ascii").strip().split()
        except (OSError, UnicodeError) as exception:
            raise PreprocessedRunValidationError(
                "manifest_checksum_missing",
                "The preprocessing manifest checksum is missing.",
            ) from exception
        if len(content) != 2 or content[1] != expected_filename:
            raise PreprocessedRunValidationError(
                "manifest_checksum_invalid",
                "The preprocessing manifest checksum file is invalid.",
            )
        expected = content[0].lower()
        PreprocessedRunValidator._require_sha256(expected)
        actual = hashlib.sha256(payload).hexdigest()
        if not hmac.compare_digest(actual, expected):
            raise PreprocessedRunValidationError(
                "manifest_checksum_mismatch",
                "The preprocessing manifest checksum does not match.",
            )

    @staticmethod
    def _parse_json(payload: bytes, code: str) -> dict[str, Any]:
        try:
            parsed = json.loads(payload)
        except (UnicodeError, json.JSONDecodeError) as exception:
            raise PreprocessedRunValidationError(
                code,
                "A preprocessing JSON file is invalid.",
            ) from exception
        if not isinstance(parsed, dict):
            raise PreprocessedRunValidationError(
                code,
                "A preprocessing JSON file must contain an object.",
            )
        return parsed

    @staticmethod
    def _require_uuid(value: Any) -> None:
        if not isinstance(value, str):
            PreprocessedRunValidator._invalid_manifest()
        try:
            UUID(value)
        except ValueError:
            PreprocessedRunValidator._invalid_manifest()

    @staticmethod
    def _require_aware_datetime(value: Any) -> None:
        if not isinstance(value, str):
            PreprocessedRunValidator._invalid_manifest()
        try:
            parsed = datetime.fromisoformat(value.replace("Z", "+00:00"))
        except ValueError:
            PreprocessedRunValidator._invalid_manifest()
        if parsed.tzinfo is None or parsed.utcoffset() is None:
            PreprocessedRunValidator._invalid_manifest()

    @staticmethod
    def _require_sha256(value: Any) -> None:
        if (
            not isinstance(value, str)
            or len(value) != 64
            or any(character not in "0123456789abcdef" for character in value.lower())
        ):
            PreprocessedRunValidator._invalid_manifest()

    @staticmethod
    def _require_non_negative_integer(value: Any) -> None:
        if isinstance(value, bool) or not isinstance(value, int) or value < 0:
            PreprocessedRunValidator._invalid_manifest()

    @staticmethod
    def _invalid_manifest() -> None:
        raise PreprocessedRunValidationError(
            "invalid_manifest",
            "The preprocessing manifest does not match the supported contract.",
        )

    @staticmethod
    def _sha256(path: Path) -> str:
        digest = hashlib.sha256()
        with path.open("rb") as handle:
            for chunk in iter(lambda: handle.read(1_048_576), b""):
                digest.update(chunk)
        return digest.hexdigest()

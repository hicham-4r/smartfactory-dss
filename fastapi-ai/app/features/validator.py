from __future__ import annotations

import csv
import hashlib
import hmac
import json
from dataclasses import dataclass
from datetime import date, datetime
from pathlib import Path
from typing import Any
from uuid import UUID

from app.features.schema import (
    FEATURE_CONTRACT,
    FEATURE_DATA_CLASSIFICATION,
    FEATURE_MANIFEST_VERSION,
    FEATURE_RULESET_VERSION,
    FEATURE_SCHEMA_VERSION,
    FEATURE_TASKS,
)


class FeatureRunValidationError(Exception):
    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True, slots=True)
class FeatureRunReceipt:
    run_id: str
    source_preprocessed_run_id: str
    total_rows: int
    tasks: tuple[str, ...]
    content_fingerprint: str

    def to_dict(self) -> dict[str, Any]:
        return {
            "status": "valid",
            "run_id": self.run_id,
            "source_preprocessed_run_id": self.source_preprocessed_run_id,
            "total_rows": self.total_rows,
            "tasks": list(self.tasks),
            "content_fingerprint": self.content_fingerprint,
        }


class FeatureRunValidator:
    def __init__(
        self,
        *,
        manifest_max_bytes: int = 1_048_576,
        file_max_bytes: int = 536_870_912,
        max_rows_per_file: int = 1_000_000,
        max_cell_characters: int = 10_000,
    ) -> None:
        if manifest_max_bytes < 1_024:
            raise ValueError("manifest_max_bytes must be at least 1024")
        if file_max_bytes < 1_024:
            raise ValueError("file_max_bytes must be at least 1024")
        if max_rows_per_file < 1:
            raise ValueError("max_rows_per_file must be positive")
        if max_cell_characters < 1:
            raise ValueError("max_cell_characters must be positive")
        self.manifest_max_bytes = manifest_max_bytes
        self.file_max_bytes = file_max_bytes
        self.max_rows_per_file = max_rows_per_file
        self.max_cell_characters = max_cell_characters

    def validate(self, run_directory: str | Path) -> FeatureRunReceipt:
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
        manifest = self._parse_json(manifest_bytes)
        self._validate_manifest_shape(manifest)

        task_names: list[str] = []
        total_rows = 0
        for task in manifest["tasks"]:
            total_rows += self._validate_task(root, task)
            task_names.append(task["name"])

        if total_rows != manifest["total_rows"]:
            raise FeatureRunValidationError(
                "feature_total_mismatch",
                "The feature row total does not match the manifest.",
            )

        expected_fingerprint = self._fingerprint(manifest)
        if not hmac.compare_digest(
            expected_fingerprint,
            manifest["content_fingerprint"],
        ):
            raise FeatureRunValidationError(
                "content_fingerprint_mismatch",
                "The feature content fingerprint is invalid.",
            )

        return FeatureRunReceipt(
            run_id=manifest["run_id"],
            source_preprocessed_run_id=manifest["source_preprocessed_run"]["run_id"],
            total_rows=manifest["total_rows"],
            tasks=tuple(task_names),
            content_fingerprint=manifest["content_fingerprint"],
        )

    def _validate_manifest_shape(self, manifest: dict[str, Any]) -> None:
        expected_keys = {
            "manifest_version",
            "feature_contract",
            "ruleset_version",
            "run_id",
            "generated_at",
            "source_preprocessed_run",
            "source_system",
            "data_classification",
            "split_policy",
            "total_rows",
            "purged_row_count",
            "tasks",
            "content_fingerprint",
        }
        if set(manifest) != expected_keys:
            self._invalid_manifest()
        if manifest["manifest_version"] != FEATURE_MANIFEST_VERSION:
            self._invalid_manifest()
        if manifest["feature_contract"] != FEATURE_CONTRACT:
            self._invalid_manifest()
        if manifest["ruleset_version"] != FEATURE_RULESET_VERSION:
            self._invalid_manifest()
        if manifest["data_classification"] != FEATURE_DATA_CLASSIFICATION:
            self._invalid_manifest()
        self._require_uuid(manifest["run_id"])
        self._require_aware_datetime(manifest["generated_at"])
        self._require_non_negative_integer(manifest["total_rows"])
        self._require_non_negative_integer(manifest["purged_row_count"])
        self._require_sha256(manifest["content_fingerprint"])

        source = manifest["source_preprocessed_run"]
        required_source = {
            "run_id",
            "content_fingerprint",
            "preprocessing_contract",
            "ruleset_version",
            "source_snapshot_id",
            "period",
        }
        if not isinstance(source, dict) or set(source) != required_source:
            self._invalid_manifest()
        self._require_uuid(source["run_id"])
        self._require_uuid(source["source_snapshot_id"])
        self._require_sha256(source["content_fingerprint"])
        if source["preprocessing_contract"] != "smartfactory.ml.preprocessed.snapshot":
            self._invalid_manifest()

        policy = manifest["split_policy"]
        required_policy = {
            "strategy",
            "train_ratio",
            "validation_ratio",
            "test_ratio",
            "supervised_boundary_purge",
        }
        if not isinstance(policy, dict) or set(policy) != required_policy:
            self._invalid_manifest()
        if policy["strategy"] != "global_chronological":
            self._invalid_manifest()
        if policy["supervised_boundary_purge"] is not True:
            self._invalid_manifest()
        try:
            ratio_total = sum(
                (
                    float(policy["train_ratio"]),
                    float(policy["validation_ratio"]),
                    float(policy["test_ratio"]),
                )
            )
        except (TypeError, ValueError):
            self._invalid_manifest()
            return
        if abs(ratio_total - 1.0) > 1e-9:
            self._invalid_manifest()

        tasks = manifest["tasks"]
        if not isinstance(tasks, list) or len(tasks) != len(FEATURE_TASKS):
            self._invalid_manifest()
        names = [task.get("name") for task in tasks if isinstance(task, dict)]
        if set(names) != set(FEATURE_TASKS) or len(names) != len(set(names)):
            self._invalid_manifest()

    def _validate_task(self, root: Path, task: dict[str, Any]) -> int:
        expected_keys = {
            "name",
            "feature_schema_version",
            "timestamp_column",
            "target_end_exclusive_column",
            "label_horizon_days",
            "source_datasets",
            "target_columns",
            "columns",
            "generated_row_count",
            "purged_row_count",
            "retained_row_count",
            "splits",
        }
        if not isinstance(task, dict) or set(task) != expected_keys:
            self._invalid_manifest()

        name = task["name"]
        definition = FEATURE_TASKS.get(name)
        if definition is None:
            self._invalid_manifest()
        if task["feature_schema_version"] != FEATURE_SCHEMA_VERSION:
            self._invalid_manifest()
        if task["timestamp_column"] != definition.timestamp_column:
            self._invalid_manifest()
        if task["target_end_exclusive_column"] != definition.target_end_exclusive_column:
            self._invalid_manifest()
        if task["label_horizon_days"] != definition.label_horizon_days:
            self._invalid_manifest()
        if task["source_datasets"] != list(definition.source_datasets):
            self._invalid_manifest()
        if task["target_columns"] != list(definition.target_columns):
            self._invalid_manifest()
        if task["columns"] != list(definition.columns):
            self._invalid_manifest()
        for key in ("generated_row_count", "purged_row_count", "retained_row_count"):
            self._require_non_negative_integer(task[key])
        if task["generated_row_count"] != (task["retained_row_count"] + task["purged_row_count"]):
            self._invalid_manifest()

        splits = task["splits"]
        if not isinstance(splits, list) or len(splits) != 3:
            self._invalid_manifest()
        split_names = [split.get("name") for split in splits if isinstance(split, dict)]
        if split_names != ["train", "validation", "test"]:
            self._invalid_manifest()

        split_rows = 0
        split_ranges: dict[str, tuple[date, date]] = {}
        target_maxima: dict[str, date | None] = {}
        for split in splits:
            count, minimum, maximum, target_maximum = self._validate_split(
                root,
                task,
                split,
            )
            split_rows += count
            split_ranges[split["name"]] = (minimum, maximum)
            target_maxima[split["name"]] = target_maximum

        if split_rows != task["retained_row_count"]:
            self._invalid_manifest()
        if not (
            split_ranges["train"][1] < split_ranges["validation"][0]
            and split_ranges["validation"][1] < split_ranges["test"][0]
        ):
            raise FeatureRunValidationError(
                "split_chronology_invalid",
                "Feature split date ranges overlap or are out of order.",
            )

        if definition.target_end_exclusive_column is not None:
            train_target_max = target_maxima["train"]
            validation_target_max = target_maxima["validation"]
            if (
                train_target_max is None
                or validation_target_max is None
                or train_target_max > split_ranges["validation"][0]
                or validation_target_max > split_ranges["test"][0]
            ):
                raise FeatureRunValidationError(
                    "target_leakage_detected",
                    "A supervised target window crosses a chronological split boundary.",
                )

        return split_rows

    def _validate_split(
        self,
        root: Path,
        task: dict[str, Any],
        split: dict[str, Any],
    ) -> tuple[int, date, date, date | None]:
        expected_keys = {
            "name",
            "file",
            "row_count",
            "byte_size",
            "sha256",
            "minimum_timestamp",
            "maximum_timestamp",
        }
        if not isinstance(split, dict) or set(split) != expected_keys:
            self._invalid_manifest()
        expected_file = f"data/{task['name']}/{split['name']}.csv"
        if split["file"] != expected_file:
            self._invalid_manifest()
        for key in ("row_count", "byte_size"):
            self._require_non_negative_integer(split[key])
        if split["row_count"] < 1 or split["byte_size"] < 1:
            self._invalid_manifest()
        self._require_sha256(split["sha256"])

        minimum = date.fromisoformat(split["minimum_timestamp"])
        maximum = date.fromisoformat(split["maximum_timestamp"])
        if maximum < minimum:
            self._invalid_manifest()

        path = self._safe_file(root, split["file"])
        self._verify_file_metadata(path, split["byte_size"], split["sha256"])
        count, observed_minimum, observed_maximum, target_maximum = self._validate_csv(
            path,
            task,
        )
        if count != split["row_count"]:
            raise FeatureRunValidationError(
                "feature_row_count_mismatch",
                "A feature file row count does not match its manifest.",
            )
        if observed_minimum != minimum or observed_maximum != maximum:
            raise FeatureRunValidationError(
                "feature_timestamp_range_mismatch",
                "A feature file timestamp range does not match its manifest.",
            )
        return count, minimum, maximum, target_maximum

    def _validate_csv(
        self,
        path: Path,
        task: dict[str, Any],
    ) -> tuple[int, date, date, date | None]:
        definition = FEATURE_TASKS[task["name"]]
        try:
            handle = path.open("r", encoding="utf-8", newline="")
        except (OSError, UnicodeError) as exception:
            raise FeatureRunValidationError(
                "feature_file_unreadable",
                "A feature file could not be opened safely.",
            ) from exception

        with handle:
            reader = csv.DictReader(handle, strict=True)
            if reader.fieldnames != list(definition.columns):
                raise FeatureRunValidationError(
                    "feature_header_mismatch",
                    "A feature file header does not match its registered schema.",
                )

            count = 0
            minimum: date | None = None
            maximum: date | None = None
            target_maximum: date | None = None
            previous_key: tuple[str, ...] | None = None
            try:
                for row in reader:
                    count += 1
                    if count > self.max_rows_per_file:
                        raise FeatureRunValidationError(
                            "feature_row_limit_exceeded",
                            "A feature file exceeds the configured row limit.",
                        )
                    if any(
                        value is None or len(value) > self.max_cell_characters or "\x00" in value
                        for value in row.values()
                    ):
                        raise FeatureRunValidationError(
                            "feature_cell_invalid",
                            "A feature file contains an invalid cell value.",
                        )
                    key = tuple(row[column] for column in definition.columns)
                    if previous_key is not None and key < previous_key:
                        raise FeatureRunValidationError(
                            "feature_order_invalid",
                            "A feature file is not deterministically ordered.",
                        )
                    previous_key = key

                    timestamp = self._timestamp_date(row[definition.timestamp_column])
                    minimum = timestamp if minimum is None else min(minimum, timestamp)
                    maximum = timestamp if maximum is None else max(maximum, timestamp)

                    if definition.target_end_exclusive_column is not None:
                        target_end = date.fromisoformat(row[definition.target_end_exclusive_column])
                        target_maximum = (
                            target_end
                            if target_maximum is None
                            else max(target_maximum, target_end)
                        )
            except csv.Error as exception:
                raise FeatureRunValidationError(
                    "feature_csv_invalid",
                    "A feature CSV file is malformed.",
                ) from exception

        if count < 1 or minimum is None or maximum is None:
            raise FeatureRunValidationError(
                "feature_file_empty",
                "A feature split file is empty.",
            )
        return count, minimum, maximum, target_maximum

    def _fingerprint(self, manifest: dict[str, Any]) -> str:
        source = manifest["source_preprocessed_run"]
        lines = [
            FEATURE_CONTRACT,
            FEATURE_RULESET_VERSION,
            source["run_id"],
            source["content_fingerprint"],
            FEATURE_DATA_CLASSIFICATION,
            json.dumps(
                manifest["split_policy"],
                sort_keys=True,
                separators=(",", ":"),
            ),
        ]
        for task in sorted(manifest["tasks"], key=lambda item: item["name"]):
            lines.append(
                "|".join(
                    [
                        task["name"],
                        str(task["generated_row_count"]),
                        str(task["purged_row_count"]),
                        str(task["retained_row_count"]),
                    ]
                )
            )
            for split in task["splits"]:
                lines.append(
                    "|".join(
                        [
                            split["name"],
                            split["sha256"],
                            str(split["row_count"]),
                            str(split["byte_size"]),
                            split["minimum_timestamp"],
                            split["maximum_timestamp"],
                        ]
                    )
                )
        return hashlib.sha256("\n".join(lines).encode("utf-8")).hexdigest()

    def _run_root(self, value: str | Path) -> Path:
        try:
            root = Path(value).expanduser().resolve(strict=True)
        except (OSError, FileNotFoundError) as exception:
            raise FeatureRunValidationError(
                "feature_run_not_found",
                "The feature run directory does not exist.",
            ) from exception
        if not root.is_dir():
            raise FeatureRunValidationError(
                "feature_run_not_directory",
                "The feature run path is not a directory.",
            )
        return root

    def _read_file(
        self,
        path: Path,
        *,
        maximum_bytes: int,
        missing_code: str,
    ) -> bytes:
        try:
            size = path.stat().st_size
        except OSError as exception:
            raise FeatureRunValidationError(
                missing_code,
                "A required feature metadata file is missing.",
            ) from exception
        if size < 2 or size > maximum_bytes:
            raise FeatureRunValidationError(
                "feature_metadata_size_invalid",
                "A feature metadata file size is invalid.",
            )
        try:
            return path.read_bytes()
        except OSError as exception:
            raise FeatureRunValidationError(
                "feature_metadata_unreadable",
                "A feature metadata file could not be read.",
            ) from exception

    def _verify_checksum_file(
        self,
        path: Path,
        *,
        expected_filename: str,
        payload: bytes,
    ) -> None:
        try:
            content = path.read_text(encoding="ascii")
        except (OSError, UnicodeError) as exception:
            raise FeatureRunValidationError(
                "manifest_checksum_missing",
                "The feature manifest checksum is missing or unreadable.",
            ) from exception
        parts = content.strip().split()
        if len(parts) != 2 or parts[1] != expected_filename:
            raise FeatureRunValidationError(
                "manifest_checksum_invalid",
                "The feature manifest checksum file is invalid.",
            )
        expected = parts[0].lower()
        self._require_sha256(expected)
        actual = hashlib.sha256(payload).hexdigest()
        if not hmac.compare_digest(actual, expected):
            raise FeatureRunValidationError(
                "manifest_checksum_mismatch",
                "The feature manifest checksum does not match.",
            )

    def _safe_file(self, root: Path, relative: str) -> Path:
        candidate = (root / relative).resolve(strict=False)
        try:
            candidate.relative_to(root)
        except ValueError as exception:
            raise FeatureRunValidationError(
                "unsafe_feature_path",
                "A feature file path escapes the run directory.",
            ) from exception
        if not candidate.is_file():
            raise FeatureRunValidationError(
                "feature_file_missing",
                "A declared feature file is missing.",
            )
        return candidate

    def _verify_file_metadata(self, path: Path, size: int, sha256: str) -> None:
        actual_size = path.stat().st_size
        if actual_size != size:
            raise FeatureRunValidationError(
                "feature_size_mismatch",
                "A feature file size does not match the manifest.",
            )
        if actual_size > self.file_max_bytes:
            raise FeatureRunValidationError(
                "feature_file_too_large",
                "A feature file exceeds the configured size limit.",
            )
        actual_hash = self._sha256(path)
        if not hmac.compare_digest(actual_hash, sha256):
            raise FeatureRunValidationError(
                "feature_checksum_mismatch",
                "A feature file checksum does not match the manifest.",
            )

    @staticmethod
    def _parse_json(payload: bytes) -> dict[str, Any]:
        try:
            parsed = json.loads(payload)
        except (UnicodeError, json.JSONDecodeError) as exception:
            raise FeatureRunValidationError(
                "invalid_manifest",
                "The feature manifest is invalid.",
            ) from exception
        if not isinstance(parsed, dict):
            raise FeatureRunValidationError(
                "invalid_manifest",
                "The feature manifest is invalid.",
            )
        return parsed

    @staticmethod
    def _timestamp_date(value: str) -> date:
        if "T" in value:
            return datetime.fromisoformat(value.replace("Z", "+00:00")).date()
        return date.fromisoformat(value)

    @staticmethod
    def _require_uuid(value: Any) -> None:
        try:
            UUID(str(value))
        except (ValueError, TypeError, AttributeError) as exception:
            raise FeatureRunValidationError(
                "invalid_manifest",
                "The feature manifest is invalid.",
            ) from exception

    @staticmethod
    def _require_aware_datetime(value: Any) -> None:
        try:
            parsed = datetime.fromisoformat(str(value).replace("Z", "+00:00"))
        except ValueError as exception:
            raise FeatureRunValidationError(
                "invalid_manifest",
                "The feature manifest is invalid.",
            ) from exception
        if parsed.tzinfo is None or parsed.utcoffset() is None:
            raise FeatureRunValidationError(
                "invalid_manifest",
                "The feature manifest is invalid.",
            )

    @staticmethod
    def _require_sha256(value: Any) -> None:
        if (
            not isinstance(value, str)
            or len(value) != 64
            or any(character not in "0123456789abcdef" for character in value.lower())
        ):
            raise FeatureRunValidationError(
                "invalid_manifest",
                "The feature manifest is invalid.",
            )

    @staticmethod
    def _require_non_negative_integer(value: Any) -> None:
        if isinstance(value, bool) or not isinstance(value, int) or value < 0:
            raise FeatureRunValidationError(
                "invalid_manifest",
                "The feature manifest is invalid.",
            )

    @staticmethod
    def _sha256(path: Path) -> str:
        digest = hashlib.sha256()
        with path.open("rb") as handle:
            for chunk in iter(lambda: handle.read(1_048_576), b""):
                digest.update(chunk)
        return digest.hexdigest()

    @staticmethod
    def _invalid_manifest() -> None:
        raise FeatureRunValidationError(
            "invalid_manifest",
            "The feature manifest does not match the supported contract.",
        )

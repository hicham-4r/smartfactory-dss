from __future__ import annotations

import csv
import hashlib
import json
import os
import shutil
from dataclasses import dataclass
from datetime import UTC, date, datetime
from decimal import Decimal
from pathlib import Path
from typing import Any
from uuid import uuid4

from app.features.engineering import build_feature_rows, load_preprocessed_datasets
from app.features.schema import (
    FEATURE_CONTRACT,
    FEATURE_DATA_CLASSIFICATION,
    FEATURE_MANIFEST_VERSION,
    FEATURE_RULESET_VERSION,
    FEATURE_SCHEMA_VERSION,
    FEATURE_TASKS,
    FeatureSplitName,
    FeatureTaskDefinition,
)
from app.preprocessing.validator import PreprocessedRunValidator


class FeatureEngineeringError(Exception):
    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True, slots=True)
class FeatureEngineeringReceipt:
    run_id: str
    source_preprocessed_run_id: str
    source_content_fingerprint: str
    total_rows: int
    task_rows: dict[str, int]
    purged_rows: int
    run_path: str
    content_fingerprint: str

    def to_dict(self) -> dict[str, Any]:
        return {
            "status": "featured",
            "run_id": self.run_id,
            "source_preprocessed_run_id": self.source_preprocessed_run_id,
            "source_content_fingerprint": self.source_content_fingerprint,
            "total_rows": self.total_rows,
            "task_rows": self.task_rows,
            "purged_rows": self.purged_rows,
            "run_path": self.run_path,
            "content_fingerprint": self.content_fingerprint,
        }


@dataclass(frozen=True, slots=True)
class SplitResult:
    rows: dict[FeatureSplitName, list[dict[str, str]]]
    purged_rows: int


class FeatureEngineeringPipeline:
    def __init__(
        self,
        *,
        train_ratio: Decimal | str = "0.70",
        validation_ratio: Decimal | str = "0.15",
        test_ratio: Decimal | str = "0.15",
    ) -> None:
        self.train_ratio = Decimal(str(train_ratio))
        self.validation_ratio = Decimal(str(validation_ratio))
        self.test_ratio = Decimal(str(test_ratio))
        self._validate_ratios()

    def run(
        self,
        preprocessed_run_directory: str | Path,
        output_root: str | Path,
    ) -> FeatureEngineeringReceipt:
        source_root = self._resolve_directory(
            preprocessed_run_directory,
            "preprocessed_run_not_found",
        )
        output = self._resolve_output_root(output_root, source_root)
        source_receipt = PreprocessedRunValidator().validate(source_root)
        source_manifest = self._load_manifest(source_root)
        period = source_manifest["source_snapshot"]["period"]
        period_start = date.fromisoformat(period["start_date"])
        period_end = date.fromisoformat(period["end_date"])
        datasets = load_preprocessed_datasets(source_root, source_manifest["datasets"])

        run_id = str(uuid4())
        staging_root = output / ".staging" / run_id
        final_root = output / "runs" / run_id
        lock_path = output / ".feature-engineering.lock"

        self._acquire_lock(lock_path)
        try:
            staging_root.mkdir(parents=True, exist_ok=False)
            (staging_root / "data").mkdir()

            task_manifests: list[dict[str, Any]] = []
            task_rows: dict[str, int] = {}
            purged_rows = 0

            for task_name, definition in FEATURE_TASKS.items():
                rows = build_feature_rows(
                    task_name,
                    datasets,
                    period_start=period_start,
                    period_end=period_end,
                )
                if not rows:
                    raise FeatureEngineeringError(
                        "feature_task_empty",
                        f"The {task_name} feature task produced no eligible rows.",
                    )

                split_result = self._split_rows(rows, definition)
                task_manifest = self._write_task_files(
                    staging_root=staging_root,
                    definition=definition,
                    split_result=split_result,
                    generated_row_count=len(rows),
                )
                task_manifests.append(task_manifest)
                retained = sum(split["row_count"] for split in task_manifest["splits"])
                task_rows[task_name] = retained
                purged_rows += split_result.purged_rows

            generated_at = datetime.now(UTC).isoformat().replace("+00:00", "Z")
            manifest = self._manifest(
                run_id=run_id,
                generated_at=generated_at,
                source_manifest=source_manifest,
                source_receipt=source_receipt,
                task_manifests=task_manifests,
            )
            manifest_path = staging_root / "manifest.json"
            self._write_json(manifest_path, manifest)
            manifest_hash = self._sha256(manifest_path)
            (staging_root / "manifest.sha256").write_text(
                f"{manifest_hash}  manifest.json\n",
                encoding="ascii",
                newline="\n",
            )

            from app.features.validator import FeatureRunValidator

            FeatureRunValidator().validate(staging_root)

            final_root.parent.mkdir(parents=True, exist_ok=True)
            if final_root.exists():
                raise FeatureEngineeringError(
                    "feature_run_already_exists",
                    "The feature run identifier already exists.",
                )
            os.replace(staging_root, final_root)
            self._publish_latest_pointer(output, run_id)

            return FeatureEngineeringReceipt(
                run_id=run_id,
                source_preprocessed_run_id=source_receipt.run_id,
                source_content_fingerprint=source_receipt.content_fingerprint,
                total_rows=sum(task_rows.values()),
                task_rows=task_rows,
                purged_rows=purged_rows,
                run_path=str(final_root),
                content_fingerprint=manifest["content_fingerprint"],
            )
        except Exception:
            shutil.rmtree(staging_root, ignore_errors=True)
            raise
        finally:
            self._release_lock(lock_path)

    def _split_rows(
        self,
        rows: list[dict[str, str]],
        definition: FeatureTaskDefinition,
    ) -> SplitResult:
        unique_timestamps = sorted(
            {self._timestamp_date(row[definition.timestamp_column]) for row in rows}
        )
        if len(unique_timestamps) < 10:
            raise FeatureEngineeringError(
                "insufficient_chronology",
                f"The {definition.name} task needs at least 10 distinct timestamps.",
            )

        train_count = max(1, int(Decimal(len(unique_timestamps)) * self.train_ratio))
        validation_count = max(
            1,
            int(Decimal(len(unique_timestamps)) * self.validation_ratio),
        )
        if train_count + validation_count >= len(unique_timestamps):
            validation_count = max(1, len(unique_timestamps) - train_count - 1)
        if train_count + validation_count >= len(unique_timestamps):
            train_count = len(unique_timestamps) - validation_count - 1

        validation_start = unique_timestamps[train_count]
        test_start = unique_timestamps[train_count + validation_count]
        split_rows: dict[FeatureSplitName, list[dict[str, str]]] = {
            "train": [],
            "validation": [],
            "test": [],
        }
        purged = 0

        for row in rows:
            timestamp = self._timestamp_date(row[definition.timestamp_column])
            if timestamp < validation_start:
                split: FeatureSplitName = "train"
                next_start = validation_start
            elif timestamp < test_start:
                split = "validation"
                next_start = test_start
            else:
                split = "test"
                next_start = None

            if next_start is not None and definition.target_end_exclusive_column is not None:
                target_end = date.fromisoformat(row[definition.target_end_exclusive_column])
                if target_end > next_start:
                    purged += 1
                    continue

            split_rows[split].append(row)

        for split, split_values in split_rows.items():
            if not split_values:
                raise FeatureEngineeringError(
                    "empty_chronological_split",
                    f"The {definition.name} {split} split is empty after leakage purging.",
                )
            split_values.sort(key=lambda row: tuple(row[column] for column in definition.columns))

        return SplitResult(rows=split_rows, purged_rows=purged)

    def _write_task_files(
        self,
        *,
        staging_root: Path,
        definition: FeatureTaskDefinition,
        split_result: SplitResult,
        generated_row_count: int,
    ) -> dict[str, Any]:
        split_manifests: list[dict[str, Any]] = []
        task_directory = staging_root / "data" / definition.name
        task_directory.mkdir(parents=True)

        for split_name in ("train", "validation", "test"):
            rows = split_result.rows[split_name]
            relative = f"data/{definition.name}/{split_name}.csv"
            path = staging_root / relative
            self._write_csv(path, definition, rows)
            timestamps = [self._timestamp_date(row[definition.timestamp_column]) for row in rows]
            split_manifests.append(
                {
                    "name": split_name,
                    "file": relative,
                    "row_count": len(rows),
                    "byte_size": path.stat().st_size,
                    "sha256": self._sha256(path),
                    "minimum_timestamp": min(timestamps).isoformat(),
                    "maximum_timestamp": max(timestamps).isoformat(),
                }
            )

        return {
            "name": definition.name,
            "feature_schema_version": FEATURE_SCHEMA_VERSION,
            "timestamp_column": definition.timestamp_column,
            "target_end_exclusive_column": definition.target_end_exclusive_column,
            "label_horizon_days": definition.label_horizon_days,
            "source_datasets": list(definition.source_datasets),
            "target_columns": list(definition.target_columns),
            "columns": list(definition.columns),
            "generated_row_count": generated_row_count,
            "purged_row_count": split_result.purged_rows,
            "retained_row_count": sum(len(rows) for rows in split_result.rows.values()),
            "splits": split_manifests,
        }

    def _manifest(
        self,
        *,
        run_id: str,
        generated_at: str,
        source_manifest: dict[str, Any],
        source_receipt,
        task_manifests: list[dict[str, Any]],
    ) -> dict[str, Any]:
        split_policy = {
            "strategy": "global_chronological",
            "train_ratio": str(self.train_ratio),
            "validation_ratio": str(self.validation_ratio),
            "test_ratio": str(self.test_ratio),
            "supervised_boundary_purge": True,
        }
        fingerprint = self._content_fingerprint(
            source_manifest=source_manifest,
            split_policy=split_policy,
            task_manifests=task_manifests,
        )

        return {
            "manifest_version": FEATURE_MANIFEST_VERSION,
            "feature_contract": FEATURE_CONTRACT,
            "ruleset_version": FEATURE_RULESET_VERSION,
            "run_id": run_id,
            "generated_at": generated_at,
            "source_preprocessed_run": {
                "run_id": source_receipt.run_id,
                "content_fingerprint": source_receipt.content_fingerprint,
                "preprocessing_contract": source_manifest["preprocessing_contract"],
                "ruleset_version": source_manifest["ruleset_version"],
                "source_snapshot_id": source_manifest["source_snapshot"]["snapshot_id"],
                "period": source_manifest["source_snapshot"]["period"],
            },
            "source_system": source_manifest["source_system"],
            "data_classification": source_manifest["data_classification"],
            "split_policy": split_policy,
            "total_rows": sum(task["retained_row_count"] for task in task_manifests),
            "purged_row_count": sum(task["purged_row_count"] for task in task_manifests),
            "tasks": task_manifests,
            "content_fingerprint": fingerprint,
        }

    def _content_fingerprint(
        self,
        *,
        source_manifest: dict[str, Any],
        split_policy: dict[str, Any],
        task_manifests: list[dict[str, Any]],
    ) -> str:
        lines = [
            FEATURE_CONTRACT,
            FEATURE_RULESET_VERSION,
            str(source_manifest["run_id"]),
            str(source_manifest["content_fingerprint"]),
            FEATURE_DATA_CLASSIFICATION,
            json.dumps(split_policy, sort_keys=True, separators=(",", ":")),
        ]
        for task in sorted(task_manifests, key=lambda item: item["name"]):
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

    def _validate_ratios(self) -> None:
        ratios = (self.train_ratio, self.validation_ratio, self.test_ratio)
        if any(ratio <= 0 or ratio >= 1 for ratio in ratios):
            raise ValueError("feature split ratios must be between zero and one")
        if sum(ratios, Decimal("0")) != Decimal("1"):
            raise ValueError("feature split ratios must add up to exactly one")

    @staticmethod
    def _write_csv(
        path: Path,
        definition: FeatureTaskDefinition,
        rows: list[dict[str, str]],
    ) -> None:
        with path.open("w", encoding="utf-8", newline="") as handle:
            writer = csv.DictWriter(
                handle,
                fieldnames=definition.columns,
                lineterminator="\n",
                extrasaction="raise",
            )
            writer.writeheader()
            writer.writerows(rows)

    @staticmethod
    def _timestamp_date(value: str) -> date:
        if "T" in value:
            return datetime.fromisoformat(value.replace("Z", "+00:00")).date()
        return date.fromisoformat(value)

    @staticmethod
    def _load_manifest(root: Path) -> dict[str, Any]:
        try:
            return json.loads((root / "manifest.json").read_text(encoding="utf-8"))
        except (OSError, UnicodeError, json.JSONDecodeError) as exception:
            raise FeatureEngineeringError(
                "preprocessed_manifest_unreadable",
                "The preprocessed manifest could not be read.",
            ) from exception

    @staticmethod
    def _resolve_directory(value: str | Path, code: str) -> Path:
        try:
            path = Path(value).expanduser().resolve(strict=True)
        except (OSError, FileNotFoundError) as exception:
            raise FeatureEngineeringError(
                code,
                "The requested directory does not exist.",
            ) from exception
        if not path.is_dir():
            raise FeatureEngineeringError(
                code,
                "The requested path is not a directory.",
            )
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
        raise FeatureEngineeringError(
            "unsafe_feature_output_root",
            "The feature output root must not be inside the preprocessed run.",
        )

    @staticmethod
    def _acquire_lock(path: Path) -> None:
        try:
            descriptor = os.open(path, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        except FileExistsError as exception:
            raise FeatureEngineeringError(
                "feature_engineering_locked",
                "Another feature-engineering run appears to be active.",
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
        temporary = output_root / ".FEATURE_LATEST.tmp"
        latest = output_root / "FEATURE_LATEST"
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

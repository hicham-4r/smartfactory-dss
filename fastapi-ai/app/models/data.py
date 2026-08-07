from __future__ import annotations

import json
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import pandas as pd

from app.features.schema import FEATURE_TASKS
from app.features.validator import FeatureRunValidator
from app.models.schema import MODEL_TASKS, ModelTaskDefinition


class ModelDataError(Exception):
    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True, slots=True)
class FeatureTaskData:
    definition: ModelTaskDefinition
    train: pd.DataFrame
    validation: pd.DataFrame
    test: pd.DataFrame
    feature_columns: tuple[str, ...]
    numeric_columns: tuple[str, ...]

    def combined_train_validation(self) -> pd.DataFrame:
        return pd.concat([self.train, self.validation], ignore_index=True)


@dataclass(frozen=True, slots=True)
class FeatureRunData:
    root: Path
    manifest: dict[str, Any]
    tasks: dict[str, FeatureTaskData]


class FeatureRunLoader:
    def load(self, run_directory: str | Path) -> FeatureRunData:
        try:
            root = Path(run_directory).expanduser().resolve(strict=True)
        except (OSError, FileNotFoundError) as exception:
            raise ModelDataError(
                "feature_run_not_found",
                "The requested feature run does not exist.",
            ) from exception

        FeatureRunValidator().validate(root)
        manifest = self._load_manifest(root)
        task_manifests = {str(task["name"]): task for task in manifest["tasks"]}

        tasks: dict[str, FeatureTaskData] = {}
        for name, definition in MODEL_TASKS.items():
            task_manifest = task_manifests.get(name)
            if task_manifest is None:
                raise ModelDataError(
                    "feature_task_missing",
                    "A required feature task is missing from the feature run.",
                )
            tasks[name] = self._load_task(root, task_manifest, definition)

        return FeatureRunData(root=root, manifest=manifest, tasks=tasks)

    def _load_task(
        self,
        root: Path,
        task_manifest: dict[str, Any],
        definition: ModelTaskDefinition,
    ) -> FeatureTaskData:
        expected_columns = tuple(FEATURE_TASKS[definition.name].columns)
        split_frames: dict[str, pd.DataFrame] = {}

        for split in task_manifest["splits"]:
            path = self._safe_file(root, str(split["file"]))
            frame = pd.read_csv(
                path,
                dtype=str,
                keep_default_na=False,
                na_filter=False,
            )
            if tuple(frame.columns) != expected_columns:
                raise ModelDataError(
                    "feature_header_mismatch",
                    "A feature file header changed after feature validation.",
                )
            if len(frame.index) != int(split["row_count"]):
                raise ModelDataError(
                    "feature_row_count_mismatch",
                    "A feature row count changed after feature validation.",
                )
            split_frames[str(split["name"])] = frame

        if set(split_frames) != {"train", "validation", "test"}:
            raise ModelDataError(
                "feature_split_missing",
                "A required chronological feature split is missing.",
            )

        feature_columns = tuple(
            column for column in expected_columns if column not in definition.all_excluded_columns
        )
        categorical = set(definition.categorical_columns)
        numeric_columns = tuple(column for column in feature_columns if column not in categorical)

        for frame in split_frames.values():
            self._coerce_frame(frame, definition, feature_columns, numeric_columns)

        return FeatureTaskData(
            definition=definition,
            train=split_frames["train"],
            validation=split_frames["validation"],
            test=split_frames["test"],
            feature_columns=feature_columns,
            numeric_columns=numeric_columns,
        )

    @staticmethod
    def _coerce_frame(
        frame: pd.DataFrame,
        definition: ModelTaskDefinition,
        feature_columns: tuple[str, ...],
        numeric_columns: tuple[str, ...],
    ) -> None:
        for column in definition.categorical_columns:
            if column in feature_columns:
                frame[column] = frame[column].astype("string")

        for column in numeric_columns:
            frame[column] = pd.to_numeric(frame[column], errors="coerce")

        for column in definition.target_columns:
            frame[column] = pd.to_numeric(frame[column], errors="coerce")
            if frame[column].isna().any():
                raise ModelDataError(
                    "target_value_invalid",
                    "A supervised feature target contains an invalid or missing value.",
                )

    @staticmethod
    def _load_manifest(root: Path) -> dict[str, Any]:
        try:
            return json.loads((root / "manifest.json").read_text(encoding="utf-8"))
        except (OSError, UnicodeError, json.JSONDecodeError) as exception:
            raise ModelDataError(
                "feature_manifest_unreadable",
                "The feature manifest could not be read.",
            ) from exception

    @staticmethod
    def _safe_file(root: Path, relative: str) -> Path:
        candidate = (root / relative).resolve(strict=False)
        try:
            candidate.relative_to(root)
        except ValueError as exception:
            raise ModelDataError(
                "unsafe_feature_path",
                "A feature file path escapes the feature run.",
            ) from exception
        if not candidate.is_file():
            raise ModelDataError(
                "feature_file_missing",
                "A declared feature file is missing.",
            )
        return candidate

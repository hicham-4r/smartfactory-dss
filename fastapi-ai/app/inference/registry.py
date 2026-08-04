from __future__ import annotations

import copy
import json
from dataclasses import dataclass
from pathlib import Path
from typing import Any
from uuid import UUID

import joblib

from app.models.validator import ModelRunValidationError, ModelRunValidator


class InferenceRegistryError(Exception):
    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True, slots=True)
class LoadedModelRun:
    run_id: str
    source_feature_run_id: str
    manifest: dict[str, Any]
    artifacts: dict[str, dict[str, Any]]


@dataclass(frozen=True, slots=True)
class LoadedModelMetrics:
    run_id: str
    source_feature_run_id: str
    task: str
    selected_model: str
    metrics: dict[str, Any]
    limitations: list[str]
    metric_derivations: dict[str, str]


class ModelRegistryLoader:
    def __init__(self, model_root: str | Path) -> None:
        self.model_root = Path(model_root).expanduser()
        self._cached_pointer: str | None = None
        self._cached_run: LoadedModelRun | None = None

    def load(self, requested_run_id: UUID | None = None) -> LoadedModelRun:
        run_id = str(requested_run_id) if requested_run_id is not None else self._latest_run_id()
        if self._cached_pointer == run_id and self._cached_run is not None:
            return self._cached_run

        run_root = self._safe_run_root(run_id)
        try:
            receipt = ModelRunValidator().validate(run_root)
        except ModelRunValidationError as exception:
            raise InferenceRegistryError(
                "model_registry_invalid",
                "The configured model registry failed integrity validation.",
            ) from exception

        manifest = self._read_json(run_root / "manifest.json")
        artifacts: dict[str, dict[str, Any]] = {}
        for task in manifest["tasks"]:
            task_name = str(task["name"])
            artifact_path = self._safe_file(run_root, str(task["artifact"]["file"]))
            try:
                payload = joblib.load(artifact_path)
            except Exception as exception:
                raise InferenceRegistryError(
                    "model_artifact_unreadable",
                    "A verified model artifact could not be loaded.",
                ) from exception
            if not isinstance(payload, dict) or payload.get("task") != task_name:
                raise InferenceRegistryError(
                    "model_artifact_contract_invalid",
                    "A model artifact does not match its registry task.",
                )
            if payload.get("feature_columns") != task["feature_columns"]:
                raise InferenceRegistryError(
                    "model_feature_contract_mismatch",
                    "A model artifact feature contract does not match the registry.",
                )
            artifacts[task_name] = payload

        loaded = LoadedModelRun(
            run_id=receipt.run_id,
            source_feature_run_id=receipt.source_feature_run_id,
            manifest=manifest,
            artifacts=artifacts,
        )
        self._cached_pointer = run_id
        self._cached_run = loaded
        return loaded

    def metrics(
        self,
        task_name: str,
        *,
        requested_run_id: UUID,
    ) -> LoadedModelMetrics:
        run_id = str(requested_run_id)
        run_root = self._safe_run_root(run_id)
        try:
            receipt = ModelRunValidator().validate(run_root)
        except ModelRunValidationError as exception:
            raise InferenceRegistryError(
                "model_registry_invalid",
                "The configured model registry failed integrity validation.",
            ) from exception

        manifest = self._read_json(run_root / "manifest.json")
        task = next(
            (item for item in manifest["tasks"] if item["name"] == task_name),
            None,
        )
        if task is None:
            raise InferenceRegistryError(
                "model_task_unavailable",
                "The requested model task is unavailable.",
            )

        metrics_path = self._safe_file(run_root, str(task["metrics"]["file"]))
        payload = self._read_json(metrics_path)
        normalized = copy.deepcopy(payload)
        derivations: dict[str, str] = {}
        self._add_derived_mse(normalized, derivations)

        return LoadedModelMetrics(
            run_id=receipt.run_id,
            source_feature_run_id=receipt.source_feature_run_id,
            task=task_name,
            selected_model=str(task["selected_model"]),
            metrics=normalized,
            limitations=[str(item) for item in task["limitations"]],
            metric_derivations=derivations,
        )

    @classmethod
    def _add_derived_mse(
        cls,
        value: Any,
        derivations: dict[str, str],
        path: str = "metrics",
    ) -> None:
        if isinstance(value, dict):
            rmse = value.get("rmse")
            if "mse" not in value and isinstance(rmse, (int, float)):
                value["mse"] = float(rmse) ** 2
                derivations[f"{path}.mse"] = (
                    "Derived as RMSE squared because registry v1 predates explicit MSE storage."
                )
            for key, item in list(value.items()):
                cls._add_derived_mse(item, derivations, f"{path}.{key}")
        elif isinstance(value, list):
            for index, item in enumerate(value):
                cls._add_derived_mse(item, derivations, f"{path}[{index}]")

    def _latest_run_id(self) -> str:
        pointer = self.model_root / "MODELS_LATEST"
        try:
            run_id = pointer.read_text(encoding="ascii").strip()
            UUID(run_id)
        except (OSError, UnicodeError, ValueError) as exception:
            raise InferenceRegistryError(
                "model_registry_not_configured",
                "No valid latest model registry pointer is configured.",
            ) from exception
        return run_id

    def _safe_run_root(self, run_id: str) -> Path:
        try:
            root = (self.model_root / "runs" / run_id).resolve(strict=True)
            root.relative_to(self.model_root.resolve(strict=True))
        except (OSError, ValueError) as exception:
            raise InferenceRegistryError(
                "model_run_not_found",
                "The requested model run is unavailable.",
            ) from exception
        if not root.is_dir():
            raise InferenceRegistryError(
                "model_run_not_found",
                "The requested model run is unavailable.",
            )
        return root

    @staticmethod
    def _safe_file(root: Path, relative: str) -> Path:
        try:
            path = (root / relative).resolve(strict=True)
            path.relative_to(root)
        except (OSError, ValueError) as exception:
            raise InferenceRegistryError(
                "unsafe_model_artifact_path",
                "A model-registry path is unsafe.",
            ) from exception
        if not path.is_file():
            raise InferenceRegistryError(
                "model_artifact_missing",
                "A model-registry file is missing.",
            )
        return path

    @staticmethod
    def _read_json(path: Path) -> dict[str, Any]:
        try:
            payload = json.loads(path.read_text(encoding="utf-8"))
        except (OSError, UnicodeError, json.JSONDecodeError) as exception:
            raise InferenceRegistryError(
                "model_manifest_unreadable",
                "A model-registry JSON document could not be read.",
            ) from exception
        if not isinstance(payload, dict):
            raise InferenceRegistryError(
                "model_manifest_unreadable",
                "A model-registry JSON document could not be read.",
            )
        return payload

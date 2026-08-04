from __future__ import annotations

from dataclasses import dataclass
from typing import Any
from uuid import UUID

import numpy as np
import pandas as pd

from app.inference.registry import InferenceRegistryError, LoadedModelRun, ModelRegistryLoader


class InferenceExecutionError(Exception):
    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True, slots=True)
class InferenceResult:
    value: dict[str, Any]
    run: LoadedModelRun
    selected_model: str
    limitations: list[str]


class InferenceService:
    def __init__(self, registry: ModelRegistryLoader) -> None:
        self.registry = registry

    def registry_metadata(self) -> LoadedModelRun:
        return self.registry.load()

    def model_metrics(self, task: str, *, model_run_id: UUID):
        return self.registry.metrics(task, requested_run_id=model_run_id)

    def forecast(
        self,
        features: dict[str, Any],
        *,
        model_run_id: UUID | None = None,
    ) -> InferenceResult:
        run = self.registry.load(model_run_id)
        payload = self._artifact(run, "production_forecasting")
        frame = self._frame(payload, features)
        try:
            prediction = float(payload["model"].predict(frame)[0])
        except Exception as exception:
            raise InferenceExecutionError(
                "forecast_execution_failed",
                "The production forecast could not be produced safely.",
            ) from exception
        return InferenceResult(
            value={"prediction": max(0.0, prediction)},
            run=run,
            selected_model=str(payload["selected_model"]),
            limitations=self._limitations(run, "production_forecasting"),
        )

    def anomaly(
        self,
        features: dict[str, Any],
        *,
        model_run_id: UUID | None = None,
    ) -> InferenceResult:
        run = self.registry.load(model_run_id)
        payload = self._artifact(run, "production_anomaly")
        frame = self._frame(payload, features)
        try:
            score = float(-payload["model"].decision_function(frame)[0])
            threshold = float(payload["threshold"])
        except Exception as exception:
            raise InferenceExecutionError(
                "anomaly_execution_failed",
                "The production anomaly score could not be produced safely.",
            ) from exception
        return InferenceResult(
            value={
                "score": score,
                "threshold": threshold,
                "is_anomaly": score >= threshold,
            },
            run=run,
            selected_model=str(payload["selected_model"]),
            limitations=self._limitations(run, "production_anomaly"),
        )

    def maintenance(
        self,
        features: dict[str, Any],
        *,
        model_run_id: UUID | None = None,
    ) -> InferenceResult:
        run = self.registry.load(model_run_id)
        payload = self._artifact(run, "maintenance_risk")
        frame = self._frame(payload, features)
        try:
            classifier = payload["failure_classifier"]
            probabilities = classifier.predict_proba(frame)
            classes = list(classifier.classes_)
            probability = (
                float(probabilities[0, classes.index(1)])
                if 1 in classes
                else float(classes[0] == 1)
            )
            downtime = float(payload["downtime_regressor"].predict(frame)[0])
        except Exception as exception:
            raise InferenceExecutionError(
                "maintenance_execution_failed",
                "The maintenance risk score could not be produced safely.",
            ) from exception

        downtime = max(0.0, downtime)
        probability = float(np.clip(probability, 0.0, 1.0))
        return InferenceResult(
            value={
                "probability": probability,
                "downtime": downtime,
                "priority": self._priority(probability, downtime, bool(features["is_critical"])),
            },
            run=run,
            selected_model=str(payload["selected_model"]),
            limitations=self._limitations(run, "maintenance_risk"),
        )

    @staticmethod
    def _artifact(run: LoadedModelRun, task: str) -> dict[str, Any]:
        try:
            return run.artifacts[task]
        except KeyError as exception:
            raise InferenceRegistryError(
                "model_task_unavailable",
                "The requested model task is unavailable.",
            ) from exception

    @staticmethod
    def _frame(payload: dict[str, Any], features: dict[str, Any]) -> pd.DataFrame:
        expected = list(payload["feature_columns"])
        if set(features) != set(expected):
            raise InferenceExecutionError(
                "feature_contract_mismatch",
                "The inference feature fields do not match the model contract.",
            )
        return pd.DataFrame([{column: features[column] for column in expected}], columns=expected)

    @staticmethod
    def _limitations(run: LoadedModelRun, task_name: str) -> list[str]:
        for task in run.manifest["tasks"]:
            if task["name"] == task_name:
                return [str(item) for item in task["limitations"]]
        return ["This output is based only on simulated-prototype data."]

    @staticmethod
    def _priority(probability: float, downtime: float, is_critical: bool) -> str:
        if is_critical and (probability >= 0.70 or downtime >= 120):
            return "critical"
        if probability >= 0.70 or downtime >= 120:
            return "high"
        if probability >= 0.35 or downtime >= 45:
            return "medium"
        return "low"

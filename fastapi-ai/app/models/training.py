from __future__ import annotations

import hashlib
import json
import os
import platform
import shutil
from collections.abc import Callable
from dataclasses import dataclass
from datetime import UTC, datetime
from pathlib import Path
from typing import Any
from uuid import uuid4

import joblib
import numpy as np
import pandas as pd
import sklearn
from sklearn.base import BaseEstimator
from sklearn.compose import ColumnTransformer
from sklearn.dummy import DummyClassifier, DummyRegressor
from sklearn.ensemble import (
    GradientBoostingRegressor,
    IsolationForest,
    RandomForestClassifier,
    RandomForestRegressor,
)
from sklearn.impute import SimpleImputer
from sklearn.linear_model import LinearRegression, LogisticRegression
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

from app.models.data import FeatureRunData, FeatureRunLoader, FeatureTaskData, ModelDataError
from app.models.metrics import anomaly_score_metrics, classification_metrics, regression_metrics
from app.models.schema import (
    MODEL_DATA_CLASSIFICATION,
    MODEL_MANIFEST_VERSION,
    MODEL_REGISTRY_CONTRACT,
    MODEL_TRAINING_RULESET_VERSION,
)


class ModelTrainingError(Exception):
    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True, slots=True)
class ModelTrainingReceipt:
    run_id: str
    source_feature_run_id: str
    total_training_rows: int
    tasks: tuple[str, ...]
    run_path: str
    content_fingerprint: str

    def to_dict(self) -> dict[str, Any]:
        return {
            "status": "trained",
            "run_id": self.run_id,
            "source_feature_run_id": self.source_feature_run_id,
            "total_training_rows": self.total_training_rows,
            "tasks": list(self.tasks),
            "run_path": self.run_path,
            "content_fingerprint": self.content_fingerprint,
        }


@dataclass(frozen=True, slots=True)
class TaskTrainingResult:
    name: str
    task_type: str
    selected_model: str
    selection_metric: str
    feature_columns: tuple[str, ...]
    target_columns: tuple[str, ...]
    train_rows: int
    validation_rows: int
    test_rows: int
    artifact_path: Path
    metrics_path: Path
    limitations: tuple[str, ...]


class ModelTrainingPipeline:
    def __init__(
        self,
        *,
        random_seed: int = 42,
        anomaly_contamination: float = 0.02,
    ) -> None:
        if random_seed < 1 or random_seed > 2_147_483_647:
            raise ValueError("random_seed must be between 1 and 2147483647")
        if not 0.001 <= anomaly_contamination <= 0.20:
            raise ValueError("anomaly_contamination must be between 0.001 and 0.20")
        self.random_seed = random_seed
        self.anomaly_contamination = anomaly_contamination

    def run(
        self,
        feature_run_directory: str | Path,
        output_root: str | Path,
    ) -> ModelTrainingReceipt:
        try:
            feature_data = FeatureRunLoader().load(feature_run_directory)
        except ModelDataError as exception:
            raise ModelTrainingError(exception.code, exception.message) from exception

        output = self._resolve_output_root(output_root, feature_data.root)
        run_id = str(uuid4())
        staging_root = output / ".staging" / run_id
        final_root = output / "runs" / run_id
        lock_path = output / ".model-training.lock"

        self._acquire_lock(lock_path)
        try:
            staging_root.mkdir(parents=True, exist_ok=False)
            (staging_root / "artifacts").mkdir()
            (staging_root / "metrics").mkdir()

            results = [
                self._train_forecasting(feature_data.tasks["production_forecasting"], staging_root),
                self._train_anomaly(feature_data.tasks["production_anomaly"], staging_root),
                self._train_maintenance(feature_data.tasks["maintenance_risk"], staging_root),
            ]
            generated_at = datetime.now(UTC).isoformat().replace("+00:00", "Z")
            manifest = self._manifest(
                run_id=run_id,
                generated_at=generated_at,
                feature_data=feature_data,
                results=results,
            )
            manifest_path = staging_root / "manifest.json"
            self._write_json(manifest_path, manifest)
            manifest_hash = self._sha256(manifest_path)
            (staging_root / "manifest.sha256").write_text(
                f"{manifest_hash}  manifest.json\n",
                encoding="ascii",
                newline="\n",
            )

            from app.models.validator import ModelRunValidator

            ModelRunValidator().validate(staging_root)
            final_root.parent.mkdir(parents=True, exist_ok=True)
            if final_root.exists():
                raise ModelTrainingError(
                    "model_run_already_exists",
                    "The generated model-run identifier already exists.",
                )
            os.replace(staging_root, final_root)
            self._publish_latest_pointer(output, run_id)

            return ModelTrainingReceipt(
                run_id=run_id,
                source_feature_run_id=str(feature_data.manifest["run_id"]),
                total_training_rows=sum(result.train_rows for result in results),
                tasks=tuple(result.name for result in results),
                run_path=str(final_root),
                content_fingerprint=manifest["content_fingerprint"],
            )
        except Exception:
            shutil.rmtree(staging_root, ignore_errors=True)
            raise
        finally:
            self._release_lock(lock_path)

    def _train_forecasting(
        self,
        data: FeatureTaskData,
        root: Path,
    ) -> TaskTrainingResult:
        target = data.definition.target_columns[0]
        candidates: dict[str, Callable[[], BaseEstimator]] = {
            "dummy_mean_regressor": lambda: DummyRegressor(strategy="mean"),
            "linear_regression": LinearRegression,
            "gradient_boosting_regressor": lambda: GradientBoostingRegressor(
                random_state=self.random_seed
            ),
            "random_forest_regressor": lambda: RandomForestRegressor(
                n_estimators=200,
                max_depth=14,
                min_samples_leaf=2,
                random_state=self.random_seed,
                n_jobs=1,
            ),
        }
        selected_name, candidate_metrics = self._select_regressor(
            data=data,
            target=target,
            candidates=candidates,
        )
        combined = data.combined_train_validation()
        final_model = self._tabular_pipeline(data, candidates[selected_name]())
        final_model.fit(
            combined[list(data.feature_columns)], combined[target].to_numpy(dtype=float)
        )
        test_prediction = final_model.predict(data.test[list(data.feature_columns)])
        test_metrics = regression_metrics(
            data.test[target].to_numpy(dtype=float),
            test_prediction,
        )

        metrics = {
            "metrics_contract": "smartfactory.ml.model-metrics",
            "metrics_version": "v1",
            "task": data.definition.name,
            "task_type": data.definition.task_type,
            "selection_metric": data.definition.selection_metric,
            "candidate_validation_metrics": candidate_metrics,
            "selected_model": selected_name,
            "test_metrics": test_metrics,
            "data_classification": MODEL_DATA_CLASSIFICATION,
            "limitations": [
                "Metrics are based only on simulated-prototype data.",
                "The model is not an industrial production commitment.",
                "The test split was not used for candidate selection.",
            ],
        }
        return self._persist_task(
            root=root,
            data=data,
            model_payload={
                "task": data.definition.name,
                "model": final_model,
                "feature_columns": list(data.feature_columns),
                "target_columns": list(data.definition.target_columns),
                "selected_model": selected_name,
            },
            metrics=metrics,
            selected_model=selected_name,
            limitations=tuple(metrics["limitations"]),
        )

    def _train_anomaly(
        self,
        data: FeatureTaskData,
        root: Path,
    ) -> TaskTrainingResult:
        estimator = IsolationForest(
            n_estimators=250,
            contamination=self.anomaly_contamination,
            random_state=self.random_seed,
            n_jobs=1,
        )
        model = self._tabular_pipeline(data, estimator)
        model.fit(data.train[list(data.feature_columns)])
        train_scores = -model.decision_function(data.train[list(data.feature_columns)])
        threshold = float(np.quantile(train_scores, 1.0 - self.anomaly_contamination))
        validation_scores = -model.decision_function(data.validation[list(data.feature_columns)])

        combined = data.combined_train_validation()
        final_model = self._tabular_pipeline(
            data,
            IsolationForest(
                n_estimators=250,
                contamination=self.anomaly_contamination,
                random_state=self.random_seed,
                n_jobs=1,
            ),
        )
        final_model.fit(combined[list(data.feature_columns)])
        combined_scores = -final_model.decision_function(combined[list(data.feature_columns)])
        final_threshold = float(np.quantile(combined_scores, 1.0 - self.anomaly_contamination))
        test_scores = -final_model.decision_function(data.test[list(data.feature_columns)])

        limitations = (
            "No ground-truth anomaly labels are available in the feature contract.",
            "Anomaly rates and score distributions are diagnostics, not accuracy metrics.",
            "All observations originate from simulated-prototype data.",
        )
        metrics = {
            "metrics_contract": "smartfactory.ml.model-metrics",
            "metrics_version": "v1",
            "task": data.definition.name,
            "task_type": data.definition.task_type,
            "selection_metric": data.definition.selection_metric,
            "selected_model": "isolation_forest",
            "configured_contamination": self.anomaly_contamination,
            "train_metrics": anomaly_score_metrics(train_scores, threshold),
            "validation_metrics": anomaly_score_metrics(validation_scores, threshold),
            "test_metrics": anomaly_score_metrics(test_scores, final_threshold),
            "data_classification": MODEL_DATA_CLASSIFICATION,
            "limitations": list(limitations),
        }
        return self._persist_task(
            root=root,
            data=data,
            model_payload={
                "task": data.definition.name,
                "model": final_model,
                "threshold": final_threshold,
                "score_direction": "higher_is_more_anomalous",
                "feature_columns": list(data.feature_columns),
                "target_columns": [],
                "selected_model": "isolation_forest",
            },
            metrics=metrics,
            selected_model="isolation_forest",
            limitations=limitations,
        )

    def _train_maintenance(
        self,
        data: FeatureTaskData,
        root: Path,
    ) -> TaskTrainingResult:
        class_target, downtime_target = data.definition.target_columns
        classifier_candidates: dict[str, Callable[[], BaseEstimator]] = {
            "dummy_prior_classifier": lambda: DummyClassifier(strategy="prior"),
            "logistic_regression": lambda: LogisticRegression(
                max_iter=2000,
                class_weight="balanced",
                solver="liblinear",
                random_state=self.random_seed,
            ),
            "random_forest_classifier": lambda: RandomForestClassifier(
                n_estimators=250,
                max_depth=14,
                min_samples_leaf=2,
                class_weight="balanced_subsample",
                random_state=self.random_seed,
                n_jobs=1,
            ),
        }
        classifier_name, classifier_metrics, classifier_limitations = self._select_classifier(
            data=data,
            target=class_target,
            candidates=classifier_candidates,
        )
        regressor_candidates: dict[str, Callable[[], BaseEstimator]] = {
            "dummy_mean_regressor": lambda: DummyRegressor(strategy="mean"),
            "linear_regression": LinearRegression,
            "gradient_boosting_regressor": lambda: GradientBoostingRegressor(
                random_state=self.random_seed
            ),
            "random_forest_regressor": lambda: RandomForestRegressor(
                n_estimators=200,
                max_depth=14,
                min_samples_leaf=2,
                random_state=self.random_seed,
                n_jobs=1,
            ),
        }
        regressor_name, regressor_metrics = self._select_regressor(
            data=data,
            target=downtime_target,
            candidates=regressor_candidates,
        )

        combined = data.combined_train_validation()
        classifier = self._tabular_pipeline(data, classifier_candidates[classifier_name]())
        classifier.fit(
            combined[list(data.feature_columns)],
            combined[class_target].to_numpy(dtype=int),
        )
        class_prediction = classifier.predict(data.test[list(data.feature_columns)])
        class_probability = self._positive_probability(classifier, data.test, data.feature_columns)
        class_test_metrics = classification_metrics(
            data.test[class_target].to_numpy(dtype=int),
            class_prediction,
            class_probability,
        )

        regressor = self._tabular_pipeline(data, regressor_candidates[regressor_name]())
        regressor.fit(
            combined[list(data.feature_columns)],
            combined[downtime_target].to_numpy(dtype=float),
        )
        downtime_prediction = regressor.predict(data.test[list(data.feature_columns)])
        downtime_test_metrics = regression_metrics(
            data.test[downtime_target].to_numpy(dtype=float),
            downtime_prediction,
        )

        limitations = tuple(
            dict.fromkeys(
                [
                    *classifier_limitations,
                    (
                        "Maintenance output is an AI-assisted prioritization prototype, "
                        "not reliable predictive maintenance."
                    ),
                    "Metrics are based only on simulated-prototype data.",
                    "The test split was not used for candidate selection.",
                ]
            )
        )
        selected_model = f"{classifier_name}+{regressor_name}"
        metrics = {
            "metrics_contract": "smartfactory.ml.model-metrics",
            "metrics_version": "v1",
            "task": data.definition.name,
            "task_type": data.definition.task_type,
            "selection_metric": data.definition.selection_metric,
            "selected_model": selected_model,
            "failure_classifier": {
                "candidate_validation_metrics": classifier_metrics,
                "selected_model": classifier_name,
                "test_metrics": class_test_metrics,
            },
            "downtime_regressor": {
                "candidate_validation_metrics": regressor_metrics,
                "selected_model": regressor_name,
                "test_metrics": downtime_test_metrics,
            },
            "data_classification": MODEL_DATA_CLASSIFICATION,
            "limitations": list(limitations),
        }
        return self._persist_task(
            root=root,
            data=data,
            model_payload={
                "task": data.definition.name,
                "failure_classifier": classifier,
                "downtime_regressor": regressor,
                "feature_columns": list(data.feature_columns),
                "target_columns": list(data.definition.target_columns),
                "selected_model": selected_model,
            },
            metrics=metrics,
            selected_model=selected_model,
            limitations=limitations,
        )

    def _select_regressor(
        self,
        *,
        data: FeatureTaskData,
        target: str,
        candidates: dict[str, Callable[[], BaseEstimator]],
    ) -> tuple[str, dict[str, dict[str, float | int | None]]]:
        validation_metrics: dict[str, dict[str, float | int | None]] = {}
        for name, factory in candidates.items():
            model = self._tabular_pipeline(data, factory())
            model.fit(
                data.train[list(data.feature_columns)],
                data.train[target].to_numpy(dtype=float),
            )
            prediction = model.predict(data.validation[list(data.feature_columns)])
            validation_metrics[name] = regression_metrics(
                data.validation[target].to_numpy(dtype=float),
                prediction,
            )

        selected = min(
            validation_metrics,
            key=lambda name: (
                float(validation_metrics[name]["mae"] or float("inf")),
                name,
            ),
        )
        return selected, validation_metrics

    def _select_classifier(
        self,
        *,
        data: FeatureTaskData,
        target: str,
        candidates: dict[str, Callable[[], BaseEstimator]],
    ) -> tuple[str, dict[str, dict[str, Any]], tuple[str, ...]]:
        y_train = data.train[target].to_numpy(dtype=int)
        limitations: list[str] = []
        usable_candidates = candidates
        if np.unique(y_train).size < 2:
            usable_candidates = {"dummy_prior_classifier": candidates["dummy_prior_classifier"]}
            limitations.append(
                "The training split contained one failure class, so only a prior "
                "baseline classifier was eligible."
            )

        validation_metrics: dict[str, dict[str, Any]] = {}
        for name, factory in usable_candidates.items():
            model = self._tabular_pipeline(data, factory())
            model.fit(data.train[list(data.feature_columns)], y_train)
            prediction = model.predict(data.validation[list(data.feature_columns)])
            probability = self._positive_probability(
                model,
                data.validation,
                data.feature_columns,
            )
            validation_metrics[name] = classification_metrics(
                data.validation[target].to_numpy(dtype=int),
                prediction,
                probability,
            )

        rank = {
            "dummy_prior_classifier": 0,
            "logistic_regression": 1,
            "random_forest_classifier": 2,
        }

        def score(name: str) -> tuple[float, float, int]:
            metrics = validation_metrics[name]
            average_precision = metrics["average_precision"]
            f1 = metrics["f1"]
            return (
                float(average_precision) if average_precision is not None else -1.0,
                float(f1) if f1 is not None else -1.0,
                rank[name],
            )

        selected = max(validation_metrics, key=score)
        return selected, validation_metrics, tuple(limitations)

    def _tabular_pipeline(
        self,
        data: FeatureTaskData,
        estimator: BaseEstimator,
    ) -> Pipeline:
        categorical = list(data.definition.categorical_columns)
        numeric = list(data.numeric_columns)
        transformers: list[tuple[str, Pipeline, list[str]]] = []
        if numeric:
            transformers.append(
                (
                    "numeric",
                    Pipeline(
                        [
                            (
                                "imputer",
                                SimpleImputer(strategy="median", keep_empty_features=True),
                            )
                        ]
                    ),
                    numeric,
                )
            )
        if categorical:
            transformers.append(
                (
                    "categorical",
                    Pipeline(
                        [
                            (
                                "imputer",
                                SimpleImputer(strategy="most_frequent"),
                            ),
                            (
                                "encoder",
                                OneHotEncoder(
                                    handle_unknown="ignore",
                                    sparse_output=False,
                                ),
                            ),
                        ]
                    ),
                    categorical,
                )
            )
        preprocessor = ColumnTransformer(
            transformers=transformers,
            remainder="drop",
            verbose_feature_names_out=True,
        )
        return Pipeline([("preprocessor", preprocessor), ("estimator", estimator)])

    @staticmethod
    def _positive_probability(
        model: Pipeline,
        frame: pd.DataFrame,
        feature_columns: tuple[str, ...],
    ) -> np.ndarray[Any, Any] | None:
        if not hasattr(model, "predict_proba"):
            return None
        probabilities = model.predict_proba(frame[list(feature_columns)])
        estimator = model.named_steps["estimator"]
        classes = list(getattr(estimator, "classes_", []))
        if 1 not in classes:
            return np.zeros(len(frame.index), dtype=float)
        return np.asarray(probabilities[:, classes.index(1)], dtype=float)

    def _persist_task(
        self,
        *,
        root: Path,
        data: FeatureTaskData,
        model_payload: dict[str, Any],
        metrics: dict[str, Any],
        selected_model: str,
        limitations: tuple[str, ...],
    ) -> TaskTrainingResult:
        artifact_path = root / "artifacts" / f"{data.definition.name}.joblib"
        metrics_path = root / "metrics" / f"{data.definition.name}.json"
        joblib.dump(model_payload, artifact_path, compress=3, protocol=5)
        self._write_json(metrics_path, metrics)
        return TaskTrainingResult(
            name=data.definition.name,
            task_type=data.definition.task_type,
            selected_model=selected_model,
            selection_metric=data.definition.selection_metric,
            feature_columns=data.feature_columns,
            target_columns=data.definition.target_columns,
            train_rows=len(data.train.index),
            validation_rows=len(data.validation.index),
            test_rows=len(data.test.index),
            artifact_path=artifact_path,
            metrics_path=metrics_path,
            limitations=limitations,
        )

    def _manifest(
        self,
        *,
        run_id: str,
        generated_at: str,
        feature_data: FeatureRunData,
        results: list[TaskTrainingResult],
    ) -> dict[str, Any]:
        tasks = []
        for result in results:
            tasks.append(
                {
                    "name": result.name,
                    "task_type": result.task_type,
                    "selected_model": result.selected_model,
                    "selection_metric": result.selection_metric,
                    "feature_columns": list(result.feature_columns),
                    "target_columns": list(result.target_columns),
                    "train_row_count": result.train_rows,
                    "validation_row_count": result.validation_rows,
                    "test_row_count": result.test_rows,
                    "artifact": self._file_manifest(result.artifact_path, "artifacts"),
                    "metrics": self._file_manifest(result.metrics_path, "metrics"),
                    "limitations": list(result.limitations),
                }
            )

        manifest: dict[str, Any] = {
            "manifest_version": MODEL_MANIFEST_VERSION,
            "model_registry_contract": MODEL_REGISTRY_CONTRACT,
            "training_ruleset_version": MODEL_TRAINING_RULESET_VERSION,
            "run_id": run_id,
            "generated_at": generated_at,
            "source_feature_run": {
                "run_id": feature_data.manifest["run_id"],
                "content_fingerprint": feature_data.manifest["content_fingerprint"],
                "feature_contract": feature_data.manifest["feature_contract"],
                "ruleset_version": feature_data.manifest["ruleset_version"],
                "source_preprocessed_run_id": feature_data.manifest["source_preprocessed_run"][
                    "run_id"
                ],
            },
            "source_system": feature_data.manifest["source_system"],
            "data_classification": feature_data.manifest["data_classification"],
            "random_seed": self.random_seed,
            "anomaly_contamination": self.anomaly_contamination,
            "environment": {
                "python_version": platform.python_version(),
                "numpy_version": np.__version__,
                "pandas_version": pd.__version__,
                "scikit_learn_version": sklearn.__version__,
                "joblib_version": joblib.__version__,
            },
            "tasks": tasks,
            "content_fingerprint": "",
        }
        manifest["content_fingerprint"] = self._content_fingerprint(manifest)
        return manifest

    def _file_manifest(self, path: Path, directory: str) -> dict[str, Any]:
        return {
            "file": f"{directory}/{path.name}",
            "byte_size": path.stat().st_size,
            "sha256": self._sha256(path),
        }

    @staticmethod
    def _content_fingerprint(manifest: dict[str, Any]) -> str:
        source = manifest["source_feature_run"]
        lines = [
            MODEL_REGISTRY_CONTRACT,
            MODEL_TRAINING_RULESET_VERSION,
            source["run_id"],
            source["content_fingerprint"],
            MODEL_DATA_CLASSIFICATION,
            str(manifest["random_seed"]),
            str(manifest["anomaly_contamination"]),
        ]
        for task in sorted(manifest["tasks"], key=lambda item: item["name"]):
            lines.append(
                "|".join(
                    [
                        task["name"],
                        task["selected_model"],
                        task["artifact"]["sha256"],
                        task["metrics"]["sha256"],
                        str(task["train_row_count"]),
                        str(task["validation_row_count"]),
                        str(task["test_row_count"]),
                    ]
                )
            )
        return hashlib.sha256("\n".join(lines).encode("utf-8")).hexdigest()

    @staticmethod
    def _resolve_output_root(value: str | Path, feature_root: Path) -> Path:
        path = Path(value).expanduser()
        path.mkdir(parents=True, exist_ok=True)
        resolved = path.resolve(strict=True)
        try:
            resolved.relative_to(feature_root)
        except ValueError:
            return resolved
        raise ModelTrainingError(
            "unsafe_model_output_root",
            "The model registry root must not be inside the source feature run.",
        )

    @staticmethod
    def _acquire_lock(path: Path) -> None:
        try:
            descriptor = os.open(path, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        except FileExistsError as exception:
            raise ModelTrainingError(
                "model_training_locked",
                "Another model-training run appears to be active.",
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
        temporary = output_root / ".MODELS_LATEST.tmp"
        latest = output_root / "MODELS_LATEST"
        temporary.write_text(run_id + "\n", encoding="ascii", newline="\n")
        os.replace(temporary, latest)

    @staticmethod
    def _write_json(path: Path, payload: dict[str, Any]) -> None:
        path.write_text(
            json.dumps(
                payload,
                ensure_ascii=False,
                indent=2,
                sort_keys=True,
                allow_nan=False,
            )
            + "\n",
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

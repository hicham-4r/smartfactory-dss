from __future__ import annotations

import hashlib
import hmac
import json
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any
from uuid import UUID

from app.models.schema import (
    MODEL_DATA_CLASSIFICATION,
    MODEL_MANIFEST_VERSION,
    MODEL_REGISTRY_CONTRACT,
    MODEL_TASKS,
    MODEL_TRAINING_RULESET_VERSION,
)


class ModelRunValidationError(Exception):
    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True, slots=True)
class ModelRunReceipt:
    run_id: str
    source_feature_run_id: str
    tasks: tuple[str, ...]
    content_fingerprint: str

    def to_dict(self) -> dict[str, Any]:
        return {
            "status": "valid",
            "run_id": self.run_id,
            "source_feature_run_id": self.source_feature_run_id,
            "tasks": list(self.tasks),
            "content_fingerprint": self.content_fingerprint,
        }


class ModelRunValidator:
    def __init__(
        self,
        *,
        manifest_max_bytes: int = 1_048_576,
        artifact_max_bytes: int = 536_870_912,
        metrics_max_bytes: int = 10_485_760,
    ) -> None:
        if manifest_max_bytes < 1024:
            raise ValueError("manifest_max_bytes must be at least 1024")
        if artifact_max_bytes < 1024:
            raise ValueError("artifact_max_bytes must be at least 1024")
        if metrics_max_bytes < 1024:
            raise ValueError("metrics_max_bytes must be at least 1024")
        self.manifest_max_bytes = manifest_max_bytes
        self.artifact_max_bytes = artifact_max_bytes
        self.metrics_max_bytes = metrics_max_bytes

    def validate(self, run_directory: str | Path) -> ModelRunReceipt:
        root = self._run_root(run_directory)
        manifest_bytes = self._read_file(
            root / "manifest.json",
            maximum_bytes=self.manifest_max_bytes,
            missing_code="model_manifest_not_found",
        )
        self._verify_checksum_file(
            root / "manifest.sha256",
            expected_filename="manifest.json",
            payload=manifest_bytes,
        )
        manifest = self._parse_json(manifest_bytes, "invalid_model_manifest")
        self._validate_manifest_shape(manifest)

        task_names: list[str] = []
        for task in manifest["tasks"]:
            self._validate_task(root, task)
            task_names.append(task["name"])

        expected_fingerprint = self._fingerprint(manifest)
        if not hmac.compare_digest(
            expected_fingerprint,
            manifest["content_fingerprint"],
        ):
            raise ModelRunValidationError(
                "model_content_fingerprint_mismatch",
                "The model-run content fingerprint is invalid.",
            )

        return ModelRunReceipt(
            run_id=manifest["run_id"],
            source_feature_run_id=manifest["source_feature_run"]["run_id"],
            tasks=tuple(task_names),
            content_fingerprint=manifest["content_fingerprint"],
        )

    def _validate_manifest_shape(self, manifest: dict[str, Any]) -> None:
        expected_keys = {
            "manifest_version",
            "model_registry_contract",
            "training_ruleset_version",
            "run_id",
            "generated_at",
            "source_feature_run",
            "source_system",
            "data_classification",
            "random_seed",
            "anomaly_contamination",
            "environment",
            "tasks",
            "content_fingerprint",
        }
        if set(manifest) != expected_keys:
            self._invalid_manifest()
        if manifest["manifest_version"] != MODEL_MANIFEST_VERSION:
            self._invalid_manifest()
        if manifest["model_registry_contract"] != MODEL_REGISTRY_CONTRACT:
            self._invalid_manifest()
        if manifest["training_ruleset_version"] != MODEL_TRAINING_RULESET_VERSION:
            self._invalid_manifest()
        if manifest["data_classification"] != MODEL_DATA_CLASSIFICATION:
            self._invalid_manifest()
        self._require_uuid(manifest["run_id"])
        self._require_aware_datetime(manifest["generated_at"])
        self._require_sha256(manifest["content_fingerprint"])

        if not isinstance(manifest["random_seed"], int) or manifest["random_seed"] < 1:
            self._invalid_manifest()
        try:
            contamination = float(manifest["anomaly_contamination"])
        except (TypeError, ValueError):
            self._invalid_manifest()
            return
        if not 0.001 <= contamination <= 0.20:
            self._invalid_manifest()

        source = manifest["source_feature_run"]
        expected_source = {
            "run_id",
            "content_fingerprint",
            "feature_contract",
            "ruleset_version",
            "source_preprocessed_run_id",
        }
        if not isinstance(source, dict) or set(source) != expected_source:
            self._invalid_manifest()
        self._require_uuid(source["run_id"])
        self._require_uuid(source["source_preprocessed_run_id"])
        self._require_sha256(source["content_fingerprint"])
        if source["feature_contract"] != "smartfactory.ml.feature.snapshot":
            self._invalid_manifest()

        environment = manifest["environment"]
        expected_environment = {
            "python_version",
            "numpy_version",
            "pandas_version",
            "scikit_learn_version",
            "joblib_version",
        }
        if not isinstance(environment, dict) or set(environment) != expected_environment:
            self._invalid_manifest()
        if any(not isinstance(value, str) or not value for value in environment.values()):
            self._invalid_manifest()

        tasks = manifest["tasks"]
        if not isinstance(tasks, list) or len(tasks) != len(MODEL_TASKS):
            self._invalid_manifest()
        names = [task.get("name") for task in tasks if isinstance(task, dict)]
        if set(names) != set(MODEL_TASKS) or len(names) != len(set(names)):
            self._invalid_manifest()

    def _validate_task(self, root: Path, task: dict[str, Any]) -> None:
        expected_keys = {
            "name",
            "task_type",
            "selected_model",
            "selection_metric",
            "feature_columns",
            "target_columns",
            "train_row_count",
            "validation_row_count",
            "test_row_count",
            "artifact",
            "metrics",
            "limitations",
        }
        if not isinstance(task, dict) or set(task) != expected_keys:
            self._invalid_manifest()
        definition = MODEL_TASKS.get(task["name"])
        if definition is None:
            self._invalid_manifest()
        if task["task_type"] != definition.task_type:
            self._invalid_manifest()
        if task["target_columns"] != list(definition.target_columns):
            self._invalid_manifest()
        if task["selection_metric"] != definition.selection_metric:
            self._invalid_manifest()
        if not isinstance(task["selected_model"], str) or not task["selected_model"]:
            self._invalid_manifest()
        if not isinstance(task["feature_columns"], list) or not task["feature_columns"]:
            self._invalid_manifest()
        if len(task["feature_columns"]) != len(set(task["feature_columns"])):
            self._invalid_manifest()
        for key in ("train_row_count", "validation_row_count", "test_row_count"):
            self._require_positive_integer(task[key])
        limitations = task["limitations"]
        if not isinstance(limitations, list) or not limitations:
            self._invalid_manifest()
        if any(not isinstance(item, str) or not item for item in limitations):
            self._invalid_manifest()

        artifact = task["artifact"]
        metrics = task["metrics"]
        self._validate_file_manifest(
            root,
            artifact,
            expected_file=f"artifacts/{task['name']}.joblib",
            maximum_bytes=self.artifact_max_bytes,
        )
        metrics_path = self._validate_file_manifest(
            root,
            metrics,
            expected_file=f"metrics/{task['name']}.json",
            maximum_bytes=self.metrics_max_bytes,
        )
        metrics_payload = self._parse_json(
            metrics_path.read_bytes(),
            "invalid_model_metrics",
        )
        if metrics_payload.get("task") != task["name"]:
            raise ModelRunValidationError(
                "model_metrics_mismatch",
                "A model metrics file does not match its declared task.",
            )
        if metrics_payload.get("selected_model") != task["selected_model"]:
            raise ModelRunValidationError(
                "model_metrics_mismatch",
                "A model metrics selection does not match the manifest.",
            )
        if metrics_payload.get("data_classification") != MODEL_DATA_CLASSIFICATION:
            raise ModelRunValidationError(
                "model_metrics_mismatch",
                "A model metrics classification does not match the manifest.",
            )

    def _validate_file_manifest(
        self,
        root: Path,
        payload: dict[str, Any],
        *,
        expected_file: str,
        maximum_bytes: int,
    ) -> Path:
        if not isinstance(payload, dict) or set(payload) != {
            "file",
            "byte_size",
            "sha256",
        }:
            self._invalid_manifest()
        if payload["file"] != expected_file:
            self._invalid_manifest()
        self._require_positive_integer(payload["byte_size"])
        self._require_sha256(payload["sha256"])
        path = self._safe_file(root, payload["file"])
        size = path.stat().st_size
        if size != payload["byte_size"]:
            raise ModelRunValidationError(
                "model_file_size_mismatch",
                "A model-run file size does not match the manifest.",
            )
        if size > maximum_bytes:
            raise ModelRunValidationError(
                "model_file_too_large",
                "A model-run file exceeds its configured size limit.",
            )
        actual = self._sha256(path)
        if not hmac.compare_digest(actual, payload["sha256"]):
            raise ModelRunValidationError(
                "model_file_checksum_mismatch",
                "A model-run file checksum does not match the manifest.",
            )
        return path

    @staticmethod
    def _fingerprint(manifest: dict[str, Any]) -> str:
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
    def _run_root(value: str | Path) -> Path:
        try:
            root = Path(value).expanduser().resolve(strict=True)
        except (OSError, FileNotFoundError) as exception:
            raise ModelRunValidationError(
                "model_run_not_found",
                "The model-run directory does not exist.",
            ) from exception
        if not root.is_dir():
            raise ModelRunValidationError(
                "model_run_not_directory",
                "The model-run path is not a directory.",
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
            raise ModelRunValidationError(
                missing_code,
                "A required model-run metadata file is missing.",
            ) from exception
        if size < 2 or size > maximum_bytes:
            raise ModelRunValidationError(
                "model_metadata_size_invalid",
                "A model-run metadata file size is invalid.",
            )
        try:
            return path.read_bytes()
        except OSError as exception:
            raise ModelRunValidationError(
                "model_metadata_unreadable",
                "A model-run metadata file could not be read.",
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
            raise ModelRunValidationError(
                "model_manifest_checksum_missing",
                "The model manifest checksum is missing or unreadable.",
            ) from exception
        parts = content.strip().split()
        if len(parts) != 2 or parts[1] != expected_filename:
            raise ModelRunValidationError(
                "model_manifest_checksum_invalid",
                "The model manifest checksum file is invalid.",
            )
        expected = parts[0].lower()
        if len(expected) != 64 or any(
            character not in "0123456789abcdef" for character in expected
        ):
            raise ModelRunValidationError(
                "model_manifest_checksum_invalid",
                "The model manifest checksum file is invalid.",
            )
        actual = hashlib.sha256(payload).hexdigest()
        if not hmac.compare_digest(actual, expected):
            raise ModelRunValidationError(
                "model_manifest_checksum_mismatch",
                "The model manifest checksum does not match.",
            )

    @staticmethod
    def _parse_json(payload: bytes, code: str) -> dict[str, Any]:
        try:
            decoded = json.loads(payload)
        except (UnicodeError, json.JSONDecodeError) as exception:
            raise ModelRunValidationError(
                code,
                "A model-run JSON file is invalid.",
            ) from exception
        if not isinstance(decoded, dict):
            raise ModelRunValidationError(
                code,
                "A model-run JSON file has an invalid root type.",
            )
        return decoded

    @staticmethod
    def _safe_file(root: Path, relative: str) -> Path:
        candidate = (root / relative).resolve(strict=False)
        try:
            candidate.relative_to(root)
        except ValueError as exception:
            raise ModelRunValidationError(
                "unsafe_model_path",
                "A model-run file path escapes the model-run directory.",
            ) from exception
        if not candidate.is_file():
            raise ModelRunValidationError(
                "model_file_missing",
                "A declared model-run file is missing.",
            )
        return candidate

    @staticmethod
    def _sha256(path: Path) -> str:
        digest = hashlib.sha256()
        with path.open("rb") as handle:
            for chunk in iter(lambda: handle.read(1_048_576), b""):
                digest.update(chunk)
        return digest.hexdigest()

    @staticmethod
    def _require_uuid(value: Any) -> None:
        try:
            UUID(str(value))
        except (ValueError, TypeError, AttributeError) as exception:
            raise ModelRunValidationError(
                "invalid_model_manifest",
                "The model manifest is invalid.",
            ) from exception

    @staticmethod
    def _require_aware_datetime(value: Any) -> None:
        try:
            parsed = datetime.fromisoformat(str(value).replace("Z", "+00:00"))
        except ValueError as exception:
            raise ModelRunValidationError(
                "invalid_model_manifest",
                "The model manifest is invalid.",
            ) from exception
        if parsed.tzinfo is None or parsed.utcoffset() is None:
            raise ModelRunValidationError(
                "invalid_model_manifest",
                "The model manifest is invalid.",
            )

    @staticmethod
    def _require_sha256(value: Any) -> None:
        if (
            not isinstance(value, str)
            or len(value) != 64
            or any(character not in "0123456789abcdef" for character in value)
        ):
            raise ModelRunValidationError(
                "invalid_model_manifest",
                "The model manifest is invalid.",
            )

    @staticmethod
    def _require_positive_integer(value: Any) -> None:
        if not isinstance(value, int) or value < 1:
            raise ModelRunValidationError(
                "invalid_model_manifest",
                "The model manifest is invalid.",
            )

    @staticmethod
    def _invalid_manifest() -> None:
        raise ModelRunValidationError(
            "invalid_model_manifest",
            "The model manifest does not match the supported contract.",
        )

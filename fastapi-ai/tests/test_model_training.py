from __future__ import annotations

import csv
import hashlib
import json
from datetime import date, datetime, timedelta
from pathlib import Path
from uuid import UUID

import numpy as np
import pytest

from app.cli.models import main
from app.features.schema import FEATURE_TASKS
from app.models.metrics import anomaly_score_metrics, classification_metrics, regression_metrics
from app.models.training import ModelTrainingError, ModelTrainingPipeline
from app.models.validator import ModelRunValidationError, ModelRunValidator

FEATURE_RUN_ID = "11111111-1111-4111-8111-111111111111"
PREPROCESSED_RUN_ID = "22222222-2222-4222-8222-222222222222"
SNAPSHOT_ID = "33333333-3333-4333-8333-333333333333"


def _sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def _date_text(day: date) -> str:
    return day.isoformat()


def _datetime_text(day: date, hour: int = 6) -> str:
    return datetime.combine(day, datetime.min.time()).replace(hour=hour).isoformat() + "Z"


def _forecast_row(day: date, index: int) -> dict[str, str]:
    good = 1000 + (index * 15)
    return {
        "feature_date": _date_text(day),
        "target_date": _date_text(day + timedelta(days=1)),
        "target_window_end_date": _date_text(day + timedelta(days=2)),
        "production_line_code": f"LINE-{(index % 2) + 1:02d}",
        "quantity_unit": "L",
        "days_of_history": str(14 + index),
        "rolling_observation_count_7d": "7",
        "day_of_week": str(day.weekday()),
        "month": str(day.month),
        "good_quantity_lag_1d": str(good),
        "good_quantity_lag_7d": str(good - 35),
        "good_quantity_mean_7d": str(good - 12),
        "good_quantity_min_7d": str(good - 55),
        "good_quantity_max_7d": str(good + 20),
        "produced_quantity_lag_1d": str(good + 30),
        "target_quantity_lag_1d": str(good + 50),
        "runtime_minutes_lag_1d": str(420 + index),
        "downtime_minutes_lag_1d": str(10 + (index % 5)),
        "rejection_rate_lag_1d": "0.02",
        "achievement_rate_lag_1d": "0.97",
        "target_good_quantity_next_day": str(good + 18),
    }


def _anomaly_row(day: date, index: int) -> dict[str, str]:
    produced = 1200 + (index * 10)
    rejected = 10 + (index % 4)
    good = produced - rejected
    return {
        "event_time_utc": _datetime_text(day, 6 + (index % 3)),
        "production_date": _date_text(day),
        "production_line_code": f"LINE-{(index % 2) + 1:02d}",
        "product_family_code": "VALENCIA-PREMIUM",
        "product_code": f"PRODUCT-{(index % 3) + 1:02d}",
        "shift_code": f"SHIFT-{(index % 2) + 1}",
        "quantity_unit": "L",
        "production_order_priority": str((index % 3) + 1),
        "target_quantity": str(produced + 40),
        "produced_quantity": str(produced),
        "good_quantity": str(good),
        "rejected_quantity": str(rejected),
        "runtime_minutes": str(400 + index),
        "downtime_minutes": str(8 + (index % 6)),
        "achievement_ratio": "0.97",
        "rejection_ratio": "0.01",
        "good_yield_ratio": "0.99",
        "throughput_per_hour": "180.0",
        "downtime_ratio": "0.03",
        "is_validated": "1",
    }


def _maintenance_row(day: date, index: int, *, single_class: bool) -> dict[str, str]:
    failure = 0 if single_class else index % 2
    return {
        "prediction_date": _date_text(day),
        "target_window_end_date": _date_text(day + timedelta(days=7)),
        "production_line_code": f"LINE-{(index % 2) + 1:02d}",
        "machine_code": f"MACHINE-{(index % 4) + 1:02d}",
        "machine_type": "FILLER" if index % 2 == 0 else "PACKER",
        "is_critical": str(index % 2),
        "days_observed": str(35 + index),
        "status_event_count_7d": str(20 + index),
        "fault_status_event_count_7d": str(failure + (index % 3)),
        "running_minutes_7d": str(2500 + (index * 5)),
        "fault_minutes_7d": str(15 + (failure * 20)),
        "downtime_event_count_7d": str(2 + failure),
        "unplanned_downtime_event_count_7d": str(failure),
        "total_downtime_minutes_7d": str(30 + (failure * 25)),
        "unplanned_downtime_minutes_7d": str(failure * 25),
        "maintenance_event_count_30d": str(2 + (index % 3)),
        "preventive_maintenance_count_30d": "1",
        "corrective_maintenance_count_30d": str(failure),
        "maintenance_downtime_minutes_30d": str(40 + (failure * 30)),
        "days_since_last_failure": str(2 + index),
        "days_since_last_maintenance": str(5 + index),
        "target_failure_next_7d": str(failure),
        "target_unplanned_downtime_minutes_next_7d": str(failure * 45),
    }


def _rows_for_task(
    task: str,
    days: list[date],
    offset: int,
    *,
    single_class: bool,
) -> list[dict[str, str]]:
    rows: list[dict[str, str]] = []
    for position, day in enumerate(days):
        index = offset + position
        if task == "production_forecasting":
            rows.append(_forecast_row(day, index))
        elif task == "production_anomaly":
            rows.append(_anomaly_row(day, index))
        else:
            rows.append(_maintenance_row(day, index, single_class=single_class))
    definition = FEATURE_TASKS[task]
    return sorted(rows, key=lambda row: tuple(row[column] for column in definition.columns))


def _write_csv(path: Path, task: str, rows: list[dict[str, str]]) -> dict[str, object]:
    definition = FEATURE_TASKS[task]
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(
            handle,
            fieldnames=definition.columns,
            lineterminator="\n",
        )
        writer.writeheader()
        writer.writerows(rows)

    timestamps = []
    for row in rows:
        value = row[definition.timestamp_column]
        timestamps.append(date.fromisoformat(value[:10]))
    return {
        "row_count": len(rows),
        "byte_size": path.stat().st_size,
        "sha256": _sha256(path),
        "minimum_timestamp": min(timestamps).isoformat(),
        "maximum_timestamp": max(timestamps).isoformat(),
    }


def _fingerprint(manifest: dict) -> str:
    source = manifest["source_preprocessed_run"]
    lines = [
        "smartfactory.ml.feature.snapshot",
        "v1",
        source["run_id"],
        source["content_fingerprint"],
        "simulated_prototype",
        json.dumps(manifest["split_policy"], sort_keys=True, separators=(",", ":")),
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


def _feature_run(tmp_path: Path, *, single_class: bool = False) -> Path:
    root = tmp_path / "features" / FEATURE_RUN_ID
    split_days = {
        "train": [date(2026, 1, 1) + timedelta(days=index) for index in range(8)],
        "validation": [date(2026, 1, 20) + timedelta(days=index) for index in range(4)],
        "test": [date(2026, 2, 5) + timedelta(days=index) for index in range(4)],
    }
    task_manifests = []
    for task_name, definition in FEATURE_TASKS.items():
        splits = []
        generated = 0
        for split_index, split_name in enumerate(("train", "validation", "test")):
            rows = _rows_for_task(
                task_name,
                split_days[split_name],
                split_index * 20,
                single_class=single_class,
            )
            relative = f"data/{task_name}/{split_name}.csv"
            metadata = _write_csv(root / relative, task_name, rows)
            generated += len(rows)
            splits.append({"name": split_name, "file": relative, **metadata})
        task_manifests.append(
            {
                "name": task_name,
                "feature_schema_version": "v1",
                "timestamp_column": definition.timestamp_column,
                "target_end_exclusive_column": definition.target_end_exclusive_column,
                "label_horizon_days": definition.label_horizon_days,
                "source_datasets": list(definition.source_datasets),
                "target_columns": list(definition.target_columns),
                "columns": list(definition.columns),
                "generated_row_count": generated,
                "purged_row_count": 0,
                "retained_row_count": generated,
                "splits": splits,
            }
        )

    manifest = {
        "manifest_version": "v1",
        "feature_contract": "smartfactory.ml.feature.snapshot",
        "ruleset_version": "v1",
        "run_id": FEATURE_RUN_ID,
        "generated_at": "2026-02-10T12:00:00Z",
        "source_preprocessed_run": {
            "run_id": PREPROCESSED_RUN_ID,
            "content_fingerprint": "a" * 64,
            "preprocessing_contract": "smartfactory.ml.preprocessed.snapshot",
            "ruleset_version": "v1",
            "source_snapshot_id": SNAPSHOT_ID,
            "period": {
                "start_date": "2026-01-01",
                "end_date": "2026-02-28",
                "timezone": "Africa/Casablanca",
                "utc_start": "2026-01-01T00:00:00Z",
                "utc_end_exclusive": "2026-03-01T00:00:00Z",
            },
        },
        "source_system": "simulated_sage",
        "data_classification": "simulated_prototype",
        "split_policy": {
            "strategy": "global_chronological",
            "train_ratio": "0.70",
            "validation_ratio": "0.15",
            "test_ratio": "0.15",
            "supervised_boundary_purge": True,
        },
        "total_rows": sum(task["retained_row_count"] for task in task_manifests),
        "purged_row_count": 0,
        "tasks": task_manifests,
        "content_fingerprint": "",
    }
    manifest["content_fingerprint"] = _fingerprint(manifest)
    manifest_path = root / "manifest.json"
    manifest_path.write_text(
        json.dumps(manifest, indent=2) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    (root / "manifest.sha256").write_text(
        f"{_sha256(manifest_path)}  manifest.json\n",
        encoding="ascii",
        newline="\n",
    )
    return root


def test_training_run_is_atomic_versioned_and_valid(tmp_path: Path) -> None:
    feature_run = _feature_run(tmp_path)
    model_root = tmp_path / "models"

    receipt = ModelTrainingPipeline(random_seed=42).run(feature_run, model_root)
    run = Path(receipt.run_path)
    validated = ModelRunValidator().validate(run)

    assert UUID(receipt.run_id)
    assert validated.tasks == (
        "production_forecasting",
        "production_anomaly",
        "maintenance_risk",
    )
    assert (model_root / "MODELS_LATEST").read_text(encoding="ascii").strip() == receipt.run_id
    assert not (model_root / ".model-training.lock").exists()

    manifest = json.loads((run / "manifest.json").read_text(encoding="utf-8"))
    assert manifest["data_classification"] == "simulated_prototype"
    assert manifest["source_feature_run"]["run_id"] == FEATURE_RUN_ID
    assert {task["name"] for task in manifest["tasks"]} == set(FEATURE_TASKS)

    anomaly_metrics = json.loads(
        (run / "metrics" / "production_anomaly.json").read_text(encoding="utf-8")
    )
    assert "accuracy" not in anomaly_metrics["test_metrics"]
    assert "ground-truth anomaly labels" in " ".join(anomaly_metrics["limitations"])

    artifact = run / "artifacts" / "maintenance_risk.joblib"
    assert artifact.is_file()
    assert artifact.stat().st_size > 0


def test_model_file_tampering_is_detected(tmp_path: Path) -> None:
    run = Path(ModelTrainingPipeline().run(_feature_run(tmp_path), tmp_path / "models").run_path)
    metrics = run / "metrics" / "production_forecasting.json"
    metrics.write_text(metrics.read_text(encoding="utf-8") + " ", encoding="utf-8")

    with pytest.raises(ModelRunValidationError) as raised:
        ModelRunValidator().validate(run)

    assert raised.value.code == "model_file_size_mismatch"


def test_single_class_maintenance_uses_safe_baseline(tmp_path: Path) -> None:
    run = Path(
        ModelTrainingPipeline()
        .run(
            _feature_run(tmp_path, single_class=True),
            tmp_path / "models",
        )
        .run_path
    )
    metrics = json.loads((run / "metrics" / "maintenance_risk.json").read_text(encoding="utf-8"))

    assert metrics["failure_classifier"]["selected_model"] == "dummy_prior_classifier"
    assert "one failure class" in " ".join(metrics["limitations"])


def test_cli_trains_and_verifies_with_compact_receipts(
    tmp_path: Path,
    capsys: pytest.CaptureFixture[str],
) -> None:
    feature_run = _feature_run(tmp_path)
    model_root = tmp_path / "models"

    assert (
        main(
            [
                "train",
                "--feature-run",
                str(feature_run),
                "--output-root",
                str(model_root),
            ]
        )
        == 0
    )
    train_output = json.loads(capsys.readouterr().out)
    assert train_output["status"] == "trained"
    assert "metrics" not in train_output

    run = model_root / "runs" / train_output["run_id"]
    assert main(["verify", "--run", str(run)]) == 0
    verify_output = json.loads(capsys.readouterr().out)
    assert verify_output["status"] == "valid"
    assert capsys.readouterr().err == ""


def test_cli_missing_feature_run_returns_safe_error(
    tmp_path: Path,
    capsys: pytest.CaptureFixture[str],
) -> None:
    exit_code = main(
        [
            "train",
            "--feature-run",
            str(tmp_path / "missing"),
            "--output-root",
            str(tmp_path / "models"),
        ]
    )

    assert exit_code == 1
    payload = json.loads(capsys.readouterr().err)
    assert payload["error"]["code"] == "feature_run_not_found"
    assert "traceback" not in json.dumps(payload).lower()


@pytest.mark.parametrize(
    ("keyword", "value"),
    [
        ("random_seed", 0),
        ("random_seed", 2_147_483_648),
        ("anomaly_contamination", 0.0),
        ("anomaly_contamination", 0.5),
    ],
)
def test_training_configuration_is_strict(keyword: str, value: int | float) -> None:
    with pytest.raises(ValueError):
        ModelTrainingPipeline(**{keyword: value})


def test_model_output_must_not_be_inside_feature_run(tmp_path: Path) -> None:
    feature_run = _feature_run(tmp_path)
    with pytest.raises(ModelTrainingError, match="must not be inside"):
        ModelTrainingPipeline().run(feature_run, feature_run / "models")


def test_metric_helpers_handle_zero_targets_and_single_classes() -> None:
    regression = regression_metrics(np.array([0.0, 10.0]), np.array([1.0, 9.0]))
    assert regression["mape_eligible_row_count"] == 1
    assert regression["rmse"] is not None

    classification = classification_metrics(
        np.array([0, 0]),
        np.array([0, 0]),
        np.array([0.1, 0.2]),
    )
    assert classification["roc_auc"] is None
    assert classification["average_precision"] is None

    anomaly = anomaly_score_metrics(np.array([0.1, 0.2, 0.9]), 0.5)
    assert anomaly["anomaly_count"] == 1

from __future__ import annotations

import csv
import hashlib
import json
from datetime import UTC, date, datetime, time, timedelta
from pathlib import Path

import pytest

from app.cli.datasets import main
from app.datasets.schema import DATASET_COLUMNS
from app.features.engineering import (
    maintenance_risk_rows,
    production_forecasting_rows,
)
from app.features.pipeline import (
    FeatureEngineeringError,
    FeatureEngineeringPipeline,
)
from app.features.validator import (
    FeatureRunValidationError,
    FeatureRunValidator,
)
from app.preprocessing.pipeline import DatasetPreprocessingPipeline

SNAPSHOT_ID = "11111111-1111-4111-8111-111111111111"


def _iso(day: date, hour: int) -> str:
    return (
        datetime.combine(day, time(hour), tzinfo=UTC)
        .isoformat()
        .replace(
            "+00:00",
            "Z",
        )
    )


def _rows(start: date, days: int = 120) -> dict[str, list[dict[str, str]]]:
    production: list[dict[str, str]] = []
    downtime: list[dict[str, str]] = []
    status: list[dict[str, str]] = []
    maintenance: list[dict[str, str]] = []

    for index in range(days):
        day = start + timedelta(days=index)
        produced = 900 + (index % 11) * 10
        rejected = index % 7
        production.append(
            {
                "production_date": day.isoformat(),
                "started_at_utc": _iso(day, 6),
                "ended_at_utc": _iso(day, 14),
                "production_line_code": "line-01",
                "product_family_code": "valencia-premium",
                "product_code": "orange-1l",
                "shift_code": "shift-a",
                "production_order_status": "completed",
                "production_order_priority": "2",
                "record_status": "locked",
                "validation_status": "validated",
                "quantity_unit": "l",
                "target_quantity": "1000",
                "produced_quantity": str(produced),
                "good_quantity": str(produced - rejected),
                "rejected_quantity": str(rejected),
                "runtime_minutes": "420",
                "downtime_minutes": str(index % 30),
                "is_validated": "1",
                "import_status": "imported",
                "source_version": "",
                "source_updated_at_utc": _iso(day, 15),
            }
        )

        fault = index % 8 == 0
        status.append(
            {
                "occurred_at_utc": _iso(day, 7),
                "ended_at_utc": _iso(day, 8),
                "production_line_code": "line-01",
                "machine_code": "filler-01",
                "machine_type": "filler",
                "is_critical": "1" if fault else "0",
                "status": "fault" if fault else "running",
                "duration_minutes": "60",
                "import_status": "imported",
                "source_version": "1",
                "source_updated_at_utc": _iso(day, 8),
            }
        )

        if fault:
            downtime.append(
                {
                    "started_at_utc": _iso(day, 7),
                    "ended_at_utc": _iso(day, 8),
                    "production_line_code": "line-01",
                    "machine_code": "filler-01",
                    "machine_type": "filler",
                    "shift_code": "shift-a",
                    "severity": "major",
                    "category": "unplanned",
                    "downtime_type": "breakdown",
                    "duration_minutes": "60",
                    "is_resolved": "1",
                    "import_status": "imported",
                    "source_version": "",
                    "source_updated_at_utc": _iso(day, 8),
                }
            )

        if index % 14 == 0:
            maintenance.append(
                {
                    "scheduled_at_utc": _iso(day, 9),
                    "started_at_utc": _iso(day, 9),
                    "completed_at_utc": _iso(day, 10),
                    "production_line_code": "line-01",
                    "machine_code": "filler-01",
                    "machine_type": "filler",
                    "is_critical": "0",
                    "maintenance_type": ("preventive" if index % 28 == 0 else "corrective"),
                    "status": "completed",
                    "downtime_minutes": "60",
                    "cost": "100",
                    "currency": "MAD",
                    "import_status": "imported",
                    "source_version": "1",
                    "source_updated_at_utc": _iso(day, 10),
                }
            )

    return {
        "production_records": production,
        "downtime_events": downtime,
        "machine_status_events": status,
        "maintenance_history": maintenance,
    }


def _snapshot(
    tmp_path: Path,
    rows_by_dataset: dict[str, list[dict[str, str]]],
    *,
    start: date,
    end: date,
) -> Path:
    root = tmp_path / "datasets" / "snapshots" / SNAPSHOT_ID
    (root / "data").mkdir(parents=True)
    datasets = []

    for name, rows in rows_by_dataset.items():
        path = root / "data" / f"{name}.csv"
        with path.open("w", encoding="utf-8", newline="") as handle:
            writer = csv.DictWriter(
                handle,
                fieldnames=DATASET_COLUMNS[name],
                lineterminator="\n",
            )
            writer.writeheader()
            writer.writerows(rows)
        payload = path.read_bytes()
        datasets.append(
            {
                "name": name,
                "file": f"data/{name}.csv",
                "schema_version": "v1",
                "row_count": len(rows),
                "byte_size": len(payload),
                "sha256": hashlib.sha256(payload).hexdigest(),
                "columns": DATASET_COLUMNS[name],
            }
        )

    manifest = {
        "manifest_version": "v1",
        "dataset_contract": "smartfactory.ml.dataset.snapshot",
        "dataset_schema_version": "v1",
        "snapshot_id": SNAPSHOT_ID,
        "source_application": "smartfactory-dss-laravel",
        "source_system": "simulated_sage",
        "data_classification": "simulated_prototype",
        "generated_at": _iso(end, 22),
        "period": {
            "start_date": start.isoformat(),
            "end_date": end.isoformat(),
            "timezone": "Africa/Casablanca",
            "utc_start": _iso(start - timedelta(days=1), 23),
            "utc_end_exclusive": _iso(end, 23),
        },
        "generator": {"name": "smartfactory-dss-laravel", "version": "0.1.0"},
        "total_rows": sum(len(rows) for rows in rows_by_dataset.values()),
        "datasets": datasets,
        "content_fingerprint": "",
    }
    lines = [
        manifest["dataset_contract"],
        manifest["dataset_schema_version"],
        manifest["source_system"],
        manifest["data_classification"],
        manifest["period"]["start_date"],
        manifest["period"]["end_date"],
        manifest["period"]["timezone"],
    ]
    for dataset in sorted(datasets, key=lambda item: item["name"]):
        lines.append(
            "|".join(
                [
                    dataset["name"],
                    dataset["sha256"],
                    str(dataset["row_count"]),
                    str(dataset["byte_size"]),
                ]
            )
        )
    manifest["content_fingerprint"] = hashlib.sha256("\n".join(lines).encode()).hexdigest()

    manifest_path = root / "manifest.json"
    manifest_path.write_text(
        json.dumps(manifest, indent=2) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    manifest_hash = hashlib.sha256(manifest_path.read_bytes()).hexdigest()
    (root / "manifest.sha256").write_text(
        f"{manifest_hash}  manifest.json\n",
        encoding="ascii",
        newline="\n",
    )
    return root


def _preprocessed_run(tmp_path: Path) -> Path:
    start = date(2026, 1, 1)
    rows = _rows(start)
    snapshot = _snapshot(
        tmp_path,
        rows,
        start=start,
        end=start + timedelta(days=119),
    )
    receipt = DatasetPreprocessingPipeline().run(
        snapshot,
        tmp_path / "datasets" / "preprocessed",
    )
    return Path(receipt.run_path)


def test_feature_pipeline_creates_three_verified_chronological_tasks(
    tmp_path: Path,
) -> None:
    preprocessed = _preprocessed_run(tmp_path)
    output = tmp_path / "datasets" / "features"

    receipt = FeatureEngineeringPipeline().run(preprocessed, output)

    assert receipt.total_rows > 100
    assert receipt.purged_rows > 0
    assert set(receipt.task_rows) == {
        "production_forecasting",
        "production_anomaly",
        "maintenance_risk",
    }
    run = Path(receipt.run_path)
    assert (output / "FEATURE_LATEST").read_text().strip() == receipt.run_id

    verified = FeatureRunValidator().validate(run)
    assert verified.total_rows == receipt.total_rows
    assert verified.tasks == (
        "production_forecasting",
        "production_anomaly",
        "maintenance_risk",
    )

    manifest = json.loads((run / "manifest.json").read_text())
    assert manifest["data_classification"] == "simulated_prototype"
    assert manifest["split_policy"]["supervised_boundary_purge"] is True
    for task in manifest["tasks"]:
        assert [split["name"] for split in task["splits"]] == [
            "train",
            "validation",
            "test",
        ]
        assert all(split["row_count"] > 0 for split in task["splits"])


def test_feature_files_do_not_cross_supervised_split_boundaries(
    tmp_path: Path,
) -> None:
    run = Path(
        FeatureEngineeringPipeline()
        .run(_preprocessed_run(tmp_path), tmp_path / "features")
        .run_path
    )
    manifest = json.loads((run / "manifest.json").read_text())

    for task in manifest["tasks"]:
        target_column = task["target_end_exclusive_column"]
        if target_column is None:
            continue
        splits = {split["name"]: split for split in task["splits"]}
        validation_start = date.fromisoformat(splits["validation"]["minimum_timestamp"])
        test_start = date.fromisoformat(splits["test"]["minimum_timestamp"])

        for split_name, boundary in (
            ("train", validation_start),
            ("validation", test_start),
        ):
            path = run / splits[split_name]["file"]
            rows = list(csv.DictReader(path.open("r", encoding="utf-8", newline="")))
            assert max(date.fromisoformat(row[target_column]) for row in rows) <= boundary


def test_production_forecast_features_do_not_use_next_day_value_as_input() -> None:
    start = date(2026, 1, 1)
    rows = _rows(start, days=10)["production_records"]
    before = production_forecasting_rows(rows)
    changed = [dict(row) for row in rows]
    changed[5]["good_quantity"] = "1"
    after = production_forecasting_rows(changed)

    before_row = next(row for row in before if row["target_date"] == changed[5]["production_date"])
    after_row = next(row for row in after if row["target_date"] == changed[5]["production_date"])

    feature_columns = [key for key in before_row if key != "target_good_quantity_next_day"]
    assert {key: before_row[key] for key in feature_columns} == {
        key: after_row[key] for key in feature_columns
    }
    assert before_row["target_good_quantity_next_day"] != after_row["target_good_quantity_next_day"]


def test_maintenance_targets_use_only_the_future_label_window() -> None:
    start = date(2026, 1, 1)
    rows = _rows(start, days=60)
    features = maintenance_risk_rows(
        downtime_rows=rows["downtime_events"],
        status_rows=rows["machine_status_events"],
        maintenance_rows=rows["maintenance_history"],
        period_start=start,
        period_end=start + timedelta(days=59),
    )
    row = next(item for item in features if item["prediction_date"] == "2026-02-02")

    assert row["target_failure_next_7d"] in {"0", "1"}
    assert int(row["status_event_count_7d"]) == 7
    assert int(row["maintenance_event_count_30d"]) >= 2
    assert row["target_window_end_date"] == "2026-02-09"


def test_feature_run_is_reproducible_for_same_preprocessed_content(
    tmp_path: Path,
) -> None:
    preprocessed = _preprocessed_run(tmp_path)
    first = FeatureEngineeringPipeline().run(preprocessed, tmp_path / "features-a")
    second = FeatureEngineeringPipeline().run(preprocessed, tmp_path / "features-b")

    assert first.content_fingerprint == second.content_fingerprint
    assert first.task_rows == second.task_rows


def test_feature_tampering_is_detected(tmp_path: Path) -> None:
    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path),
        tmp_path / "features",
    )
    run = Path(receipt.run_path)
    path = run / "data" / "production_anomaly" / "train.csv"
    payload = bytearray(path.read_bytes())
    payload[-2] = ord("8") if payload[-2] != ord("8") else ord("7")
    path.write_bytes(payload)

    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)

    assert raised.value.code == "feature_checksum_mismatch"


def test_feature_manifest_checksum_tampering_is_detected(tmp_path: Path) -> None:
    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path),
        tmp_path / "features",
    )
    run = Path(receipt.run_path)
    (run / "manifest.json").write_text("{}\n", encoding="utf-8")

    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)

    assert raised.value.code == "manifest_checksum_mismatch"


def test_invalid_ratios_and_unsafe_output_are_rejected(tmp_path: Path) -> None:
    with pytest.raises(ValueError):
        FeatureEngineeringPipeline(
            train_ratio="0.7",
            validation_ratio="0.2",
            test_ratio="0.2",
        )
    with pytest.raises(ValueError):
        FeatureEngineeringPipeline(
            train_ratio="0",
            validation_ratio="0.5",
            test_ratio="0.5",
        )

    preprocessed = _preprocessed_run(tmp_path)
    with pytest.raises(FeatureEngineeringError) as raised:
        FeatureEngineeringPipeline().run(preprocessed, preprocessed / "features")
    assert raised.value.code == "unsafe_feature_output_root"


def test_existing_feature_lock_is_rejected(tmp_path: Path) -> None:
    preprocessed = _preprocessed_run(tmp_path)
    output = tmp_path / "features"
    output.mkdir()
    (output / ".feature-engineering.lock").write_text("busy\n")

    with pytest.raises(FeatureEngineeringError) as raised:
        FeatureEngineeringPipeline().run(preprocessed, output)

    assert raised.value.code == "feature_engineering_locked"


def test_validator_limits_and_missing_run_are_safe(tmp_path: Path) -> None:
    with pytest.raises(ValueError):
        FeatureRunValidator(manifest_max_bytes=1)
    with pytest.raises(ValueError):
        FeatureRunValidator(file_max_bytes=1)
    with pytest.raises(ValueError):
        FeatureRunValidator(max_rows_per_file=0)
    with pytest.raises(ValueError):
        FeatureRunValidator(max_cell_characters=0)

    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(tmp_path / "missing")
    assert raised.value.code == "feature_run_not_found"


def test_cli_features_and_verify_features(tmp_path: Path, capsys) -> None:
    preprocessed = _preprocessed_run(tmp_path)
    output = tmp_path / "features"

    exit_code = main(
        [
            "features",
            "--run",
            str(preprocessed),
            "--output-root",
            str(output),
        ]
    )
    assert exit_code == 0
    receipt = json.loads(capsys.readouterr().out)
    assert receipt["status"] == "featured"

    verify_code = main(
        [
            "verify-features",
            "--run",
            receipt["run_path"],
        ]
    )
    assert verify_code == 0
    verified = json.loads(capsys.readouterr().out)
    assert verified["status"] == "valid"


def test_cli_reports_safe_feature_error(tmp_path: Path, capsys) -> None:
    exit_code = main(
        [
            "features",
            "--run",
            str(tmp_path / "missing"),
            "--output-root",
            str(tmp_path / "features"),
        ]
    )

    assert exit_code == 1
    error = json.loads(capsys.readouterr().err)
    assert error["error"]["code"] == "preprocessed_run_not_found"
    assert "traceback" not in json.dumps(error).lower()


def _rewrite_feature_manifest(run: Path, manifest: dict) -> None:
    path = run / "manifest.json"
    path.write_text(
        json.dumps(manifest, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    digest = hashlib.sha256(path.read_bytes()).hexdigest()
    (run / "manifest.sha256").write_text(
        f"{digest}  manifest.json\n",
        encoding="ascii",
        newline="\n",
    )


@pytest.mark.parametrize(
    ("mutation", "expected_code"),
    [
        (lambda manifest: manifest.pop("source_system"), "invalid_manifest"),
        (lambda manifest: manifest.__setitem__("manifest_version", "v2"), "invalid_manifest"),
        (lambda manifest: manifest.__setitem__("feature_contract", "wrong"), "invalid_manifest"),
        (lambda manifest: manifest.__setitem__("ruleset_version", "v2"), "invalid_manifest"),
        (
            lambda manifest: manifest.__setitem__(
                "data_classification",
                "real_company_data",
            ),
            "invalid_manifest",
        ),
        (lambda manifest: manifest.__setitem__("run_id", "invalid"), "invalid_manifest"),
        (
            lambda manifest: manifest.__setitem__(
                "generated_at",
                "2026-01-01T00:00:00",
            ),
            "invalid_manifest",
        ),
        (lambda manifest: manifest.__setitem__("purged_row_count", True), "invalid_manifest"),
        (
            lambda manifest: manifest.__setitem__(
                "content_fingerprint",
                "not-a-hash",
            ),
            "invalid_manifest",
        ),
        (
            lambda manifest: manifest.__setitem__(
                "source_preprocessed_run",
                {},
            ),
            "invalid_manifest",
        ),
        (
            lambda manifest: manifest["source_preprocessed_run"].__setitem__(
                "preprocessing_contract",
                "wrong",
            ),
            "invalid_manifest",
        ),
        (lambda manifest: manifest.__setitem__("split_policy", {}), "invalid_manifest"),
        (
            lambda manifest: manifest["split_policy"].__setitem__(
                "strategy",
                "random",
            ),
            "invalid_manifest",
        ),
        (
            lambda manifest: manifest["split_policy"].__setitem__(
                "supervised_boundary_purge",
                False,
            ),
            "invalid_manifest",
        ),
        (
            lambda manifest: manifest["split_policy"].__setitem__(
                "train_ratio",
                "invalid",
            ),
            "invalid_manifest",
        ),
        (
            lambda manifest: manifest["split_policy"].__setitem__(
                "train_ratio",
                "0.50",
            ),
            "invalid_manifest",
        ),
        (lambda manifest: manifest.__setitem__("tasks", []), "invalid_manifest"),
        (
            lambda manifest: manifest["tasks"][1].__setitem__(
                "name",
                manifest["tasks"][0]["name"],
            ),
            "invalid_manifest",
        ),
    ],
)
def test_manifest_contract_mutations_are_rejected(
    tmp_path: Path,
    mutation,
    expected_code: str,
) -> None:
    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path),
        tmp_path / "features",
    )
    run = Path(receipt.run_path)
    manifest = json.loads((run / "manifest.json").read_text())
    mutation(manifest)
    _rewrite_feature_manifest(run, manifest)

    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)

    assert raised.value.code == expected_code


@pytest.mark.parametrize(
    "mutation",
    [
        lambda task: task.pop("columns"),
        lambda task: task.__setitem__("name", "unknown_task"),
        lambda task: task.__setitem__("feature_schema_version", "v2"),
        lambda task: task.__setitem__("timestamp_column", "wrong"),
        lambda task: task.__setitem__("target_end_exclusive_column", "wrong"),
        lambda task: task.__setitem__("label_horizon_days", 99),
        lambda task: task.__setitem__("source_datasets", []),
        lambda task: task.__setitem__("target_columns", ["wrong"]),
        lambda task: task.__setitem__("columns", ["wrong"]),
        lambda task: task.__setitem__("generated_row_count", -1),
        lambda task: task.__setitem__(
            "generated_row_count",
            task["generated_row_count"] + 1,
        ),
        lambda task: task.__setitem__("splits", []),
        lambda task: task["splits"].reverse(),
    ],
)
def test_task_contract_mutations_are_rejected(
    tmp_path: Path,
    mutation,
) -> None:
    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path),
        tmp_path / "features",
    )
    run = Path(receipt.run_path)
    manifest = json.loads((run / "manifest.json").read_text())
    mutation(manifest["tasks"][0])
    _rewrite_feature_manifest(run, manifest)

    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)

    assert raised.value.code == "invalid_manifest"


@pytest.mark.parametrize(
    "mutation",
    [
        lambda split: split.pop("sha256"),
        lambda split: split.__setitem__("file", "../outside.csv"),
        lambda split: split.__setitem__("row_count", 0),
        lambda split: split.__setitem__("sha256", "invalid"),
        lambda split: split.__setitem__(
            "minimum_timestamp",
            "2030-01-01",
        ),
    ],
)
def test_split_contract_mutations_are_rejected(
    tmp_path: Path,
    mutation,
) -> None:
    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path),
        tmp_path / "features",
    )
    run = Path(receipt.run_path)
    manifest = json.loads((run / "manifest.json").read_text())
    mutation(manifest["tasks"][0]["splits"][0])
    _rewrite_feature_manifest(run, manifest)

    with pytest.raises((FeatureRunValidationError, ValueError)):
        FeatureRunValidator().validate(run)


def test_manifest_total_and_fingerprint_mismatches_are_detected(
    tmp_path: Path,
) -> None:
    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path),
        tmp_path / "features-a",
    )
    run = Path(receipt.run_path)
    manifest = json.loads((run / "manifest.json").read_text())
    manifest["total_rows"] += 1
    _rewrite_feature_manifest(run, manifest)

    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)
    assert raised.value.code == "feature_total_mismatch"

    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path / "second"),
        tmp_path / "features-b",
    )
    run = Path(receipt.run_path)
    manifest = json.loads((run / "manifest.json").read_text())
    manifest["content_fingerprint"] = "0" * 64
    _rewrite_feature_manifest(run, manifest)

    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)
    assert raised.value.code == "content_fingerprint_mismatch"


def test_checksum_and_path_validation_errors_are_safe(tmp_path: Path) -> None:
    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path),
        tmp_path / "features",
    )
    run = Path(receipt.run_path)

    (run / "manifest.sha256").write_text("invalid\n", encoding="ascii")
    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)
    assert raised.value.code == "manifest_checksum_invalid"

    (run / "manifest.sha256").unlink()
    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)
    assert raised.value.code == "manifest_checksum_missing"

    file_path = tmp_path / "not-a-directory"
    file_path.write_text("x", encoding="utf-8")
    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(file_path)
    assert raised.value.code == "feature_run_not_directory"


def test_feature_file_header_order_row_limit_and_cell_limit_are_detected(
    tmp_path: Path,
) -> None:
    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path),
        tmp_path / "features",
    )
    run = Path(receipt.run_path)
    manifest = json.loads((run / "manifest.json").read_text())
    split = manifest["tasks"][1]["splits"][0]
    path = run / split["file"]
    rows = list(csv.reader(path.open("r", encoding="utf-8", newline="")))

    rows[0][0] = "wrong"
    with path.open("w", encoding="utf-8", newline="") as handle:
        csv.writer(handle, lineterminator="\n").writerows(rows)
    payload = path.read_bytes()
    split["byte_size"] = len(payload)
    split["sha256"] = hashlib.sha256(payload).hexdigest()
    _rewrite_feature_manifest(run, manifest)
    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)
    assert raised.value.code == "feature_header_mismatch"

    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path / "ordered"),
        tmp_path / "features-ordered",
    )
    run = Path(receipt.run_path)
    manifest = json.loads((run / "manifest.json").read_text())
    split = manifest["tasks"][1]["splits"][0]
    path = run / split["file"]
    rows = list(csv.reader(path.open("r", encoding="utf-8", newline="")))
    rows[1], rows[2] = rows[2], rows[1]
    with path.open("w", encoding="utf-8", newline="") as handle:
        csv.writer(handle, lineterminator="\n").writerows(rows)
    payload = path.read_bytes()
    split["byte_size"] = len(payload)
    split["sha256"] = hashlib.sha256(payload).hexdigest()
    _rewrite_feature_manifest(run, manifest)
    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)
    assert raised.value.code == "feature_order_invalid"

    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path / "limits"),
        tmp_path / "features-limits",
    )
    run = Path(receipt.run_path)
    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator(max_rows_per_file=1).validate(run)
    assert raised.value.code == "feature_row_limit_exceeded"

    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator(max_cell_characters=2).validate(run)
    assert raised.value.code == "feature_cell_invalid"


def test_missing_feature_file_and_size_limits_are_detected(tmp_path: Path) -> None:
    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path),
        tmp_path / "features",
    )
    run = Path(receipt.run_path)
    manifest = json.loads((run / "manifest.json").read_text())
    split = manifest["tasks"][0]["splits"][0]
    path = run / split["file"]
    path.unlink()

    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)
    assert raised.value.code == "feature_file_missing"

    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path / "size"),
        tmp_path / "features-size",
    )
    run = Path(receipt.run_path)
    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator(file_max_bytes=1024).validate(run)
    assert raised.value.code == "feature_file_too_large"


def test_invalid_json_and_empty_feature_file_are_rejected(tmp_path: Path) -> None:
    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path),
        tmp_path / "features",
    )
    run = Path(receipt.run_path)
    manifest_path = run / "manifest.json"
    manifest_path.write_text("{invalid", encoding="utf-8")
    digest = hashlib.sha256(manifest_path.read_bytes()).hexdigest()
    (run / "manifest.sha256").write_text(
        f"{digest}  manifest.json\n",
        encoding="ascii",
    )
    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)
    assert raised.value.code == "invalid_manifest"

    receipt = FeatureEngineeringPipeline().run(
        _preprocessed_run(tmp_path / "empty"),
        tmp_path / "features-empty",
    )
    run = Path(receipt.run_path)
    manifest = json.loads((run / "manifest.json").read_text())
    split = manifest["tasks"][0]["splits"][0]
    path = run / split["file"]
    header = path.read_text(encoding="utf-8").partition("\n")[0] + "\n"
    path.write_text(header, encoding="utf-8")
    payload = path.read_bytes()
    split["byte_size"] = len(payload)
    split["sha256"] = hashlib.sha256(payload).hexdigest()
    split["row_count"] = 1
    _rewrite_feature_manifest(run, manifest)
    with pytest.raises(FeatureRunValidationError) as raised:
        FeatureRunValidator().validate(run)
    assert raised.value.code == "feature_file_empty"

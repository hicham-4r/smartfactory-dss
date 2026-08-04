from __future__ import annotations

import csv
import hashlib
import json
from pathlib import Path
from uuid import UUID

import pytest

from app.cli.datasets import main
from app.datasets.schema import DATASET_COLUMNS
from app.preprocessing.normalization import normalize_row
from app.preprocessing.pipeline import (
    DatasetPreprocessingError,
    DatasetPreprocessingPipeline,
)
from app.preprocessing.validator import (
    PreprocessedRunValidationError,
    PreprocessedRunValidator,
)

SNAPSHOT_ID = "11111111-1111-4111-8111-111111111111"


def _valid_rows() -> dict[str, list[dict[str, str]]]:
    return {
        "production_records": [
            {
                "production_date": "2026-08-01",
                "started_at_utc": "2026-08-01T06:00:00+00:00",
                "ended_at_utc": "2026-08-01T14:00:00+00:00",
                "production_line_code": "line-01",
                "product_family_code": "valencia-premium",
                "product_code": "orange-1l",
                "shift_code": "shift-a",
                "production_order_status": "Completed",
                "production_order_priority": "2",
                "record_status": "Locked",
                "validation_status": "Validated",
                "quantity_unit": "l",
                "target_quantity": "1000.000",
                "produced_quantity": "980.000",
                "good_quantity": "970.000",
                "rejected_quantity": "10.000",
                "runtime_minutes": "420",
                "downtime_minutes": "20",
                "is_validated": "true",
                "import_status": "Imported",
                "source_version": "7",
                "source_updated_at_utc": "2026-08-01T15:00:00Z",
            }
        ],
        "downtime_events": [
            {
                "started_at_utc": "2026-08-01T10:00:00Z",
                "ended_at_utc": "2026-08-01T10:30:00Z",
                "production_line_code": "line-01",
                "machine_code": "filler-01",
                "machine_type": "Filler",
                "shift_code": "shift-a",
                "severity": "Major",
                "category": "Unplanned",
                "downtime_type": "Mechanical",
                "duration_minutes": "30",
                "is_resolved": "1",
                "import_status": "Imported",
                "source_version": "2",
                "source_updated_at_utc": "2026-08-01T11:00:00Z",
            }
        ],
        "machine_status_events": [
            {
                "occurred_at_utc": "2026-08-01T06:00:00Z",
                "ended_at_utc": "2026-08-01T08:00:00Z",
                "production_line_code": "line-01",
                "machine_code": "filler-01",
                "machine_type": "Filler",
                "is_critical": "false",
                "status": "Running",
                "duration_minutes": "120",
                "import_status": "Imported",
                "source_version": "3",
                "source_updated_at_utc": "2026-08-01T08:00:00Z",
            }
        ],
        "maintenance_history": [
            {
                "scheduled_at_utc": "2026-08-01T09:00:00Z",
                "started_at_utc": "2026-08-01T09:05:00Z",
                "completed_at_utc": "2026-08-01T09:45:00Z",
                "production_line_code": "line-01",
                "machine_code": "filler-01",
                "machine_type": "Filler",
                "is_critical": "0",
                "maintenance_type": "Corrective",
                "status": "Completed",
                "downtime_minutes": "40",
                "cost": "250.00",
                "currency": "mad",
                "import_status": "Imported",
                "source_version": "4",
                "source_updated_at_utc": "2026-08-01T10:00:00Z",
            }
        ],
        "quality_inspections": [
            {
                "inspected_at_utc": "2026-08-01T16:00:00Z",
                "production_line_code": "line-01",
                "product_family_code": "valencia-premium",
                "product_code": "orange-1l",
                "inspection_type": "Final",
                "result": "Passed",
                "sample_size": "100",
                "passed_quantity": "99",
                "failed_quantity": "1",
                "import_status": "Imported",
                "source_version": "5",
                "source_updated_at_utc": "2026-08-01T16:10:00Z",
            }
        ],
        "finished_lots": [
            {
                "produced_at_utc": "2026-08-01T14:00:00Z",
                "expiry_date": "2027-08-01",
                "released_at_utc": "2026-08-01T18:00:00Z",
                "production_line_code": "line-01",
                "product_family_code": "valencia-premium",
                "product_code": "orange-1l",
                "status": "Released",
                "quantity_unit": "l",
                "produced_quantity": "980",
                "released_quantity": "970",
                "rejected_quantity": "10",
                "import_status": "Imported",
                "source_version": "6",
                "source_updated_at_utc": "2026-08-01T18:00:00Z",
            }
        ],
        "nonconformities": [
            {
                "detected_at_utc": "2026-08-01T16:00:00Z",
                "corrected_at_utc": "2026-08-01T17:00:00Z",
                "production_line_code": "line-01",
                "product_family_code": "valencia-premium",
                "product_code": "orange-1l",
                "severity": "Minor",
                "status": "Resolved",
                "category": "Label",
                "import_status": "Imported",
                "source_version": "8",
                "source_updated_at_utc": "2026-08-01T17:00:00Z",
            }
        ],
    }


def _fingerprint(manifest: dict) -> str:
    period = manifest["period"]
    lines = [
        manifest["dataset_contract"],
        manifest["dataset_schema_version"],
        manifest["source_system"],
        manifest["data_classification"],
        period["start_date"],
        period["end_date"],
        period["timezone"],
    ]
    for dataset in sorted(manifest["datasets"], key=lambda item: item["name"]):
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
    return hashlib.sha256("\n".join(lines).encode()).hexdigest()


def _write_snapshot(
    tmp_path: Path,
    rows_by_dataset: dict[str, list[dict[str, str]]],
) -> Path:
    root = tmp_path / "datasets" / "snapshots" / SNAPSHOT_ID
    (root / "data").mkdir(parents=True)
    dataset_manifests = []

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
        dataset_manifests.append(
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
        "generated_at": "2026-08-02T22:00:00Z",
        "period": {
            "start_date": "2026-08-01",
            "end_date": "2026-08-02",
            "timezone": "Africa/Casablanca",
            "utc_start": "2026-07-31T23:00:00Z",
            "utc_end_exclusive": "2026-08-02T23:00:00Z",
        },
        "generator": {"name": "smartfactory-dss-laravel", "version": "0.1.0"},
        "total_rows": sum(len(rows) for rows in rows_by_dataset.values()),
        "datasets": dataset_manifests,
        "content_fingerprint": "",
    }
    manifest["content_fingerprint"] = _fingerprint(manifest)
    manifest_path = root / "manifest.json"
    manifest_path.write_text(
        json.dumps(manifest, indent=2) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    digest = hashlib.sha256(manifest_path.read_bytes()).hexdigest()
    (root / "manifest.sha256").write_text(
        f"{digest}  manifest.json\n",
        encoding="ascii",
        newline="\n",
    )
    return root


def test_pipeline_processes_all_registered_datasets(tmp_path: Path) -> None:
    snapshot = _write_snapshot(tmp_path, _valid_rows())
    output_root = tmp_path / "datasets" / "preprocessed"

    receipt = DatasetPreprocessingPipeline().run(snapshot, output_root)

    assert UUID(receipt.run_id)
    assert receipt.input_rows == 7
    assert receipt.output_rows == 7
    assert receipt.rejected_rows == 0
    assert receipt.duplicate_rows == 0
    assert receipt.quality_status == "passed"
    run = Path(receipt.run_path)
    assert run == output_root / "runs" / receipt.run_id
    assert (output_root / "PREPROCESSED_LATEST").read_text().strip() == receipt.run_id
    verified = PreprocessedRunValidator().validate(run)
    assert verified.total_rows == 7
    assert len(verified.datasets) == 7

    production = next(
        iter(
            csv.DictReader(
                (run / "data" / "production_records.csv").open("r", encoding="utf-8", newline="")
            )
        )
    )
    assert production["production_line_code"] == "LINE-01"
    assert production["production_order_status"] == "completed"
    assert production["quantity_unit"] == "L"
    assert production["target_quantity"] == "1000"
    assert production["is_validated"] == "1"


def test_pipeline_rejects_invalid_row_and_removes_duplicate(tmp_path: Path) -> None:
    rows = _valid_rows()
    original = rows["production_records"][0]
    duplicate = {key: f" {value} " for key, value in original.items()}
    invalid = dict(original)
    invalid["good_quantity"] = "980"
    invalid["rejected_quantity"] = "20"
    rows = {"production_records": [original, duplicate, invalid]}
    snapshot = _write_snapshot(tmp_path, rows)

    receipt = DatasetPreprocessingPipeline().run(
        snapshot,
        tmp_path / "datasets" / "preprocessed",
    )

    assert receipt.input_rows == 3
    assert receipt.output_rows == 1
    assert receipt.rejected_rows == 1
    assert receipt.duplicate_rows == 1
    assert receipt.quality_status == "passed_with_warnings"
    run = Path(receipt.run_path)
    report = json.loads((run / "quality-report.json").read_text())
    assert report["policies"]["numeric_imputation"] == "not_performed"
    assert report["policies"]["raw_values_in_report"] is False
    assert report["summary"]["rejected_row_count"] == 1
    issues = (run / "issues" / "production_records.jsonl").read_text()
    assert "quantity_balance_invalid" in issues
    assert "duplicate_row_removed" in issues
    assert "980.000" not in issues


def test_strict_mode_does_not_publish_rejected_rows(tmp_path: Path) -> None:
    row = _valid_rows()["quality_inspections"][0]
    invalid = dict(row, sample_size="1", passed_quantity="1", failed_quantity="1")
    snapshot = _write_snapshot(
        tmp_path,
        {"quality_inspections": [row, invalid]},
    )
    output = tmp_path / "datasets" / "preprocessed"

    with pytest.raises(DatasetPreprocessingError) as raised:
        DatasetPreprocessingPipeline(fail_on_rejected=True).run(snapshot, output)

    assert raised.value.code == "rejected_rows_present"
    assert not (output / "PREPROCESSED_LATEST").exists()
    assert list((output / ".staging").glob("*")) == []


def test_all_invalid_nonempty_dataset_is_not_published(tmp_path: Path) -> None:
    row = _valid_rows()["downtime_events"][0]
    invalid = dict(row, ended_at_utc="", is_resolved="1")
    snapshot = _write_snapshot(tmp_path, {"downtime_events": [invalid]})

    with pytest.raises(DatasetPreprocessingError) as raised:
        DatasetPreprocessingPipeline().run(
            snapshot,
            tmp_path / "datasets" / "preprocessed",
        )

    assert raised.value.code == "dataset_fully_rejected"


def test_normalization_rejects_invalid_types_and_time_order() -> None:
    row = _valid_rows()["maintenance_history"][0]
    row = dict(
        row,
        started_at_utc="2026-08-01T10:00:00Z",
        completed_at_utc="2026-08-01T09:00:00Z",
        source_version="1.5",
        is_critical="maybe",
        cost="nan",
    )

    _, issues = normalize_row("maintenance_history", row)

    codes = {issue.code for issue in issues}
    assert "invalid_integer" in codes
    assert "invalid_boolean" in codes
    assert "non_finite_number" in codes


def test_normalization_detects_logical_rules_and_formula_prefixes() -> None:
    finished = dict(
        _valid_rows()["finished_lots"][0],
        production_line_code="=line-01",
        released_quantity="980",
        rejected_quantity="20",
        expiry_date="2025-01-01",
        released_at_utc="2026-07-31T00:00:00Z",
    )

    normalized, issues = normalize_row("finished_lots", finished)

    assert normalized["production_line_code"] == "'=LINE-01"
    codes = {issue.code for issue in issues}
    assert "lot_quantity_balance_invalid" in codes
    assert "expiry_before_production" in codes
    assert "invalid_time_order" in codes


def test_optional_blank_values_are_preserved(tmp_path: Path) -> None:
    row = dict(
        _valid_rows()["maintenance_history"][0],
        started_at_utc="",
        completed_at_utc="",
        downtime_minutes="",
        cost="",
        currency="",
    )
    snapshot = _write_snapshot(tmp_path, {"maintenance_history": [row]})

    receipt = DatasetPreprocessingPipeline().run(
        snapshot,
        tmp_path / "datasets" / "preprocessed",
    )

    output = next(
        iter(
            csv.DictReader(
                (Path(receipt.run_path) / "data" / "maintenance_history.csv").open(
                    "r", encoding="utf-8", newline=""
                )
            )
        )
    )
    assert output["started_at_utc"] == ""
    assert output["cost"] == ""
    report = json.loads((Path(receipt.run_path) / "quality-report.json").read_text())
    stats = report["datasets"][0]["columns"]
    assert stats["cost"]["input_blank_count"] == 1
    assert stats["cost"]["output_blank_count"] == 1


def test_warning_row_is_kept(tmp_path: Path) -> None:
    row = dict(_valid_rows()["maintenance_history"][0], currency="")
    snapshot = _write_snapshot(tmp_path, {"maintenance_history": [row]})

    receipt = DatasetPreprocessingPipeline().run(
        snapshot,
        tmp_path / "datasets" / "preprocessed",
    )

    assert receipt.output_rows == 1
    assert receipt.quality_status == "passed_with_warnings"
    issues = (Path(receipt.run_path) / "issues" / "maintenance_history.jsonl").read_text()
    assert "cost_without_currency" in issues


def test_issue_storage_limit_records_omissions(tmp_path: Path) -> None:
    rows = _valid_rows()
    invalid = dict(rows["production_records"][0], target_quantity="bad")
    snapshot = _write_snapshot(
        tmp_path,
        {
            "production_records": [rows["production_records"][0], invalid, invalid],
            "quality_inspections": rows["quality_inspections"],
        },
    )

    receipt = DatasetPreprocessingPipeline(maximum_stored_issues=1).run(
        snapshot,
        tmp_path / "datasets" / "preprocessed",
    )

    manifest = json.loads((Path(receipt.run_path) / "manifest.json").read_text())
    production_issues = next(
        item for item in manifest["issue_files"] if item["dataset"] == "production_records"
    )
    assert production_issues["issue_count"] == 2
    assert production_issues["stored_issue_count"] == 1
    assert production_issues["omitted_issue_count"] == 1


def test_unique_tracking_can_be_capped(tmp_path: Path) -> None:
    base = _valid_rows()["production_records"][0]
    rows = []
    for index in range(101):
        rows.append(dict(base, product_code=f"P-{index:03d}"))
    snapshot = _write_snapshot(tmp_path, {"production_records": rows})

    receipt = DatasetPreprocessingPipeline(maximum_unique_values=100).run(
        snapshot,
        tmp_path / "datasets" / "preprocessed",
    )

    report = json.loads((Path(receipt.run_path) / "quality-report.json").read_text())
    stats = report["datasets"][0]["columns"]["product_code"]
    assert stats["unique_tracking_capped"] is True
    assert stats["unique_non_blank"] is None


def test_pipeline_rejects_output_inside_source_snapshot(tmp_path: Path) -> None:
    snapshot = _write_snapshot(
        tmp_path,
        {"production_records": _valid_rows()["production_records"]},
    )

    with pytest.raises(DatasetPreprocessingError) as raised:
        DatasetPreprocessingPipeline().run(snapshot, snapshot / "output")

    assert raised.value.code == "unsafe_output_root"


def test_pipeline_configuration_is_validated() -> None:
    with pytest.raises(ValueError):
        DatasetPreprocessingPipeline(maximum_stored_issues=-1)
    with pytest.raises(ValueError):
        DatasetPreprocessingPipeline(maximum_unique_values=99)


def test_existing_lock_is_rejected(tmp_path: Path) -> None:
    snapshot = _write_snapshot(
        tmp_path,
        {"production_records": _valid_rows()["production_records"]},
    )
    output = tmp_path / "datasets" / "preprocessed"
    output.mkdir(parents=True)
    (output / ".preprocessing.lock").write_text("busy\n")

    with pytest.raises(DatasetPreprocessingError) as raised:
        DatasetPreprocessingPipeline().run(snapshot, output)

    assert raised.value.code == "preprocessing_locked"


def test_preprocessed_tampering_is_detected(tmp_path: Path) -> None:
    snapshot = _write_snapshot(
        tmp_path,
        {"production_records": _valid_rows()["production_records"]},
    )
    receipt = DatasetPreprocessingPipeline().run(
        snapshot,
        tmp_path / "datasets" / "preprocessed",
    )
    run = Path(receipt.run_path)
    path = run / "data" / "production_records.csv"
    payload = bytearray(path.read_bytes())
    payload[-2] = ord("8") if payload[-2] != ord("8") else ord("7")
    path.write_bytes(payload)

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "file_checksum_mismatch"


def test_manifest_checksum_tampering_is_detected(tmp_path: Path) -> None:
    snapshot = _write_snapshot(
        tmp_path,
        {"production_records": _valid_rows()["production_records"]},
    )
    receipt = DatasetPreprocessingPipeline().run(
        snapshot,
        tmp_path / "datasets" / "preprocessed",
    )
    run = Path(receipt.run_path)
    (run / "manifest.json").write_text("{}\n", encoding="utf-8")

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "manifest_checksum_mismatch"


def test_validator_rejects_missing_run_and_invalid_limits(tmp_path: Path) -> None:
    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(tmp_path / "missing")
    assert raised.value.code == "run_not_found"

    with pytest.raises(ValueError):
        PreprocessedRunValidator(manifest_max_bytes=1)
    with pytest.raises(ValueError):
        PreprocessedRunValidator(file_max_bytes=1)
    with pytest.raises(ValueError):
        PreprocessedRunValidator(max_rows_per_file=0)


def test_cli_preprocess_and_verify_preprocessed(tmp_path: Path, capsys) -> None:
    snapshot = _write_snapshot(
        tmp_path,
        {"production_records": _valid_rows()["production_records"]},
    )
    output = tmp_path / "datasets" / "preprocessed"

    exit_code = main(
        [
            "preprocess",
            "--snapshot",
            str(snapshot),
            "--output-root",
            str(output),
        ]
    )
    assert exit_code == 0
    receipt = json.loads(capsys.readouterr().out)
    assert receipt["status"] == "preprocessed"

    verify_code = main(
        [
            "verify-preprocessed",
            "--run",
            receipt["run_path"],
        ]
    )
    assert verify_code == 0
    verified = json.loads(capsys.readouterr().out)
    assert verified["status"] == "valid"
    assert verified["run_id"] == receipt["run_id"]


def test_cli_reports_safe_preprocessing_error(tmp_path: Path, capsys) -> None:
    exit_code = main(
        [
            "preprocess",
            "--snapshot",
            str(tmp_path / "missing"),
            "--output-root",
            str(tmp_path / "output"),
        ]
    )

    assert exit_code == 1
    error = json.loads(capsys.readouterr().err)
    assert error["error"]["code"] == "snapshot_not_found"
    assert "traceback" not in json.dumps(error).lower()


def _rewrite_run_manifest(run: Path, manifest: dict) -> None:
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


def _create_run(tmp_path: Path, row_count: int = 1) -> Path:
    base = _valid_rows()["production_records"][0]
    rows = [dict(base, product_code=f"ORANGE-{index}") for index in range(row_count)]
    snapshot = _write_snapshot(tmp_path, {"production_records": rows})
    receipt = DatasetPreprocessingPipeline().run(
        snapshot,
        tmp_path / "datasets" / "preprocessed",
    )
    return Path(receipt.run_path)


@pytest.mark.parametrize(
    ("mutator", "expected_code"),
    [
        (lambda manifest: manifest.update(manifest_version="v2"), "invalid_manifest"),
        (
            lambda manifest: manifest.update(preprocessing_contract="wrong"),
            "invalid_manifest",
        ),
        (lambda manifest: manifest.update(ruleset_version="v2"), "invalid_manifest"),
        (
            lambda manifest: manifest.update(data_classification="real_company_data"),
            "invalid_manifest",
        ),
        (lambda manifest: manifest.update(run_id="not-a-uuid"), "invalid_manifest"),
        (
            lambda manifest: manifest.update(generated_at="2026-08-02T12:00:00"),
            "invalid_manifest",
        ),
        (
            lambda manifest: manifest["source_snapshot"].update(dataset_contract="wrong"),
            "invalid_manifest",
        ),
        (
            lambda manifest: manifest["source_snapshot"].update(dataset_schema_version="v2"),
            "invalid_manifest",
        ),
        (lambda manifest: manifest.update(input_row_count=-1), "invalid_manifest"),
    ],
)
def test_validator_rejects_manifest_contract_mutations(
    tmp_path: Path,
    mutator,
    expected_code: str,
) -> None:
    run = _create_run(tmp_path)
    manifest = json.loads((run / "manifest.json").read_text())
    mutator(manifest)
    _rewrite_run_manifest(run, manifest)

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == expected_code


@pytest.mark.parametrize(
    "mutator",
    [
        lambda item: item.update(file="data/wrong.csv"),
        lambda item: item.update(schema_version="v2"),
        lambda item: item.update(columns=list(reversed(item["columns"]))),
        lambda item: item.update(input_row_count=999),
        lambda item: item.update(byte_size=-1),
        lambda item: item.update(sha256="x" * 64),
    ],
)
def test_validator_rejects_dataset_manifest_mutations(tmp_path: Path, mutator) -> None:
    run = _create_run(tmp_path)
    manifest = json.loads((run / "manifest.json").read_text())
    mutator(manifest["datasets"][0])
    _rewrite_run_manifest(run, manifest)

    with pytest.raises(PreprocessedRunValidationError):
        PreprocessedRunValidator().validate(run)


def test_validator_detects_dataset_row_count_mismatch(tmp_path: Path) -> None:
    run = _create_run(tmp_path)
    manifest = json.loads((run / "manifest.json").read_text())
    manifest["datasets"][0]["row_count"] = 2
    manifest["datasets"][0]["input_row_count"] = 2
    manifest["total_rows"] = 2
    manifest["input_row_count"] = 2
    _rewrite_run_manifest(run, manifest)

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "dataset_row_count_mismatch"


def test_validator_detects_dataset_header_mismatch(tmp_path: Path) -> None:
    run = _create_run(tmp_path)
    path = run / "data" / "production_records.csv"
    rows = list(csv.reader(path.open("r", encoding="utf-8", newline="")))
    rows[0][0] = "wrong_column"
    with path.open("w", encoding="utf-8", newline="") as handle:
        csv.writer(handle, lineterminator="\n").writerows(rows)

    manifest = json.loads((run / "manifest.json").read_text())
    payload = path.read_bytes()
    manifest["datasets"][0]["byte_size"] = len(payload)
    manifest["datasets"][0]["sha256"] = hashlib.sha256(payload).hexdigest()
    _rewrite_run_manifest(run, manifest)

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "dataset_header_mismatch"


def test_validator_detects_dataset_column_count_mismatch(tmp_path: Path) -> None:
    run = _create_run(tmp_path)
    path = run / "data" / "production_records.csv"
    rows = list(csv.reader(path.open("r", encoding="utf-8", newline="")))
    rows[1] = rows[1][:-1]
    with path.open("w", encoding="utf-8", newline="") as handle:
        csv.writer(handle, lineterminator="\n").writerows(rows)

    manifest = json.loads((run / "manifest.json").read_text())
    payload = path.read_bytes()
    manifest["datasets"][0]["byte_size"] = len(payload)
    manifest["datasets"][0]["sha256"] = hashlib.sha256(payload).hexdigest()
    _rewrite_run_manifest(run, manifest)

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "dataset_column_count_mismatch"


def test_validator_enforces_preprocessed_row_limit(tmp_path: Path) -> None:
    run = _create_run(tmp_path, row_count=2)

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator(max_rows_per_file=1).validate(run)

    assert raised.value.code == "dataset_row_limit_exceeded"


def test_validator_detects_missing_declared_file(tmp_path: Path) -> None:
    run = _create_run(tmp_path)
    (run / "data" / "production_records.csv").unlink()

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "file_missing"


def test_validator_rejects_invalid_issue_json(tmp_path: Path) -> None:
    run = _create_run(tmp_path)
    issue_path = run / "issues" / "production_records.jsonl"
    issue_path.write_text('{"unexpected":true}\n', encoding="utf-8")
    manifest = json.loads((run / "manifest.json").read_text())
    payload = issue_path.read_bytes()
    issue = manifest["issue_files"][0]
    issue["byte_size"] = len(payload)
    issue["sha256"] = hashlib.sha256(payload).hexdigest()
    issue["issue_count"] = 1
    issue["stored_issue_count"] = 1
    _rewrite_run_manifest(run, manifest)

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "issue_file_invalid"


def test_validator_detects_issue_count_mismatch(tmp_path: Path) -> None:
    run = _create_run(tmp_path)
    issue_path = run / "issues" / "production_records.jsonl"
    issue_path.write_text(
        json.dumps(
            {
                "row_number": 2,
                "severity": "warning",
                "code": "test",
                "field": None,
                "message": "Test warning.",
            }
        )
        + "\n",
        encoding="utf-8",
    )
    manifest = json.loads((run / "manifest.json").read_text())
    payload = issue_path.read_bytes()
    issue = manifest["issue_files"][0]
    issue["byte_size"] = len(payload)
    issue["sha256"] = hashlib.sha256(payload).hexdigest()
    _rewrite_run_manifest(run, manifest)

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "issue_count_mismatch"


def test_validator_rejects_quality_report_mismatch(tmp_path: Path) -> None:
    run = _create_run(tmp_path)
    report_path = run / "quality-report.json"
    report = json.loads(report_path.read_text())
    report["run_id"] = "22222222-2222-4222-8222-222222222222"
    report_path.write_text(
        json.dumps(report, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )
    manifest = json.loads((run / "manifest.json").read_text())
    payload = report_path.read_bytes()
    manifest["quality_report"]["byte_size"] = len(payload)
    manifest["quality_report"]["sha256"] = hashlib.sha256(payload).hexdigest()
    _rewrite_run_manifest(run, manifest)

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "quality_report_mismatch"


def test_validator_rejects_invalid_quality_privacy_policy(tmp_path: Path) -> None:
    run = _create_run(tmp_path)
    report_path = run / "quality-report.json"
    report = json.loads(report_path.read_text())
    report["policies"]["raw_values_in_report"] = True
    report_path.write_text(
        json.dumps(report, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )
    manifest = json.loads((run / "manifest.json").read_text())
    payload = report_path.read_bytes()
    manifest["quality_report"]["byte_size"] = len(payload)
    manifest["quality_report"]["sha256"] = hashlib.sha256(payload).hexdigest()
    _rewrite_run_manifest(run, manifest)

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "invalid_quality_report"


def test_validator_detects_content_fingerprint_mismatch(tmp_path: Path) -> None:
    run = _create_run(tmp_path)
    manifest = json.loads((run / "manifest.json").read_text())
    manifest["content_fingerprint"] = "0" * 64
    _rewrite_run_manifest(run, manifest)

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "content_fingerprint_mismatch"


def test_validator_rejects_invalid_checksum_file(tmp_path: Path) -> None:
    run = _create_run(tmp_path)
    (run / "manifest.sha256").write_text("invalid\n", encoding="ascii")

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(run)

    assert raised.value.code == "manifest_checksum_invalid"


def test_validator_rejects_run_path_that_is_a_file(tmp_path: Path) -> None:
    path = tmp_path / "run.txt"
    path.write_text("not a directory", encoding="utf-8")

    with pytest.raises(PreprocessedRunValidationError) as raised:
        PreprocessedRunValidator().validate(path)

    assert raised.value.code == "run_not_directory"


def test_cli_environment_and_path_helpers(monkeypatch, tmp_path: Path) -> None:
    from app.cli.datasets import _environment_boolean, _preprocessing_root, _safe_error

    monkeypatch.delenv("TEST_BOOLEAN", raising=False)
    assert _environment_boolean("TEST_BOOLEAN", True) is True
    monkeypatch.setenv("TEST_BOOLEAN", "yes")
    assert _environment_boolean("TEST_BOOLEAN", False) is True
    monkeypatch.setenv("TEST_BOOLEAN", "off")
    assert _environment_boolean("TEST_BOOLEAN", True) is False
    monkeypatch.setenv("TEST_BOOLEAN", "unexpected")
    assert _environment_boolean("TEST_BOOLEAN", True) is True

    configured = tmp_path / "configured"
    assert _preprocessing_root(tmp_path / "snapshot", str(configured)) == configured
    nested = tmp_path / "datasets" / "snapshots" / SNAPSHOT_ID
    assert _preprocessing_root(nested, "") == tmp_path / "datasets" / "preprocessed"
    ordinary = tmp_path / "ordinary" / "run"
    assert _preprocessing_root(ordinary, "") == ordinary.parent / "preprocessed"

    code, message = _safe_error(PreprocessedRunValidationError("example", "Safe example."))
    assert code == "example"
    assert message == "Safe example."
    code, _ = _safe_error(ValueError("private detail"))
    assert code == "invalid_preprocessing_configuration"


def test_schema_registry_rejects_unknown_names() -> None:
    from app.preprocessing.schema import dataset_rules, rule_for

    with pytest.raises(KeyError):
        rule_for("unknown", "field")
    with pytest.raises(KeyError):
        rule_for("production_records", "unknown")
    assert len(dataset_rules("production_records")) == len(DATASET_COLUMNS["production_records"])


def test_normalizer_rejects_missing_timezone_and_negative_numbers() -> None:
    row = dict(
        _valid_rows()["production_records"][0],
        started_at_utc="2026-08-01T06:00:00",
        production_order_priority="-1",
        target_quantity="-1.5",
    )

    _, issues = normalize_row("production_records", row)

    codes = {issue.code for issue in issues}
    assert "timezone_required" in codes
    assert "negative_value" in codes

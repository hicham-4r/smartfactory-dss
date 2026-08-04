from __future__ import annotations

import csv
import hashlib
import json
from copy import deepcopy
from pathlib import Path

import pytest

from app.cli.datasets import main
from app.datasets.schema import DATASET_COLUMNS
from app.datasets.validator import (
    DatasetSnapshotValidationError,
    DatasetSnapshotValidator,
)

SNAPSHOT_ID = "11111111-1111-4111-8111-111111111111"


def _write_csv(
    root: Path,
    *,
    dataset: str = "production_records",
    rows: list[list[str]] | None = None,
) -> tuple[Path, int, str]:
    rows = rows or [
        [
            "2026-08-01",
            "2026-08-01T06:00:00Z",
            "2026-08-01T14:00:00Z",
            "LINE-01",
            "VALENCIA-PREMIUM",
            "ORANGE-1L",
            "SHIFT-A",
            "completed",
            "2",
            "locked",
            "validated",
            "bottles",
            "1000.000",
            "980.000",
            "970.000",
            "10.000",
            "420",
            "20",
            "1",
            "imported",
            "7",
            "2026-08-01T15:00:00Z",
        ]
    ]

    data_path = root / "data" / f"{dataset}.csv"
    data_path.parent.mkdir(parents=True, exist_ok=True)

    with data_path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.writer(handle, lineterminator="\n")
        writer.writerow(DATASET_COLUMNS[dataset])
        writer.writerows(rows)

    payload = data_path.read_bytes()
    return data_path, len(payload), hashlib.sha256(payload).hexdigest()


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

    return hashlib.sha256("\n".join(lines).encode("utf-8")).hexdigest()


def _snapshot(tmp_path: Path) -> Path:
    root = tmp_path / SNAPSHOT_ID
    data_path, byte_size, sha256 = _write_csv(root)

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
        "generator": {
            "name": "smartfactory-dss-laravel",
            "version": "0.1.0",
        },
        "total_rows": 1,
        "datasets": [
            {
                "name": "production_records",
                "file": "data/production_records.csv",
                "schema_version": "v1",
                "row_count": 1,
                "byte_size": byte_size,
                "sha256": sha256,
                "columns": DATASET_COLUMNS["production_records"],
            }
        ],
        "content_fingerprint": "",
    }
    manifest["content_fingerprint"] = _fingerprint(manifest)

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

    assert data_path.is_file()
    return root


def _rewrite_manifest(root: Path, manifest: dict) -> None:
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


def test_valid_snapshot_is_accepted(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)

    receipt = DatasetSnapshotValidator().validate(root)

    assert receipt.snapshot_id == SNAPSHOT_ID
    assert receipt.total_rows == 1
    assert receipt.datasets == ("production_records",)
    assert len(receipt.content_fingerprint) == 64


def test_dataset_tampering_is_detected(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    data_path = root / "data" / "production_records.csv"
    data_path.write_text(
        data_path.read_text(encoding="utf-8") + "tampered\n",
        encoding="utf-8",
    )

    with pytest.raises(
        DatasetSnapshotValidationError,
        match="size",
    ) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "dataset_size_mismatch"


def test_manifest_checksum_tampering_is_detected(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    manifest_path = root / "manifest.json"
    manifest_path.write_text(
        manifest_path.read_text(encoding="utf-8").replace(
            "simulated_sage",
            "other_source",
        ),
        encoding="utf-8",
    )

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "manifest_checksum_mismatch"


def test_unknown_or_reordered_columns_are_rejected(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
    manifest["datasets"][0]["columns"] = list(reversed(manifest["datasets"][0]["columns"]))
    _rewrite_manifest(root, manifest)

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "invalid_manifest"


def test_row_count_mismatch_is_rejected(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
    manifest["datasets"][0]["row_count"] = 2
    manifest["total_rows"] = 2
    manifest["content_fingerprint"] = _fingerprint(manifest)
    _rewrite_manifest(root, manifest)

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "dataset_row_count_mismatch"


def test_content_fingerprint_mismatch_is_rejected(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
    manifest["content_fingerprint"] = "0" * 64
    _rewrite_manifest(root, manifest)

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "content_fingerprint_mismatch"


def test_wrong_classification_is_rejected(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
    manifest["data_classification"] = "real_company_data"
    _rewrite_manifest(root, manifest)

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "invalid_manifest"


def test_unsafe_dataset_path_is_rejected_by_contract(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
    manifest["datasets"][0]["file"] = "../outside.csv"
    _rewrite_manifest(root, manifest)

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "invalid_manifest"


def test_cli_prints_compact_receipt(tmp_path: Path, capsys: pytest.CaptureFixture[str]) -> None:
    root = _snapshot(tmp_path)

    exit_code = main(["verify", "--snapshot", str(root)])

    assert exit_code == 0
    captured = capsys.readouterr()
    payload = json.loads(captured.out)
    assert payload["status"] == "valid"
    assert payload["snapshot_id"] == SNAPSHOT_ID
    assert captured.err == ""


def test_cli_reports_safe_error(tmp_path: Path, capsys: pytest.CaptureFixture[str]) -> None:
    missing = tmp_path / "missing"

    exit_code = main(["verify", "--snapshot", str(missing)])

    assert exit_code == 1
    captured = capsys.readouterr()
    payload = json.loads(captured.err)
    assert payload["status"] == "invalid"
    assert payload["error"]["code"] == "snapshot_not_found"
    assert "traceback" not in captured.err.lower()


def test_validator_limits_are_enforced(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    data_path = root / "data" / "production_records.csv"
    original = data_path.read_text(encoding="utf-8")
    data_path.write_text(original + ("x" * 2_000), encoding="utf-8")

    manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
    payload = data_path.read_bytes()
    manifest["datasets"][0]["byte_size"] = len(payload)
    manifest["datasets"][0]["sha256"] = hashlib.sha256(payload).hexdigest()
    manifest["content_fingerprint"] = _fingerprint(manifest)
    _rewrite_manifest(root, manifest)

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator(file_max_bytes=1024).validate(root)

    assert raised.value.code == "dataset_file_too_large"


def test_manifest_dataset_names_must_be_unique(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
    duplicate = deepcopy(manifest["datasets"][0])
    manifest["datasets"].append(duplicate)
    manifest["total_rows"] = 2
    _rewrite_manifest(root, manifest)

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "invalid_manifest"


@pytest.mark.parametrize(
    ("keyword", "value"),
    [
        ("manifest_max_bytes", 1),
        ("file_max_bytes", 1),
        ("max_rows_per_file", 0),
        ("max_cell_characters", 0),
    ],
)
def test_validator_rejects_invalid_limits(keyword: str, value: int) -> None:
    with pytest.raises(ValueError):
        DatasetSnapshotValidator(**{keyword: value})


def test_snapshot_path_must_be_directory(tmp_path: Path) -> None:
    path = tmp_path / "snapshot.txt"
    path.write_text("not a directory", encoding="utf-8")

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(path)

    assert raised.value.code == "snapshot_not_directory"


def test_missing_manifest_is_rejected(tmp_path: Path) -> None:
    root = tmp_path / SNAPSHOT_ID
    root.mkdir()

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "manifest_not_found"


def test_invalid_manifest_checksum_file_is_rejected(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    (root / "manifest.sha256").write_text(
        "not-a-checksum\n",
        encoding="ascii",
    )

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "manifest_checksum_invalid"


def test_missing_dataset_file_is_rejected(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    (root / "data" / "production_records.csv").unlink()

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "dataset_file_missing"


def test_same_size_dataset_hash_tampering_is_detected(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    path = root / "data" / "production_records.csv"
    payload = bytearray(path.read_bytes())
    payload[-2] = ord("9") if payload[-2] != ord("9") else ord("8")
    path.write_bytes(bytes(payload))

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "dataset_checksum_mismatch"


def test_dataset_header_mismatch_is_rejected(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    path = root / "data" / "production_records.csv"
    rows = list(csv.reader(path.open("r", encoding="utf-8", newline="")))
    rows[0][0] = "wrong_column"

    with path.open("w", encoding="utf-8", newline="") as handle:
        csv.writer(handle, lineterminator="\n").writerows(rows)

    manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
    payload = path.read_bytes()
    manifest["datasets"][0]["byte_size"] = len(payload)
    manifest["datasets"][0]["sha256"] = hashlib.sha256(payload).hexdigest()
    manifest["content_fingerprint"] = _fingerprint(manifest)
    _rewrite_manifest(root, manifest)

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "dataset_header_mismatch"


def test_dataset_column_count_mismatch_is_rejected(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    path = root / "data" / "production_records.csv"
    rows = list(csv.reader(path.open("r", encoding="utf-8", newline="")))
    rows[1] = rows[1][:-1]

    with path.open("w", encoding="utf-8", newline="") as handle:
        csv.writer(handle, lineterminator="\n").writerows(rows)

    manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
    payload = path.read_bytes()
    manifest["datasets"][0]["byte_size"] = len(payload)
    manifest["datasets"][0]["sha256"] = hashlib.sha256(payload).hexdigest()
    manifest["content_fingerprint"] = _fingerprint(manifest)
    _rewrite_manifest(root, manifest)

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator().validate(root)

    assert raised.value.code == "dataset_column_count_mismatch"


def test_dataset_row_limit_is_enforced(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)
    path = root / "data" / "production_records.csv"
    rows = list(csv.reader(path.open("r", encoding="utf-8", newline="")))
    rows.append(rows[1])

    with path.open("w", encoding="utf-8", newline="") as handle:
        csv.writer(handle, lineterminator="\n").writerows(rows)

    manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
    payload = path.read_bytes()
    manifest["datasets"][0]["row_count"] = 2
    manifest["datasets"][0]["byte_size"] = len(payload)
    manifest["datasets"][0]["sha256"] = hashlib.sha256(payload).hexdigest()
    manifest["total_rows"] = 2
    manifest["content_fingerprint"] = _fingerprint(manifest)
    _rewrite_manifest(root, manifest)

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator(max_rows_per_file=1).validate(root)

    assert raised.value.code == "dataset_row_limit_exceeded"


def test_dataset_cell_limit_is_enforced(tmp_path: Path) -> None:
    root = _snapshot(tmp_path)

    with pytest.raises(DatasetSnapshotValidationError) as raised:
        DatasetSnapshotValidator(max_cell_characters=5).validate(root)

    assert raised.value.code == "dataset_cell_invalid"

from __future__ import annotations

import csv
import hashlib
import hmac
import json
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from pydantic import ValidationError

from app.datasets.models import DatasetFileManifest, DatasetSnapshotManifest


class DatasetSnapshotValidationError(Exception):
    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True, slots=True)
class DatasetValidationReceipt:
    snapshot_id: str
    content_fingerprint: str
    total_rows: int
    datasets: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "status": "valid",
            "snapshot_id": self.snapshot_id,
            "content_fingerprint": self.content_fingerprint,
            "total_rows": self.total_rows,
            "datasets": list(self.datasets),
        }


class DatasetSnapshotValidator:
    def __init__(
        self,
        *,
        manifest_max_bytes: int = 1_048_576,
        file_max_bytes: int = 536_870_912,
        max_rows_per_file: int = 1_000_000,
        max_cell_characters: int = 10_000,
    ) -> None:
        if manifest_max_bytes < 1_024:
            raise ValueError("manifest_max_bytes must be at least 1024")
        if file_max_bytes < 1_024:
            raise ValueError("file_max_bytes must be at least 1024")
        if max_rows_per_file < 1:
            raise ValueError("max_rows_per_file must be positive")
        if max_cell_characters < 1:
            raise ValueError("max_cell_characters must be positive")

        self.manifest_max_bytes = manifest_max_bytes
        self.file_max_bytes = file_max_bytes
        self.max_rows_per_file = max_rows_per_file
        self.max_cell_characters = max_cell_characters

    def validate(self, snapshot_directory: str | Path) -> DatasetValidationReceipt:
        root = self._snapshot_root(snapshot_directory)
        manifest_path = root / "manifest.json"
        checksum_path = root / "manifest.sha256"

        manifest_bytes = self._read_manifest(manifest_path)
        self._verify_manifest_checksum(checksum_path, manifest_bytes)

        try:
            manifest = DatasetSnapshotManifest.model_validate_json(manifest_bytes)
        except ValidationError as exception:
            raise DatasetSnapshotValidationError(
                "invalid_manifest",
                "The dataset manifest does not match the supported contract.",
            ) from exception

        for dataset in manifest.datasets:
            self._validate_dataset_file(root, dataset)

        expected_fingerprint = self._fingerprint(manifest)
        if not hmac.compare_digest(
            expected_fingerprint,
            manifest.content_fingerprint,
        ):
            raise DatasetSnapshotValidationError(
                "content_fingerprint_mismatch",
                "The dataset content fingerprint is invalid.",
            )

        return DatasetValidationReceipt(
            snapshot_id=str(manifest.snapshot_id),
            content_fingerprint=manifest.content_fingerprint,
            total_rows=manifest.total_rows,
            datasets=tuple(item.name for item in manifest.datasets),
        )

    def _snapshot_root(self, value: str | Path) -> Path:
        path = Path(value).expanduser()

        try:
            resolved = path.resolve(strict=True)
        except (FileNotFoundError, OSError) as exception:
            raise DatasetSnapshotValidationError(
                "snapshot_not_found",
                "The dataset snapshot directory does not exist.",
            ) from exception

        if not resolved.is_dir():
            raise DatasetSnapshotValidationError(
                "snapshot_not_directory",
                "The dataset snapshot path is not a directory.",
            )

        return resolved

    def _read_manifest(self, path: Path) -> bytes:
        try:
            size = path.stat().st_size
        except OSError as exception:
            raise DatasetSnapshotValidationError(
                "manifest_not_found",
                "The dataset manifest is missing.",
            ) from exception

        if size < 2 or size > self.manifest_max_bytes:
            raise DatasetSnapshotValidationError(
                "manifest_size_invalid",
                "The dataset manifest size is invalid.",
            )

        try:
            return path.read_bytes()
        except OSError as exception:
            raise DatasetSnapshotValidationError(
                "manifest_unreadable",
                "The dataset manifest could not be read.",
            ) from exception

    def _verify_manifest_checksum(self, path: Path, manifest_bytes: bytes) -> None:
        try:
            content = path.read_text(encoding="ascii")
        except (OSError, UnicodeError) as exception:
            raise DatasetSnapshotValidationError(
                "manifest_checksum_missing",
                "The dataset manifest checksum is missing or unreadable.",
            ) from exception

        parts = content.strip().split()
        if len(parts) != 2 or parts[1] != "manifest.json":
            raise DatasetSnapshotValidationError(
                "manifest_checksum_invalid",
                "The dataset manifest checksum file is invalid.",
            )

        expected = parts[0].lower()
        if len(expected) != 64 or any(
            character not in "0123456789abcdef" for character in expected
        ):
            raise DatasetSnapshotValidationError(
                "manifest_checksum_invalid",
                "The dataset manifest checksum file is invalid.",
            )

        actual = hashlib.sha256(manifest_bytes).hexdigest()
        if not hmac.compare_digest(actual, expected):
            raise DatasetSnapshotValidationError(
                "manifest_checksum_mismatch",
                "The dataset manifest checksum does not match.",
            )

    def _validate_dataset_file(
        self,
        root: Path,
        dataset: DatasetFileManifest,
    ) -> None:
        candidate = (root / dataset.file).resolve(strict=False)

        try:
            candidate.relative_to(root)
        except ValueError as exception:
            raise DatasetSnapshotValidationError(
                "unsafe_dataset_path",
                "A dataset file path escapes the snapshot directory.",
            ) from exception

        if not candidate.is_file():
            raise DatasetSnapshotValidationError(
                "dataset_file_missing",
                "A declared dataset file is missing.",
            )

        size = candidate.stat().st_size
        if size != dataset.byte_size:
            raise DatasetSnapshotValidationError(
                "dataset_size_mismatch",
                "A dataset file size does not match the manifest.",
            )
        if size > self.file_max_bytes:
            raise DatasetSnapshotValidationError(
                "dataset_file_too_large",
                "A dataset file exceeds the configured size limit.",
            )

        actual_hash = self._sha256(candidate)
        if not hmac.compare_digest(actual_hash, dataset.sha256):
            raise DatasetSnapshotValidationError(
                "dataset_checksum_mismatch",
                "A dataset file checksum does not match the manifest.",
            )

        row_count = self._validate_csv(candidate, dataset)
        if row_count != dataset.row_count:
            raise DatasetSnapshotValidationError(
                "dataset_row_count_mismatch",
                "A dataset row count does not match the manifest.",
            )

    def _validate_csv(
        self,
        path: Path,
        dataset: DatasetFileManifest,
    ) -> int:
        try:
            handle = path.open(
                "r",
                encoding="utf-8",
                newline="",
            )
        except (OSError, UnicodeError) as exception:
            raise DatasetSnapshotValidationError(
                "dataset_unreadable",
                "A dataset file could not be opened safely.",
            ) from exception

        with handle:
            try:
                reader = csv.reader(handle, strict=True)
                header = next(reader)
            except (StopIteration, csv.Error, UnicodeError) as exception:
                raise DatasetSnapshotValidationError(
                    "dataset_header_invalid",
                    "A dataset CSV header is missing or invalid.",
                ) from exception

            if header != dataset.columns:
                raise DatasetSnapshotValidationError(
                    "dataset_header_mismatch",
                    "A dataset CSV header does not match the manifest.",
                )

            row_count = 0
            try:
                for row in reader:
                    row_count += 1
                    if row_count > self.max_rows_per_file:
                        raise DatasetSnapshotValidationError(
                            "dataset_row_limit_exceeded",
                            "A dataset exceeds the configured row limit.",
                        )
                    if len(row) != len(header):
                        raise DatasetSnapshotValidationError(
                            "dataset_column_count_mismatch",
                            "A dataset row has an invalid column count.",
                        )
                    if any(
                        len(value) > self.max_cell_characters or "\x00" in value
                        for value in row
                    ):
                        raise DatasetSnapshotValidationError(
                            "dataset_cell_invalid",
                            "A dataset contains an invalid cell value.",
                        )
            except csv.Error as exception:
                raise DatasetSnapshotValidationError(
                    "dataset_csv_invalid",
                    "A dataset CSV file is malformed.",
                ) from exception

        return row_count

    @staticmethod
    def _sha256(path: Path) -> str:
        digest = hashlib.sha256()

        try:
            with path.open("rb") as handle:
                for chunk in iter(lambda: handle.read(1_048_576), b""):
                    digest.update(chunk)
        except OSError as exception:
            raise DatasetSnapshotValidationError(
                "dataset_unreadable",
                "A dataset file could not be read safely.",
            ) from exception

        return digest.hexdigest()

    @staticmethod
    def _fingerprint(manifest: DatasetSnapshotManifest) -> str:
        lines = [
            manifest.dataset_contract,
            manifest.dataset_schema_version,
            manifest.source_system,
            manifest.data_classification,
            manifest.period.start_date.isoformat(),
            manifest.period.end_date.isoformat(),
            manifest.period.timezone,
        ]

        for dataset in sorted(manifest.datasets, key=lambda item: item.name):
            lines.append(
                "|".join(
                    [
                        dataset.name,
                        dataset.sha256,
                        str(dataset.row_count),
                        str(dataset.byte_size),
                    ]
                )
            )

        return hashlib.sha256("\n".join(lines).encode("utf-8")).hexdigest()

from __future__ import annotations

import argparse
import json
import os
import sys
from collections.abc import Sequence
from decimal import Decimal
from pathlib import Path

from app.datasets.validator import (
    DatasetSnapshotValidationError,
    DatasetSnapshotValidator,
)
from app.features.pipeline import (
    FeatureEngineeringError,
    FeatureEngineeringPipeline,
)
from app.features.validator import (
    FeatureRunValidationError,
    FeatureRunValidator,
)
from app.preprocessing.pipeline import (
    DatasetPreprocessingError,
    DatasetPreprocessingPipeline,
)
from app.preprocessing.validator import (
    PreprocessedRunValidationError,
    PreprocessedRunValidator,
)


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="smartfactory-datasets",
        description=(
            "Validate, preprocess, engineer features, and verify SmartFactory DSS "
            "simulated-prototype dataset snapshots."
        ),
    )
    subcommands = parser.add_subparsers(dest="command", required=True)

    verify = subcommands.add_parser(
        "verify",
        help="Verify a raw snapshot manifest, hashes, schemas, and row counts.",
    )
    verify.add_argument(
        "--snapshot",
        required=True,
        help="Absolute path to one published raw snapshot directory.",
    )
    _add_validation_limits(verify)

    preprocess = subcommands.add_parser(
        "preprocess",
        help="Create an atomic, quality-reported, preprocessed dataset run.",
    )
    preprocess.add_argument(
        "--snapshot",
        required=True,
        help="Absolute path to one verified raw snapshot directory.",
    )
    preprocess.add_argument(
        "--output-root",
        default=os.getenv("AI_PREPROCESSING_ROOT", ""),
        help=(
            "Preprocessing root. Defaults to AI_PREPROCESSING_ROOT or a preprocessed "
            "directory beside the raw snapshots directory."
        ),
    )
    preprocess.add_argument(
        "--max-stored-issues",
        type=int,
        default=int(os.getenv("AI_PREPROCESSING_MAX_STORED_ISSUES", "10000")),
    )
    preprocess.add_argument(
        "--max-unique-values",
        type=int,
        default=int(os.getenv("AI_PREPROCESSING_MAX_UNIQUE_VALUES", "50000")),
    )
    preprocess.add_argument(
        "--fail-on-rejected",
        action="store_true",
        default=_environment_boolean("AI_PREPROCESSING_FAIL_ON_REJECTED", False),
        help="Abort publication when any row is rejected.",
    )

    verify_preprocessed = subcommands.add_parser(
        "verify-preprocessed",
        help="Verify a published preprocessing run and all checksums.",
    )
    verify_preprocessed.add_argument(
        "--run",
        required=True,
        help="Absolute path to one published preprocessing run directory.",
    )
    _add_validation_limits(verify_preprocessed)

    features = subcommands.add_parser(
        "features",
        help=(
            "Create leakage-safe production forecasting, anomaly, and maintenance feature splits."
        ),
    )
    features.add_argument(
        "--run",
        required=True,
        help="Absolute path to one verified Step 21D preprocessing run.",
    )
    features.add_argument(
        "--output-root",
        default=os.getenv("AI_FEATURE_ROOT", ""),
        help=(
            "Feature root. Defaults to AI_FEATURE_ROOT or a features directory beside "
            "the preprocessing root."
        ),
    )
    features.add_argument(
        "--train-ratio",
        type=Decimal,
        default=Decimal(os.getenv("AI_FEATURE_TRAIN_RATIO", "0.70")),
    )
    features.add_argument(
        "--validation-ratio",
        type=Decimal,
        default=Decimal(os.getenv("AI_FEATURE_VALIDATION_RATIO", "0.15")),
    )
    features.add_argument(
        "--test-ratio",
        type=Decimal,
        default=Decimal(os.getenv("AI_FEATURE_TEST_RATIO", "0.15")),
    )

    verify_features = subcommands.add_parser(
        "verify-features",
        help="Verify a published feature run, split chronology, and all checksums.",
    )
    verify_features.add_argument(
        "--run",
        required=True,
        help="Absolute path to one published feature run directory.",
    )
    _add_validation_limits(verify_features)

    return parser


def _add_validation_limits(parser: argparse.ArgumentParser) -> None:
    parser.add_argument(
        "--manifest-max-bytes",
        type=int,
        default=int(os.getenv("AI_DATASET_MANIFEST_MAX_BYTES", "1048576")),
    )
    parser.add_argument(
        "--file-max-bytes",
        type=int,
        default=int(os.getenv("AI_DATASET_FILE_MAX_BYTES", "536870912")),
    )
    parser.add_argument(
        "--max-rows",
        type=int,
        default=int(os.getenv("AI_DATASET_MAX_ROWS_PER_FILE", "1000000")),
    )


def _environment_boolean(name: str, default: bool) -> bool:
    raw = os.getenv(name)
    if raw is None:
        return default
    normalized = raw.strip().lower()
    if normalized in {"1", "true", "yes", "on"}:
        return True
    if normalized in {"0", "false", "no", "off"}:
        return False
    return default


def _preprocessing_root(snapshot: Path, configured: str) -> Path:
    if configured.strip():
        return Path(configured)
    resolved = snapshot.expanduser().resolve(strict=False)
    if resolved.parent.name.lower() == "snapshots":
        return resolved.parent.parent / "preprocessed"
    return resolved.parent / "preprocessed"


def _feature_root(run: Path, configured: str) -> Path:
    if configured.strip():
        return Path(configured)
    resolved = run.expanduser().resolve(strict=False)
    if resolved.parent.name.lower() == "runs":
        return resolved.parent.parent.parent / "features"
    return resolved.parent / "features"


def main(argv: Sequence[str] | None = None) -> int:
    parser = build_parser()
    arguments = parser.parse_args(argv)

    try:
        if arguments.command == "verify":
            receipt = DatasetSnapshotValidator(
                manifest_max_bytes=arguments.manifest_max_bytes,
                file_max_bytes=arguments.file_max_bytes,
                max_rows_per_file=arguments.max_rows,
            ).validate(Path(arguments.snapshot))

        elif arguments.command == "preprocess":
            snapshot = Path(arguments.snapshot)
            receipt = DatasetPreprocessingPipeline(
                maximum_stored_issues=arguments.max_stored_issues,
                maximum_unique_values=arguments.max_unique_values,
                fail_on_rejected=arguments.fail_on_rejected,
            ).run(
                snapshot,
                _preprocessing_root(snapshot, arguments.output_root),
            )

        elif arguments.command == "verify-preprocessed":
            receipt = PreprocessedRunValidator(
                manifest_max_bytes=arguments.manifest_max_bytes,
                file_max_bytes=arguments.file_max_bytes,
                max_rows_per_file=arguments.max_rows,
            ).validate(Path(arguments.run))

        elif arguments.command == "features":
            run = Path(arguments.run)
            receipt = FeatureEngineeringPipeline(
                train_ratio=arguments.train_ratio,
                validation_ratio=arguments.validation_ratio,
                test_ratio=arguments.test_ratio,
            ).run(
                run,
                _feature_root(run, arguments.output_root),
            )

        elif arguments.command == "verify-features":
            receipt = FeatureRunValidator(
                manifest_max_bytes=arguments.manifest_max_bytes,
                file_max_bytes=arguments.file_max_bytes,
                max_rows_per_file=arguments.max_rows,
            ).validate(Path(arguments.run))

        else:
            parser.error("unsupported command")
            return 2

    except (
        DatasetSnapshotValidationError,
        DatasetPreprocessingError,
        PreprocessedRunValidationError,
        FeatureEngineeringError,
        FeatureRunValidationError,
        ValueError,
    ) as exception:
        code, message = _safe_error(exception)
        print(
            json.dumps(
                {
                    "status": "invalid",
                    "error": {
                        "code": code,
                        "message": message,
                    },
                },
                separators=(",", ":"),
            ),
            file=sys.stderr,
        )
        return 1

    print(json.dumps(receipt.to_dict(), separators=(",", ":")))
    return 0


def _safe_error(exception: Exception) -> tuple[str, str]:
    if isinstance(exception, DatasetSnapshotValidationError):
        return exception.code, exception.message
    if isinstance(exception, DatasetPreprocessingError):
        return exception.code, exception.message
    if isinstance(exception, PreprocessedRunValidationError):
        return exception.code, exception.message
    if isinstance(exception, FeatureEngineeringError):
        return exception.code, exception.message
    if isinstance(exception, FeatureRunValidationError):
        return exception.code, exception.message
    return (
        "invalid_preprocessing_configuration",
        "The dataset pipeline configuration is invalid.",
    )


if __name__ == "__main__":
    raise SystemExit(main())

from __future__ import annotations

import argparse
import json
import os
import sys
from collections.abc import Sequence
from pathlib import Path

from app.models.data import ModelDataError
from app.models.training import ModelTrainingError, ModelTrainingPipeline
from app.models.validator import ModelRunValidationError, ModelRunValidator


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="smartfactory-models",
        description=(
            "Train, evaluate, version, and verify SmartFactory DSS simulated-prototype models."
        ),
    )
    subcommands = parser.add_subparsers(dest="command", required=True)

    train = subcommands.add_parser(
        "train",
        help="Train all registered model tasks from one verified feature run.",
    )
    train.add_argument(
        "--feature-run",
        required=True,
        help="Absolute path to one published Step 21E feature run.",
    )
    train.add_argument(
        "--output-root",
        default=os.getenv("AI_MODEL_ROOT", ""),
        help="Model registry root. Defaults to AI_MODEL_ROOT.",
    )
    train.add_argument(
        "--random-seed",
        type=int,
        default=int(os.getenv("AI_MODEL_RANDOM_SEED", "42")),
    )
    train.add_argument(
        "--anomaly-contamination",
        type=float,
        default=float(os.getenv("AI_MODEL_ANOMALY_CONTAMINATION", "0.02")),
    )

    verify = subcommands.add_parser(
        "verify",
        help="Verify one published model run without loading executable artifacts.",
    )
    verify.add_argument(
        "--run",
        required=True,
        help="Absolute path to one published model-run directory.",
    )
    verify.add_argument(
        "--manifest-max-bytes",
        type=int,
        default=int(os.getenv("AI_MODEL_MANIFEST_MAX_BYTES", "1048576")),
    )
    verify.add_argument(
        "--artifact-max-bytes",
        type=int,
        default=int(os.getenv("AI_MODEL_ARTIFACT_MAX_BYTES", "536870912")),
    )
    verify.add_argument(
        "--metrics-max-bytes",
        type=int,
        default=int(os.getenv("AI_MODEL_METRICS_MAX_BYTES", "10485760")),
    )
    return parser


def main(argv: Sequence[str] | None = None) -> int:
    parser = build_parser()
    arguments = parser.parse_args(argv)

    try:
        if arguments.command == "train":
            if not str(arguments.output_root).strip():
                raise ValueError("AI_MODEL_ROOT is required")
            receipt = ModelTrainingPipeline(
                random_seed=arguments.random_seed,
                anomaly_contamination=arguments.anomaly_contamination,
            ).run(
                Path(arguments.feature_run),
                Path(arguments.output_root),
            )
        elif arguments.command == "verify":
            receipt = ModelRunValidator(
                manifest_max_bytes=arguments.manifest_max_bytes,
                artifact_max_bytes=arguments.artifact_max_bytes,
                metrics_max_bytes=arguments.metrics_max_bytes,
            ).validate(Path(arguments.run))
        else:
            parser.error("unsupported command")
            return 2
    except (
        ModelDataError,
        ModelTrainingError,
        ModelRunValidationError,
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
    if isinstance(exception, (ModelDataError, ModelTrainingError, ModelRunValidationError)):
        return exception.code, exception.message
    return (
        "invalid_model_configuration",
        "The model-training configuration is invalid.",
    )


if __name__ == "__main__":
    raise SystemExit(main())

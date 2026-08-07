#!/usr/bin/env python3
from __future__ import annotations

import argparse
import base64
import os
import pathlib
import secrets
import sys
import tempfile

PROJECT_ROOT = pathlib.Path(__file__).resolve().parents[3]
COMPOSE_ROOT = PROJECT_ROOT / "deploy" / "compose"

TARGETS = {
    COMPOSE_ROOT / "compose.env.example": PROJECT_ROOT / ".env",
    COMPOSE_ROOT / "env" / "mysql-dss" / ".env.example":
        COMPOSE_ROOT / "env" / "mysql-dss" / ".env",
    COMPOSE_ROOT / "env" / "mysql-erp" / ".env.example":
        COMPOSE_ROOT / "env" / "mysql-erp" / ".env",
    COMPOSE_ROOT / "env" / "laravel" / ".env.example":
        COMPOSE_ROOT / "env" / "laravel" / ".env",
    COMPOSE_ROOT / "env" / "simulator" / ".env.example":
        COMPOSE_ROOT / "env" / "simulator" / ".env",
    COMPOSE_ROOT / "env" / "fastapi" / ".env.example":
        COMPOSE_ROOT / "env" / "fastapi" / ".env",
}


def laravel_key() -> str:
    encoded = base64.b64encode(secrets.token_bytes(32)).decode("ascii")
    return f"base64:{encoded}"


def replacements() -> dict[str, str]:
    return {
        "__DSS_DB_ROOT_PASSWORD__": secrets.token_hex(32),
        "__DSS_DB_PASSWORD__": secrets.token_hex(32),
        "__ERP_DB_ROOT_PASSWORD__": secrets.token_hex(32),
        "__ERP_DB_PASSWORD__": secrets.token_hex(32),
        "__ERP_API_TOKEN__": secrets.token_hex(32),
        "__AI_INTERNAL_TOKEN__": secrets.token_hex(32),
        "__LARAVEL_APP_KEY__": laravel_key(),
        "__SIMULATOR_APP_KEY__": laravel_key(),
    }


def atomic_write(path: pathlib.Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    descriptor, temporary_name = tempfile.mkstemp(
        prefix=f".{path.name}.",
        dir=path.parent,
        text=True,
    )
    temporary = pathlib.Path(temporary_name)
    try:
        with os.fdopen(descriptor, "w", encoding="utf-8", newline="\n") as stream:
            stream.write(content)
        if os.name != "nt":
            temporary.chmod(0o600)
        temporary.replace(path)
        if os.name != "nt":
            path.chmod(0o600)
    except Exception:
        temporary.unlink(missing_ok=True)
        raise


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Generate ignored local Compose environment files safely."
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Replace existing generated environment files.",
    )
    arguments = parser.parse_args()

    existing = [target for target in TARGETS.values() if target.exists()]
    if existing and not arguments.force:
        rendered = "\n".join(f"- {path}" for path in existing)
        raise SystemExit(
            "Refusing to overwrite existing local environment files.\n"
            "Use --force only after backing them up intentionally:\n"
            f"{rendered}"
        )

    values = replacements()
    generated: list[pathlib.Path] = []

    for source, target in TARGETS.items():
        if not source.is_file():
            raise SystemExit(f"Environment example is missing: {source}")

        content = source.read_text(encoding="utf-8")
        for placeholder, value in values.items():
            content = content.replace(placeholder, value)

        unresolved = sorted(
            token
            for token in values
            if token in content
        )
        if unresolved:
            raise SystemExit(
                f"Unresolved placeholders in {source}: {', '.join(unresolved)}"
            )

        atomic_write(target, content)
        generated.append(target)

    print("SMARTFACTORY COMPOSE LOCAL ENVIRONMENT GENERATION PASSED")
    print("Generated ignored files:")
    for path in generated:
        print(f"- {path.relative_to(PROJECT_ROOT)}")
    print("Secret values were not printed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())

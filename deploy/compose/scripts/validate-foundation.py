#!/usr/bin/env python3
from __future__ import annotations

import json
import pathlib
import re
import sys

PROJECT_ROOT = pathlib.Path(__file__).resolve().parents[3]
COMPOSE = PROJECT_ROOT / "compose.yaml"

REQUIRED_SERVICES = {
    "mysql-dss",
    "mysql-erp",
    "redis",
    "fastapi-ai",
    "laravel-app",
    "laravel-queue",
    "laravel-scheduler",
    "sage-erp-simulator",
    "nginx",
}
PROTECTED_FROM_HOST_PORTS = REQUIRED_SERVICES - {"nginx"}
REQUIRED_VOLUMES = {
    "dss-mysql-data",
    "erp-mysql-data",
    "redis-data",
    "laravel-storage",
    "simulator-storage",
    "ai-datasets",
    "ai-runtime-data",
}
REQUIRED_NETWORKS = {"edge", "ai-link", "dss-data", "erp-data"}


def fail(message: str) -> None:
    raise SystemExit(message)


def service_blocks(lines: list[str]) -> dict[str, list[str]]:
    try:
        services_index = lines.index("services:")
    except ValueError:
        fail("compose.yaml is missing the services section.")

    blocks: dict[str, list[str]] = {}
    current: str | None = None

    for line in lines[services_index + 1 :]:
        if line and not line.startswith(" "):
            break

        match = re.match(r"^  ([a-z0-9][a-z0-9-]*):\s*$", line)
        if match:
            current = match.group(1)
            blocks[current] = []
            continue

        if current is not None:
            blocks[current].append(line)

    return blocks


def top_level_names(lines: list[str], section: str) -> set[str]:
    marker = f"{section}:"
    try:
        start = lines.index(marker)
    except ValueError:
        fail(f"compose.yaml is missing the {section} section.")

    names: set[str] = set()
    for line in lines[start + 1 :]:
        if line and not line.startswith(" "):
            break
        match = re.match(r"^  ([a-z0-9][a-z0-9-]*):", line)
        if match:
            names.add(match.group(1))
    return names


def main() -> int:
    if not COMPOSE.is_file():
        fail(f"compose file not found: {COMPOSE}")

    text = COMPOSE.read_text(encoding="utf-8")
    lines = text.splitlines()

    if re.search(r"(?m)^\s*version\s*:", text):
        fail("Obsolete top-level Compose version key is not allowed.")
    if "container_name:" in text:
        fail("container_name is not allowed because it prevents safe scaling.")
    if ":latest" in text:
        fail("Mutable latest image tags are not allowed.")

    blocks = service_blocks(lines)
    missing_services = sorted(REQUIRED_SERVICES - set(blocks))
    unknown_services = sorted(set(blocks) - REQUIRED_SERVICES)
    if missing_services:
        fail(f"Missing services: {', '.join(missing_services)}")
    if unknown_services:
        fail(f"Unexpected services: {', '.join(unknown_services)}")

    for service in sorted(PROTECTED_FROM_HOST_PORTS):
        block = "\n".join(blocks[service])
        if re.search(r"(?m)^    ports:\s*$", block):
            fail(f"Service must not publish host ports: {service}")

    nginx = "\n".join(blocks["nginx"])
    for marker in (
        "${SMARTFACTORY_DSS_BIND_ADDRESS:-127.0.0.1}",
        "${SMARTFACTORY_ERP_BIND_ADDRESS:-127.0.0.1}",
        "condition: service_healthy",
    ):
        if marker not in nginx:
            fail(f"NGINX block is missing: {marker}")

    fastapi = "\n".join(blocks["fastapi-ai"])
    if "host.docker.internal:host-gateway" not in fastapi:
        fail("FastAPI portability host mapping is missing.")
    if "ai-runtime-data:/var/lib/smartfactory-ai" not in fastapi:
        fail("FastAPI runtime volume is missing.")

    queue = "\n".join(blocks["laravel-queue"])
    scheduler = "\n".join(blocks["laravel-scheduler"])
    if "profiles:" not in queue or "- workers" not in queue:
        fail("Queue worker must remain opt-in through the workers profile.")
    if "profiles:" not in scheduler or "- workers" not in scheduler:
        fail("Scheduler must remain opt-in through the workers profile.")

    volumes = top_level_names(lines, "volumes")
    networks = top_level_names(lines, "networks")
    if volumes != REQUIRED_VOLUMES:
        fail(
            "Named volume set mismatch: "
            f"expected {sorted(REQUIRED_VOLUMES)}, got {sorted(volumes)}"
        )
    if networks != REQUIRED_NETWORKS:
        fail(
            "Network set mismatch: "
            f"expected {sorted(REQUIRED_NETWORKS)}, got {sorted(networks)}"
        )

    for internal_network in ("dss-data", "erp-data"):
        pattern = rf"(?ms)^  {re.escape(internal_network)}:\s*\n    internal: true\s*$"
        if not re.search(pattern, text):
            fail(f"Internal database network is not isolated: {internal_network}")

    actual_env_files = [
        PROJECT_ROOT / ".env",
        PROJECT_ROOT / "deploy/compose/env/mysql-dss/.env",
        PROJECT_ROOT / "deploy/compose/env/mysql-erp/.env",
        PROJECT_ROOT / "deploy/compose/env/laravel/.env",
        PROJECT_ROOT / "deploy/compose/env/simulator/.env",
        PROJECT_ROOT / "deploy/compose/env/fastapi/.env",
    ]
    present_actual_envs = [str(path) for path in actual_env_files if path.exists()]
    if present_actual_envs:
        fail(
            "Actual local environment files exist before the generation step:\n"
            + "\n".join(present_actual_envs)
        )

    examples = sorted(
        (PROJECT_ROOT / "deploy/compose").rglob("*.env.example")
    )
    examples.append(PROJECT_ROOT / "deploy/compose/compose.env.example")
    required_placeholders = {
        "__DSS_DB_ROOT_PASSWORD__",
        "__DSS_DB_PASSWORD__",
        "__ERP_DB_ROOT_PASSWORD__",
        "__ERP_DB_PASSWORD__",
        "__ERP_API_TOKEN__",
        "__AI_INTERNAL_TOKEN__",
        "__LARAVEL_APP_KEY__",
        "__SIMULATOR_APP_KEY__",
    }
    combined = "\n".join(
        path.read_text(encoding="utf-8")
        for path in examples
        if path.is_file()
    )
    missing_placeholders = sorted(
        placeholder
        for placeholder in required_placeholders
        if placeholder not in combined
    )
    if missing_placeholders:
        fail(
            "Environment examples are missing placeholders: "
            + ", ".join(missing_placeholders)
        )

    laravel_example = (
        PROJECT_ROOT
        / "deploy/compose/env/laravel/.env.example"
    )
    laravel_values = {}
    for raw_line in laravel_example.read_text(
        encoding="utf-8"
    ).splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#"):
            continue
        key, separator, value = line.partition("=")
        if separator:
            laravel_values[key.strip()] = value.strip().strip('"')

    try:
        erp_page_size = int(
            laravel_values["ERP_PAGE_SIZE"]
        )
        erp_maximum_page_size = int(
            laravel_values["ERP_MAXIMUM_PAGE_SIZE"]
        )
    except (KeyError, ValueError) as exception:
        fail(
            "Laravel Compose ERP page-size settings must be "
            f"present integers: {exception}"
        )

    if not (
        1
        <= erp_page_size
        <= erp_maximum_page_size
        <= 100
    ):
        fail(
            "Laravel Compose ERP page-size settings must satisfy "
            "1 <= ERP_PAGE_SIZE <= ERP_MAXIMUM_PAGE_SIZE <= 100."
        )

    report = {
        "passed": True,
        "compose_file": str(COMPOSE),
        "services": sorted(blocks),
        "volumes": sorted(volumes),
        "networks": sorted(networks),
        "host_ports_published_only_by_nginx": True,
        "database_networks_internal": True,
        "workers_profile_opt_in": True,
        "actual_env_files_present": False,
    }
    print(json.dumps(report, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())

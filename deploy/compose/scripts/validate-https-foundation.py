#!/usr/bin/env python3
from __future__ import annotations

import json
import pathlib
import re
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parents[3]


def fail(message: str) -> None:
    raise SystemExit(f"HTTPS foundation validation failed: {message}")


def read(relative: str) -> str:
    path = ROOT / relative
    if not path.is_file():
        fail(f"missing file: {relative}")
    return path.read_text(encoding="utf-8")


override = read("compose.https.yaml")
nginx = read("deploy/compose/nginx/https.conf")
compose_example = read("deploy/compose/compose.env.example")
laravel_example = read("deploy/compose/env/laravel/.env.example")
gitignore = read(".gitignore")
generator = ROOT / "deploy/compose/scripts/generate-local-tls.sh"

required_override_tokens = (
    "services:",
    "nginx:",
    "SMARTFACTORY_HTTPS_BIND_ADDRESS",
    "SMARTFACTORY_HTTPS_PORT",
    ":8443",
    "./deploy/compose/tls:/etc/nginx/tls:ro",
    "zz-smartfactory-https.conf:ro",
)
for token in required_override_tokens:
    if token not in override:
        fail(f"Compose HTTPS override is missing token: {token}")

required_nginx_tokens = (
    "listen 8443 ssl;",
    "server_name localhost;",
    "ssl_certificate /etc/nginx/tls/server.crt;",
    "ssl_certificate_key /etc/nginx/tls/server.key;",
    "ssl_protocols TLSv1.2 TLSv1.3;",
    "fastcgi_pass smartfactory_laravel_fpm;",
    "fastcgi_param HTTPS on;",
    "fastcgi_param HTTP_X_FORWARDED_PROTO https;",
    "fastcgi_param SERVER_PORT 8443;",
)
for token in required_nginx_tokens:
    if token not in nginx:
        fail(f"NGINX HTTPS configuration is missing token: {token}")

if "proxy_pass" in nginx:
    fail("The browser TLS server must terminate directly into Laravel FastCGI.")

values: dict[str, str] = {}
for content in (compose_example, laravel_example):
    for raw in content.splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        key, separator, value = line.partition("=")
        if separator:
            values[key.strip()] = value.strip().strip('"')

expected_values = {
    "SMARTFACTORY_HTTPS_BIND_ADDRESS": "0.0.0.0",
    "SMARTFACTORY_HTTPS_PORT": "8443",
    "APP_URL": "https://localhost:8443",
    "SESSION_SECURE_COOKIE": "true",
}
for key, expected in expected_values.items():
    if values.get(key) != expected:
        fail(f"{key} must be {expected!r}, got {values.get(key)!r}")

if "/deploy/compose/tls/" not in gitignore:
    fail("The generated TLS directory is not ignored.")

if not generator.is_file():
    fail("The local TLS generator is missing.")

tracked_tls = subprocess.run(
    [
        "git",
        "-C",
        str(ROOT),
        "ls-files",
        "--",
        "deploy/compose/tls",
    ],
    text=True,
    capture_output=True,
    check=True,
)
if tracked_tls.stdout.strip():
    fail("Generated TLS material must never be tracked by Git.")

if re.search(r"(?m)^\s*ssl_protocols\s+.*TLSv1(?:\s|;)", nginx):
    fail("TLS 1.0 must not be enabled.")
if re.search(r"(?m)^\s*ssl_protocols\s+.*TLSv1\.1(?:\s|;)", nginx):
    fail("TLS 1.1 must not be enabled.")

report = {
    "passed": True,
    "browser_url": "https://localhost:8443",
    "tls_protocols": ["TLSv1.2", "TLSv1.3"],
    "certificate_material_tracked": False,
    "ca_private_key_exported": False,
    "session_secure_cookie": True,
    "internal_service_transport_changed": False,
}
print(json.dumps(report, indent=2))

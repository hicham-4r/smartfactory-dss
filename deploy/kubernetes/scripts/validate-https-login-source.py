#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import re
import sys
from pathlib import Path

ROOT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path.cwd()

bootstrap = ROOT / "laravel-app/bootstrap/app.php"
nginx = ROOT / "docker/nginx/conf.d/smartfactory.conf"
test = ROOT / "laravel-app/tests/Feature/Security/TrustedProxyHttpsTest.php"
docs = ROOT / "docs/deployment/kubernetes-https-login.md"
configmaps = ROOT / "deploy/kubernetes/base/configmaps.yaml"
deployments = ROOT / "deploy/kubernetes/base/deployments.yaml"

required = [bootstrap, nginx, test, docs, configmaps, deployments]
missing = [str(p.relative_to(ROOT)) for p in required if not p.is_file()]
if missing:
    raise SystemExit("Missing required files: " + ", ".join(missing))

bootstrap_text = bootstrap.read_text(encoding="utf-8")
nginx_text = nginx.read_text(encoding="utf-8")
test_text = test.read_text(encoding="utf-8")
config_text = configmaps.read_text(encoding="utf-8")
deployment_text = deployments.read_text(encoding="utf-8")

checks = {
    "Laravel trusted proxy call": "$middleware->trustProxies(at: '*');" in bootstrap_text,
    "NGINX forwarded proto map": "map $http_x_forwarded_proto $smartfactory_forwarded_proto" in nginx_text,
    "NGINX preserves forwarded proto": nginx_text.count(
        "fastcgi_param HTTP_X_FORWARDED_PROTO $smartfactory_forwarded_proto;"
    ) == 2,
    "NGINX no longer forces local scheme": (
        "fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;" not in nginx_text
    ),
    "HTTPS asset origin": "ASSET_URL: https://localhost:8443" in config_text,
    "Secure session cookie": 'SESSION_SECURE_COOKIE: "true"' in config_text,
    "Regression test": "TrustedProxyHttpsTest" in test_text,
    "Regression HTTPS form": "https://localhost:8443/login" in test_text,
    "Laravel image is versioned": bool(
        re.search(r"image:\s+smartfactory/laravel:[a-z0-9][a-z0-9._-]+", deployment_text)
    ),
    "NGINX image is versioned": bool(
        re.search(r"image:\s+smartfactory/nginx:[a-z0-9][a-z0-9._-]+", deployment_text)
    ),
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    for name in failed:
        print(f"FAILED: {name}", file=sys.stderr)
    raise SystemExit(1)

for path in [bootstrap, nginx, test, docs]:
    digest = hashlib.sha256(path.read_bytes()).hexdigest()
    print(f"{path.relative_to(ROOT)}: {digest}")

print("Laravel HTTPS login source contract: passed")

#!/usr/bin/env python3
from __future__ import annotations

import argparse
import pathlib
import re


parser = argparse.ArgumentParser()
parser.add_argument("--root", required=True)
parser.add_argument("--base-rendered", required=True)
parser.add_argument("--demo-rendered", required=True)
args = parser.parse_args()

root = pathlib.Path(args.root)
base = pathlib.Path(args.base_rendered).read_text(
    encoding="utf-8"
)
demo = pathlib.Path(args.demo_rendered).read_text(
    encoding="utf-8"
)

source_files = [
    path
    for path in sorted(root.rglob("*"))
    if path.is_file()
]

all_source = "\n".join(
    path.read_text(
        encoding="utf-8",
        errors="replace",
    )
    for path in source_files
)

yaml_source = "\n".join(
    path.read_text(
        encoding="utf-8",
        errors="replace",
    )
    for path in source_files
    if path.suffix in {".yaml", ".yml"}
)

if re.search(
    r"(?m)^kind:\s*Secret\s*$",
    yaml_source,
):
    raise SystemExit(
        "A Kubernetes Secret object was committed."
    )

secret_like_files = [
    path.relative_to(root).as_posix()
    for path in source_files
    if (
        path.name == ".env"
        or path.suffix.lower()
        in {
            ".key",
            ".pem",
            ".p12",
            ".pfx",
        }
    )
]

if secret_like_files:
    raise SystemExit(
        "Secret-like files were committed: "
        + ", ".join(secret_like_files)
    )

private_key_marker = re.compile(
    r"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"
)

if private_key_marker.search(all_source):
    raise SystemExit(
        "A private-key marker was committed."
    )

# Scan only all-uppercase environment-style keys in YAML. Kubernetes schema
# fields such as secretName and secretRef are references, not secret values.
sensitive_literal = re.compile(
    r"""(?mx)
    ^\s*
    (?:
        APP_KEY
        |
        [A-Z0-9_]*(
            PASSWORD
            |
            TOKEN
            |
            PRIVATE_KEY
            |
            CLIENT_SECRET
            |
            INTERNAL_SECRET
        )
    )
    \s*:\s*
    ["']?
    [^\s#"'{}$][^#]*
    $
    """
)

matches = [
    line.strip()
    for line in yaml_source.splitlines()
    if sensitive_literal.match(line)
]

if matches:
    raise SystemExit(
        "A sensitive environment-style key has a committed literal value: "
        + ", ".join(matches)
    )

required_secret_refs = {
    "laravel-runtime",
    "fastapi-runtime",
    "erp-simulator-runtime",
    "mysql-dss-runtime",
    "mysql-erp-runtime",
    "smartfactory-local-tls",
}

missing_refs = sorted(
    name
    for name in required_secret_refs
    if name not in all_source
)

if missing_refs:
    raise SystemExit(
        "Missing runtime Secret references: "
        + ", ".join(missing_refs)
    )


def document_for(
    text: str,
    kind: str,
    name: str,
) -> str:
    documents = re.split(
        r"(?m)^---\s*$",
        text,
    )

    for document in documents:
        if (
            re.search(
                rf"(?m)^kind:\s*{re.escape(kind)}\s*$",
                document,
            )
            and re.search(
                rf"(?m)^\s*name:\s*{re.escape(name)}\s*$",
                document,
            )
        ):
            return document

    raise SystemExit(
        f"Missing rendered {kind}/{name}."
    )


baseline = {
    ("Deployment", "laravel-app"): 2,
    ("Deployment", "fastapi-ai"): 2,
    ("Deployment", "sage-erp-simulator"): 1,
    ("Deployment", "nginx"): 1,
    ("StatefulSet", "mysql-dss"): 1,
    ("StatefulSet", "mysql-erp"): 1,
    ("StatefulSet", "redis"): 1,
}

pod_total = 0

for (kind, name), expected in baseline.items():
    document = document_for(
        base,
        kind,
        name,
    )
    match = re.search(
        r"(?m)^\s*replicas:\s*(\d+)\s*$",
        document,
    )

    if not match:
        raise SystemExit(
            f"Missing replicas for {kind}/{name}."
        )

    actual = int(match.group(1))

    if actual != expected:
        raise SystemExit(
            f"Replica count differs for {kind}/{name}: {actual}"
        )

    pod_total += actual

if pod_total != 9:
    raise SystemExit(
        f"Baseline application Pod count differs: {pod_total}"
    )

for name in (
    "laravel-app",
    "fastapi-ai",
):
    document = document_for(
        demo,
        "HorizontalPodAutoscaler",
        name,
    )

    min_match = re.search(
        r"(?m)^\s*minReplicas:\s*(\d+)\s*$",
        document,
    )
    max_match = re.search(
        r"(?m)^\s*maxReplicas:\s*(\d+)\s*$",
        document,
    )

    if (
        not min_match
        or int(min_match.group(1)) != 2
        or not max_match
        or int(max_match.group(1)) != 3
    ):
        raise SystemExit(
            f"HPA bounds differ for {name}."
        )

pvc_count = len(
    re.findall(
        r"(?m)^kind:\s*PersistentVolumeClaim\s*$",
        base,
    )
)

if pvc_count != 7:
    raise SystemExit(
        f"PVC count differs: {pvc_count}"
    )

if 'AI_OLLAMA_ENABLED: "false"' not in all_source:
    raise SystemExit(
        "The source does not default Ollama generation to disabled."
    )

if "imagePullPolicy: IfNotPresent" not in base:
    raise SystemExit(
        "Local-image pull policy is missing."
    )

if "kind: NetworkPolicy" not in base:
    raise SystemExit(
        "NetworkPolicy resources are missing."
    )

print("Secret-safe source validation: passed")
print("Kubernetes Secret references accepted as references.")
print("Baseline application Pods: 9")
print("Maximum manual demo application Pods: 11")
print("PVC count: 7")

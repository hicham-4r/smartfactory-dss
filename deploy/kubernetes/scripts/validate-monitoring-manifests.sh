#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORK_DIR="$(mktemp -d)"

cleanup() {
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

RENDERED="$WORK_DIR/monitoring.yaml"
RESOURCES="$WORK_DIR/monitoring-resources.txt"

kubectl kustomize "$ROOT/overlays/monitoring" >"$RENDERED"

kubectl apply     --dry-run=client     --validate=true     --filename "$RENDERED"     --output=name     >"$RESOURCES"

python3 "$ROOT/scripts/validate-monitoring-source.py"     --root "$ROOT"     --rendered "$RENDERED"

echo "Kubernetes monitoring client-side dry-run: passed"
echo "Rendered resources: $(wc -l < "$RESOURCES")"
echo "No Kubernetes resource was created or changed."

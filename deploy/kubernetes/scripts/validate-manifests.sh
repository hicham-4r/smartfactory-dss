#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORK_DIR="$(mktemp -d)"

cleanup() {
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

BASE_RENDERED="$WORK_DIR/base.yaml"
DEMO_RENDERED="$WORK_DIR/demo.yaml"

kubectl kustomize "$ROOT/base" > "$BASE_RENDERED"
kubectl kustomize "$ROOT/overlays/demo" > "$DEMO_RENDERED"

kubectl apply \
    --dry-run=client \
    --validate=true \
    --filename "$BASE_RENDERED" \
    --output=name \
    > "$WORK_DIR/base-resources.txt"

kubectl apply \
    --dry-run=client \
    --validate=true \
    --filename "$DEMO_RENDERED" \
    --output=name \
    > "$WORK_DIR/demo-resources.txt"

python3 "$ROOT/scripts/validate-manifests.py" \
    --root "$ROOT" \
    --base-rendered "$BASE_RENDERED" \
    --demo-rendered "$DEMO_RENDERED"

echo "Kubernetes client-side dry-run: passed"
echo "Base rendered resources: $(wc -l < "$WORK_DIR/base-resources.txt")"
echo "Demo rendered resources: $(wc -l < "$WORK_DIR/demo-resources.txt")"
echo "No Kubernetes resource was created."

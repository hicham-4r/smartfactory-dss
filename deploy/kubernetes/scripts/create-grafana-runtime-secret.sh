#!/usr/bin/env bash
set -Eeuo pipefail

NAMESPACE="smartfactory-dss"
SECRET_NAME="grafana-runtime"
TMP_DIR=""
PASSWORD_ONE=""
PASSWORD_TWO=""

cleanup() {
    unset PASSWORD_ONE PASSWORD_TWO
    if [[ -n "$TMP_DIR" && -d "$TMP_DIR" ]]; then
        if command -v shred >/dev/null 2>&1; then
            find "$TMP_DIR" -type f -exec shred -u {} + 2>/dev/null || true
        fi
        rm -rf "$TMP_DIR"
    fi
}
trap cleanup EXIT

kubectl get namespace "$NAMESPACE" >/dev/null

if kubectl get secret "$SECRET_NAME" -n "$NAMESPACE" -o name >/dev/null 2>&1; then
    echo "Secret $SECRET_NAME already exists; it was not changed."
    exit 0
fi

read -r -s -p "Enter the Grafana admin password (minimum 12 characters): " PASSWORD_ONE
printf '\n'
read -r -s -p "Confirm the Grafana admin password: " PASSWORD_TWO
printf '\n'

if [[ ${#PASSWORD_ONE} -lt 12 ]]; then
    echo "The password must contain at least 12 characters." >&2
    exit 1
fi
if [[ "$PASSWORD_ONE" != "$PASSWORD_TWO" ]]; then
    echo "The passwords do not match." >&2
    exit 1
fi

umask 077
TMP_DIR="$(mktemp -d)"
printf '%s' "$PASSWORD_ONE" >"$TMP_DIR/GF_SECURITY_ADMIN_PASSWORD"

kubectl create secret generic "$SECRET_NAME" \
    --namespace "$NAMESPACE" \
    --from-file="GF_SECURITY_ADMIN_PASSWORD=$TMP_DIR/GF_SECURITY_ADMIN_PASSWORD" \
    --dry-run=client \
    --output=yaml \
    | kubectl apply --filename=- >/dev/null

echo "Grafana runtime Secret created without exposing its value."

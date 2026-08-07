#!/usr/bin/env bash
set -Eeuo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
NAMESPACE="smartfactory-dss"

LARAVEL_ENV="${LARAVEL_ENV:-$REPO/deploy/compose/env/laravel/.env}"
FASTAPI_ENV="${FASTAPI_ENV:-$REPO/deploy/compose/env/fastapi/.env}"
SIMULATOR_ENV="${SIMULATOR_ENV:-$REPO/deploy/compose/env/simulator/.env}"
MYSQL_DSS_ENV="${MYSQL_DSS_ENV:-$REPO/deploy/compose/env/mysql-dss/.env}"
MYSQL_ERP_ENV="${MYSQL_ERP_ENV:-$REPO/deploy/compose/env/mysql-erp/.env}"

required_files=(
    "$LARAVEL_ENV"
    "$FASTAPI_ENV"
    "$SIMULATOR_ENV"
    "$MYSQL_DSS_ENV"
    "$MYSQL_ERP_ENV"
)

for path in "${required_files[@]}"; do
    if [[ ! -f "$path" ]]; then
        echo "ERROR: Required ignored environment file is missing: $path" >&2
        exit 1
    fi
done

if [[ -z "${TLS_CERT_PATH:-}" || -z "${TLS_KEY_PATH:-}" ]]; then
    echo "ERROR: Set TLS_CERT_PATH and TLS_KEY_PATH explicitly." >&2
    exit 1
fi

[[ -f "$TLS_CERT_PATH" ]]
[[ -f "$TLS_KEY_PATH" ]]

kubectl get namespace "$NAMESPACE" >/dev/null

create_env_secret() {
    local name="$1"
    local file="$2"

    kubectl create secret generic "$name" \
        --namespace "$NAMESPACE" \
        --from-env-file="$file" \
        --dry-run=client \
        --output=yaml \
        | kubectl apply -f - >/dev/null

    echo "Applied runtime Secret contract: $name"
}

create_env_secret laravel-runtime "$LARAVEL_ENV"
create_env_secret fastapi-runtime "$FASTAPI_ENV"
create_env_secret erp-simulator-runtime "$SIMULATOR_ENV"
create_env_secret mysql-dss-runtime "$MYSQL_DSS_ENV"
create_env_secret mysql-erp-runtime "$MYSQL_ERP_ENV"

kubectl create secret tls smartfactory-local-tls \
    --namespace "$NAMESPACE" \
    --cert="$TLS_CERT_PATH" \
    --key="$TLS_KEY_PATH" \
    --dry-run=client \
    --output=yaml \
    | kubectl apply -f - >/dev/null

echo "Applied runtime TLS Secret contract: smartfactory-local-tls"
echo "Secret values were not printed or written to the repository."

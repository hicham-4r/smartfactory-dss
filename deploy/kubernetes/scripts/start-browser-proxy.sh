#!/usr/bin/env bash
set -Eeuo pipefail

CONTEXT="${SMARTFACTORY_K8S_CONTEXT:-smartfactory}"
NAMESPACE="${SMARTFACTORY_INGRESS_NAMESPACE:-ingress-nginx}"
SERVICE="${SMARTFACTORY_INGRESS_SERVICE:-ingress-nginx-controller}"
BIND_ADDRESS="${SMARTFACTORY_BROWSER_BIND_ADDRESS:-0.0.0.0}"
PORT="${SMARTFACTORY_BROWSER_PORT:-8443}"

STATE_DIR="${XDG_STATE_HOME:-$HOME/.local/state}/smartfactory-dss"
PID_FILE="$STATE_DIR/k8s-browser-proxy.pid"
LOG_FILE="$STATE_DIR/k8s-browser-proxy.log"

mkdir -p "$STATE_DIR"
chmod 700 "$STATE_DIR"

KUBECTL="$(command -v kubectl)"

if [[ -z "$KUBECTL" ]]; then
    echo "ERROR: kubectl is not available." >&2
    exit 1
fi

if [[ -f "$PID_FILE" ]]; then
    OLD_PID="$(cat "$PID_FILE" 2>/dev/null || true)"

    if [[ "$OLD_PID" =~ ^[0-9]+$ ]] && kill -0 "$OLD_PID" 2>/dev/null; then
        OLD_COMMAND="$(
            tr '\0' ' ' < "/proc/$OLD_PID/cmdline" 2>/dev/null || true
        )"

        if [[ "$OLD_COMMAND" == *"kubectl"* ]] \
            && [[ "$OLD_COMMAND" == *"port-forward"* ]] \
            && [[ "$OLD_COMMAND" == *"$SERVICE"* ]]
        then
            kill "$OLD_PID"
            wait "$OLD_PID" 2>/dev/null || true
        else
            echo "ERROR: The saved PID belongs to another process." >&2
            exit 1
        fi
    fi

    rm -f "$PID_FILE"
fi

if timeout 1 bash -c "</dev/tcp/127.0.0.1/$PORT" 2>/dev/null; then
    echo "ERROR: Local port $PORT is already occupied." >&2
    exit 1
fi

: > "$LOG_FILE"
chmod 600 "$LOG_FILE"

nohup "$KUBECTL" \
    --context="$CONTEXT" \
    --namespace="$NAMESPACE" \
    port-forward \
    --address="$BIND_ADDRESS" \
    "service/$SERVICE" \
    "${PORT}:443" \
    > "$LOG_FILE" \
    2>&1 &

PID=$!
printf '%s\n' "$PID" > "$PID_FILE"
chmod 600 "$PID_FILE"

for _ in $(seq 1 60); do
    if ! kill -0 "$PID" 2>/dev/null; then
        echo "ERROR: Browser proxy exited during startup." >&2
        cat "$LOG_FILE" >&2
        exit 1
    fi

    if timeout 1 bash -c "</dev/tcp/127.0.0.1/$PORT" 2>/dev/null; then
        echo "SmartFactory Kubernetes browser proxy is ready."
        echo "Guest bind: $BIND_ADDRESS:$PORT"
        echo "PID file: $PID_FILE"
        echo "Log file: $LOG_FILE"
        exit 0
    fi

    sleep 0.5
done

echo "ERROR: Browser proxy did not open port $PORT." >&2
cat "$LOG_FILE" >&2
exit 1

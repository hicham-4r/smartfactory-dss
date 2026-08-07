#!/usr/bin/env bash
set -Eeuo pipefail

NAMESPACE="smartfactory-dss"
STATE_DIR="${XDG_RUNTIME_DIR:-/tmp}/smartfactory-monitoring-proxies"
mkdir -p "$STATE_DIR"

start_proxy() {
    local name="$1"
    local service="$2"
    local mapping="$3"
    local pid_file="$STATE_DIR/${name}.pid"
    local log_file="$STATE_DIR/${name}.log"

    if [[ -f "$pid_file" ]]; then
        local old_pid
        old_pid="$(cat "$pid_file")"
        if kill -0 "$old_pid" 2>/dev/null; then
            kill "$old_pid"
            wait "$old_pid" 2>/dev/null || true
        fi
        rm -f "$pid_file"
    fi

    nohup kubectl port-forward         --namespace "$NAMESPACE"         "service/$service"         "$mapping"         >"$log_file" 2>&1 &

    local pid=$!
    echo "$pid" >"$pid_file"

    for _ in $(seq 1 20); do
        if grep -q "Forwarding from" "$log_file" 2>/dev/null; then
            echo "$name proxy started with PID $pid."
            return 0
        fi
        if ! kill -0 "$pid" 2>/dev/null; then
            cat "$log_file" >&2
            return 1
        fi
        sleep 0.5
    done

    echo "$name proxy did not become ready." >&2
    cat "$log_file" >&2
    return 1
}

start_proxy "prometheus" "prometheus" "9090:9090"
start_proxy "grafana" "grafana" "3000:3000"

echo "Prometheus: http://localhost:9090"
echo "Grafana:    http://localhost:3000"
echo "Proxy state: $STATE_DIR"

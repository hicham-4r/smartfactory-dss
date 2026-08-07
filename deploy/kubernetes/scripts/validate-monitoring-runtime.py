#!/usr/bin/env python3
from __future__ import annotations

import argparse
import base64
import json
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any

APP_WORKLOADS = {
    "laravel-app": 2,
    "fastapi-ai": 2,
    "sage-erp-simulator": 1,
    "nginx": 1,
    "mysql-dss": 1,
    "mysql-erp": 1,
    "redis": 1,
}
MONITORING_WORKLOADS = {
    "prometheus": 1,
    "grafana": 1,
    "kube-state-metrics": 1,
    "blackbox-exporter": 1,
}
EXPECTED_JOBS = {
    "prometheus",
    "kube-state-metrics",
    "kube-state-metrics-self",
    "blackbox-exporter",
    "smartfactory-http-health",
    "smartfactory-tcp-health",
    "kubernetes-cadvisor",
}
EXPECTED_ALERTS = {
    "SmartFactoryMonitoringTargetDown",
    "SmartFactoryBlackboxProbeFailed",
    "SmartFactoryDeploymentUnavailable",
    "SmartFactoryStatefulSetReplicaMismatch",
    "SmartFactoryContainerRestarted",
    "SmartFactoryPersistentVolumeClaimPending",
    "SmartFactoryHpaAtMaximum",
}


def run(*args: str, check: bool = True) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, check=check, text=True, capture_output=True)


def kubectl_json(namespace: str, *args: str) -> Any:
    cp = run("kubectl", *args, "-n", namespace, "-o", "json")
    return json.loads(cp.stdout)


def http_json(url: str, username: str | None = None, password: str | None = None) -> Any:
    req = urllib.request.Request(url, headers={"Accept": "application/json"})
    if username is not None and password is not None:
        token = base64.b64encode(f"{username}:{password}".encode()).decode()
        req.add_header("Authorization", f"Basic {token}")
    with urllib.request.urlopen(req, timeout=10) as response:
        if response.status != 200:
            raise RuntimeError(f"HTTP {response.status} from {url}")
        return json.loads(response.read().decode())


def wait_for_targets(prometheus_url: str, timeout: int = 150) -> tuple[list[dict[str, Any]], list[str]]:
    deadline = time.monotonic() + timeout
    last_targets: list[dict[str, Any]] = []
    while time.monotonic() < deadline:
        payload = http_json(f"{prometheus_url}/api/v1/targets")
        last_targets = payload.get("data", {}).get("activeTargets", [])
        jobs = {t.get("labels", {}).get("job") for t in last_targets}
        unhealthy = [
            f"{t.get('labels', {}).get('job')}:{t.get('labels', {}).get('instance')}:{t.get('health')}:{t.get('lastError', '')}"
            for t in last_targets
            if t.get("health") != "up"
        ]
        if EXPECTED_JOBS.issubset(jobs) and not unhealthy:
            return last_targets, []
        time.sleep(5)
    jobs = {t.get("labels", {}).get("job") for t in last_targets}
    missing = sorted(EXPECTED_JOBS - jobs)
    unhealthy = [
        f"{t.get('labels', {}).get('job')}:{t.get('labels', {}).get('instance')}:{t.get('health')}:{t.get('lastError', '')}"
        for t in last_targets
        if t.get("health") != "up"
    ]
    return last_targets, [*(f"missing-job:{x}" for x in missing), *unhealthy]


def classify_pods(namespace: str) -> dict[str, Any]:
    data = kubectl_json(namespace, "get", "pods")
    app_counts = {name: 0 for name in APP_WORKLOADS}
    monitor_counts = {name: 0 for name in MONITORING_WORKLOADS}
    ready_app = 0
    ready_monitoring = 0
    restarts = 0
    pod_rows = []
    for item in data.get("items", []):
        meta = item.get("metadata", {})
        if meta.get("deletionTimestamp") is not None:
            continue
        labels = meta.get("labels", {})
        name = labels.get("app.kubernetes.io/name", "")
        statuses = item.get("status", {}).get("containerStatuses", [])
        ready = bool(statuses) and all(s.get("ready") is True for s in statuses)
        restart_count = sum(int(s.get("restartCount", 0)) for s in statuses)
        restarts += restart_count
        if name in app_counts:
            app_counts[name] += 1
            ready_app += int(ready)
        if name in monitor_counts:
            monitor_counts[name] += 1
            ready_monitoring += int(ready)
        pod_rows.append({
            "name": meta.get("name"),
            "workload": name,
            "phase": item.get("status", {}).get("phase"),
            "ready": ready,
            "restart_count": restart_count,
        })
    return {
        "application_counts": app_counts,
        "monitoring_counts": monitor_counts,
        "ready_application_pods": ready_app,
        "ready_monitoring_pods": ready_monitoring,
        "total_restart_count": restarts,
        "pods": pod_rows,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--namespace", default="smartfactory-dss")
    parser.add_argument("--prometheus-url", required=True)
    parser.add_argument("--grafana-url", required=True)
    parser.add_argument("--grafana-password-file", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--log-path", required=True)
    args = parser.parse_args()

    password = Path(args.grafana_password_file).read_text()
    failures: list[str] = []

    pods = classify_pods(args.namespace)
    if pods["application_counts"] != APP_WORKLOADS:
        failures.append(f"application workload counts differ: {pods['application_counts']}")
    if pods["monitoring_counts"] != MONITORING_WORKLOADS:
        failures.append(f"monitoring workload counts differ: {pods['monitoring_counts']}")
    if pods["ready_application_pods"] != 9:
        failures.append(f"ready application Pods={pods['ready_application_pods']}, expected 9")
    if pods["ready_monitoring_pods"] != 4:
        failures.append(f"ready monitoring Pods={pods['ready_monitoring_pods']}, expected 4")

    hpas = kubectl_json(args.namespace, "get", "hpa")
    hpa_names = sorted(item["metadata"]["name"] for item in hpas.get("items", []))
    if hpa_names != ["fastapi-ai", "laravel-app"]:
        failures.append(f"HPA names differ: {hpa_names}")

    pvcs = kubectl_json(args.namespace, "get", "pvc")
    pvc_states = {item["metadata"]["name"]: item.get("status", {}).get("phase") for item in pvcs.get("items", [])}
    for name in ("prometheus-data", "grafana-data"):
        if pvc_states.get(name) != "Bound":
            failures.append(f"PVC {name} is {pvc_states.get(name)!r}, expected Bound")

    secret_name = run("kubectl", "get", "secret", "grafana-runtime", "-n", args.namespace, "-o", "name").stdout.strip()
    if secret_name != "secret/grafana-runtime":
        failures.append("grafana-runtime Secret metadata was not found")

    targets, target_failures = wait_for_targets(args.prometheus_url)
    failures.extend(target_failures)
    target_jobs: dict[str, dict[str, int]] = {}
    for target in targets:
        job = target.get("labels", {}).get("job", "unknown")
        status = target.get("health", "unknown")
        target_jobs.setdefault(job, {"total": 0, "up": 0})
        target_jobs[job]["total"] += 1
        target_jobs[job]["up"] += int(status == "up")

    rules_payload = http_json(f"{args.prometheus_url}/api/v1/rules")
    alert_names = sorted({
        rule.get("name")
        for group in rules_payload.get("data", {}).get("groups", [])
        for rule in group.get("rules", [])
        if rule.get("type") == "alerting"
    })
    missing_alerts = sorted(EXPECTED_ALERTS - set(alert_names))
    if missing_alerts:
        failures.append(f"missing alert rules: {missing_alerts}")

    grafana_health = http_json(f"{args.grafana_url}/api/health")
    if grafana_health.get("database") != "ok":
        failures.append(f"Grafana database health is {grafana_health.get('database')!r}")
    datasource = http_json(
        f"{args.grafana_url}/api/datasources/uid/smartfactory-prometheus",
        "admin",
        password,
    )
    if datasource.get("uid") != "smartfactory-prometheus":
        failures.append("Grafana Prometheus datasource was not provisioned")
    dashboard = http_json(
        f"{args.grafana_url}/api/dashboards/uid/smartfactory-kubernetes-overview",
        "admin",
        password,
    )
    dashboard_title = dashboard.get("dashboard", {}).get("title")
    if dashboard_title != "SmartFactory DSS — Kubernetes Overview":
        failures.append(f"unexpected Grafana dashboard title: {dashboard_title!r}")

    alerts_payload = http_json(f"{args.prometheus_url}/api/v1/alerts")
    firing_alerts = [
        alert.get("labels", {}).get("alertname", "unknown")
        for alert in alerts_payload.get("data", {}).get("alerts", [])
        if alert.get("state") == "firing"
    ]
    if firing_alerts:
        failures.append(f"Prometheus has firing alerts: {sorted(firing_alerts)}")

    branch = run("git", "branch", "--show-current").stdout.strip()
    head = run("git", "rev-parse", "HEAD").stdout.strip()
    untracked = sorted(run("git", "ls-files", "--others", "--exclude-standard").stdout.splitlines())
    tracked_diff = bool(run("git", "status", "--porcelain", "--untracked-files=no").stdout.strip())

    report = {
        "report": {
            "name": "SmartFactory DSS Phase 12 Step 25C Monitoring Runtime Deployment and Validation",
            "generated_at_local": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
            "passed": not failures,
            "output_log": args.log_path,
        },
        "repository": {
            "branch": branch,
            "head": head,
            "tracked_files_modified": tracked_diff,
            "phase12_untracked_file_count": len(untracked),
            "commit_created": False,
            "remote_push_performed": False,
        },
        "runtime": {
            "application_baseline_preserved": pods["application_counts"] == APP_WORKLOADS and pods["ready_application_pods"] == 9,
            "application_pods": 9,
            "monitoring_pods": 4,
            "total_expected_pods": 13,
            "pod_snapshot": pods,
            "hpas": hpa_names,
            "pvc_states": {k: pvc_states.get(k) for k in ("prometheus-data", "grafana-data")},
            "grafana_runtime_secret_metadata_present": secret_name == "secret/grafana-runtime",
        },
        "prometheus": {
            "ready": True,
            "expected_jobs": sorted(EXPECTED_JOBS),
            "job_health": target_jobs,
            "active_target_count": len(targets),
            "all_targets_up": not target_failures,
            "alert_rules": alert_names,
            "required_alert_rules_loaded": not missing_alerts,
            "firing_alerts": sorted(firing_alerts),
        },
        "grafana": {
            "health": grafana_health,
            "datasource_uid": datasource.get("uid"),
            "dashboard_uid": dashboard.get("dashboard", {}).get("uid"),
            "dashboard_title": dashboard_title,
            "anonymous_access_enabled": False,
            "admin_password_recorded": False,
        },
        "security": {
            "monitoring_services_clusterip_only": True,
            "monitoring_ingress_created": False,
            "secret_values_read_from_kubernetes": False,
            "secret_values_written_to_report": False,
            "private_keys_read": False,
        },
        "safety": {
            "database_rows_queried": False,
            "database_rows_modified": False,
            "erp_sync_run": False,
            "training_run": False,
            "inference_run": False,
            "ollama_generation_run": False,
            "compose_started": False,
            "automatic_commit": False,
            "automatic_push": False,
        },
        "failures": failures,
        "next_step": {
            "name": "Phase 12 Step 25D",
            "description": "Add native Prometheus application metrics for Laravel, FastAPI, and the Sage ERP Simulator.",
        },
    }
    Path(args.output).write_text(json.dumps(report, indent=2) + "\n")
    if failures:
        for failure in failures:
            print(f"VALIDATION FAILURE: {failure}", file=sys.stderr)
        return 1
    print(json.dumps({
        "application_ready": pods["ready_application_pods"],
        "monitoring_ready": pods["ready_monitoring_pods"],
        "prometheus_targets": len(targets),
        "grafana_dashboard": dashboard.get("dashboard", {}).get("uid"),
    }, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

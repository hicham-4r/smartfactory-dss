#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import pathlib
import re

parser = argparse.ArgumentParser()
parser.add_argument("--root", required=True)
parser.add_argument("--rendered", required=True)
args = parser.parse_args()

root = pathlib.Path(args.root)
rendered = pathlib.Path(args.rendered).read_text(encoding="utf-8")
monitoring = root / "monitoring"

required_files = [
    monitoring / "kustomization.yaml",
    monitoring / "serviceaccounts-rbac.yaml",
    monitoring / "persistent-volumes.yaml",
    monitoring / "services.yaml",
    monitoring / "workloads.yaml",
    monitoring / "networkpolicies.yaml",
    monitoring / "files" / "prometheus.yml",
    monitoring / "files" / "alerts.yml",
    monitoring / "files" / "blackbox.yml",
    monitoring / "files" / "grafana-datasource.yml",
    monitoring / "files" / "grafana-dashboard-provider.yml",
    monitoring / "files" / "smartfactory-overview.json",
    root / "overlays" / "monitoring" / "kustomization.yaml",
]

missing = [path.as_posix() for path in required_files if not path.is_file()]
if missing:
    raise SystemExit("Missing monitoring source files: " + ", ".join(missing))

source = "\n".join(path.read_text(encoding="utf-8", errors="replace") for path in required_files)

if re.search(r"(?m)^kind:\s*Secret\s*$", source):
    raise SystemExit("A Kubernetes Secret object was committed in monitoring source.")
if "remote_write:" in source or "remote_read:" in source:
    raise SystemExit("External Prometheus remote endpoints are not allowed in this phase.")
if re.search(r"(?m)^\s*type:\s*(NodePort|LoadBalancer)\s*$", source):
    raise SystemExit("Monitoring Services must remain ClusterIP-only.")
if re.search(r"(?m)^kind:\s*Ingress\s*$", source):
    raise SystemExit("Monitoring must not be exposed through an Ingress.")
if re.search(r"(?m)^\s*image:\s*\S+:latest\s*$", source):
    raise SystemExit("Monitoring images must use pinned tags.")

required_images = {
    "prom/prometheus:v3.5.2",
    "grafana/grafana:13.1.1",
    "registry.k8s.io/kube-state-metrics/kube-state-metrics:v2.19.0",
    "prom/blackbox-exporter:v0.28.0",
}
for image in required_images:
    if image not in source:
        raise SystemExit(f"Required pinned image is missing: {image}")

if "GF_SECURITY_ADMIN_PASSWORD" not in source:
    raise SystemExit("Grafana must reference a runtime password Secret.")
if re.search(r"GF_SECURITY_ADMIN_PASSWORD\s*\n\s*value:\s*\S+", source):
    raise SystemExit("A Grafana admin password appears to be hardcoded.")

required_names = {
    "prometheus",
    "grafana",
    "kube-state-metrics",
    "blackbox-exporter",
    "prometheus-data",
    "grafana-data",
}
rendered_names = set(re.findall(r"(?m)^  name:\s*([A-Za-z0-9.-]+)\s*$", rendered))
missing_names = sorted(required_names - rendered_names)
if missing_names:
    raise SystemExit("Rendered resources are missing names: " + ", ".join(missing_names))

for kind in ["StatefulSet", "Deployment", "Service", "PersistentVolumeClaim", "NetworkPolicy", "ClusterRole", "Role"]:
    if not re.search(rf"(?m)^kind:\s*{re.escape(kind)}\s*$", rendered):
        raise SystemExit(f"Rendered monitoring layer is missing kind {kind}.")

dashboard_path = monitoring / "files" / "smartfactory-overview.json"
dashboard = json.loads(dashboard_path.read_text(encoding="utf-8"))
if dashboard.get("uid") != "smartfactory-kubernetes-overview":
    raise SystemExit("Grafana dashboard UID is unexpected.")
if len(dashboard.get("panels", [])) < 8:
    raise SystemExit("Grafana overview dashboard has too few panels.")

prometheus = (monitoring / "files" / "prometheus.yml").read_text(encoding="utf-8")
for job in ["prometheus", "kube-state-metrics", "blackbox-exporter", "smartfactory-laravel-native", "smartfactory-fastapi-native", "smartfactory-erp-native", "smartfactory-http-health", "smartfactory-tcp-health", "kubernetes-cadvisor"]:
    if f"job_name: {job}" not in prometheus:
        raise SystemExit(f"Prometheus scrape job is missing: {job}")

alerts = (monitoring / "files" / "alerts.yml").read_text(encoding="utf-8")
if alerts.count("- alert:") < 7:
    raise SystemExit("The monitoring source must include at least seven alert rules.")

print("Monitoring source contract: passed")
print(f"Dashboard panels: {len(dashboard['panels'])}")
print(f"Alert rules: {alerts.count('- alert:')}")
print("No Secret object, public Service, monitoring Ingress, or unpinned image was found.")

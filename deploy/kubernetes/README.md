# SmartFactory DSS Kubernetes deployment

This directory contains the source-controlled Kubernetes deployment used for the SmartFactory DSS local demonstration and Phase 12 observability acceptance.

## Accepted topology

| Workload | Controller | Accepted replicas |
|---|---|---:|
| Laravel DSS | Deployment | 2 |
| FastAPI AI | Deployment | 2 |
| Sage ERP simulator | Deployment | 1 |
| MySQL DSS | StatefulSet | 1 |
| MySQL ERP | StatefulSet | 1 |
| Redis | StatefulSet | 1 |
| NGINX | Deployment | 1 |
| Prometheus | StatefulSet | 1 |
| Grafana | Deployment | 1 |
| kube-state-metrics | Deployment | 1 |
| blackbox exporter | Deployment | 1 |

The accepted runtime contains 13 ready Pods, zero active-Pod restarts and two HPAs for Laravel/FastAPI. This single-node Minikube deployment demonstrates workload scaling and governance; it is not a multi-node high-availability claim.

## Source layout

- `base/`: namespace, ConfigMaps, Services, PVCs, controllers, policies, Ingress, disruption budgets and resource governance.
- `overlays/demo/`: application demonstration overlay and HPA behavior.
- `overlays/monitoring/`: accepted application stack plus monitoring.
- `monitoring/`: Prometheus, Grafana, kube-state-metrics, blackbox exporter, alert rules and dashboards.
- `runtime/`: runtime prerequisite documentation.
- `scripts/create-runtime-secrets.sh`: creates application runtime Secrets from ignored local sources.
- `scripts/create-grafana-runtime-secret.sh`: creates the Grafana runtime Secret without committing credentials.
- `scripts/start-browser-proxy.sh`: local HTTPS browser access helper.
- `scripts/start-monitoring-proxies.sh`: local monitoring access helper.
- `scripts/validate-*`: source/runtime validators.
- `secrets/README.md`: secret-handling contract.

## State-preservation rule

Normal validation and rollout preserve MySQL/Redis state, PVCs, Secrets, model artifacts, datasets and Git history. No reset/recreate workflow is part of accepted validation, and runtime Secrets are never committed.

## Application boundaries

- **Laravel** is the authenticated browser-facing DSS and owns RBAC, reporting, audit and service orchestration.
- **FastAPI** is an internal AI boundary for verified ML inference and guarded explanation contracts; it has no direct Laravel/ERP database or Redis access.
- **Ollama** runs on the Windows host and is reachable only through the verified private Minikube-to-host route. Browsers never access it directly.
- **Sage ERP simulator** is explicitly a simulated integration source, not a live production Sage instance.

## Native metrics

Phase 12 provides private native metrics for Laravel, FastAPI and the ERP simulator. Laravel/ERP metrics pass through private NGINX gateways; FastAPI metrics are restricted to the intended private host boundary. PHP metrics use the dedicated `smartfactory_metrics` Redis connection with bounded connect/read timeouts and local-file fallback so observability fails open quickly during Redis/network disturbances.

## Monitoring

The monitoring overlay provisions Prometheus, Grafana, kube-state-metrics, blackbox exporter, 17 alerting rules, **SmartFactory DSS — Kubernetes Overview** (9 panels), and **SmartFactory DSS — Application Observability** (17 panels). The accepted runtime has 17/17 healthy Prometheus targets.

## Local HTTPS browser access

Start or refresh browser access with:

```bash
./deploy/kubernetes/scripts/start-browser-proxy.sh
```

Open:

```text
https://localhost:8443/login
```

The local VirtualBox/Minikube route is for development/demo use. Production should use externally operated ingress/load balancing, managed certificate lifecycle, durable backup/restore and infrastructure-appropriate secret management.

## Validation

Source/runtime validators under `deploy/kubernetes/scripts/` cover Kustomize rendering, client-side schemas, HTTPS/session behavior, native metrics privacy/performance, Prometheus targets/rules and Grafana provisioning without resetting application state.
